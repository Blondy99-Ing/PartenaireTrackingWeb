<?php

namespace App\Services\Keycloak;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakDriverProvisioningService
{
    private string $baseUrl;
    private string $realm;
    private string $adminClientId;
    private string $adminClientSecret;
    private string $targetClientId;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.keycloak.base_url'), '/');
        $this->realm = config('services.keycloak.realm', 'master');
        $this->adminClientId = config('services.keycloak.admin_client_id', 'admin-cli');
        $this->adminClientSecret = config('services.keycloak.admin_client_secret');
        $this->targetClientId = config('services.keycloak.target_client_id', 'tracking_app');
    }

    public function provisionDriver(User $driver, string $plainPassword): array
    {
        $adminToken = $this->getAdminAccessToken();

        $userId = $this->createUser($adminToken, $driver, $plainPassword);

        $createdUser = $this->getUserById($adminToken, $userId);

        $clientUuid = $this->getClientUuid($adminToken, $this->targetClientId);

        $driverRole = $this->getClientRoleByName($adminToken, $clientUuid, 'DRIVER');

        $this->assignClientRoleToUser(
            adminToken: $adminToken,
            userId: $userId,
            clientUuid: $clientUuid,
            role: [
                'id' => $driverRole['id'],
                'name' => $driverRole['name'],
            ],
        );

        return [
            'keycloak_user_id' => $userId,
            'keycloak_user' => $createdUser,
            'client_uuid' => $clientUuid,
            'role' => $driverRole,
        ];
    }

    private function getAdminAccessToken(): string
    {
        $response = Http::asForm()->post(
            "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/token",
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->adminClientId,
                'client_secret' => $this->adminClientSecret,
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException("Impossible d'obtenir le token admin Keycloak: {$response->body()}");
        }

        $json = $response->json();

        if (empty($json['access_token'])) {
            throw new RuntimeException("Le token admin Keycloak est introuvable dans la réponse.");
        }

        return $json['access_token'];
    }

    private function createUser(string $adminToken, User $driver, string $plainPassword): string
    {
        $payload = [
            'username' => $driver->email,
            'email' => $driver->email,
            'firstName' => $driver->nom ?? $driver->name ?? 'Driver',
            'lastName' => $driver->prenom ?? '',
            'enabled' => true,
            'emailVerified' => true,
            'credentials' => [
                [
                    'type' => 'password',
                    'value' => $plainPassword,
                    'temporary' => false,
                ],
            ],
            'attributes' => [
                'local_user_id' => [(string) $driver->id],
                'partner_user_id' => [(string) $driver->partner_id],
                'source_app' => ['tracking'],
                'local_role' => ['utilisateur_secondaire'],
            ],
        ];

        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->post("{$this->baseUrl}/admin/realms/{$this->realm}/users", $payload);

        if ($response->status() !== 201) {
            throw new RuntimeException("Création utilisateur Keycloak échouée: {$response->body()}");
        }

        $location = $response->header('Location');

        if (! $location || ! str_contains($location, '/users/')) {
            throw new RuntimeException("Header Location introuvable après création utilisateur Keycloak.");
        }

        return basename($location);
    }

    private function getUserById(string $adminToken, string $userId): array
    {
        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->get("{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}");

        if (! $response->successful()) {
            throw new RuntimeException("Impossible de récupérer l'utilisateur Keycloak créé: {$response->body()}");
        }

        return $response->json();
    }

    private function getClientUuid(string $adminToken, string $clientId): string
    {
        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->get("{$this->baseUrl}/admin/realms/{$this->realm}/clients", [
                'clientId' => $clientId,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Impossible de récupérer le client Keycloak {$clientId}: {$response->body()}");
        }

        $clients = $response->json();

        if (! is_array($clients) || empty($clients[0]['id'])) {
            throw new RuntimeException("UUID du client Keycloak {$clientId} introuvable.");
        }

        return $clients[0]['id'];
    }

    private function getClientRoleByName(string $adminToken, string $clientUuid, string $roleName): array
    {
        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->get("{$this->baseUrl}/admin/realms/{$this->realm}/clients/{$clientUuid}/roles/{$roleName}");

        if (! $response->successful()) {
            throw new RuntimeException("Impossible de récupérer le rôle Keycloak {$roleName}: {$response->body()}");
        }

        $role = $response->json();

        if (empty($role['id']) || empty($role['name'])) {
            throw new RuntimeException("Rôle Keycloak {$roleName} invalide ou incomplet.");
        }

        return $role;
    }

    private function assignClientRoleToUser(
        string $adminToken,
        string $userId,
        string $clientUuid,
        array $role
    ): void {
        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->post(
                "{$this->baseUrl}/admin/realms/{$this->realm}/users/{$userId}/role-mappings/clients/{$clientUuid}",
                [[
                    'id' => $role['id'],
                    'name' => $role['name'],
                ]]
            );

        if ($response->status() !== 204) {
            throw new RuntimeException("Attribution du rôle Keycloak échouée: {$response->body()}");
        }
    }
}