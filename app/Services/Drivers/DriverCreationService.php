<?php

namespace App\Services\Drivers;

use App\Models\User;
use App\Services\Keycloak\KeycloakDriverProvisioningService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DriverCreationService
{
    public function __construct(
        private KeycloakDriverProvisioningService $keycloakDriverProvisioningService
    ) {}

    public function create(User $actor, array $data): User
    {
        $partner = $this->resolvePartner($actor);

        return DB::transaction(function () use ($partner, $data) {
            $driver = User::create([
                'nom' => $data['nom'],
                'prenom' => $data['prenom'] ?? null,
                'email' => $data['email'],
                'telephone' => $data['telephone'] ?? null,
                'partner_id' => $partner->id,
                'role' => 'utilisateur_secondaire',
                'type_partner' => null,
                'keycloak_sync_status' => $partner->type_partner === 'PARTNER_LEASE'
                    ? 'PENDING'
                    : 'NOT_REQUIRED',
            ]);

            if ($partner->type_partner === 'PARTNER_LEASE') {
                if (empty($data['password'])) {
                    throw new RuntimeException("Le mot de passe est obligatoire pour synchroniser le chauffeur dans Keycloak.");
                }

                $kc = $this->keycloakDriverProvisioningService
                    ->provisionDriver($driver, $data['password']);

                $driver->update([
                    'keycloak_user_id' => $kc['keycloak_user_id'],
                    'keycloak_sync_status' => 'SYNCED',
                ]);
            }

            return $driver->fresh();
        });
    }

    private function resolvePartner(User $actor): User
    {
        $partnerUserId = $actor->partner_id ?: $actor->id;

        return User::findOrFail($partnerUserId);
    }
}