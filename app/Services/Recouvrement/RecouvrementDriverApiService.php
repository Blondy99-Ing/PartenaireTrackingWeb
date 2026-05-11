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
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');
        $timeout = (int) config('services.partner_lease_api.timeout', 20);

        if ($baseUrl === '') {
            throw new RuntimeException('PARTNER_LEASE_API_BASE_URL est vide.');
        }

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $partnerAccessToken = $tokenManager->getValidAccessToken(60);

        $payload = $this->buildDriverPayload(
            partner: $partner,
            driver: $driver,
            keycloakId: $keycloakId,
            plainPassword: $plainPassword
        );

        $url = "{$baseUrl}/accounts/chauffeurs/";

        Log::info('[RECOUVREMENT_DRIVER_CREATE_REQUEST]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'driver_id' => $driver->id,
            'keycloak_id' => $keycloakId,
            'payload_safe' => [
                ...$payload,
                'credentials' => '[HIDDEN]',
            ],
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
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');
        $timeout = (int) config('services.partner_lease_api.timeout', 20);

        if ($baseUrl === '') {
            throw new RuntimeException('PARTNER_LEASE_API_BASE_URL est vide.');
        }

        if (empty($driver->recouvrement_driver_id)) {
            throw new RuntimeException("Impossible de modifier le chauffeur recouvrement : recouvrement_driver_id absent pour le user local #{$driver->id}.");
        }

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $partnerAccessToken = $tokenManager->getValidAccessToken(60);

        $payload = $this->buildDriverPayload(
            partner: $partner,
            driver: $driver,
            keycloakId: (string) $driver->keycloak_id,
            plainPassword: $plainPassword
        );

        $url = "{$baseUrl}/accounts/chauffeurs/{$driver->recouvrement_driver_id}/";

        Log::info('[RECOUVREMENT_DRIVER_UPDATE_REQUEST]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'driver_id' => $driver->id,
            'recouvrement_driver_id' => $driver->recouvrement_driver_id,
            'keycloak_id' => $driver->keycloak_id,
            'payload_safe' => [
                ...$payload,
                'credentials' => isset($payload['credentials']) ? '[HIDDEN]' : null,
            ],
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($partnerAccessToken)
            ->patch($url, $payload);

        if ($response->status() === 401) {
            Log::warning('[RECOUVREMENT_DRIVER_UPDATE_401_REFRESH_RETRY]', [
                'url' => $url,
                'partner_id' => $partner->id,
                'driver_id' => $driver->id,
            ]);

            $partnerAccessToken = $tokenManager->forceRefresh('recouvrement_driver_update_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($partnerAccessToken)
                ->patch($url, $payload);
        }

        Log::info('[RECOUVREMENT_DRIVER_UPDATE_RESPONSE]', [
            'url' => $url,
            'partner_id' => $partner->id,
            'driver_id' => $driver->id,
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

    private function buildDriverPayload(
        User $partner,
        User $driver,
        string $keycloakId,
        ?string $plainPassword = null
    ): array {
        $firstName = trim((string) $driver->prenom);
        $lastName = trim((string) $driver->nom);
        $fullName = trim($firstName . ' ' . $lastName);

        $payload = [
            'username' => $driver->keycloak_username ?: $driver->phone,
            'email' => $driver->email,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'nom_complet' => $fullName,
            'enabled' => true,
            'keycloak_id' => $keycloakId,
            'attributes' => [
                'compte_id' => (string) $partner->id,
            ],
            'emailVerified' => true,
        ];

        if ($plainPassword) {
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
}
