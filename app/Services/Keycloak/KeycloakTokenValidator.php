<?php

namespace App\Services\Keycloak;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakTokenValidator
{
    public function validate(string $jwt): array
    {
        $jwksUrl = config('services.keycloak.jwks_url');

        if (! $jwksUrl) {
            throw new RuntimeException('JWKS URL Keycloak non configurée.');
        }

        $response = Http::get($jwksUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Impossible de récupérer les clés publiques Keycloak.');
        }

        $jwks = $response->json();

        if (! is_array($jwks)) {
            throw new RuntimeException('Réponse JWKS invalide.');
        }

        // Tolérance pour petits décalages d'horloge
        JWT::$leeway = 60;

        $decoded = JWT::decode($jwt, JWK::parseKeySet($jwks));

        return (array) $decoded;
    }
}