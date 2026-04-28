<?php

namespace App\Services\Partner;

use App\Models\Role;
use App\Models\User;
use App\Services\Keycloak\KeycloakAdminService;
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
     * Rôle local dans la base Tracking.
     */
    private const DRIVER_LOCAL_ROLE_SLUG = 'utilisateur_secondaire';

    private const SIMPLE_PARTNER = 'SIMPLE_PARTNER';

    private const LEASE_PARTNER = 'LEASE_PARTNER';

    /**
     * Rôle Keycloak dans le client tracking_app.
     *
     * Important :
     * Ce rôle doit exister exactement avec ce nom dans Keycloak,
     * dans le client tracking_app.
     */
    private const TRACKING_KEYCLOAK_ROLE = 'utilisateur_secondaire';

    /**
     * Rôle Keycloak dans le client recouvrement_app.
     *
     * Important :
     * Ce rôle doit exister exactement avec ce nom dans Keycloak,
     * dans le client recouvrement_app.
     */
    private const RECOUVREMENT_KEYCLOAK_ROLE = 'DRIVER';

    public function __construct(
        private KeycloakAdminService $keycloakAdminService,
        private RecouvrementDriverApiService $recouvrementDriverApiService
    ) {}

    public function listDrivers(User $partner)
    {
        return User::query()
            ->with('role')
            ->where('partner_id', $partner->id)
            ->whereHas('role', function ($q) {
                $q->where('slug', self::DRIVER_LOCAL_ROLE_SLUG);
            })
            ->latest()
            ->get();
    }

    public function createDriver(User $partner, array $data): User
    {
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

        $createdKeycloakUserId = null;
        $createdKeycloakUserWasNew = false;

        DB::beginTransaction();

        try {
            /**
             * 1. Création locale du chauffeur dans la transaction MySQL.
             */
            $driver = $this->createLocalDriverInsideTransaction(
                partner: $partner,
                data: $data,
                roleId: $roleId
            );

            /**
             * 2. Création ou récupération du user Keycloak.
             * Username Keycloak = téléphone E.164.
             */
            $keycloakResult = $this->keycloakAdminService->createOrFindUserWithPassword(
                $driver,
                $data['password'],
                false
            );

            if (empty($keycloakResult['id'])) {
                throw new RuntimeException('Keycloak a répondu sans keycloak_id.');
            }

            $createdKeycloakUserId = $keycloakResult['id'];
            $createdKeycloakUserWasNew = (bool) ($keycloakResult['created'] ?? false);

            $driver->forceFill([
                'keycloak_id' => $keycloakResult['id'],
                'keycloak_username' => $keycloakResult['username'] ?? $data['phone'],
                'keycloak_sync_status' => 'SYNCED',
                'last_synced_at' => now(),
                'sync_error' => null,
            ])->save();

            /**
             * 3. Attribution du rôle côté client tracking_app.
             *
             * Local Tracking DB      : utilisateur_secondaire
             * Keycloak tracking_app  : utilisateur_secondaire
             */
            $this->keycloakAdminService->assignClientRoleToUser(
                keycloakUserId: $driver->keycloak_id,
                clientId: config('services.keycloak.tracking_client_id', 'tracking_app'),
                roleName: self::TRACKING_KEYCLOAK_ROLE
            );

            /**
             * 4. Si partenaire LEASE_PARTNER :
             *    - attribution rôle DRIVER sur recouvrement_app
             *    - création chauffeur dans recouvrement
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

                $driver->forceFill([
                    'recouvrement_driver_id' => $this->extractRecouvrementDriverId($recouvrementResponse),
                    'recouvrement_sync_status' => 'SYNCED',
                    'last_synced_at' => now(),
                    'sync_error' => null,
                ])->save();
            }

            /**
             * 5. Tout a réussi : commit.
             */
            DB::commit();

            return $driver->fresh('role');
        } catch (Throwable $e) {
            /**
             * 6. Une étape a échoué : rollback MySQL.
             */
            DB::rollBack();

            /**
             * 7. Compensation Keycloak.
             *
             * Si on a créé un user Keycloak pendant cette opération,
             * on tente de le supprimer.
             *
             * Si le user existait déjà avant, on ne le supprime pas
             * pour éviter de casser un compte légitime.
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

            Log::error('[DRIVER_ATOMIC_CREATION_FAILED]', [
                'partner_id' => $partner->id,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                'Création chauffeur annulée. Aucun chauffeur local n’a été conservé. Cause : ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function updateDriver(User $partner, int $driverId, array $data): User
    {
        $driver = $this->findOrFailDriverOfPartner($partner, $driverId);

        $phoneE164 = Phone::e164($data['phone']);

        if (! $phoneE164) {
            throw new ConflictHttpException('Téléphone invalide.');
        }

        $data['phone'] = $phoneE164;

        $this->assertPhoneAvailable($data['phone'], $driver->id);
        $this->assertEmailAvailable($data['email'] ?? null, $driver->id);

        return DB::transaction(function () use ($driver, $data) {
            if (! empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $disk = config('media.disk', 'public');

                if ($driver->photo) {
                    Storage::disk($disk)->delete($driver->photo);
                }

                $driver->photo = $data['photo']->store('users/photos', $disk);
            }

            $driver->nom = $data['nom'];
            $driver->prenom = $data['prenom'];
            $driver->phone = $data['phone'];
            $driver->email = $data['email'] ?? null;
            $driver->ville = $data['ville'] ?? null;
            $driver->quartier = $data['quartier'] ?? null;

            if (! empty($data['password'])) {
                $driver->password = Hash::make($data['password']);

                if ($driver->keycloak_id) {
                    $this->keycloakAdminService->resetUserPassword(
                        $driver->keycloak_id,
                        $data['password'],
                        false
                    );
                }
            }

            try {
                $driver->save();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->throwConflictFromDuplicate($e->getMessage());
                }

                throw $e;
            }

            return $driver->fresh('role');
        });
    }

    public function deleteDriver(User $partner, int $driverId): void
    {
        $driver = $this->findOrFailDriverOfPartner($partner, $driverId);

        DB::transaction(function () use ($driver) {
            $disk = config('media.disk', 'public');

            if ($driver->photo) {
                Storage::disk($disk)->delete($driver->photo);
            }

            /**
             * Suppression locale uniquement.
             * Ne pas supprimer Keycloak sans décision métier claire.
             */
            $driver->delete();
        });
    }

    private function createLocalDriverInsideTransaction(User $partner, array $data, int $roleId): User
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

            /**
             * Rôle local Tracking.
             */
            'role_id' => $roleId,

            /**
             * Le chauffeur appartient au user partenaire.
             */
            'partner_id' => $partner->id,
            'created_by' => $partner->id,

            'user_unique_id' => $this->generateUserUniqueId(),

            /**
             * Username Keycloak = téléphone E.164.
             */
            'keycloak_username' => $data['phone'],
            'keycloak_sync_status' => 'PENDING',

            /**
             * Recouvrement uniquement pour LEASE_PARTNER.
             */
            'recouvrement_sync_status' => $this->isLeasePartner($partner) ? 'PENDING' : 'NOT_REQUIRED',

            'sync_error' => null,
        ];

        try {
            return User::create($payload)->load('role');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $this->throwConflictFromDuplicate($e->getMessage());
            }

            throw $e;
        }
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
        return data_get($response, 'id')
            ?? data_get($response, 'data.id')
            ?? data_get($response, 'user.id')
            ?? data_get($response, 'chauffeur.id')
            ?? data_get($response, 'devMsg.id');
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