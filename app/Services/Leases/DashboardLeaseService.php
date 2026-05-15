<?php

namespace App\Services\Leases;

use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffContractRule;
use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffQueue;
use App\Models\User;
use App\Models\Voiture;
use App\Services\Keycloak\KeycloakSessionTokenManager;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DashboardLeaseService
{
    public function __construct(
        private readonly PartnerLeaseApiService $leaseApiService,
        private readonly KeycloakSessionTokenManager $tokenManager
    ) {
    }

    public function build(User $user, array $filters = []): array
    {
        $partnerId = $this->resolvePartnerId($user);
        $selectedPeriod = $this->resolvePeriod('today');
        $weekPeriod = $this->currentWeekPeriod();
        $search = trim((string) ($filters['search'] ?? ''));

        $warnings = [];
        $dailyStats = null;
        $contracts = [];
        $chauffeurs = [];
        $selectedLeases = [];
        $weekLeases = [];
        $payments = [];

        try {
            $dailyStats = $this->fetchDailyStats();
        } catch (Throwable $e) {
            report($e);
            $warnings[] = 'Les statistiques du jour sont momentanément indisponibles.';
            Log::warning('[LEASE_DASHBOARD_DAILY_STATS_FAILED]', ['error' => $e->getMessage()]);
        }

        try {
            $contracts = $this->leaseApiService->fetchContracts();
        } catch (Throwable $e) {
            report($e);
            $warnings[] = 'Les contrats ne sont pas disponibles pour le moment.';
            Log::error('[LEASE_DASHBOARD_CONTRACTS_FAILED]', ['error' => $e->getMessage()]);
        }

        try {
            $chauffeurs = $this->leaseApiService->fetchChauffeurs();
        } catch (Throwable $e) {
            report($e);
            Log::warning('[LEASE_DASHBOARD_CHAUFFEURS_FAILED]', ['error' => $e->getMessage()]);
        }

        try {
            $selectedLeases = $this->leaseApiService->fetchLeases(null, $contracts, [
                'date_echeance_start' => $selectedPeriod['start_date'],
                'date_echeance_end' => $selectedPeriod['end_date'],
            ]);
        } catch (Throwable $e) {
            report($e);
            $warnings[] = 'Les échéances du jour ne sont pas disponibles.';
            Log::error('[LEASE_DASHBOARD_SELECTED_LEASES_FAILED]', ['error' => $e->getMessage()]);
        }

        try {
            $weekLeases = $this->leaseApiService->fetchLeases(null, $contracts, [
                'date_echeance_start' => $weekPeriod['start_date'],
                'date_echeance_end' => $weekPeriod['end_date'],
            ]);
        } catch (Throwable $e) {
            report($e);
            $warnings[] = 'L’évolution de la semaine est indisponible.';
            Log::error('[LEASE_DASHBOARD_WEEK_LEASES_FAILED]', ['error' => $e->getMessage()]);
        }

        try {
            $payments = $this->filterPaymentsForPeriod(
                $this->leaseApiService->fetchPayments(),
                $selectedPeriod
            );
        } catch (Throwable $e) {
            report($e);
            $warnings[] = 'Les paiements du jour sont momentanément indisponibles.';
            Log::error('[LEASE_DASHBOARD_PAYMENTS_FAILED]', ['error' => $e->getMessage()]);
        }

        $vehicles = $this->getPartnerVehicles($partnerId);
        $cutoffData = $this->getLocalCutoffData($partnerId, $selectedPeriod);
        $vehicleUsage = $this->buildVehicleUsage($partnerId, $vehicles);

        $kpis = $this->buildKpis(
            dailyStats: $dailyStats,
            selectedLeases: $selectedLeases,
            payments: $payments,
            contracts: $contracts,
            vehicles: $vehicles,
            vehicleUsage: $vehicleUsage
        );

        return [
            'filters' => [
                'search' => $search,
            ],
            'period' => $selectedPeriod,
            'week_period' => $weekPeriod,
            'warnings' => $warnings,
            'kpis' => $kpis,
            'charts' => [
                'recovery' => $this->buildCurrentWeekRecoveryChart($weekLeases, $weekPeriod),
                'type_recovery' => $this->buildTypeRecoveryBreakdown($selectedLeases),
            ],
            'tables' => [
                'drivers_risk' => $this->buildDriversRiskTable($selectedLeases),
                'payments_today' => $this->buildPaymentsTable($payments),
                'cutoffs' => $this->buildCutoffTimeline($cutoffData),
            ],
            'contracts_summary' => $this->buildContractsSummary($contracts),
            'cutoff_summary' => $this->buildCutoffSummary($cutoffData),
        ];
    }

    private function fetchDailyStats(): ?array
    {
        $json = $this->apiGet('/statistiques/jour/');

        if (! is_array($json) || empty($json)) {
            return null;
        }

        return [
            'id' => (int) ($json['id'] ?? 0),
            'date' => (string) ($json['date'] ?? now()->toDateString()),
            'montant_attendu' => $this->toFloat($json['montant_attendu'] ?? 0),
            'montant_collecte' => $this->toFloat($json['montant_collecte'] ?? 0),
            'montant_echec' => $this->toFloat($json['montant_echec'] ?? 0),
            'total_attendus' => (int) ($json['total_attendus'] ?? 0),
            'ayant_verse' => (int) ($json['ayant_verse'] ?? 0),
            'n_ayant_pas_verse' => (int) ($json['n_ayant_pas_verse'] ?? 0),
        ];
    }

    private function apiGet(string $endpoint, array $query = []): array
    {
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('PARTNER_LEASE_API_BASE_URL est vide.');
        }

        $url = $baseUrl . $endpoint;
        $token = $this->tokenManager->getValidAccessToken(60);

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout((int) config('services.partner_lease_api.timeout', 20))
            ->get($url, $query);

        if ($response->status() === 401) {
            $token = $this->tokenManager->forceRefresh('lease_dashboard_stats_401');

            $response = Http::acceptJson()
                ->withToken($token)
                ->timeout((int) config('services.partner_lease_api.timeout', 20))
                ->get($url, $query);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Échec API Recouvrement GET {$endpoint} [{$response->status()}] : " . $response->body());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException("Réponse API Recouvrement invalide pour GET {$endpoint}.");
        }

        return $json;
    }

    private function buildKpis(
        ?array $dailyStats,
        array $selectedLeases,
        array $payments,
        array $contracts,
        Collection $vehicles,
        array $vehicleUsage
    ): array {
        $expectedFromLeases = collect($selectedLeases)->sum(fn ($row) => $this->leaseExpected($row));
        $paidFromLeases = collect($selectedLeases)->sum(fn ($row) => $this->leasePaid($row));
        $remainingFromLeases = collect($selectedLeases)->sum(fn ($row) => $this->leaseRemaining($row));

        $expected = $dailyStats['montant_attendu'] ?? $expectedFromLeases;
        $paid = $dailyStats['montant_collecte'] ?? $paidFromLeases;
        $remaining = $dailyStats['montant_echec'] ?? max(0, $remainingFromLeases ?: ($expected - $paid));
        $rate = $expected > 0 ? (int) round(($paid / $expected) * 100) : 0;

        $unpaidLeases = collect($selectedLeases)->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid');

        return [
            'date' => $dailyStats['date'] ?? now()->toDateString(),
            'recovery_rate' => min(100, max(0, $rate)),
            'expected_amount' => $expected,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'total_expected_drivers' => $dailyStats['total_attendus'] ?? collect($selectedLeases)->pluck('chauffeur')->filter()->unique()->count(),
            'drivers_paid' => $dailyStats['ayant_verse'] ?? collect($payments)->pluck('chauffeur')->filter()->unique()->count(),
            'drivers_unpaid' => $dailyStats['n_ayant_pas_verse'] ?? $unpaidLeases->pluck('chauffeur')->filter()->unique()->count(),
            'unpaid_leases_count' => $unpaidLeases->count(),
            'active_contracts' => collect($contracts)->filter(fn ($row) => $this->isActiveStatus($row['statut'] ?? $row['status'] ?? ''))->count(),
            'vehicles_count' => $vehicles->count(),
            'vehicles_used' => $vehicleUsage['used'],
            'vehicles_never_used' => $vehicleUsage['never_used'],
        ];
    }

    private function buildCurrentWeekRecoveryChart(array $weekLeases, array $weekPeriod): array
    {
        $labelsByIsoDay = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        $days = [];
        $start = Carbon::parse($weekPeriod['start_date']);
        $end = Carbon::parse($weekPeriod['end_date']);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $days[$key] = [
                'label' => $labelsByIsoDay[$date->isoWeekday()] ?? $date->format('d/m'),
                'date' => $key,
                'expected' => 0.0,
                'paid' => 0.0,
                'remaining' => 0.0,
                'rate' => 0,
            ];
        }

        foreach ($weekLeases as $lease) {
            $date = $this->safeDate($lease['date_echeance'] ?? $lease['date'] ?? null);
            if (! $date || ! isset($days[$date])) {
                continue;
            }

            $days[$date]['expected'] += $this->leaseExpected($lease);
            $days[$date]['paid'] += $this->leasePaid($lease);
            $days[$date]['remaining'] += $this->leaseRemaining($lease);
        }

        foreach ($days as &$day) {
            $day['rate'] = $day['expected'] > 0 ? (int) round(($day['paid'] / $day['expected']) * 100) : 0;
        }

        return [
            'title' => 'Semaine courante',
            'labels' => collect($days)->pluck('label')->values()->all(),
            'dates' => collect($days)->pluck('date')->values()->all(),
            'expected' => collect($days)->pluck('expected')->values()->all(),
            'paid' => collect($days)->pluck('paid')->values()->all(),
            'remaining' => collect($days)->pluck('remaining')->values()->all(),
            'rate' => collect($days)->pluck('rate')->values()->all(),
        ];
    }

    private function buildTypeRecoveryBreakdown(array $leases): array
    {
        $groups = collect($leases)
            ->groupBy(fn ($row) => $this->typeGroupKey($row))
            ->map(function (Collection $rows, string $key) {
                $first = $rows->first() ?: [];
                $expected = $rows->sum(fn ($row) => $this->leaseExpected($row));
                $paid = $rows->sum(fn ($row) => $this->leasePaid($row));
                $remaining = max(0, $expected - $paid);
                $rate = $expected > 0 ? (int) round(($paid / $expected) * 100) : 0;
                $isMain = $this->isMainContract($first);

                return [
                    'key' => $key,
                    'label' => $this->displayTypeLabel($first),
                    'kind' => $isMain ? 'principal' : 'sous-contrat',
                    'is_main' => $isMain,
                    'expected' => $expected,
                    'paid' => $paid,
                    'remaining' => $remaining,
                    'rate' => min(100, max(0, $rate)),
                    'count' => $rows->count(),
                ];
            })
            ->sortByDesc(fn ($item) => ($item['is_main'] ? 1_000_000_000 : 0) + $item['paid'])
            ->values()
            ->all();

        return [
            'items' => $groups,
            'total_expected' => collect($groups)->sum('expected'),
            'total_paid' => collect($groups)->sum('paid'),
        ];
    }

    private function buildCutoffSummary(array $cutoffData): array
    {
        $queue = $cutoffData['queue'];
        $histories = $cutoffData['histories'];

        return [
            'planned' => $queue->where('status', 'PENDING')->count() + $histories->where('status', 'PENDING')->count(),
            'command_sent' => $queue->where('status', 'COMMAND_SENT')->count() + $histories->where('status', 'COMMAND_SENT')->count(),
            'confirmed' => $histories->where('status', 'CUT_OFF')->count(),
            'gps_failed' => $histories->where('status', 'FAILED')->count(),
        ];
    }

    private function getLocalCutoffData(int $partnerId, array $period): array
    {
        $start = Carbon::parse($period['start_date'])->startOfDay();
        $end = Carbon::parse($period['end_date'])->endOfDay();

        $queue = LeaseCutoffQueue::query()
            ->with('vehicle')
            ->where('partner_id', $partnerId)
            ->whereBetween('lease_date_echeance', [$start->toDateString(), $end->toDateString()])
            ->get();

        $histories = LeaseCutoffHistory::query()
            ->with('vehicle')
            ->where('partner_id', $partnerId)
            ->whereBetween('lease_date_echeance', [$start->toDateString(), $end->toDateString()])
            ->get();

        $rules = LeaseCutoffContractRule::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereNotNull('contract_link_id')
            ->get();

        return [
            'queue' => $queue,
            'active_queue' => $queue->whereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])->values(),
            'histories' => $histories,
            'active_rules_count' => $rules->count(),
        ];
    }

    private function buildDriversRiskTable(array $leases): array
    {
        return collect($leases)
            ->groupBy(fn ($row) => (string) ($row['chauffeur'] ?? 'Chauffeur'))
            ->map(function (Collection $rows, string $driver) {
                $unpaid = $rows->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid');
                $amountDue = $unpaid->sum(fn ($row) => $this->leaseRemaining($row));
                $types = $unpaid->map(fn ($row) => $this->displayTypeLabel($row))->filter()->unique()->implode(', ');
                $vehicle = $rows->pluck('vehicule')->filter()->first() ?: $rows->pluck('immatriculation')->filter()->first() ?: '—';

                return [
                    'driver' => $driver ?: '—',
                    'vehicle' => $vehicle,
                    'unpaid_count' => $unpaid->count(),
                    'amount_due' => $amountDue,
                    'types' => $types ?: '—',
                    'status' => $amountDue > 0 ? ['label' => 'À suivre', 'badge' => 'warning'] : ['label' => 'À jour', 'badge' => 'success'],
                    'action' => $amountDue > 0 ? 'Suivre' : 'OK',
                    'last_info' => $this->latestLeaseInfo($rows),
                    'search' => mb_strtolower($driver . ' ' . $vehicle . ' ' . $types, 'UTF-8'),
                ];
            })
            ->filter(fn ($row) => ($row['unpaid_count'] ?? 0) > 0)
            ->sortByDesc('amount_due')
            ->values()
            ->all();
    }

    private function buildPaymentsTable(array $payments): array
    {
        return collect($payments)
            ->sortByDesc(fn ($payment) => optional($this->safeCarbon($payment['date_paiement'] ?? null))->timestamp ?? 0)
            ->map(function ($payment) {
                return [
                    'time' => $this->formatTime($payment['date_paiement'] ?? null),
                    'driver' => $payment['chauffeur_nom_complet'] ?? $payment['chauffeur'] ?? '—',
                    'lease' => ! empty($payment['lease_id']) ? 'Lease ' . $payment['lease_id'] : '—',
                    'amount' => $this->toFloat($payment['montant'] ?? $payment['amount'] ?? 0),
                    'method' => $payment['methode_label'] ?? $payment['methode'] ?? $payment['method'] ?? '—',
                    'status' => $this->paymentStatusLabel($payment['statut'] ?? $payment['status'] ?? ''),
                    'search' => mb_strtolower(($payment['chauffeur_nom_complet'] ?? $payment['chauffeur'] ?? '') . ' ' . ($payment['lease_id'] ?? '') . ' ' . ($payment['methode_label'] ?? $payment['methode'] ?? ''), 'UTF-8'),
                ];
            })
            ->values()
            ->all();
    }

    private function buildCutoffTimeline(array $cutoffData): array
    {
        return $cutoffData['histories']
            ->sortByDesc(fn ($row) => optional($row->updated_at)->timestamp ?? 0)
            ->take(10)
            ->map(function (LeaseCutoffHistory $history) {
                return [
                    'vehicle' => optional($history->vehicle)->immatriculation ?? 'Véhicule',
                    'type' => $this->cleanLabel((string) ($history->type_contrat_label ?: 'Contrat')),
                    'status' => $history->status,
                    'status_label' => $this->cutoffStatusLabel((string) $history->status),
                    'badge' => $this->cutoffBadge((string) $history->status),
                    'reason' => $this->shortCutoffReason($history),
                    'updated_at' => optional($history->updated_at)->format('d/m H:i'),
                    'search' => mb_strtolower((optional($history->vehicle)->immatriculation ?? '') . ' ' . ($history->type_contrat_label ?? '') . ' ' . ($history->reason ?? ''), 'UTF-8'),
                ];
            })
            ->values()
            ->all();
    }

    private function buildContractsSummary(array $contracts): array
    {
        $rows = collect($contracts);

        return [
            'main_active' => $rows->filter(fn ($row) => $this->isMainContract($row))->filter(fn ($row) => $this->isActiveStatus($row['statut'] ?? $row['status'] ?? ''))->count(),
            'sub_active' => $rows->filter(fn ($row) => ! $this->isMainContract($row))->filter(fn ($row) => $this->isActiveStatus($row['statut'] ?? $row['status'] ?? ''))->count(),
            'sold' => $rows->filter(fn ($row) => $this->isSoldStatus($row['statut'] ?? $row['status'] ?? ''))->count(),
            'suspended' => $rows->filter(fn ($row) => $this->isSuspendedStatus($row['statut'] ?? $row['status'] ?? ''))->count(),
            'remaining_total' => $rows->sum(fn ($row) => $this->toFloat($row['montant_restant'] ?? 0)),
        ];
    }

    private function buildVehicleUsage(int $partnerId, Collection $vehicles): array
    {
        $total = $vehicles->count();
        $usedVehicleIds = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->whereNotNull('vehicle_id')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->pluck('vehicle_id')
            ->filter()
            ->unique()
            ->count();

        $used = min($total, $usedVehicleIds);

        return [
            'total' => $total,
            'used' => $used,
            'never_used' => max(0, $total - $used),
        ];
    }

    private function getPartnerVehicles(int $partnerId): Collection
    {
        return Voiture::query()
            ->join('association_user_voitures', 'association_user_voitures.voiture_id', '=', 'voitures.id')
            ->where('association_user_voitures.user_id', $partnerId)
            ->select('voitures.*')
            ->get();
    }

    private function filterPaymentsForPeriod(array $payments, array $period): array
    {
        $start = Carbon::parse($period['start_date'])->startOfDay();
        $end = Carbon::parse($period['end_date'])->endOfDay();

        return collect($payments)
            ->filter(function ($payment) use ($start, $end) {
                $date = $this->safeCarbon($payment['date_paiement'] ?? null);
                return $date && $date->betweenIncluded($start, $end);
            })
            ->values()
            ->all();
    }

    private function resolvePeriod(string $period): array
    {
        $timezone = config('app.timezone') ?: 'Africa/Douala';
        $today = now($timezone)->startOfDay();

        return [
            'key' => 'today',
            'label' => 'Aujourd’hui',
            'start_date' => $today->toDateString(),
            'end_date' => $today->toDateString(),
        ];
    }

    private function currentWeekPeriod(): array
    {
        $timezone = config('app.timezone') ?: 'Africa/Douala';
        $today = now($timezone);

        return [
            'key' => 'current_week',
            'label' => 'Semaine courante',
            'start_date' => $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'end_date' => $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
        ];
    }

    private function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    private function leaseExpected(array $lease): float
    {
        return $this->toFloat($lease['montant_requis'] ?? $lease['montant_attendu'] ?? data_get($lease, 'raw.montant_attendu') ?? 0);
    }

    private function leasePaid(array $lease): float
    {
        return $this->toFloat($lease['montant_paye'] ?? data_get($lease, 'raw.montant_paye') ?? 0);
    }

    private function leaseRemaining(array $lease): float
    {
        return $this->toFloat($lease['reste_a_payer'] ?? data_get($lease, 'raw.reste_a_payer') ?? max(0, $this->leaseExpected($lease) - $this->leasePaid($lease)));
    }

    private function typeGroupKey(array $row): string
    {
        return ($this->isMainContract($row) ? 'main:' : 'sub:') . mb_strtolower($this->displayTypeLabel($row), 'UTF-8');
    }

    private function displayTypeLabel(array|object $row): string
    {
        $data = is_object($row) ? (array) $row : $row;
        $label = $data['type_contrat_libelle']
            ?? $data['type_contrat_label']
            ?? $data['contrat_type']
            ?? $data['contract_type_label']
            ?? '';

        return $this->cleanLabel((string) $label, $this->isMainContract($data) ? 'Contrat principal' : 'Sous-contrat');
    }

    private function cleanLabel(string $label, string $fallback = 'Contrat'): string
    {
        $label = trim($label);

        if ($label === '' || preg_match('/^(type|contrat)\s*#?\d+$/i', $label)) {
            return $fallback;
        }

        return $label;
    }

    private function isMainContract(array|object $row): bool
    {
        $data = is_object($row) ? (array) $row : $row;
        $kind = strtoupper((string) ($data['contract_kind'] ?? $data['kind'] ?? ''));

        if ($kind === 'MAIN') {
            return true;
        }

        if ($kind === 'SUB') {
            return false;
        }

        return empty($data['parent_contract_id'] ?? $data['source_parent_contract_id'] ?? $data['parent_id'] ?? null);
    }

    private function isActiveStatus(mixed $status): bool
    {
        return in_array(mb_strtoupper((string) $status, 'UTF-8'), ['ACTIF', 'ACTIVE', 'EN_COURS'], true);
    }

    private function isSoldStatus(mixed $status): bool
    {
        return in_array(mb_strtoupper((string) $status, 'UTF-8'), ['SOLDE', 'SOLD', 'TERMINE', 'TERMINÉ'], true);
    }

    private function isSuspendedStatus(mixed $status): bool
    {
        return in_array(mb_strtoupper((string) $status, 'UTF-8'), ['SUSPENDU', 'SUSPENDED'], true);
    }

    private function latestLeaseInfo(Collection $rows): string
    {
        $lastDate = $rows->pluck('date_echeance')->filter()->sort()->last();
        return $lastDate ? 'Échéance ' . Carbon::parse($lastDate)->format('d/m/Y') : 'Échéance non disponible';
    }

    private function paymentStatusLabel(string $status): array
    {
        $value = mb_strtoupper(trim($status), 'UTF-8');

        return match ($value) {
            'VALIDE', 'VALIDÉ', 'PAYE', 'PAYÉ', 'SUCCESS', 'SUCCES', 'SUCCÈS' => ['label' => 'Validé', 'badge' => 'success'],
            'EN_ATTENTE', 'PENDING', 'PROCESSING' => ['label' => 'En attente', 'badge' => 'warning'],
            'ECHEC', 'ÉCHEC', 'FAILED' => ['label' => 'Échec', 'badge' => 'danger'],
            default => ['label' => $status ?: '—', 'badge' => 'muted'],
        };
    }

    private function shortCutoffReason(LeaseCutoffHistory $history): string
    {
        $status = (string) $history->status;

        return match ($status) {
            'PENDING' => 'Coupure planifiée.',
            'WAITING_STOP' => 'Coupure reportée : sécurité GPS.',
            'COMMAND_SENT' => 'Commande envoyée.',
            'CUT_OFF' => 'Coupure confirmée.',
            'CANCELLED_PAID', 'CANCELLED' => 'Annulée après régularisation.',
            'FAILED' => 'Échec de coupure.',
            default => 'Décision enregistrée.',
        };
    }

    private function cutoffStatusLabel(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Planifiée',
            'WAITING_STOP' => 'En attente',
            'COMMAND_SENT' => 'Envoyée',
            'CUT_OFF' => 'Confirmée',
            'CANCELLED_PAID', 'CANCELLED' => 'Annulée',
            'FAILED' => 'Échec',
            default => $status ?: '—',
        };
    }

    private function cutoffBadge(string $status): string
    {
        return match ($status) {
            'PENDING' => 'warning',
            'WAITING_STOP' => 'info',
            'COMMAND_SENT' => 'info',
            'CUT_OFF' => 'success',
            'CANCELLED_PAID', 'CANCELLED' => 'muted',
            'FAILED' => 'danger',
            default => 'muted',
        };
    }

    private function toFloat(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return (float) $value;
    }

    private function safeDate(?string $value): ?string
    {
        return $this->safeCarbon($value)?->toDateString();
    }

    private function safeCarbon(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function formatTime(?string $value): string
    {
        $date = $this->safeCarbon($value);
        return $date ? $date->format('H:i') : '—';
    }
}
