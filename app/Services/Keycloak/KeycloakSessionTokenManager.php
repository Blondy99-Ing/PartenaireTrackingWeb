<?php

namespace App\Services\Keycloak;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class KeycloakSessionTokenManager
{
    public function __construct(
        private readonly KeycloakAuthService $keycloakAuthService
    ) {
    }

    /**
     * Retourne un access_token valide.
     *
     * Si le token est proche de l’expiration, on utilise le refresh_token.
     */
    public function getValidAccessToken(int $minRemainingSeconds = 60): string
    {
        $accessToken = (string) session('keycloak_access_token');
        $refreshToken = (string) session('keycloak_refresh_token');

        if ($accessToken === '') {
            throw new AuthenticationException('Token Keycloak absent de la session.');
        }

        if ($refreshToken === '') {
            throw new AuthenticationException('Refresh token Keycloak absent de la session.');
        }

        if ($this->shouldRefresh($minRemainingSeconds)) {
            $this->refreshOrFail('token_proche_expiration');
        }

        $accessToken = (string) session('keycloak_access_token');

        if ($accessToken === '') {
            throw new AuthenticationException('Token Keycloak absent après refresh.');
        }

        return $accessToken;
    }

    /**
     * Force un refresh du token.
     *
     * Utile quand une API externe répond 401 alors que Laravel pense encore
     * que le token est valide.
     */
    public function forceRefresh(string $reason = 'force_refresh'): string
    {
        $this->refreshOrFail($reason);

        $accessToken = (string) session('keycloak_access_token');

        if ($accessToken === '') {
            throw new AuthenticationException('Token Keycloak absent après refresh forcé.');
        }

        return $accessToken;
    }

    /**
     * Détermine si l’access_token doit être rafraîchi.
     */
    public function shouldRefresh(int $minRemainingSeconds = 60): bool
    {
        $expiresIn = (int) session('keycloak_expires_in', 0);
        $issuedAt = (int) session('keycloak_issued_at', 0);

        if ($expiresIn <= 0 || $issuedAt <= 0) {
            return true;
        }

        $expiresAt = $issuedAt + $expiresIn;
        $remaining = $expiresAt - now()->timestamp;

        return $remaining <= $minRemainingSeconds;
    }

    /**
     * Rafraîchit les tokens et met la session à jour.
     */
    private function refreshOrFail(string $reason): void
    {
        $refreshToken = (string) session('keycloak_refresh_token');

        if ($refreshToken === '') {
            throw new AuthenticationException('Refresh token Keycloak absent.');
        }

        try {
            Log::info('[KEYCLOAK_TOKEN_REFRESH_START]', [
                'reason' => $reason,
                'user_id' => auth()->id(),
                'issued_at' => session('keycloak_issued_at'),
                'expires_in' => session('keycloak_expires_in'),
            ]);

            $tokens = $this->keycloakAuthService->refresh($refreshToken);

            $this->storeTokensInSession($tokens);

            Log::info('[KEYCLOAK_TOKEN_REFRESH_DONE]', [
                'reason' => $reason,
                'user_id' => auth()->id(),
                'expires_in' => $tokens['expires_in'] ?? null,
                'refresh_expires_in' => $tokens['refresh_expires_in'] ?? null,
                'session_state' => $tokens['session_state'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::warning('[KEYCLOAK_TOKEN_REFRESH_FAILED]', [
                'reason' => $reason,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException(
                'Session Keycloak expirée. Veuillez vous reconnecter.',
                previous: $e
            );
        }
    }

    /**
     * Stocke la réponse Keycloak dans la session Laravel.
     */
    public function storeTokensInSession(array $tokens): void
    {
        session([
            'keycloak_access_token' => $tokens['access_token'] ?? null,
            'keycloak_refresh_token' => $tokens['refresh_token'] ?? session('keycloak_refresh_token'),
            'keycloak_id_token' => $tokens['id_token'] ?? null,
            'keycloak_expires_in' => $tokens['expires_in'] ?? null,
            'keycloak_refresh_expires_in' => $tokens['refresh_expires_in'] ?? null,
            'keycloak_session_state' => $tokens['session_state'] ?? null,
            'keycloak_issued_at' => now()->timestamp,
        ]);
    }

    /**
     * Nettoie les tokens Keycloak en session.
     */
    public function clearTokens(): void
    {
        session()->forget([
            'keycloak_access_token',
            'keycloak_refresh_token',
            'keycloak_id_token',
            'keycloak_expires_in',
            'keycloak_refresh_expires_in',
            'keycloak_session_state',
            'keycloak_issued_at',
        ]);
    }
}