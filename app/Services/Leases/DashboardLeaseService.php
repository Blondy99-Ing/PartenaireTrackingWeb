<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffQueue;
use App\Models\LeaseCutoffRule;
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

        $selectedPeriod = $this->resolvePeriod((string) ($filters['period'] ?? 'today'));
        $weekPeriod = $this->currentWeekPeriod();

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
            $warnings[] = "Les statistiques journalières Recouvrement sont indisponibles. Les KPI seront recalculés depuis les leases.";
            Log::warning('[LEASE_DASHBOARD_DAILY_STATS_FAILED]', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $contracts = $this->leaseApiService->fetchContracts();
        } catch (Throwable $e) {
            report($e);
            $warnings[] = "Les contrats Recouvrement sont indisponibles.";
            Log::error('[LEASE_DASHBOARD_CONTRACTS_FAILED]', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $chauffeurs = $this->leaseApiService->fetchChauffeurs();
        } catch (Throwable $e) {
            report($e);
            $warnings[] = "La liste des chauffeurs est indisponible.";
            Log::error('[LEASE_DASHBOARD_CHAUFFEURS_FAILED]', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $selectedLeases = $this->leaseApiService->fetchLeases(null, $contracts, [
                'date_echeance_start' => $selectedPeriod['start_date'],
                'date_echeance_end' => $selectedPeriod['end_date'],
            ]);
        } catch (Throwable $e) {
            report($e);
            $warnings[] = "Les leases de la période sont indisponibles.";
            Log::error('[LEASE_DASHBOARD_SELECTED_LEASES_FAILED]', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $weekLeases = $this->leaseApiService->fetchLeases(null, $contracts, [
                'date_echeance_start' => $weekPeriod['start_date'],
                'date_echeance_end' => $weekPeriod['end_date'],
            ]);
        } catch (Throwable $e) {
            report($e);
            $warnings[] = "Les leases de la semaine courante sont indisponibles.";
            Log::error('[LEASE_DASHBOARD_WEEK_LEASES_FAILED]', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $payments = $this->filterPaymentsForPeriod(
                $this->leaseApiService->fetchPayments(),
                $selectedPeriod
            );
        } catch (Throwable $e) {
            report($e);
            $warnings[] = "Les paiements Recouvrement sont indisponibles.";
            Log::error('[LEASE_DASHBOARD_PAYMENTS_FAILED]', [
                'error' => $e->getMessage(),
            ]);
        }

        $vehicles = $this->getPartnerVehicles($partnerId);
        $cutoffData = $this->getLocalCutoffData($partnerId, $selectedPeriod);

        $kpis = $this->buildKpis(
            dailyStats: $dailyStats,
            selectedLeases: $selectedLeases,
            payments: $payments,
            contracts: $contracts,
            chauffeurs: $chauffeurs,
            vehicles: $vehicles,
            cutoffData: $cutoffData
        );

        return [
            'filters' => [
                'period' => $filters['period'] ?? 'today',
                'type' => $filters['type'] ?? 'all',
                'status' => $filters['status'] ?? 'all',
                'search' => $filters['search'] ?? '',
            ],

            'period' => $selectedPeriod,
            'week_period' => $weekPeriod,
            'warnings' => $warnings,

            'kpis' => $kpis,

            'priorities' => $this->buildPriorities($selectedLeases, $cutoffData, $kpis),

            'charts' => [
                'recovery' => $this->buildCurrentWeekRecoveryChart($weekLeases, $weekPeriod),
                'payment_breakdown' => $this->buildPaymentBreakdown($kpis),
                'type_breakdown' => $this->buildTypeBreakdown($selectedLeases),
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
            'updated_at' => (string) ($json['updated_at'] ?? ''),
            'raw' => $json,
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
            throw new RuntimeException(
                "Échec API Recouvrement GET {$endpoint} [{$response->status()}] : " . $response->body()
            );
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
        array $chauffeurs,
        Collection $vehicles,
        array $cutoffData
    ): array {
        $expectedFromLeases = collect($selectedLeases)->sum(fn ($row) => $this->leaseExpected($row));
        $paidFromLeases = collect($selectedLeases)->sum(fn ($row) => $this->leasePaid($row));
        $remainingFromLeases = collect($selectedLeases)->sum(fn ($row) => $this->leaseRemaining($row));

        $expected = $dailyStats['montant_attendu'] ?? $expectedFromLeases;
        $paid = $dailyStats['montant_collecte'] ?? $paidFromLeases;
        $remaining = $dailyStats['montant_echec'] ?? max(0, $remainingFromLeases ?: ($expected - $paid));

        $rate = $expected > 0
            ? (int) round(($paid / $expected) * 100)
            : 0;

        $unpaidLeases = collect($selectedLeases)
            ->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid')
            ->values();

        $activeChauffeurs = collect($chauffeurs)
            ->filter(fn ($row) => (bool) ($row['is_active'] ?? false))
            ->count();

        $inactiveChauffeurs = max(0, count($chauffeurs) - $activeChauffeurs);

        $activeContractDrivers = collect($contracts)
            ->filter(fn ($row) => ($row['statut'] ?? null) === 'actif' || strtoupper((string) ($row['statut'] ?? '')) === 'ACTIF')
            ->pluck('chauffeur_id')
            ->filter()
            ->unique()
            ->count();

        return [
            'date' => $dailyStats['date'] ?? now()->toDateString(),

            'recovery_rate' => $rate,
            'expected_amount' => $expected,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,

            'total_expected_drivers' => $dailyStats['total_attendus'] ?? $unpaidLeases->pluck('chauffeur')->filter()->unique()->count(),
            'drivers_paid' => $dailyStats['ayant_verse'] ?? 0,
            'drivers_unpaid' => $dailyStats['n_ayant_pas_verse'] ?? $unpaidLeases->pluck('chauffeur')->filter()->unique()->count(),

            'drivers_to_call' => $unpaidLeases->pluck('chauffeur')->filter()->unique()->count(),
            'unpaid_leases_count' => $unpaidLeases->count(),

            'active_chauffeurs' => $activeChauffeurs,
            'inactive_chauffeurs' => $inactiveChauffeurs,
            'active_contract_drivers' => $activeContractDrivers,

            'active_contracts' => collect($contracts)
                ->filter(fn ($row) => ($row['statut'] ?? null) === 'actif' || strtoupper((string) ($row['statut'] ?? '')) === 'ACTIF')
                ->count(),

            'vehicles_count' => $vehicles->count(),

            'cutoffs_to_follow' => $cutoffData['active_queue']->count(),
            'cutoffs_confirmed' => $cutoffData['histories']->where('status', 'CUT_OFF')->count(),
        ];
    }

    private function buildCurrentWeekRecoveryChart(array $weekLeases, array $weekPeriod): array
    {
        $labelsByIsoDay = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim',
        ];

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

        return [
            'title' => 'Semaine courante',
            'labels' => collect($days)->pluck('label')->values()->all(),
            'dates' => collect($days)->pluck('date')->values()->all(),
            'expected' => collect($days)->pluck('expected')->values()->all(),
            'paid' => collect($days)->pluck('paid')->values()->all(),
            'remaining' => collect($days)->pluck('remaining')->values()->all(),
        ];
    }

    private function buildPaymentBreakdown(array $kpis): array
    {
        $expected = (float) ($kpis['expected_amount'] ?? 0);
        $paid = (float) ($kpis['paid_amount'] ?? 0);
        $remaining = max(0, (float) ($kpis['remaining_amount'] ?? ($expected - $paid)));

        $rate = $expected > 0
            ? (int) round(($paid / $expected) * 100)
            : 0;

        $paidPercent = $expected > 0
            ? (int) round(($paid / $expected) * 100)
            : 0;

        $remainingPercent = max(0, 100 - $paidPercent);

        return [
            'rate' => $rate,
            'total' => $expected,
            'items' => [
                [
                    'label' => 'Collecté',
                    'amount' => $paid,
                    'percent' => $paidPercent,
                    'badge' => 'success',
                ],
                [
                    'label' => 'Reste à payer',
                    'amount' => $remaining,
                    'percent' => $remainingPercent,
                    'badge' => 'danger',
                ],
            ],
        ];
    }

    private function buildTypeBreakdown(array $leases): array
    {
        $unpaid = collect($leases)->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid');
        $total = max(1, $unpaid->count());

        $items = $unpaid
            ->groupBy(fn ($row) => $row['type_contrat_label'] ?? $row['contrat_type'] ?? 'Autre')
            ->map(function ($rows, $label) use ($total) {
                $count = count($rows);

                return [
                    'label' => (string) $label,
                    'count' => $count,
                    'amount' => collect($rows)->sum(fn ($row) => $this->leaseRemaining($row)),
                    'percent' => (int) round(($count / $total) * 100),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'total' => $unpaid->count(),
            'items' => $items,
        ];
    }

    private function buildCutoffSummary(array $cutoffData): array
    {
        $queue = $cutoffData['queue'];
        $histories = $cutoffData['histories'];

        return [
            'planned' => $queue->where('status', 'PENDING')->count() + $histories->where('status', 'PENDING')->count(),
            'waiting_stop' => $queue->where('status', 'WAITING_STOP')->count() + $histories->where('status', 'WAITING_STOP')->count(),
            'command_sent' => $queue->where('status', 'COMMAND_SENT')->count() + $histories->where('status', 'COMMAND_SENT')->count(),
            'confirmed' => $histories->where('status', 'CUT_OFF')->count(),
            'cancelled_paid' => $histories->whereIn('status', ['CANCELLED_PAID', 'CANCELLED'])->count(),
            'gps_failed' => $histories->where('status', 'FAILED')->count(),
            'active_rules' => $cutoffData['active_rules_count'],
            'vehicles_with_rules' => $cutoffData['vehicles_with_rules'],
            'vehicles_without_rules' => $cutoffData['vehicles_without_rules'],
        ];
    }

    private function getLocalCutoffData(int $partnerId, array $period): array
    {
        $start = Carbon::parse($period['start_date'])->startOfDay();
        $end = Carbon::parse($period['end_date'])->endOfDay();

        $queue = LeaseCutoffQueue::query()
            ->with('vehicle')
            ->where('partner_id', $partnerId)
            ->where(function ($query) use ($start, $end) {
                $query->whereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])
                    ->orWhereBetween('scheduled_for', [$start, $end])
                    ->orWhereBetween('updated_at', [$start, $end]);
            })
            ->get();

        $activeQueue = $queue
            ->whereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])
            ->values();

        $histories = LeaseCutoffHistory::query()
            ->with('vehicle')
            ->where('partner_id', $partnerId)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('scheduled_for', [$start, $end])
                    ->orWhereBetween('updated_at', [$start, $end])
                    ->orWhereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT']);
            })
            ->get();

        $activeRules = LeaseCutoffRule::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->get();

        $partnerVehicleCount = $this->getPartnerVehicles($partnerId)->count();

        return [
            'queue' => $queue,
            'active_queue' => $activeQueue,
            'histories' => $histories,
            'active_rules_count' => $activeRules->count(),
            'vehicles_with_rules' => $activeRules->pluck('vehicle_id')->filter()->unique()->count(),
            'vehicles_without_rules' => max(0, $partnerVehicleCount - $activeRules->pluck('vehicle_id')->filter()->unique()->count()),
        ];
    }

    private function buildPriorities(array $leases, array $cutoffData, array $kpis): array
    {
        $unpaid = collect($leases)->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid');

        return [
            [
                'type' => 'danger',
                'icon' => 'fas fa-user-clock',
                'title' => ($kpis['drivers_to_call'] ?? 0) . ' chauffeurs nécessitent une relance',
                'description' => 'Le reste à payer est de ' . $this->money((float) ($kpis['remaining_amount'] ?? 0)) . '. Les chauffeurs avec plusieurs impayés sont prioritaires.',
                'badges' => [
                    ['type' => 'danger', 'label' => $unpaid->count() . ' leases NON_PAYE'],
                    ['type' => 'warning', 'label' => ($kpis['drivers_unpaid'] ?? 0) . ' n’ayant pas versé'],
                ],
            ],
            [
                'type' => 'warning',
                'icon' => 'fas fa-motorcycle',
                'title' => $cutoffData['active_queue']->count() . ' coupures à suivre',
                'description' => 'Une coupure ne doit être exécutée que si le lease reste impayé, le véhicule est arrêté et le GPS est fiable.',
                'badges' => [
                    ['type' => 'warning', 'label' => $cutoffData['active_queue']->where('status', 'WAITING_STOP')->count() . ' en attente arrêt'],
                    ['type' => 'info', 'label' => $cutoffData['active_queue']->where('status', 'COMMAND_SENT')->count() . ' commandes envoyées'],
                ],
            ],
            [
                'type' => 'info',
                'icon' => 'fas fa-users',
                'title' => 'Chauffeurs actifs / inactifs',
                'description' => 'Le gestionnaire voit immédiatement la base chauffeur exploitable pour les versements.',
                'badges' => [
                    ['type' => 'success', 'label' => ($kpis['active_chauffeurs'] ?? 0) . ' actifs'],
                    ['type' => 'muted', 'label' => ($kpis['inactive_chauffeurs'] ?? 0) . ' inactifs'],
                ],
            ],
        ];
    }

    private function buildDriversRiskTable(array $leases): array
    {
        return collect($leases)
            ->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid')
            ->groupBy(function ($row) {
                return trim((string) ($row['chauffeur'] ?? 'Chauffeur inconnu'))
                    . '|'
                    . trim((string) ($row['vehicule'] ?? '—'));
            })
            ->map(function ($rows, $key) {
                [$driver, $vehicle] = array_pad(explode('|', $key), 2, '—');

                $types = collect($rows)
                    ->pluck('type_contrat_label')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $amount = collect($rows)->sum(fn ($row) => $this->leaseRemaining($row));

                $cutoffLabel = collect($rows)
                    ->pluck('coupure_label')
                    ->filter()
                    ->first() ?: 'À relancer';

                return [
                    'driver' => $driver,
                    'vehicle' => $vehicle,
                    'unpaid_count' => count($rows),
                    'amount_due' => $amount,
                    'types' => implode(' + ', $types) ?: '—',
                    'status' => $this->driverOperationalStatus($cutoffLabel),
                    'action' => $this->driverAction($cutoffLabel),
                    'last_info' => $this->latestLeaseInfo($rows),
                    'search' => mb_strtolower($driver . ' ' . $vehicle . ' ' . implode(' ', $types), 'UTF-8'),
                ];
            })
            ->sortByDesc(fn ($row) => ($row['unpaid_count'] * 1000000) + $row['amount_due'])
            ->values()
            ->take(10)
            ->all();
    }

    private function buildPaymentsTable(array $payments): array
    {
        return collect($payments)
            ->sortByDesc(fn ($row) => strtotime((string) ($row['date_paiement'] ?? '')) ?: 0)
            ->take(8)
            ->map(function ($payment) {
                return [
                    'time' => $this->formatTime($payment['date_paiement'] ?? null),
                    'driver' => $payment['chauffeur_nom_complet'] ?: '—',
                    'lease' => '#' . ($payment['lease_id'] ?? '—'),
                    'amount' => $this->toFloat($payment['montant'] ?? 0),
                    'method' => $payment['methode_label'] ?? $payment['methode'] ?? '—',
                    'status' => $this->paymentStatusLabel($payment['statut'] ?? ''),
                    'recorded_by' => $payment['enregistre_par'] ?: 'Système',
                ];
            })
            ->values()
            ->all();
    }

    private function buildCutoffTimeline(array $cutoffData): array
    {
        return $cutoffData['histories']
            ->sortByDesc(fn ($row) => optional($row->updated_at)->timestamp ?? 0)
            ->take(8)
            ->map(function (LeaseCutoffHistory $history) {
                return [
                    'vehicle' => optional($history->vehicle)->immatriculation ?? 'Véhicule #' . $history->vehicle_id,
                    'lease' => '#' . ($history->lease_id ?: '—'),
                    'type' => $history->type_contrat_label ?: 'Contrat',
                    'status' => $history->status,
                    'status_label' => $this->cutoffStatusLabel((string) $history->status),
                    'badge' => $this->cutoffBadge((string) $history->status),
                    'reason' => $history->reason ?: $history->trigger_label ?: 'Décision de coupure enregistrée.',
                    'updated_at' => optional($history->updated_at)->format('d/m H:i'),
                ];
            })
            ->values()
            ->all();
    }

    private function buildContractsSummary(array $contracts): array
    {
        $rows = collect($contracts);

        return [
            'main_active' => $rows
                ->filter(fn ($row) => empty($row['source_parent_contract_id'] ?? null))
                ->filter(fn ($row) => ($row['statut'] ?? null) === 'actif' || strtoupper((string) ($row['statut'] ?? '')) === 'ACTIF')
                ->count(),

            'sub_active' => $rows
                ->filter(fn ($row) => ! empty($row['source_parent_contract_id'] ?? null))
                ->filter(fn ($row) => ($row['statut'] ?? null) === 'actif' || strtoupper((string) ($row['statut'] ?? '')) === 'ACTIF')
                ->count(),

            'sold' => $rows->filter(fn ($row) => ($row['statut'] ?? null) === 'solde' || strtoupper((string) ($row['statut'] ?? '')) === 'SOLDE')->count(),

            'suspended' => $rows->filter(fn ($row) => ($row['statut'] ?? null) === 'suspendu' || strtoupper((string) ($row['statut'] ?? '')) === 'SUSPENDU')->count(),

            'remaining_total' => $rows->sum(fn ($row) => $this->toFloat($row['montant_restant'] ?? 0)),
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

        return match ($period) {
            'yesterday' => [
                'key' => 'yesterday',
                'label' => 'Hier',
                'start_date' => $today->copy()->subDay()->toDateString(),
                'end_date' => $today->copy()->subDay()->toDateString(),
            ],

            'week' => [
                'key' => 'week',
                'label' => '7 derniers jours',
                'start_date' => $today->copy()->subDays(6)->toDateString(),
                'end_date' => $today->toDateString(),
            ],

            'month' => [
                'key' => 'month',
                'label' => '30 derniers jours',
                'start_date' => $today->copy()->subDays(29)->toDateString(),
                'end_date' => $today->toDateString(),
            ],

            default => [
                'key' => 'today',
                'label' => 'Aujourd’hui',
                'start_date' => $today->toDateString(),
                'end_date' => $today->toDateString(),
            ],
        };
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
        return $this->toFloat(
            $lease['montant_requis']
            ?? $lease['montant_attendu']
            ?? data_get($lease, 'raw.montant_attendu')
            ?? 0
        );
    }

    private function leasePaid(array $lease): float
    {
        return $this->toFloat(
            $lease['montant_paye']
            ?? data_get($lease, 'raw.montant_paye')
            ?? 0
        );
    }

    private function leaseRemaining(array $lease): float
    {
        return $this->toFloat(
            $lease['reste_a_payer']
            ?? data_get($lease, 'raw.reste_a_payer')
            ?? max(0, $this->leaseExpected($lease) - $this->leasePaid($lease))
        );
    }

    private function toFloat(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([' ', ','], ['', '.'], $value);
        }

        return (float) $value;
    }

    private function money(float|int $amount): string
    {
        return number_format((float) $amount, 0, ',', ' ') . ' FCFA';
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

    private function latestLeaseInfo(Collection $rows): string
    {
        $lastDate = $rows->pluck('date_echeance')->filter()->sort()->last();

        return $lastDate
            ? 'Échéance : ' . Carbon::parse($lastDate)->format('d/m/Y')
            : 'Échéance non disponible';
    }

    private function paymentStatusLabel(string $status): array
    {
        $value = mb_strtoupper(trim($status), 'UTF-8');

        return match ($value) {
            'VALIDE', 'VALIDÉ', 'PAYE', 'PAYÉ', 'SUCCESS', 'SUCCES', 'SUCCÈS' => [
                'label' => 'Validé',
                'badge' => 'success',
            ],

            'EN_ATTENTE', 'PENDING', 'PROCESSING' => [
                'label' => 'En attente',
                'badge' => 'warning',
            ],

            'ECHEC', 'ÉCHEC', 'FAILED' => [
                'label' => 'Échec',
                'badge' => 'danger',
            ],

            default => [
                'label' => $status ?: '—',
                'badge' => 'muted',
            ],
        };
    }

    private function driverOperationalStatus(string $cutoffLabel): array
    {
        $label = mb_strtolower($cutoffLabel, 'UTF-8');

        if (str_contains($label, 'attente')) {
            return ['label' => 'En attente arrêt', 'badge' => 'info'];
        }

        if (str_contains($label, 'plan')) {
            return ['label' => 'Coupure planifiée', 'badge' => 'warning'];
        }

        if (str_contains($label, 'commande')) {
            return ['label' => 'Commande envoyée', 'badge' => 'info'];
        }

        return ['label' => 'À relancer', 'badge' => 'danger'];
    }

    private function driverAction(string $cutoffLabel): string
    {
        $label = mb_strtolower($cutoffLabel, 'UTF-8');

        if (str_contains($label, 'attente')) {
            return 'Surveiller';
        }

        if (str_contains($label, 'commande') || str_contains($label, 'plan')) {
            return 'Suivre';
        }

        return 'Appeler';
    }

    private function cutoffStatusLabel(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Planifiée',
            'WAITING_STOP' => 'En attente arrêt',
            'COMMAND_SENT' => 'Commande envoyée',
            'CUT_OFF' => 'Coupure confirmée',
            'CANCELLED_PAID', 'CANCELLED' => 'Annulée paiement',
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
}