<?php

namespace App\Services\Partner;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Keycloak\KeycloakAdminService;
use App\Services\Keycloak\KeycloakUserProvisioningService;
use App\Support\Phone;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class PartnerStaffService
{
    /**
     * Local role slug for staff members who can log into the partner web app.
     */
    private const STAFF_LOCAL_ROLE_SLUG = 'partner_admin';

    /**
     * Role assigned in tracking_app Keycloak client.
     */
    private const TRACKING_KEYCLOAK_ROLE = 'partner_admin';

    /**
     * Role assigned in recouvrement_app Keycloak client.
     */
    private const RECOUVREMENT_KEYCLOAK_ROLE = 'PARTNER_ADMIN';

    public function __construct(
        private KeycloakAdminService $keycloakAdminService,
        private KeycloakUserProvisioningService $keycloakUserProvisioningService,
    ) {}

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function listStaff(User $actor): \Illuminate\Database\Eloquent\Collection
    {
        $partner = $this->resolveTenantPartner($actor);

        return User::query()
            ->with(['role', 'permissions'])
            ->where('partner_id', $partner->id)
            ->whereHas('role', fn ($q) => $q->where('slug', self::STAFF_LOCAL_ROLE_SLUG))
            ->latest()
            ->get();
    }

    public function createStaff(User $actor, array $data): User
    {
        $partner = $this->resolveTenantPartner($actor);

        $roleId = Role::where('slug', self::STAFF_LOCAL_ROLE_SLUG)->value('id');

        if (! $roleId) {
            throw new ConflictHttpException("Le rôle '" . self::STAFF_LOCAL_ROLE_SLUG . "' n'existe pas en base.");
        }

        $phoneE164 = Phone::e164($data['phone']);

        if (! $phoneE164) {
            throw new ConflictHttpException('Numéro de téléphone invalide.');
        }

        $data['phone'] = $phoneE164;

        $this->assertPhoneAvailable($data['phone']);
        $this->assertEmailAvailable($data['email'] ?? null);

        $staff                     = null;
        $createdKeycloakUserId     = null;
        $createdKeycloakUserWasNew = false;

        DB::beginTransaction();

        try {
            // 1. Local MySQL record
            $staff = $this->createLocalStaffInsideTransaction(
                partner: $partner,
                actor:   $actor,
                data:    $data,
                roleId:  $roleId,
            );

            // 1b. Permissions granted by the partner (local only)
            $this->syncPermissions($staff, $data['permissions'] ?? [], $actor);

            // 2. Keycloak provisioning
            // resolveRoleName() reads role.slug = 'partner_admin' and maps it
            // to 'partner_admin' in tracking_app via TRACKING_ROLE_MAP
            $keycloakResult = $this->keycloakUserProvisioningService->provisionDriverCreatedByPartner(
                user:          $staff,
                plainPassword: $data['password'],
            );

            if (empty($keycloakResult['keycloak_user_id'])) {
                throw new RuntimeException('Keycloak responded without a keycloak_id.');
            }

            $createdKeycloakUserId     = $keycloakResult['keycloak_user_id'];
            $createdKeycloakUserWasNew = (bool) ($keycloakResult['created'] ?? false);

            $staff->refresh();

            // 3. Assign PARTNER_ADMIN role in recouvrement_app
            // Staff members need this role to access the recouvrement app
            $this->keycloakAdminService->assignClientRoleToUser(
                keycloakUserId: $staff->keycloak_id,
                clientId:       config('services.keycloak.recouvrement_client_id', 'recouvrement_app'),
                roleName:       self::RECOUVREMENT_KEYCLOAK_ROLE,
            );

            // 4. No data sync to Recouvrement API — staff are not drivers
            $staff->forceFill([
                'recouvrement_sync_status' => 'NOT_REQUIRED',
                'sync_error'               => null,
            ])->save();

            DB::commit();

            return $staff->fresh('role');

        } catch (Throwable $e) {
            DB::rollBack();

            // Keycloak compensation — only delete if we created a brand-new account
            if ($createdKeycloakUserId && $createdKeycloakUserWasNew) {
                try {
                    $this->keycloakAdminService->deleteUser($createdKeycloakUserId);

                    Log::warning('[STAFF_ATOMIC_COMPENSATION_KEYCLOAK_DELETED]', [
                        'partner_id'  => $partner->id,
                        'keycloak_id' => $createdKeycloakUserId,
                    ]);
                } catch (Throwable $deleteException) {
                    Log::error('[STAFF_ATOMIC_COMPENSATION_KEYCLOAK_DELETE_FAILED]', [
                        'partner_id'  => $partner->id,
                        'keycloak_id' => $createdKeycloakUserId,
                        'error'       => $deleteException->getMessage(),
                    ]);
                }
            }

            // Clean up uploaded photo if the local record was partially created
            if ($staff?->photo) {
                Storage::disk(config('media.disk', 'public'))->delete($staff->photo);
            }

            Log::error('[STAFF_ATOMIC_CREATION_FAILED]', [
                'actor_id'            => $actor->id,
                'partner_id'          => $partner->id,
                'phone'               => $data['phone'] ?? null,
                'email'               => $data['email'] ?? null,
                'created_keycloak_id' => $createdKeycloakUserId,
                'error'               => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Staff creation rolled back. No local record was kept. Cause: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function updateStaff(User $actor, int $staffId, array $data): User
    {
        $partner       = $this->resolveTenantPartner($actor);
        $staff         = $this->findOrFailStaffOfPartner($partner, $staffId);
        $originalStaff = $this->cloneForExternalRestore($staff);

        $phoneE164 = Phone::e164($data['phone']);

        if (! $phoneE164) {
            throw new ConflictHttpException('Numéro de téléphone invalide.');
        }

        $data['phone'] = $phoneE164;

        $this->assertPhoneAvailable($data['phone'], $staff->id);
        $this->assertEmailAvailable($data['email'] ?? null, $staff->id);

        $disk            = config('media.disk', 'public');
        $oldPhotoPath    = $staff->photo;
        $newPhotoPath    = null;
        $keycloakUpdated = false;

        DB::beginTransaction();

        try {
            if (! empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $newPhotoPath = $data['photo']->store('users/photos', $disk);
                $staff->photo = $newPhotoPath;
            }

            $staff->nom      = $data['nom'];
            $staff->prenom   = $data['prenom'];
            $staff->phone    = $data['phone'];
            $staff->email    = $data['email'] ?? null;
            $staff->ville    = $data['ville'] ?? null;
            $staff->quartier = $data['quartier'] ?? null;

            // Keycloak username tracks phone
            $staff->keycloak_username = $data['phone'];

            $plainPassword = null;

            if (! empty($data['password'])) {
                $plainPassword   = $data['password'];
                $staff->password = Hash::make($plainPassword);
            }

            try {
                $staff->save();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->throwConflictFromDuplicate($e->getMessage());
                }
                throw $e;
            }

            // Permissions are local-only; sync them within the same transaction.
            if (array_key_exists('permissions', $data)) {
                $this->syncPermissions($staff, $data['permissions'] ?? [], $actor);
            }

            if (empty($staff->keycloak_id)) {
                throw new RuntimeException(
                    "Update blocked: staff member #{$staff->id} has no keycloak_id."
                );
            }

            $this->keycloakAdminService->updateUserFromLocalUser($staff);
            $keycloakUpdated = true;

            $staff->forceFill([
                'keycloak_sync_status' => 'SYNCED',
                'last_synced_at'       => now(),
                'sync_error'           => null,
            ])->save();

            // Password is pushed last — Keycloak credential only changes after
            // the full local + profile sync succeeded
            if ($plainPassword) {
                $this->keycloakAdminService->resetUserPassword(
                    $staff->keycloak_id,
                    $plainPassword,
                    false,
                );
            }

            DB::commit();

            // Delete old photo only after a clean commit
            if ($newPhotoPath && $oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
                Storage::disk($disk)->delete($oldPhotoPath);
            }

            return $staff->fresh('role');

        } catch (Throwable $e) {
            DB::rollBack();

            if ($newPhotoPath) {
                Storage::disk($disk)->delete($newPhotoPath);
            }

            // Keycloak profile compensation
            if ($keycloakUpdated) {
                try {
                    $this->keycloakAdminService->updateUserFromLocalUser($originalStaff);
                } catch (Throwable $restoreException) {
                    Log::error('[STAFF_UPDATE_KEYCLOAK_RESTORE_FAILED]', [
                        'actor_id'    => $actor->id,
                        'partner_id'  => $partner->id,
                        'staff_id'    => $staffId,
                        'keycloak_id' => $originalStaff->keycloak_id,
                        'error'       => $restoreException->getMessage(),
                    ]);
                }
            }

            Log::error('[STAFF_UPDATE_ATOMIC_FAILED]', [
                'actor_id'         => $actor->id,
                'partner_id'       => $partner->id,
                'staff_id'         => $staffId,
                'keycloak_updated' => $keycloakUpdated,
                'error'            => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Staff update rolled back. Cause: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function deleteStaff(User $actor, int $staffId): void
    {
        $partner   = $this->resolveTenantPartner($actor);
        $staff     = $this->findOrFailStaffOfPartner($partner, $staffId);

        $disk      = config('media.disk', 'public');
        $photoPath = $staff->photo;

        // 1. Keycloak deletion is mandatory
        if (empty($staff->keycloak_id)) {
            throw new RuntimeException(
                "Deletion blocked: staff member #{$staff->id} has no keycloak_id."
            );
        }

        $this->keycloakAdminService->deleteUser($staff->keycloak_id);

        // 2. Local deletion only after Keycloak succeeds
        DB::transaction(fn () => $staff->delete());

        // 3. Photo cleanup after DB commit
        if ($photoPath) {
            Storage::disk($disk)->delete($photoPath);
        }

        Log::warning('[STAFF_DELETED_ATOMICALLY]', [
            'actor_id'    => $actor->id,
            'partner_id'  => $partner->id,
            'staff_id'    => $staffId,
            'keycloak_id' => $staff->keycloak_id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createLocalStaffInsideTransaction(
        User $partner,
        User $actor,
        array $data,
        int $roleId,
    ): User {
        $photoPath = null;

        if (! empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
            $disk      = config('media.disk', 'public');
            $photoPath = $data['photo']->store('users/photos', $disk);
        }

        $payload = [
            'nom'                      => $data['nom'],
            'prenom'                   => $data['prenom'],
            'phone'                    => $data['phone'],
            'email'                    => $data['email'] ?? null,
            'ville'                    => $data['ville'] ?? null,
            'quartier'                 => $data['quartier'] ?? null,
            'photo'                    => $photoPath,
            'password'                 => Hash::make($data['password']),
            'role_id'                  => $roleId,
            'partner_id'               => $partner->id,
            'created_by'               => $actor->id,
            'user_unique_id'           => $this->generateUserUniqueId(),
            'keycloak_username'        => $data['phone'],
            'keycloak_sync_status'     => 'PENDING',
            'recouvrement_sync_status' => 'NOT_REQUIRED',
            'sync_error'               => null,
        ];

        try {
            return User::create($payload)->load('role');
        } catch (QueryException $e) {
            if ($photoPath) {
                Storage::disk(config('media.disk', 'public'))->delete($photoPath);
            }

            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $this->throwConflictFromDuplicate($e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Sync the granted permissions for a staff member (local pivot only).
     * `granted_by` records who performed the grant.
     *
     * @param  list<string>  $keys  permission keys (already validated)
     */
    private function syncPermissions(User $staff, array $keys, User $actor): void
    {
        $permissionIds = Permission::query()
            ->whereIn('key', $keys)
            ->pluck('id');

        $syncPayload = $permissionIds
            ->mapWithKeys(fn ($id) => [$id => ['granted_by' => $actor->id]])
            ->all();

        $staff->permissions()->sync($syncPayload);
        $staff->forgetCachedPermissions();
    }

    private function resolveTenantPartner(User $actor): User
    {
        if (! empty($actor->partner_id)) {
            return User::query()->findOrFail($actor->partner_id);
        }

        return $actor;
    }

    private function findOrFailStaffOfPartner(User $partner, int $staffId): User
    {
        return User::query()
            ->with('role')
            ->where('id', $staffId)
            ->where('partner_id', $partner->id)
            ->whereHas('role', fn ($q) => $q->where('slug', self::STAFF_LOCAL_ROLE_SLUG))
            ->firstOrFail();
    }

    private function cloneForExternalRestore(User $staff): User
    {
        $snapshot = new User();
        $snapshot->exists = true;
        $snapshot->setRawAttributes($staff->getAttributes(), true);

        if ($staff->relationLoaded('role')) {
            $snapshot->setRelation('role', $staff->getRelation('role'));
        } else {
            $snapshot->setRelation('role', $staff->role()->first());
        }

        return $snapshot;
    }

    private function assertPhoneAvailable(string $phoneInput, ?int $ignoreUserId = null): void
    {
        $candidates = Phone::candidates($phoneInput);

        if (empty($candidates)) {
            throw new ConflictHttpException('Numéro de téléphone invalide.');
        }

        $query = User::query()->whereIn('phone', $candidates);

        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw new ConflictHttpException('Ce numéro est déjà utilisé.');
        }
    }

    private function assertEmailAvailable(?string $email, ?int $ignoreUserId = null): void
    {
        if (! $email) {
            return;
        }

        $query = User::query()->where('email', strtolower($email));

        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw new ConflictHttpException('Cet email est déjà utilisé.');
        }
    }

    private function generateUserUniqueId(): string
    {
        $prefix = 'PxT-' . now()->format('Ym') . '-';

        do {
            $id = $prefix . Str::ucfirst(Str::lower(Str::random(4)));
        } while (User::where('user_unique_id', $id)->exists());

        return $id;
    }

    private function throwConflictFromDuplicate(string $message): void
    {
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'users_phone_unique') || str_contains($lower, 'phone')) {
            throw new ConflictHttpException('Ce numéro est déjà utilisé.');
        }

        if (str_contains($lower, 'users_email_unique') || str_contains($lower, 'email')) {
            throw new ConflictHttpException('Cet email est déjà utilisé.');
        }

        if (str_contains($lower, 'keycloak_id')) {
            throw new ConflictHttpException('Ce compte Keycloak est déjà lié à un utilisateur.');
        }

        throw new ConflictHttpException('Donnée déjà utilisée.');
    }
}
