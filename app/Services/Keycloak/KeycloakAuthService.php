<?php

namespace App\Services\Keycloak;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class KeycloakAuthService
{
    public function login(string $username, string $password): array
    {
        $payload = [
            'client_id' => config('services.keycloak.client_id'),
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
            'scope' => 'openid profile email',
        ];

        if (filled(config('services.keycloak.client_secret'))) {
            $payload['client_secret'] = config('services.keycloak.client_secret');
        }

        Log::info('[KEYCLOAK_LOGIN_REQUEST]', [
            'username' => $username,
            'client_id' => config('services.keycloak.client_id'),
            'token_url' => config('services.keycloak.token_url'),
        ]);

        $response = Http::asForm()
            ->timeout(20)
            ->post(config('services.keycloak.token_url'), $payload);

        Log::info('[KEYCLOAK_LOGIN_RESPONSE]', [
            'username' => $username,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 500),
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'login' => ['Identifiant ou mot de passe incorrect.'],
            ]);
        }

        $json = $response->json();

        if (! is_array($json) || empty($json['access_token'])) {
            throw ValidationException::withMessages([
                'login' => ['Réponse Keycloak invalide.'],
            ]);
        }

        return $json;
    }

    public function refresh(string $refreshToken): array
    {
        $payload = [
            'client_id' => config('services.keycloak.client_id'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        if (filled(config('services.keycloak.client_secret'))) {
            $payload['client_secret'] = config('services.keycloak.client_secret');
        }

        Log::info('[KEYCLOAK_REFRESH_REQUEST]', [
            'client_id' => config('services.keycloak.client_id'),
            'token_url' => config('services.keycloak.token_url'),
            'has_refresh_token' => $refreshToken !== '',
        ]);

        $response = Http::asForm()
            ->timeout(20)
            ->post(config('services.keycloak.token_url'), $payload);

        Log::info('[KEYCLOAK_REFRESH_RESPONSE]', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 500),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Refresh token Keycloak refusé. Réponse: ' . $response->body()
            );
        }

        $json = $response->json();

        if (! is_array($json) || empty($json['access_token'])) {
            throw new RuntimeException('Réponse refresh Keycloak invalide.');
        }

        return $json;
    }

    public function logout(?string $refreshToken): void
    {
        if (! $refreshToken) {
            return;
        }

        $payload = [
            'client_id' => config('services.keycloak.client_id'),
            'refresh_token' => $refreshToken,
        ];

        if (filled(config('services.keycloak.client_secret'))) {
            $payload['client_secret'] = config('services.keycloak.client_secret');
        }

        Log::info('[KEYCLOAK_LOGOUT_REQUEST]', [
            'logout_url' => config('services.keycloak.logout_url'),
            'client_id' => config('services.keycloak.client_id'),
        ]);

        Http::asForm()
            ->timeout(15)
            ->post(config('services.keycloak.logout_url'), $payload);
    }
}