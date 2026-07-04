<?php

namespace App\Services\Partner;

use App\Models\Role;
use App\Models\User;
use App\Services\Keycloak\KeycloakAdminService;
use App\Services\Keycloak\KeycloakUserProvisioningService;
use App\Services\Recouvrement\RecouvrementDriverApiService;
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

class PartnerDriverService
{
    /**
     * Rôle local Tracking attribué aux chauffeurs créés par un partenaire.
     */
    private const DRIVER_LOCAL_ROLE_SLUG = 'utilisateur_secondaire';

    private const SIMPLE_PARTNER = 'SIMPLE_PARTNER';

    private const LEASE_PARTNER = 'LEASE_PARTNER';

    /**
     * Rôle Keycloak dans le client recouvrement_app.
     */
    private const RECOUVREMENT_KEYCLOAK_ROLE = 'DRIVER';

    public function __construct(
        private KeycloakAdminService $keycloakAdminService,
        private KeycloakUserProvisioningService $keycloakUserProvisioningService,
        private RecouvrementDriverApiService $recouvrementDriverApiService
    ) {}

    public function listDrivers(User $actor)
    {
        $partner = $this->resolveTenantPartner($actor);

        return User::query()
            ->with('role')
            ->where('partner_id', $partner->id)
            ->whereHas('role', function ($q) {
                $q->where('slug', self::DRIVER_LOCAL_ROLE_SLUG);
            })
            ->latest()
            ->get();
    }

