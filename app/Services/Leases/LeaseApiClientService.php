<?php

namespace App\Services\Leases;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LeaseApiClientService
{
    private string $baseUrl;
    private int $timeout;
    private string $tokenCacheKey = 'lease_api:recouvrement_access_token';

    public function __construct()
    {
        $this->baseUrl = rtrim((string) (
            config('services.partner_lease_api.base_url')
            ?: env('PARTNER_LEASE_API_BASE_URL')
            ?: env('LEASE_API_BASE_URL')
        ), '/');

        $this->timeout = (int) (
            config('services.partner_lease_api.timeout')
            ?: env('PARTNER_LEASE_API_TIMEOUT')
            ?: 20
        );
    }

    public function fetchContracts(): array
    {
        return $this->get('/contrats/');
    }

    public function fetchLeases(?string $status = null): array
    {
        $query = [];

        if ($status) {
            $query['statut'] = $status;
        }

        return $this->get('/leases/', $query);
    }

    public function fetchContractsIndexedById(): array
    {
        $contracts = $this->fetchContracts();

        $indexed = [];
        foreach ($contracts as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $indexed[$id] = $row;
            }
        }

        return $indexed;
    }

    public function fetchLatestNonPaidLeasesIndexedByContractId(): array
    {
        $leases = $this->fetchLeases('NON_PAYE');

        $indexed = [];

        foreach ($leases as $row) {
            if (!is_array($row)) {
                continue;
            }

            $contractId = (int) ($row['contrat_id'] ?? 0);
            if ($contractId <= 0) {
                continue;
            }

            $currentCreatedAt = strtotime((string) ($row['created_at'] ?? '')) ?: 0;
            $existingCreatedAt = isset($indexed[$contractId])
                ? (strtotime((string) ($indexed[$contractId]['created_at'] ?? '')) ?: 0)
                : 0;

            if (!isset($indexed[$contractId]) || $currentCreatedAt >= $existingCreatedAt) {
                $indexed[$contractId] = $row;
            }
        }

        return $indexed;
    }

    public function clearCachedToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }

    private function get(string $endpoint, array $query = [], bool $retryAfterRefresh = true): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('PARTNER_LEASE_API_BASE_URL / LEASE_API_BASE_URL manquant.');
        }

        $token = $this->getTechnicalAccessToken();

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout($this->timeout)
            ->get($this->baseUrl . $endpoint, $query);

        if (in_array($response->status(), [401, 403], true) && $retryAfterRefresh) {
            $this->clearCachedToken();
            return $this->get($endpoint, $query, false);
        }

        if (!$response->successful()) {
            throw new RuntimeException(
                "Échec API lease [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw new RuntimeException('Réponse API lease invalide.');
        }

        return $json;
    }

    private function getTechnicalAccessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey);
        if (is_string($cached) && trim($cached) !== '') {
            return $cached;
        }

        $tokenUrl = trim((string) env('LEASE_AUTH_TOKEN_URL', ''));
        $clientId = trim((string) env('LEASE_AUTH_CLIENT_ID', ''));
        $clientSecret = trim((string) env('LEASE_AUTH_CLIENT_SECRET', ''));
        $username = trim((string) env('LEASE_AUTH_USERNAME', ''));
        $password = (string) env('LEASE_AUTH_PASSWORD', '');
        $scope = trim((string) env('LEASE_AUTH_SCOPE', 'openid email profile'));

        if ($tokenUrl === '') {
            throw new RuntimeException('LEASE_AUTH_TOKEN_URL manquant.');
        }

        if ($clientId === '') {
            throw new RuntimeException('LEASE_AUTH_CLIENT_ID manquant.');
        }

        if ($username === '') {
            throw new RuntimeException('LEASE_AUTH_USERNAME manquant.');
        }

        if ($password === '') {
            throw new RuntimeException('LEASE_AUTH_PASSWORD manquant.');
        }

        $payload = [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'username' => $username,
            'password' => $password,
            'scope' => $scope,
        ];

        if ($clientSecret !== '') {
            $payload['client_secret'] = $clientSecret;
        }

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post($tokenUrl, $payload);

        if (!$response->successful()) {
            throw new RuntimeException(
                "Impossible d’obtenir le token recouvrement_app [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        $accessToken = (string) ($json['access_token'] ?? '');
        $expiresIn = (int) ($json['expires_in'] ?? 300);

        if ($accessToken === '') {
            throw new RuntimeException('Aucun access_token retourné pour les jobs lease.');
        }

        $ttl = max(60, $expiresIn - 30);
        Cache::put($this->tokenCacheKey, $accessToken, now()->addSeconds($ttl));

        return $accessToken;
    }
}