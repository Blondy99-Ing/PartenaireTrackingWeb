<?php

namespace App\Services\Leases;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        return $this->getRows('/contrats/');
    }

    public function fetchContractsIndexedById(): array
    {
        $indexed = [];

        foreach ($this->fetchContracts() as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $this->extractContractId($row);

            if ($id > 0) {
                $indexed[$id] = $row;
            }
        }

        Log::info('[LEASE_API] Contrats Recouvrement indexés.', [
            'contracts_count' => count($indexed),
            'sample_ids' => collect($indexed)->keys()->take(20)->values()->all(),
        ]);

        return $indexed;
    }

    public function fetchLeases(?string $status = null, array $extraQuery = []): array
    {
        $query = $extraQuery;

        if ($status !== null && trim($status) !== '') {
            $query['statut'] = trim($status);
        }

        return $this->getRows('/leases/', $query);
    }

    /**
     * Endpoint métier principal pour la coupure :
     * /leases/?statut=NON_PAYE&date_echeance=YYYY-MM-DD
     */
    public function fetchNonPaidLeasesForDate(string|Carbon $dateEcheance): array
    {
        $date = $this->normalizeDate($dateEcheance);

        $rows = $this->fetchLeases('NON_PAYE', [
            'date_echeance' => $date,
        ]);

        $validRows = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! $this->isNonPaidLeaseRow($row)) {
                Log::warning('[LEASE_API] Lease ignoré pendant la lecture NON_PAYE.', [
                    'lease_id' => $this->extractLeaseId($row),
                    'contrat_id' => $this->extractLeaseContractId($row),
                    'statut' => $row['statut'] ?? null,
                    'reste_a_payer' => $row['reste_a_payer'] ?? null,
                    'date_echeance' => $row['date_echeance'] ?? null,
                    'target_date' => $date,
                ]);

                continue;
            }

            $validRows[] = $row;
        }

        Log::info('[LEASE_API] Leases NON_PAYE récupérés pour une date précise.', [
            'date_echeance' => $date,
            'raw_count' => count($rows),
            'valid_count' => count($validRows),
            'lease_ids' => collect($validRows)->pluck('id')->take(50)->values()->all(),
        ]);

        return $validRows;
    }

    public function fetchNonPaidLeasesForDateIndexedByLeaseId(string|Carbon $dateEcheance): array
    {
        $indexed = [];

        foreach ($this->fetchNonPaidLeasesForDate($dateEcheance) as $lease) {
            $leaseId = $this->extractLeaseId($lease);

            if ($leaseId > 0) {
                $indexed[$leaseId] = $lease;
            }
        }

        return $indexed;
    }

    /**
     * Compatibilité avec l’ancien code.
     *
     * Attention :
     * ne pas utiliser cette méthode pour décider la coupure finale.
     * La décision finale doit utiliser lease_id + date_echeance.
     */
    public function fetchLatestNonPaidLeasesIndexedByContractId(?string $dateEcheance = null): array
    {
        $leases = $dateEcheance
            ? $this->fetchNonPaidLeasesForDate($dateEcheance)
            : $this->fetchLeases('NON_PAYE');

        $indexed = [];

        foreach ($leases as $row) {
            if (! is_array($row)) {
                continue;
            }

            $contractId = $this->extractLeaseContractId($row);

            if ($contractId <= 0) {
                continue;
            }

            $currentCreatedAt = strtotime((string) ($row['created_at'] ?? '')) ?: 0;

            $existingCreatedAt = isset($indexed[$contractId])
                ? (strtotime((string) ($indexed[$contractId]['created_at'] ?? '')) ?: 0)
                : 0;

            if (! isset($indexed[$contractId]) || $currentCreatedAt >= $existingCreatedAt) {
                $indexed[$contractId] = $row;
            }
        }

        return $indexed;
    }

    public function isLeaseStillNonPaid(int $leaseId, string|Carbon $dateEcheance): bool
    {
        if ($leaseId <= 0) {
            return false;
        }

        $leasesById = $this->fetchNonPaidLeasesForDateIndexedByLeaseId($dateEcheance);

        return isset($leasesById[$leaseId]);
    }

    public function clearCachedToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }

    public function extractLeaseId(array $lease): int
    {
        return (int) (
            $lease['id']
            ?? $lease['lease_id']
            ?? 0
        );
    }

    public function extractLeaseContractId(array $lease): int
    {
        if (isset($lease['contrat_id'])) {
            return (int) $lease['contrat_id'];
        }

        if (isset($lease['contract_id'])) {
            return (int) $lease['contract_id'];
        }

        if (isset($lease['contrat']) && is_numeric($lease['contrat'])) {
            return (int) $lease['contrat'];
        }

        if (isset($lease['contrat']) && is_array($lease['contrat'])) {
            return (int) ($lease['contrat']['id'] ?? 0);
        }

        if (isset($lease['contract']) && is_array($lease['contract'])) {
            return (int) ($lease['contract']['id'] ?? 0);
        }

        return 0;
    }

    public function extractContractId(array $contract): int
    {
        return (int) (
            $contract['id']
            ?? $contract['contract_id']
            ?? $contract['contrat_id']
            ?? 0
        );
    }

    public function isNonPaidLeaseRow(array $lease): bool
    {
        $status = strtoupper(trim((string) (
            $lease['statut']
            ?? $lease['status']
            ?? ''
        )));

        if ($status !== 'NON_PAYE') {
            return false;
        }

        $reste = $lease['reste_a_payer']
            ?? $lease['remaining_amount']
            ?? null;

        if ($reste === null || $reste === '') {
            return true;
        }

        return (float) $reste > 0;
    }

    private function getRows(string $endpoint, array $query = []): array
    {
        $json = $this->get($endpoint, $query);

        $rows = $this->unwrapRows($json);

        $next = $json['next'] ?? null;

        while (is_string($next) && trim($next) !== '') {
            $nextJson = $this->getAbsoluteUrl($next);

            $rows = array_merge($rows, $this->unwrapRows($nextJson));

            $next = $nextJson['next'] ?? null;
        }

        return $rows;
    }

    private function get(string $endpoint, array $query = [], bool $retryAfterRefresh = true): array
    {
        if ($this->baseUrl === '') {
            throw new RuntimeException('PARTNER_LEASE_API_BASE_URL / LEASE_API_BASE_URL manquant.');
        }

        $token = $this->getTechnicalAccessToken();

        $url = $this->baseUrl . $endpoint;

        Log::info('[LEASE_API] Requête GET Recouvrement.', [
            'url' => $url,
            'query' => $query,
            'auth_mode' => 'lease_auth_technical_token',
            'client_id' => env('LEASE_AUTH_CLIENT_ID'),
            'username' => env('LEASE_AUTH_USERNAME'),
        ]);

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout($this->timeout)
            ->get($url, $query);

        if (in_array($response->status(), [401, 403], true) && $retryAfterRefresh) {
            Log::warning('[LEASE_API] Token technique refusé, refresh puis retry.', [
                'url' => $url,
                'query' => $query,
                'status' => $response->status(),
                'body_preview' => mb_substr($response->body(), 0, 1000),
            ]);

            $this->clearCachedToken();

            return $this->get($endpoint, $query, false);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Échec API lease GET {$endpoint} [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException("Réponse API lease invalide pour GET {$endpoint}.");
        }

        return $json;
    }

    private function getAbsoluteUrl(string $url, bool $retryAfterRefresh = true): array
    {
        $token = $this->getTechnicalAccessToken();

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout($this->timeout)
            ->get($url);

        if (in_array($response->status(), [401, 403], true) && $retryAfterRefresh) {
            $this->clearCachedToken();

            return $this->getAbsoluteUrl($url, false);
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Échec API lease GET {$url} [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException("Réponse API lease invalide pour GET {$url}.");
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

        Log::info('[LEASE_API] Demande token technique Recouvrement.', [
            'token_url' => $tokenUrl,
            'client_id' => $clientId,
            'username' => $username,
            'has_client_secret' => $clientSecret !== '',
            'scope' => $scope,
        ]);

        $response = Http::asForm()
            ->timeout($this->timeout)
            ->post($tokenUrl, $payload);

        if (! $response->successful()) {
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

    private function unwrapRows(array $json): array
    {
        if (array_is_list($json)) {
            return $json;
        }

        foreach (['results', 'data', 'items'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                return $json[$key];
            }
        }

        return [];
    }

    private function normalizeDate(string|Carbon $date): string
    {
        if ($date instanceof Carbon) {
            return $date->toDateString();
        }

        return Carbon::parse($date)->toDateString();
    }
}