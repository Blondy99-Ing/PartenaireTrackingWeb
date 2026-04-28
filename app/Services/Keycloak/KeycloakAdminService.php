<?php

namespace App\Services\Keycloak;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class KeycloakAdminService
{
    protected ?string $adminToken = null;

    protected array $clientUuidCache = [];

    public function getAdminToken(): string
    {
        if ($this->adminToken) {
            return $this->adminToken;
        }

        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');
        $authRealm = config('services.keycloak.admin_auth_realm', 'master');

        if ($baseUrl === '') {
            throw new RuntimeException('KEYCLOAK_BASE_URL est manquant.');
        }

        $response = Http::asForm()->post(
            "{$baseUrl}/realms/{$authRealm}/protocol/openid-connect/token",
            [
                'grant_type' => 'password',
                'client_id' => config('services.keycloak.admin_client_id', 'admin-cli'),
                'username' => config('services.keycloak.admin_user'),
                'password' => config('services.keycloak.admin_password'),
            ]
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Impossible d’obtenir le token admin Keycloak. Réponse: ' . $response->body()
            );
        }

        $accessToken = $response->json('access_token');

        if (! $accessToken) {
            throw new RuntimeException('Token admin Keycloak absent.');
        }

        return $this->adminToken = $accessToken;
    }

    protected function adminHttp(?string $realm = null)
    {
        $baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');

        $targetRealm = $realm ?: config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        return Http::withToken($this->getAdminToken())
            ->acceptJson()
            ->contentType('application/json')
            ->baseUrl("{$baseUrl}/admin/realms/{$targetRealm}");
    }

    public function findUserByUsername(string $username): ?array
    {
        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->get('/users', [
            'username' => $username,
            'exact' => 'true',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Recherche utilisateur Keycloak impossible. Réponse: ' . $response->body()
            );
        }

        $users = $response->json();

        return ! empty($users) ? $users[0] : null;
    }

    public function createOrFindUserWithPassword(
        User $user,
        string $plainPassword,
        bool $temporaryPassword = false
    ): array {
        $username = $this->resolveUsername($user);
        $email = $this->sanitizeEmail($user->email);

        $existing = $this->findUserByUsername($username);

        if ($existing) {
            return [
                'id' => $existing['id'],
                'username' => $existing['username'] ?? $username,
                'created' => false,
            ];
        }

        $payload = [
            'username' => $username,
            'email' => $email,
            'firstName' => $user->prenom ?: 'Non renseigné',
            'lastName' => $user->nom ?: 'Non renseigné',
            'enabled' => true,
            'emailVerified' => true,
            'credentials' => [
                [
                    'type' => 'password',
                    'value' => $plainPassword,
                    'temporary' => $temporaryPassword,
                ],
            ],
            'attributes' => [
                'local_user_id' => [(string) $user->id],
                'user_unique_id' => [(string) ($user->user_unique_id ?? '')],
                'phone' => [(string) ($user->phone ?? '')],
            ],
        ];

        if ($temporaryPassword) {
            $payload['requiredActions'] = ['UPDATE_PASSWORD'];
        }

        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->post('/users', $payload);

        if (! in_array($response->status(), [201, 409], true)) {
            throw new RuntimeException(
                'Création utilisateur Keycloak échouée: ' . $response->body()
            );
        }

        $createdUser = $this->findUserByUsername($username);

        if (! $createdUser || empty($createdUser['id'])) {
            throw new RuntimeException('Utilisateur créé mais UUID Keycloak introuvable.');
        }

        return [
            'id' => $createdUser['id'],
            'username' => $username,
            'created' => true,
        ];
    }

    public function resetUserPassword(string $keycloakUserId, string $plainPassword, bool $temporary = false): void
    {
        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->put(
            "/users/{$keycloakUserId}/reset-password",
            [
                'type' => 'password',
                'value' => $plainPassword,
                'temporary' => $temporary,
            ]
        );

        if (! in_array($response->status(), [204, 200], true)) {
            throw new RuntimeException(
                'Réinitialisation du mot de passe Keycloak échouée: ' . $response->body()
            );
        }
    }

    public function assignClientRoleToUser(
        string $keycloakUserId,
        string $clientId,
        string $roleName
    ): void {
        $clientUuid = $this->getClientUuid($clientId);
        $role = $this->getClientRole($clientUuid, $roleName);

        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->post(
            "/users/{$keycloakUserId}/role-mappings/clients/{$clientUuid}",
            [
                [
                    'id' => $role['id'],
                    'name' => $role['name'],
                ],
            ]
        );

        if (! in_array($response->status(), [204, 200], true)) {
            throw new RuntimeException(
                "Échec assignation rôle {$roleName} sur client {$clientId}. Réponse: " . $response->body()
            );
        }
    }

    public function getClientUuid(string $clientId): string
    {
        if (isset($this->clientUuidCache[$clientId])) {
            return $this->clientUuidCache[$clientId];
        }

        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->get('/clients', [
            'clientId' => $clientId,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Impossible de récupérer le client {$clientId}. Réponse: " . $response->body()
            );
        }

        $clients = $response->json();

        if (empty($clients) || empty($clients[0]['id'])) {
            throw new RuntimeException("UUID du client {$clientId} introuvable.");
        }

        return $this->clientUuidCache[$clientId] = $clients[0]['id'];
    }

    public function getClientRole(string $clientUuid, string $roleName): array
    {
        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->get(
            "/clients/{$clientUuid}/roles/{$roleName}"
        );

        if (! $response->successful()) {
            throw new RuntimeException(
                "Rôle {$roleName} introuvable pour le client UUID {$clientUuid}. Réponse: " . $response->body()
            );
        }

        $role = $response->json();

        if (empty($role['id']) || empty($role['name'])) {
            throw new RuntimeException("Réponse rôle Keycloak invalide pour {$roleName}.");
        }

        return $role;
    }

    public function deleteUser(string $keycloakUserId): void
    {
        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->delete("/users/{$keycloakUserId}");

        if (! in_array($response->status(), [204, 200, 404], true)) {
            throw new RuntimeException(
                "Suppression utilisateur Keycloak échouée. Réponse: " . $response->body()
            );
        }
    }

    public function assignTrackingAppRole(User $user, string $roleName): void
    {
        if (! $user->keycloak_id) {
            throw new RuntimeException("L'utilisateur local {$user->id} n'a pas de keycloak_id.");
        }

        $this->assignClientRoleToUser(
            $user->keycloak_id,
            config('services.keycloak.tracking_client_id', 'tracking_app'),
            $roleName
        );
    }

    public function getTrackingClientUuid(): string
    {
        return $this->getClientUuid(
            config('services.keycloak.tracking_client_id', 'tracking_app')
        );
    }

    public function getTrackingClientRoles(): Collection
    {
        $clientUuid = $this->getTrackingClientUuid();

        $targetRealm = config(
            'services.keycloak.admin_target_realm',
            config('services.keycloak.realm')
        );

        $response = $this->adminHttp($targetRealm)->get("/clients/{$clientUuid}/roles");

        if (! $response->successful()) {
            throw new RuntimeException(
                "Impossible de récupérer les rôles tracking_app. Réponse: " . $response->body()
            );
        }

        return collect($response->json());
    }

    public function syncLocalLink(User $user, array $keycloakResult): User
    {
        $user->keycloak_id = $keycloakResult['id'];
        $user->keycloak_username = $keycloakResult['username'];
        $user->last_synced_at = now();

        if (Schema::hasColumn('users', 'keycloak_sync_status')) {
            $user->keycloak_sync_status = 'SYNCED';
        }

        if (Schema::hasColumn('users', 'keycloak_migrated_at')) {
            $user->keycloak_migrated_at = now();
        }

        $user->save();

        $roleName = $this->resolveKeycloakRoleName($user->role);

        if ($roleName) {
            $this->assignTrackingAppRole($user, $roleName);
        }

        return $user->fresh();
    }

    public function resolveKeycloakRoleName(?Role $role): ?string
    {
        if (! $role?->slug) {
            return null;
        }

        return match ($role->slug) {
            'admin' => 'ADMIN',
            'call_center' => 'CALL_CENTER',
            'gestionnaire_plateforme' => 'GESTIONNAIRE_PLATEFORME',
            'utilisateur_principale' => 'UTILISATEUR_PRINCIPALE',
            'utilisateur_secondaire' => 'UTILISATEUR_SECONDAIRE',
            default => null,
        };
    }

    protected function resolveUsername(User $user): string
    {
        if ($user->keycloak_username) {
            return trim($user->keycloak_username);
        }

        if ($user->phone) {
            return trim($user->phone);
        }

        if ($user->email) {
            return Str::lower(trim($user->email));
        }

        return Str::lower((string) ($user->user_unique_id ?: Str::uuid()));
    }

    protected function sanitizeEmail(?string $email): string
    {
        $email = trim((string) $email);

        if ($email !== '') {
            return Str::lower($email);
        }

        return 'user' . substr(md5((string) microtime(true)), 0, 10) . '@proxymgroup.local';
    }
}