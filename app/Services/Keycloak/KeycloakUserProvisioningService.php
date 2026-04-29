<?php

namespace App\Services\Keycloak;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Service central de provisioning Keycloak.
 *
 * IMPORTANT :
 * Ce service respecte la configuration actuelle du projet :
 *
 * KEYCLOAK_ADMIN_CLIENT_ID=admin-cli
 * KEYCLOAK_ADMIN_USER=...
 * KEYCLOAK_ADMIN_PASSWORD=...
 * KEYCLOAK_ADMIN_TOKEN_URL=https://auth.proxymgroup.com/realms/master/protocol/openid-connect/token
 * KEYCLOAK_ADMIN_TARGET_REALM=proxymgroup
 * KEYCLOAK_ADMIN_TARGET_CLIENT_ID=tracking_app
 *
 * On n'utilise PAS client_credentials ici, parce que admin-cli est un client public.
 * On utilise le password grant admin comme ton ancienne logique.
 *
 * Règles métier :
 *
 * PARTENAIRE :
 * - users.partner_id = NULL
 * - compte_id = users.id
 * - rôle client tracking_app = gestionnaire_plateforme
 *
 * CHAUFFEUR / UTILISATEUR SECONDAIRE :
 * - users.partner_id = id du partenaire
 * - compte_id = users.partner_id
 * - rôle client tracking_app = utilisateur_secondaire
 */
class KeycloakUserProvisioningService
{
    private string $baseUrl;

    /**
     * Realm applicatif principal, généralement proxymgroup.
     */
    private string $realm;

    /**
     * Realm dans lequel on crée/met à jour les utilisateurs.
     * Chez toi : proxymgroup.
     */
    private string $targetRealm;

    /**
     * Client admin utilisé avec password grant.
     * Chez toi : admin-cli.
     */
    private string $adminClientId;

    /**
     * Login admin Keycloak.
     */
    private ?string $adminUsername;

    /**
     * Mot de passe admin Keycloak.
     */
    private ?string $adminPassword;

    /**
     * URL de token admin.
     * Chez toi : realm master.
     */
    private string $adminTokenUrl;

    /**
     * Client cible où assigner les rôles.
     * Chez toi : tracking_app.
     */
    private string $targetClientId;

    private const ROLE_PARTNER = 'gestionnaire_plateforme';
    private const ROLE_DRIVER = 'utilisateur_secondaire';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.keycloak.base_url'), '/');

        $this->realm = (string) config('services.keycloak.realm', 'proxymgroup');

        $this->targetRealm = (string) config(
            'services.keycloak.admin_target_realm',
            $this->realm
        );

        $this->adminClientId = (string) config(
            'services.keycloak.admin_client_id',
            'admin-cli'
        );

        $this->adminUsername = config('services.keycloak.admin_user');
        $this->adminPassword = config('services.keycloak.admin_password');

        $this->adminTokenUrl = (string) config(
            'services.keycloak.admin_token_url',
            "{$this->baseUrl}/realms/master/protocol/openid-connect/token"
        );

        $this->targetClientId = (string) config(
            'services.keycloak.admin_target_client_id',
            config(
                'services.keycloak.target_client_id',
                config('services.keycloak.tracking_client_id', 'tracking_app')
            )
        );

