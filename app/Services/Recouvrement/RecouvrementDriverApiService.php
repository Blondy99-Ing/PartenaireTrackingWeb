<?php

namespace App\Services\Recouvrement;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use App\Services\Keycloak\KeycloakSessionTokenManager;

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

        $firstName = trim((string) $driver->prenom);
        $lastName = trim((string) $driver->nom);
        $fullName = trim($firstName . ' ' . $lastName);

        $payload = [
            'username' => $driver->keycloak_username ?: $driver->phone,
            'email' => $driver->email,

            // Champs conservés pour compatibilité API / Keycloak-like payload.
            'firstName' => $firstName,
            'lastName' => $lastName,

            // Champ explicitement attendu côté recouvrement pour éviter nom_complet = null.
            'nom_complet' => $fullName,

            'enabled' => true,
            'keycloak_id' => $keycloakId,
            'attributes' => [
                'compte_id' => (string) $partner->id,
            ],
            'emailVerified' => true,
            'credentials' => [
                [
                    'type' => 'password',
                    'value' => $plainPassword,
                    'temporary' => false,
                ],
            ],
        ];

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
}