    public function createDriver(User $actor, array $data): User
    {
        $partner = $this->resolveTenantPartner($actor);

        $roleId = Role::where('slug', self::DRIVER_LOCAL_ROLE_SLUG)->value('id');

        if (! $roleId) {
            throw new ConflictHttpException("Le rôle local '" . self::DRIVER_LOCAL_ROLE_SLUG . "' n'existe pas.");
        }

        $phoneE164 = Phone::e164($data['phone']);

        if (! $phoneE164) {
            throw new ConflictHttpException('Téléphone invalide.');
        }

        $data['phone'] = $phoneE164;

        $this->assertPhoneAvailable($data['phone']);
        $this->assertEmailAvailable($data['email'] ?? null);

        $driver = null;
        $createdKeycloakUserId = null;
        $createdKeycloakUserWasNew = false;
        $createdRecouvrementDriverId = null;

        DB::beginTransaction();

        try {
            /**
             * 1. Création locale dans MySQL.
             * Le tenant est toujours le vrai partenaire.
             * created_by garde l'utilisateur connecté réel : partenaire ou partner_admin.
             */
            $driver = $this->createLocalDriverInsideTransaction(
                partner: $partner,
                actor: $actor,
                data: $data,
                roleId: $roleId
            );

            /**
             * 2. Création ou récupération du compte Keycloak.
             */
            $keycloakResult = $this->keycloakUserProvisioningService->provisionDriverCreatedByPartner(
                user: $driver,
                plainPassword: $data['password']
            );

            if (empty($keycloakResult['keycloak_user_id'])) {
                throw new RuntimeException('Keycloak a répondu sans keycloak_id.');
            }

            $createdKeycloakUserId = $keycloakResult['keycloak_user_id'];
            $createdKeycloakUserWasNew = (bool) ($keycloakResult['created'] ?? false);

            $driver->refresh();

            /**
             * 3. Pour LEASE_PARTNER, on synchronise aussi Recouvrement.
             */
            if ($this->isLeasePartner($partner)) {
                $this->keycloakAdminService->assignClientRoleToUser(
                    keycloakUserId: $driver->keycloak_id,
                    clientId: config('services.keycloak.recouvrement_client_id', 'recouvrement_app'),
                    roleName: self::RECOUVREMENT_KEYCLOAK_ROLE
                );

                $recouvrementResponse = $this->recouvrementDriverApiService->createDriver(
                    partner: $partner,
                    driver: $driver,
                    plainPassword: $data['password'],
                    keycloakId: $driver->keycloak_id
                );

                $createdRecouvrementDriverId = $this->extractRecouvrementDriverId($recouvrementResponse);

                if (empty($createdRecouvrementDriverId)) {
                    throw new RuntimeException('Recouvrement a créé/répondu sans id chauffeur exploitable.');
                }

                $driver->forceFill([
                    'recouvrement_driver_id' => $createdRecouvrementDriverId,
                    'recouvrement_sync_status' => 'SYNCED',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ])->save();
            } else {
                $driver->forceFill([
                    'recouvrement_sync_status' => 'NOT_REQUIRED',
                    'sync_error' => null,
                ])->save();
            }

            DB::commit();

            return $driver->fresh('role');
        } catch (Throwable $e) {
            DB::rollBack();

            /**
             * Compensation Recouvrement si le chauffeur y a déjà été créé.
             */
            if ($createdRecouvrementDriverId) {
                try {
                    $this->recouvrementDriverApiService->deleteDriverByRecouvrementId(
                        partner: $partner,
                        recouvrementDriverId: (string) $createdRecouvrementDriverId,
                        context: [
                            'local_driver_id' => $driver?->id,
                            'keycloak_id' => $createdKeycloakUserId,
                            'reason' => 'compensation_after_create_failure',
                        ]
                    );
                } catch (Throwable $deleteException) {
                    Log::error('[DRIVER_ATOMIC_COMPENSATION_RECOUVREMENT_DELETE_FAILED]', [
                        'partner_id' => $partner->id,
                        'recouvrement_driver_id' => $createdRecouvrementDriverId,
                        'error' => $deleteException->getMessage(),
                    ]);
                }
            }

            /**
             * Compensation Keycloak.
             * Si Keycloak existait déjà avant, on ne le supprime pas.
             */
            if ($createdKeycloakUserId && $createdKeycloakUserWasNew) {
                try {
                    $this->keycloakAdminService->deleteUser($createdKeycloakUserId);

                    Log::warning('[DRIVER_ATOMIC_COMPENSATION_KEYCLOAK_DELETED]', [
                        'partner_id' => $partner->id,
                        'keycloak_id' => $createdKeycloakUserId,
                    ]);
                } catch (Throwable $deleteException) {
                    Log::error('[DRIVER_ATOMIC_COMPENSATION_KEYCLOAK_DELETE_FAILED]', [
                        'partner_id' => $partner->id,
                        'keycloak_id' => $createdKeycloakUserId,
                        'error' => $deleteException->getMessage(),
                    ]);
                }
            }

            /**
             * La transaction DB est annulée, mais le fichier photo uploadé doit être supprimé manuellement.
             */
            if ($driver?->photo) {
                Storage::disk(config('media.disk', 'public'))->delete($driver->photo);
            }

            Log::error('[DRIVER_ATOMIC_CREATION_FAILED]', [
                'actor_id' => $actor->id,
                'partner_id' => $partner->id,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'created_keycloak_id' => $createdKeycloakUserId,
                'created_recouvrement_driver_id' => $createdRecouvrementDriverId,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Création chauffeur annulée. Aucun chauffeur local n’a été conservé. Cause : ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function updateDriver(User $actor, int $driverId, array $data): User
    {
        $partner = $this->resolveTenantPartner($actor);
        $driver = $this->findOrFailDriverOfPartner($partner, $driverId);
        $originalDriver = $this->cloneDriverForExternalRestore($driver);

        $phoneE164 = Phone::e164($data['phone']);

        if (! $phoneE164) {
            throw new ConflictHttpException('Téléphone invalide.');
        }

        $data['phone'] = $phoneE164;

        $this->assertPhoneAvailable($data['phone'], $driver->id);
        $this->assertEmailAvailable($data['email'] ?? null, $driver->id);

        $disk = config('media.disk', 'public');
        $oldPhotoPath = $driver->photo;
        $newPhotoPath = null;

        $keycloakUpdated = false;
        $keycloakPasswordUpdated = false;
        $recouvrementUpdated = false;

        DB::beginTransaction();

        try {
            if (! empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $newPhotoPath = $data['photo']->store('users/photos', $disk);
                $driver->photo = $newPhotoPath;
            }

            $driver->nom = $data['nom'];
            $driver->prenom = $data['prenom'];
            $driver->phone = $data['phone'];
            $driver->email = $data['email'] ?? null;
            $driver->ville = $data['ville'] ?? null;
            $driver->quartier = $data['quartier'] ?? null;

            /**
             * Username Keycloak = téléphone.
             */
            $driver->keycloak_username = $data['phone'];

            $plainPassword = null;

            if (! empty($data['password'])) {
                $plainPassword = $data['password'];
                $driver->password = Hash::make($plainPassword);
            }

            try {
                $driver->save();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->throwConflictFromDuplicate($e->getMessage());
                }

                throw $e;
            }

            /**
             * Keycloak est obligatoire pour SIMPLE_PARTNER et LEASE_PARTNER.
             */
            if (empty($driver->keycloak_id)) {
                throw new RuntimeException(
                    "Modification bloquée : le chauffeur local #{$driver->id} n'a pas de keycloak_id."
                );
            }

            $this->keycloakAdminService->updateUserFromLocalUser($driver);
            $keycloakUpdated = true;

            $driver->forceFill([
                'keycloak_sync_status' => 'SYNCED',
                'last_synced_at' => now(),
                'sync_error' => null,
            ])->save();

            /**
             * Recouvrement uniquement pour LEASE_PARTNER.
             */
            if ($this->isLeasePartner($partner)) {
                if (empty($driver->recouvrement_driver_id)) {
                    throw new RuntimeException(
                        "Modification bloquée : le chauffeur local #{$driver->id} n'a pas de recouvrement_driver_id."
                    );
                }

                $this->recouvrementDriverApiService->updateDriver(
                    partner: $partner,
                    driver: $driver,
                    plainPassword: $plainPassword
                );

                $recouvrementUpdated = true;

                $driver->forceFill([
                    'recouvrement_sync_status' => 'SYNCED',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ])->save();
            } else {
                $driver->forceFill([
                    'recouvrement_sync_status' => 'NOT_REQUIRED',
                ])->save();
            }

            /**
             * Mot de passe : on le pousse vers Keycloak à la fin pour éviter de changer
             * le credential Keycloak si la mise à jour Recouvrement échoue avant.
             * Limite connue : l'ancien mot de passe ne peut pas être restauré car il est hashé localement.
             */
            if ($plainPassword) {
                $this->keycloakAdminService->resetUserPassword(
                    $driver->keycloak_id,
                    $plainPassword,
                    false
                );

                $keycloakPasswordUpdated = true;
            }

            DB::commit();

            /**
             * On supprime l'ancienne photo seulement après commit complet.
             */
            if ($newPhotoPath && $oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
                Storage::disk($disk)->delete($oldPhotoPath);
            }

            return $driver->fresh('role');
        } catch (Throwable $e) {
            DB::rollBack();

            if ($newPhotoPath) {
                Storage::disk($disk)->delete($newPhotoPath);
            }

            /**
             * Compensation Recouvrement si la mise à jour externe avait déjà réussi.
             */
            if ($recouvrementUpdated) {
                try {
                    $this->recouvrementDriverApiService->updateDriver(
                        partner: $partner,
                        driver: $originalDriver,
                        plainPassword: null
                    );
                } catch (Throwable $restoreException) {
                    Log::error('[PARTNER_DRIVER_UPDATE_RECOUVREMENT_RESTORE_FAILED]', [
                        'actor_id' => $actor->id,
                        'partner_id' => $partner->id,
                        'driver_id' => $driverId,
                        'error' => $restoreException->getMessage(),
                    ]);
                }
            }

            /**
             * Compensation Keycloak profil si la mise à jour externe avait déjà réussi.
             */
            if ($keycloakUpdated) {
                try {
                    $this->keycloakAdminService->updateUserFromLocalUser($originalDriver);
                } catch (Throwable $restoreException) {
                    Log::error('[PARTNER_DRIVER_UPDATE_KEYCLOAK_RESTORE_FAILED]', [
                        'actor_id' => $actor->id,
                        'partner_id' => $partner->id,
                        'driver_id' => $driverId,
                        'keycloak_id' => $originalDriver->keycloak_id,
                        'error' => $restoreException->getMessage(),
                    ]);
                }
            }

            Log::error('[PARTNER_DRIVER_UPDATE_ATOMIC_FAILED]', [
                'actor_id' => $actor->id,
                'partner_id' => $partner->id,
                'driver_id' => $driverId,
                'keycloak_profile_updated' => $keycloakUpdated,
                'keycloak_password_updated' => $keycloakPasswordUpdated,
                'recouvrement_updated' => $recouvrementUpdated,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Modification chauffeur annulée. Cause : ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function deleteDriver(User $actor, int $driverId): void
    {
        $partner = $this->resolveTenantPartner($actor);
        $driver = $this->findOrFailDriverOfPartner($partner, $driverId);

        $disk = config('media.disk', 'public');
        $photoPath = $driver->photo;

        /**
         * 1. Pour LEASE_PARTNER, supprimer d'abord dans Recouvrement.
         * deleteDriver() accepte 404 comme succès côté service Recouvrement.
         */
        if ($this->isLeasePartner($partner)) {
            if (empty($driver->recouvrement_driver_id)) {
                throw new RuntimeException(
                    "Suppression bloquée : le chauffeur local #{$driver->id} n'a pas de recouvrement_driver_id."
                );
            }

            $this->recouvrementDriverApiService->deleteDriver(
                partner: $partner,
                driver: $driver
            );
        }

        /**
         * 2. Suppression Keycloak obligatoire pour SIMPLE_PARTNER et LEASE_PARTNER.
         * deleteUser() accepte déjà 404 comme succès côté Keycloak.
         */
        if (empty($driver->keycloak_id)) {
            throw new RuntimeException(
                "Suppression bloquée : le chauffeur local #{$driver->id} n'a pas de keycloak_id."
            );
        }

        $this->keycloakAdminService->deleteUser($driver->keycloak_id);

        /**
         * 3. Suppression locale uniquement après succès des suppressions externes.
         */
        DB::transaction(function () use ($driver) {
            $driver->delete();
        });

        /**
         * 4. Suppression du fichier après commit DB.
         */
        if ($photoPath) {
            Storage::disk($disk)->delete($photoPath);
        }

        Log::warning('[PARTNER_DRIVER_DELETED_ATOMICALLY]', [
            'actor_id' => $actor->id,
            'partner_id' => $partner->id,
            'driver_id' => $driverId,
            'keycloak_id' => $driver->keycloak_id,
            'recouvrement_driver_id' => $driver->recouvrement_driver_id,
            'partner_type' => $partner->type_partner,
        ]);
    }

    private function createLocalDriverInsideTransaction(User $partner, User $actor, array $data, int $roleId): User
    {
        $photoPath = null;

        if (! empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
            $disk = config('media.disk', 'public');
            $photoPath = $data['photo']->store('users/photos', $disk);
        }

        $payload = [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'ville' => $data['ville'] ?? null,
            'quartier' => $data['quartier'] ?? null,
            'photo' => $photoPath,
            'password' => Hash::make($data['password']),
            'role_id' => $roleId,

            /**
             * Le chauffeur appartient toujours au vrai partenaire tenant.
             */
            'partner_id' => $partner->id,

            /**
             * Celui qui a créé peut être le partenaire lui-même ou un partner_admin.
             */
            'created_by' => $actor->id,

            'user_unique_id' => $this->generateUserUniqueId(),
            'keycloak_username' => $data['phone'],
            'keycloak_sync_status' => 'PENDING',
            'recouvrement_sync_status' => $this->isLeasePartner($partner) ? 'PENDING' : 'NOT_REQUIRED',
            'sync_error' => null,
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

    private function resolveTenantPartner(User $actor): User
    {
        if (! empty($actor->partner_id)) {
            return User::query()->findOrFail($actor->partner_id);
        }

        return $actor;
    }

    private function isLeasePartner(User $partner): bool
    {
        return strtoupper((string) ($partner->type_partner ?? self::SIMPLE_PARTNER)) === self::LEASE_PARTNER;
    }

    private function findOrFailDriverOfPartner(User $partner, int $driverId): User
    {
        return User::query()
            ->with('role')
            ->where('id', $driverId)
            ->where('partner_id', $partner->id)
            ->whereHas('role', function ($q) {
                $q->where('slug', self::DRIVER_LOCAL_ROLE_SLUG);
            })
            ->firstOrFail();
    }

    private function cloneDriverForExternalRestore(User $driver): User
    {
        $snapshot = new User();
        $snapshot->exists = true;
        $snapshot->setRawAttributes($driver->getAttributes(), true);

        if ($driver->relationLoaded('role')) {
            $snapshot->setRelation('role', $driver->getRelation('role'));
        } else {
            $snapshot->setRelation('role', $driver->role()->first());
        }

        return $snapshot;
    }

    private function assertPhoneAvailable(string $phoneInput, ?int $ignoreUserId = null): void
    {
        $candidates = Phone::candidates($phoneInput);

        if (empty($candidates)) {
            throw new ConflictHttpException('Téléphone invalide.');
        }

        $query = User::query()->whereIn('phone', $candidates);

        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        if ($query->exists()) {
            throw new ConflictHttpException('Téléphone déjà utilisé.');
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
            throw new ConflictHttpException('Email déjà utilisé.');
        }
    }

    private function generateUserUniqueId(): string
    {
        $prefix = 'PxT-' . now()->format('Ym') . '-';

        do {
            $suffix = Str::ucfirst(Str::lower(Str::random(4)));
            $id = $prefix . $suffix;
        } while (User::where('user_unique_id', $id)->exists());

        return $id;
    }

    private function extractRecouvrementDriverId(array $response): ?string
    {
        $id = data_get($response, 'id')
            ?? data_get($response, 'data.id')
            ?? data_get($response, 'user.id')
            ?? data_get($response, 'chauffeur.id')
            ?? data_get($response, 'devMsg.id');

        return $id !== null ? (string) $id : null;
    }

    private function throwConflictFromDuplicate(string $message): void
    {
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'users_phone_unique') || str_contains($lower, 'phone')) {
            throw new ConflictHttpException('Téléphone déjà utilisé.');
        }

        if (str_contains($lower, 'users_email_unique') || str_contains($lower, 'email')) {
            throw new ConflictHttpException('Email déjà utilisé.');
        }

        if (str_contains($lower, 'keycloak_id')) {
            throw new ConflictHttpException('Compte Keycloak déjà lié à un utilisateur.');
        }

        throw new ConflictHttpException('Donnée déjà utilisée.');
    }
}