        if ($this->targetClientId === '') {
            $this->targetClientId = 'tracking_app';
        }
    }

    /**
     * Pré-provisionne un utilisateur local dans Keycloak SANS mot de passe.
     *
     * Utilisé par :
     * php artisan keycloak:migrate-users --include-existing
     *
     * Le mot de passe sera créé plus tard lors du premier login,
     * après validation du hash local Laravel.
     */
    public function preProvisionUserWithoutPassword(User $user): array
    {
        return $this->provision(
            user: $user,
            plainPassword: null,
            resetPasswordIfExists: false,
            source: 'pre_provision_without_password'
        );
    }

    /**
     * Provisionne un utilisateur Keycloak AVEC le mot de passe saisi.
     *
     * Cette méthode doit être appelée uniquement après :
     * Hash::check($plainPassword, $user->password) === true
     */
    public function provisionUserWithPassword(User $user, string $plainPassword): array
    {
        if (trim($plainPassword) === '') {
            throw new RuntimeException('Mot de passe vide : impossible de créer le credential Keycloak.');
        }

        return $this->provision(
            user: $user,
            plainPassword: $plainPassword,
            resetPasswordIfExists: true,
            source: 'first_login_local_password_match'
        );
    }

    /**
     * Compatibilité avec les anciens appels.
     */
    public function provisionUser(User $user, string $plainPassword): array
    {
        return $this->provisionUserWithPassword($user, $plainPassword);
    }

    private function provision(
        User $user,
        ?string $plainPassword,
        bool $resetPasswordIfExists,
        string $source
    ): array {
        $adminToken = $this->getAdminAccessToken();

        $username = $this->resolveUsername($user);
        $compteId = $this->resolveCompteId($user);
        $businessType = $this->resolveBusinessType($user);
        $roleName = $this->resolveRoleName($user);

        Log::info('[KEYCLOAK_USER_PROVISION_START]', [
            'source' => $source,
            'local_user_id' => $user->id,
            'partner_id' => $user->partner_id,
            'business_type' => $businessType,
            'compte_id' => $compteId,
            'username' => $username,
            'email' => $user->email,
            'target_realm' => $this->targetRealm,
            'target_client_id' => $this->targetClientId,
            'role' => $roleName,
            'with_password' => $plainPassword !== null,
        ]);

        $clientUuid = $this->getClientUuid($adminToken, $this->targetClientId);
        $role = $this->getClientRoleByName($adminToken, $clientUuid, $roleName);

        $existingId = $this->findUserIdByUsernameOrEmail(
            adminToken: $adminToken,
            username: $username,
            email: $user->email
        );

        $created = false;

        if ($existingId) {
            $keycloakUserId = $existingId;

            Log::info('[KEYCLOAK_USER_PROVISION_EXISTING_FOUND]', [
                'local_user_id' => $user->id,
                'keycloak_id' => $keycloakUserId,
                'username' => $username,
            ]);

            $this->updateUserProfile(
                adminToken: $adminToken,
                userId: $keycloakUserId,
                user: $user,
                username: $username
            );
        } else {
            $keycloakUserId = $this->createUser(
                adminToken: $adminToken,
                user: $user,
                username: $username,
                plainPassword: $plainPassword
            );

            $created = true;

            Log::info('[KEYCLOAK_USER_PROVISION_CREATED]', [
                'local_user_id' => $user->id,
                'keycloak_id' => $keycloakUserId,
                'username' => $username,
            ]);
        }

        if ($plainPassword !== null && $resetPasswordIfExists) {
            $this->resetUserPassword(
                adminToken: $adminToken,
                userId: $keycloakUserId,
                plainPassword: $plainPassword,
                temporary: false
            );
        }

        $this->assignClientRoleToUser(
            adminToken: $adminToken,
            userId: $keycloakUserId,
            clientUuid: $clientUuid,
            role: [
                'id' => $role['id'],
                'name' => $role['name'],
            ]
        );

        $createdUser = $this->getUserById($adminToken, $keycloakUserId);

        $user->forceFill([
            'keycloak_id' => $keycloakUserId,
            'keycloak_username' => $createdUser['username'] ?? $username,
            'keycloak_sync_status' => 'SYNCED',
            'last_synced_at' => now(),
            'sync_error' => null,
        ])->save();

        Log::info('[KEYCLOAK_USER_PROVISION_DONE]', [
            'source' => $source,
            'local_user_id' => $user->id,
            'keycloak_id' => $keycloakUserId,
            'keycloak_username' => $createdUser['username'] ?? $username,
            'compte_id' => $compteId,
            'business_type' => $businessType,
            'role' => $roleName,
            'created' => $created,
            'with_password' => $plainPassword !== null,
        ]);

        return [
            'ok' => true,
            'created' => $created,
            'keycloak_user_id' => $keycloakUserId,
            'keycloak_username' => $createdUser['username'] ?? $username,
            'client_uuid' => $clientUuid,
            'role' => $role,
            'role_name' => $roleName,
            'compte_id' => $compteId,
            'business_type' => $businessType,
            'with_password' => $plainPassword !== null,
        ];
    }

    /**
     * Récupération du token admin.
     *
     * Ici on respecte ta config actuelle :
     * - admin-cli
     * - admin username/password
     * - token URL dans le realm master
     *
     * On ne fait pas client_credentials.
     */
    private function getAdminAccessToken(): string
    {
        if (empty($this->adminUsername) || empty($this->adminPassword)) {
            throw new RuntimeException(
                'Configuration admin Keycloak invalide : KEYCLOAK_ADMIN_USER ou KEYCLOAK_ADMIN_PASSWORD absent.'
            );
        }

        $payload = [
            'grant_type' => 'password',
            'client_id' => $this->adminClientId ?: 'admin-cli',
            'username' => $this->adminUsername,
            'password' => $this->adminPassword,
        ];

        Log::info('[KEYCLOAK_ADMIN_TOKEN_REQUEST_PASSWORD_GRANT]', [
            'token_url' => $this->adminTokenUrl,
            'client_id' => $payload['client_id'],
            'username' => $this->adminUsername,
            'target_realm' => $this->targetRealm,
        ]);

        $response = Http::asForm()
            ->timeout(20)
            ->post($this->adminTokenUrl, $payload);

        Log::info('[KEYCLOAK_ADMIN_TOKEN_RESPONSE_PASSWORD_GRANT]', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 500),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Impossible d'obtenir le token admin Keycloak via password grant. Réponse: {$response->body()}"
            );
        }

        $json = $response->json();

        if (empty($json['access_token'])) {
            throw new RuntimeException('Le token admin Keycloak est introuvable dans la réponse password grant.');
        }

        return $json['access_token'];
    }

    private function createUser(
        string $adminToken,
        User $user,
        string $username,
        ?string $plainPassword
    ): string {
        $payload = $this->buildUserPayload($user, $username, $plainPassword);

        Log::info('[KEYCLOAK_CREATE_USER_REQUEST]', [
            'target_realm' => $this->targetRealm,
            'local_user_id' => $user->id,
            'username' => $username,
            'email' => $user->email,
            'attributes' => $payload['attributes'] ?? [],
            'with_password' => $plainPassword !== null,
        ]);

        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->timeout(20)
            ->post("{$this->baseUrl}/admin/realms/{$this->targetRealm}/users", $payload);

        Log::info('[KEYCLOAK_CREATE_USER_RESPONSE]', [
            'target_realm' => $this->targetRealm,
            'local_user_id' => $user->id,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 1000),
            'location' => $response->header('Location'),
        ]);

        if ($response->status() !== 201) {
            throw new RuntimeException("Création utilisateur Keycloak échouée: {$response->body()}");
        }

        $location = $response->header('Location');

        if (! $location || ! str_contains($location, '/users/')) {
            throw new RuntimeException('Header Location introuvable après création utilisateur Keycloak.');
        }

        return basename($location);
    }

    private function updateUserProfile(
        string $adminToken,
        string $userId,
        User $user,
        string $username
    ): void {
        $payload = $this->buildUserPayload($user, $username, null);

        unset($payload['credentials']);

        Log::info('[KEYCLOAK_UPDATE_USER_REQUEST]', [
            'target_realm' => $this->targetRealm,
            'local_user_id' => $user->id,
            'keycloak_id' => $userId,
            'username' => $username,
            'attributes' => $payload['attributes'] ?? [],
        ]);

        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->timeout(20)
            ->put("{$this->baseUrl}/admin/realms/{$this->targetRealm}/users/{$userId}", $payload);

        Log::info('[KEYCLOAK_UPDATE_USER_RESPONSE]', [
            'target_realm' => $this->targetRealm,
            'local_user_id' => $user->id,
            'keycloak_id' => $userId,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 1000),
        ]);

        if (! $response->successful() && $response->status() !== 204) {
            throw new RuntimeException("Mise à jour utilisateur Keycloak échouée: {$response->body()}");
        }
    }

    private function buildUserPayload(User $user, string $username, ?string $plainPassword): array
    {
        $payload = [
            'username' => $username,
            'email' => $user->email ?: null,
            'firstName' => $user->prenom ?? $user->firstName ?? $user->name ?? '',
            'lastName' => $user->nom ?? $user->lastName ?? '',
            'enabled' => true,
            'emailVerified' => ! empty($user->email),
            'attributes' => [
                'compte_id' => [(string) $this->resolveCompteId($user)],
                'local_user_id' => [(string) $user->id],
                'local_partner_id' => [(string) ($user->partner_id ?: '')],
                'business_type' => [$this->resolveBusinessType($user)],
                'source_app' => ['tracking'],
                'local_role' => [(string) ($user->role?->slug ?? '')],
            ],
        ];

        if ($plainPassword !== null) {
            $payload['credentials'] = [
                [
                    'type' => 'password',
                    'value' => $plainPassword,
                    'temporary' => false,
                ],
            ];
        }

        return $payload;
    }

    private function resetUserPassword(
        string $adminToken,
        string $userId,
        string $plainPassword,
        bool $temporary = false
    ): void {
        Log::info('[KEYCLOAK_RESET_PASSWORD_REQUEST]', [
            'target_realm' => $this->targetRealm,
            'keycloak_id' => $userId,
            'temporary' => $temporary,
        ]);

        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->timeout(20)
            ->put(
                "{$this->baseUrl}/admin/realms/{$this->targetRealm}/users/{$userId}/reset-password",
                [
                    'type' => 'password',
                    'value' => $plainPassword,
                    'temporary' => $temporary,
                ]
            );

        Log::info('[KEYCLOAK_RESET_PASSWORD_RESPONSE]', [
            'target_realm' => $this->targetRealm,
            'keycloak_id' => $userId,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 500),
        ]);

        if (! $response->successful() && $response->status() !== 204) {
            throw new RuntimeException(
                "Réinitialisation du mot de passe Keycloak échouée: {$response->body()}"
            );
        }
    }

    private function findUserIdByUsernameOrEmail(
        string $adminToken,
        string $username,
        ?string $email
    ): ?string {
        $byUsername = $this->searchUser($adminToken, [
            'username' => $username,
            'exact' => true,
        ]);

        if ($byUsername) {
            return $byUsername['id'];
        }

        if ($email) {
            $byEmail = $this->searchUser($adminToken, [
                'email' => $email,
                'exact' => true,
            ]);

            if ($byEmail) {
                return $byEmail['id'];
            }
        }

        return null;
    }

    private function searchUser(string $adminToken, array $query): ?array
    {
        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->timeout(20)
            ->get("{$this->baseUrl}/admin/realms/{$this->targetRealm}/users", $query);

        if (! $response->successful()) {
            throw new RuntimeException("Recherche utilisateur Keycloak échouée: {$response->body()}");
        }

        $users = $response->json();

        if (! is_array($users) || empty($users)) {
            return null;
        }

        return $users[0];
    }

    private function getUserById(string $adminToken, string $userId): array
    {
        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->timeout(20)
            ->get("{$this->baseUrl}/admin/realms/{$this->targetRealm}/users/{$userId}");

        if (! $response->successful()) {
            throw new RuntimeException("Impossible de récupérer l'utilisateur Keycloak: {$response->body()}");
        }

        return $response->json();
    }

    private function getClientUuid(string $adminToken, string $clientId): string
    {
        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->timeout(20)
            ->get("{$this->baseUrl}/admin/realms/{$this->targetRealm}/clients", [
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
            ->timeout(20)
            ->get("{$this->baseUrl}/admin/realms/{$this->targetRealm}/clients/{$clientUuid}/roles/{$roleName}");

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
        Log::info('[KEYCLOAK_ASSIGN_ROLE_REQUEST]', [
            'target_realm' => $this->targetRealm,
            'keycloak_id' => $userId,
            'client_uuid' => $clientUuid,
            'role' => $role['name'] ?? null,
        ]);

        $response = Http::withToken($adminToken)
            ->acceptJson()
            ->timeout(20)
            ->post(
                "{$this->baseUrl}/admin/realms/{$this->targetRealm}/users/{$userId}/role-mappings/clients/{$clientUuid}",
                [[
                    'id' => $role['id'],
                    'name' => $role['name'],
                ]]
            );

        Log::info('[KEYCLOAK_ASSIGN_ROLE_RESPONSE]', [
            'target_realm' => $this->targetRealm,
            'keycloak_id' => $userId,
            'role' => $role['name'] ?? null,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 500),
        ]);

        if ($response->status() !== 204) {
            throw new RuntimeException("Attribution du rôle Keycloak échouée: {$response->body()}");
        }
    }

    private function resolveUsername(User $user): string
    {
        $username = trim((string) ($user->keycloak_username ?: ''));

        if ($username !== '') {
            return $username;
        }

        $phone = trim((string) ($user->phone ?: ''));

        if ($phone !== '') {
            return $phone;
        }

        $email = trim((string) ($user->email ?: ''));

        if ($email !== '') {
            return $email;
        }

        throw new RuntimeException("Aucun username possible pour l'utilisateur local #{$user->id}.");
    }

    private function resolveCompteId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    private function resolveBusinessType(User $user): string
    {
        return empty($user->partner_id) ? 'PARTNER' : 'DRIVER';
    }

    private function resolveRoleName(User $user): string
    {
        return empty($user->partner_id)
            ? self::ROLE_PARTNER
            : self::ROLE_DRIVER;
    }
}