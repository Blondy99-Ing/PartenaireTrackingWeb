<?php

namespace App\Services\Recouvrement;

use App\Models\User;
use App\Services\Keycloak\KeycloakSessionTokenManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RecouvrementDriverApiService
{
    public function createDriver(
        User $partner,
        User $driver,
        string $plainPassword,
        string $keycloakId
    ): array {
        $baseUrl = $this->baseUrl();
        $timeout = $this->timeout();

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $partnerAccessToken = $tokenManager->getValidAccessToken(60);

        $payload = $this->buildDriverPayload(
            partner: $partner,
            driver: $driver,
            keycloakId: $keycloakId
        );

        $url = "{$baseUrl}/accounts/chauffeurs/";

        Log::info('[RECOUVREMENT_DRIVER_CREATE_REQUEST]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'tenant_partner_id' => $partner->tenantPartnerId(),
            'driver_id' => $driver->id,
            'driver_tenant_partner_id' => $driver->tenantPartnerId(),
            'keycloak_id' => $keycloakId,
            'payload_safe' => $this->safePayload($payload),
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($partnerAccessToken)
            ->post($url, $payload);

        if ($response->status() === 401) {
            Log::warning('[RECOUVREMENT_DRIVER_CREATE_401_REFRESH_RETRY]', [
                'url' => $url,
                'partner_id' => $partner->id,
                'tenant_partner_id' => $partner->tenantPartnerId(),
                'driver_id' => $driver->id,
            ]);

            $partnerAccessToken = $tokenManager->forceRefresh('recouvrement_driver_create_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($partnerAccessToken)
                ->post($url, $payload);
        }

        Log::info('[RECOUVREMENT_DRIVER_CREATE_RESPONSE]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'tenant_partner_id' => $partner->tenantPartnerId(),
            'driver_id' => $driver->id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Création chauffeur recouvrement échouée [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    public function updateDriver(
        User $partner,
        User $driver,
        ?string $plainPassword = null
    ): array {
        if (empty($driver->recouvrement_driver_id)) {
            throw new RuntimeException(
                "Impossible de modifier le chauffeur recouvrement : recouvrement_driver_id absent pour le user local #{$driver->id}."
            );
        }

        if (empty($driver->keycloak_id)) {
            throw new RuntimeException(
                "Impossible de modifier le chauffeur recouvrement : keycloak_id absent pour le user local #{$driver->id}."
            );
        }

        $baseUrl = $this->baseUrl();
        $timeout = $this->timeout();

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $partnerAccessToken = $tokenManager->getValidAccessToken(60);

        $payload = $this->buildDriverPayload(
            partner: $partner,
            driver: $driver,
            keycloakId: (string) $driver->keycloak_id
        );

        $url = "{$baseUrl}/accounts/chauffeurs/{$driver->recouvrement_driver_id}/";

        Log::info('[RECOUVREMENT_DRIVER_UPDATE_REQUEST]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'tenant_partner_id' => $partner->tenantPartnerId(),
            'driver_id' => $driver->id,
            'driver_tenant_partner_id' => $driver->tenantPartnerId(),
            'recouvrement_driver_id' => $driver->recouvrement_driver_id,
            'keycloak_id' => $driver->keycloak_id,
            'payload_safe' => $this->safePayload($payload),
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($partnerAccessToken)
            ->put($url, $payload);

        if ($response->status() === 401) {
            Log::warning('[RECOUVREMENT_DRIVER_UPDATE_401_REFRESH_RETRY]', [
                'url' => $url,
                'partner_id' => $partner->id,
                'tenant_partner_id' => $partner->tenantPartnerId(),
                'driver_id' => $driver->id,
                'recouvrement_driver_id' => $driver->recouvrement_driver_id,
            ]);

            $partnerAccessToken = $tokenManager->forceRefresh('recouvrement_driver_update_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($partnerAccessToken)
                ->put($url, $payload);
        }

        Log::info('[RECOUVREMENT_DRIVER_UPDATE_RESPONSE]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'tenant_partner_id' => $partner->tenantPartnerId(),
            'driver_id' => $driver->id,
            'recouvrement_driver_id' => $driver->recouvrement_driver_id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Modification chauffeur recouvrement échouée [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    public function deleteDriver(User $partner, User $driver): void
    {
        if (empty($driver->recouvrement_driver_id)) {
            throw new RuntimeException(
                "Impossible de supprimer le chauffeur recouvrement : recouvrement_driver_id absent pour le user local #{$driver->id}."
            );
        }

        $this->deleteDriverByRecouvrementId(
            partner: $partner,
            recouvrementDriverId: (string) $driver->recouvrement_driver_id,
            context: [
                'driver_id' => $driver->id,
                'driver_tenant_partner_id' => $driver->tenantPartnerId(),
                'keycloak_id' => $driver->keycloak_id,
            ]
        );
    }

    public function deleteDriverByRecouvrementId(
        User $partner,
        string $recouvrementDriverId,
        array $context = []
    ): void {
        $baseUrl = $this->baseUrl();
        $timeout = $this->timeout();

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $partnerAccessToken = $tokenManager->getValidAccessToken(60);

        $url = "{$baseUrl}/accounts/chauffeurs/{$recouvrementDriverId}/";

        Log::warning('[RECOUVREMENT_DRIVER_DELETE_REQUEST]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'tenant_partner_id' => $partner->tenantPartnerId(),
            'recouvrement_driver_id' => $recouvrementDriverId,
            ...$context,
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($partnerAccessToken)
            ->delete($url);

        if ($response->status() === 401) {
            Log::warning('[RECOUVREMENT_DRIVER_DELETE_401_REFRESH_RETRY]', [
                'url' => $url,
                'partner_id' => $partner->id,
                'tenant_partner_id' => $partner->tenantPartnerId(),
                'recouvrement_driver_id' => $recouvrementDriverId,
                ...$context,
            ]);

            $partnerAccessToken = $tokenManager->forceRefresh('recouvrement_driver_delete_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withToken($partnerAccessToken)
                ->delete($url);
        }

        Log::warning('[RECOUVREMENT_DRIVER_DELETE_RESPONSE]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'tenant_partner_id' => $partner->tenantPartnerId(),
            'recouvrement_driver_id' => $recouvrementDriverId,
            'status' => $response->status(),
            'body' => $response->body(),
            ...$context,
        ]);

        /**
         * 404 est accepté pour rendre la suppression idempotente.
         * Si Recouvrement dit "déjà supprimé", on continue.
         */
        if (! $response->successful() && ! in_array($response->status(), [204, 404], true)) {
            throw new RuntimeException(
                "Suppression chauffeur recouvrement échouée [{$response->status()}] : " . $response->body()
            );
        }
    }

    private function buildDriverPayload(
        User $partner,
        User $driver,
        string $keycloakId
    ): array {
        if (empty($keycloakId)) {
            throw new RuntimeException(
                "Impossible de créer le chauffeur recouvrement : keycloak_id vide pour le user local #{$driver->id}."
            );
        }

        if (empty($driver->email)) {
            throw new RuntimeException(
                "Impossible de créer le chauffeur recouvrement : email vide pour le user local #{$driver->id}."
            );
        }

        /**
         * Sécurité métier locale :
         * Le chauffeur doit être rattaché au même tenant que le partenaire.
         *
         * - Partenaire principal : tenantPartnerId() retourne son id.
         * - Chauffeur : tenantPartnerId() retourne partner_id.
         */
        if ($driver->tenantPartnerId() !== $partner->tenantPartnerId()) {
            throw new RuntimeException(
                "Impossible de créer le chauffeur recouvrement : le chauffeur #{$driver->id} n'appartient pas au même tenant que le partenaire #{$partner->id}."
            );
        }

        /**
         * Contrat attendu par l'API Recouvrement :
         *
         * {
         *   "keycloak_id": "...",
         *   "nom_complet": "...",
         *   "email": "..."
         * }
         *
         * On utilise displayName() depuis ton modèle User.
         */
        return [
            'keycloak_id' => $keycloakId,
            'nom_complet' => $driver->displayName(),
            'email' => $driver->email,
        ];
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('PARTNER_LEASE_API_BASE_URL est vide.');
        }

        return $baseUrl;
    }

    private function timeout(): int
    {
        return (int) config('services.partner_lease_api.timeout', 20);
    }

    private function safePayload(array $payload): array
    {
        if (array_key_exists('credentials', $payload)) {
            $payload['credentials'] = '[HIDDEN]';
        }

        return $payload;
    }
}