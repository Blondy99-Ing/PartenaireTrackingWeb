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
        $selectedPeriod = $this->resolvePeriod($filters);
        $search = trim((string) ($filters['search'] ?? ''));

        $warnings = [];
        $dailyStats = null;
        $contracts = [];
        $chauffeurs = [];
        $selectedLeases = [];
        $payments = [];

        if (($selectedPeriod['key'] ?? null) === 'today') {
            try {
                $dailyStats = $this->fetchDailyStats();
            } catch (Throwable $e) {
                report($e);
                $warnings[] = 'Les statistiques du jour sont momentanément indisponibles.';
                Log::warning('[LEASE_DASHBOARD_DAILY_STATS_FAILED]', ['error' => $e->getMessage()]);
            }
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
            /**
             * fetchLeases() renvoie désormais une structure paginée
             * ['data' => [...leases], 'count' => ..., 'current_page' => ..., ...].
             * Le dashboard ne consomme que les lignes de lease : on extrait `data`.
             * Sans cette extraction, collect()->sum() itérerait aussi sur les
             * entiers de pagination (count, page_size...) et les passerait à
             * leaseExpected(array $lease) → TypeError.
             */
            $leasesResult = $this->leaseApiService->fetchLeases(null, $contracts, $this->leaseFiltersForPeriod($selectedPeriod));
            $selectedLeases = $leasesResult['data'] ?? [];
        } catch (Throwable $e) {
            report($e);
            $warnings[] = 'Les échéances de la période sélectionnée ne sont pas disponibles.';
            Log::error('[LEASE_DASHBOARD_SELECTED_LEASES_FAILED]', [
                'period' => $selectedPeriod,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $payments = $this->leaseApiService->fetchPayments(array_merge(
                $this->paymentFiltersForPeriod($selectedPeriod),
                [
                    'est_annule' => 'false',
                    'statut__in' => 'VALIDE,SUCCESS,PAID',
                ]
            ));
        } catch (Throwable $e) {
            report($e);
            $warnings[] = 'Les paiements de la période sélectionnée sont momentanément indisponibles.';
            Log::error('[LEASE_DASHBOARD_PAYMENTS_FAILED]', [
                'period' => $selectedPeriod,
                'error' => $e->getMessage(),
            ]);
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

        $overdueLedger = $this->buildOverdueLedger($contracts, $chauffeurs, $cutoffData);
        $paymentsSummary = $this->buildPaymentsSummary($payments);

        return [
            'filters' => [
                'search' => $search,
                'period' => $selectedPeriod['key'] ?? 'today',
                'date' => $selectedPeriod['date'] ?? null,
                'start_date' => $selectedPeriod['start_date'],
                'end_date' => $selectedPeriod['end_date'],
            ],
            'period' => $selectedPeriod,
            'warnings' => $warnings,
            'kpis' => $kpis,
            'charts' => [
                'recovery' => $this->buildRecoveryChart($selectedLeases, $selectedPeriod),
                'type_recovery' => $this->buildTypeRecoveryBreakdown($selectedLeases),
            ],
            'tables' => [
                'drivers_risk' => $this->buildDriversRiskTable($selectedLeases, $cutoffData),
                'payments_today' => $this->buildPaymentsTable($payments, $selectedLeases, $contracts, $cutoffData),
                'cutoffs' => $this->buildCutoffTimeline($cutoffData),
                'contract_rules' => $this->buildContractRulesTable($cutoffData),
            ],
            'contracts_summary' => $this->buildContractsSummary($contracts),
            'cutoff_summary' => $this->buildCutoffSummary($cutoffData),
            'overdue_ledger' => $overdueLedger,
            'payments_summary' => $paymentsSummary,
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

        /**
         * Les KPI financiers du dashboard doivent être cohérents avec le bloc
         * "Chauffeurs à suivre". On utilise donc les leases affichés comme
         * source de vérité principale. Les statistiques journalières de l'API
         * restent seulement un fallback lorsque les leases sont indisponibles.
         */
        $hasLeaseRows = count($selectedLeases) > 0;

        $expected = $hasLeaseRows ? $expectedFromLeases : ($dailyStats['montant_attendu'] ?? 0);
        $paid = $hasLeaseRows ? $paidFromLeases : ($dailyStats['montant_collecte'] ?? 0);
        $remaining = $hasLeaseRows
            ? $remainingFromLeases
            : ($dailyStats['montant_echec'] ?? max(0, $expected - $paid));

        $rate = $expected > 0 ? (int) round(($paid / $expected) * 100) : 0;

        $leasesCollection = collect($selectedLeases);
        $unpaidLeases = $leasesCollection->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid');
        $paidLeases = $leasesCollection->filter(fn ($row) => ($row['statut'] ?? null) === 'paid');

        $driversExpected = $hasLeaseRows
            ? $leasesCollection->pluck('chauffeur')->filter()->unique()->count()
            : ($dailyStats['total_attendus'] ?? 0);

        $driversPaid = $hasLeaseRows
            ? $paidLeases->pluck('chauffeur')->filter()->unique()->count()
            : ($dailyStats['ayant_verse'] ?? collect($payments)->pluck('chauffeur_nom_complet')->filter()->unique()->count());

        $driversUnpaid = $hasLeaseRows
            ? $unpaidLeases->pluck('chauffeur')->filter()->unique()->count()
            : ($dailyStats['n_ayant_pas_verse'] ?? 0);

        return [
            'date' => $dailyStats['date'] ?? now()->toDateString(),
            'recovery_rate' => min(100, max(0, $rate)),
            'expected_amount' => $expected,
            'paid_amount' => $paid,
            'remaining_amount' => $remaining,
            'total_expected_drivers' => $driversExpected,
            'drivers_paid' => $driversPaid,
            'drivers_unpaid' => $driversUnpaid,
            'unpaid_leases_count' => $unpaidLeases->count(),
            'active_contracts' => collect($contracts)->filter(fn ($row) => $this->isActiveStatus($row['statut'] ?? $row['status'] ?? ''))->count(),
            'vehicles_count' => $vehicles->count(),
            'vehicles_used' => $vehicleUsage['used'],
            'vehicles_never_used' => $vehicleUsage['never_used'],
        ];
    }

    private function buildRecoveryChart(array $leases, array $period): array
    {
        $start = Carbon::parse($period['start_date'])->startOfDay();
        $end = Carbon::parse($period['end_date'])->startOfDay();
        $grouping = $this->resolveChartGrouping($start, $end);
        $buckets = $this->buildChartBuckets($start, $end, $grouping);

        foreach ($leases as $lease) {
            $date = $this->safeCarbon($lease['date_echeance'] ?? $lease['date'] ?? null);

            if (! $date) {
                continue;
            }

            $key = $this->chartBucketKey($date, $grouping);

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['expected'] += $this->leaseExpected($lease);
            $buckets[$key]['paid'] += $this->leasePaid($lease);
            $buckets[$key]['remaining'] += $this->leaseRemaining($lease);
        }

        foreach ($buckets as &$bucket) {
            $bucket['rate'] = $bucket['expected'] > 0
                ? (int) round(($bucket['paid'] / $bucket['expected']) * 100)
                : 0;
        }
        unset($bucket);

        return [
            'title' => $period['label'] ?? 'Période sélectionnée',
            'grouping' => $grouping,
            'grouping_label' => $this->chartGroupingLabel($grouping),
            'labels' => collect($buckets)->pluck('label')->values()->all(),
            'dates' => collect($buckets)->pluck('date')->values()->all(),
            'expected' => collect($buckets)->pluck('expected')->values()->all(),
            'paid' => collect($buckets)->pluck('paid')->values()->all(),
            'remaining' => collect($buckets)->pluck('remaining')->values()->all(),
            'rate' => collect($buckets)->pluck('rate')->values()->all(),
        ];
    }

    private function resolveChartGrouping(Carbon $start, Carbon $end): string
    {
        $days = $start->diffInDays($end) + 1;

        if ($days <= 14) {
            return 'day';
        }

        if ($days <= 92) {
            return 'week';
        }

        if ($days <= 730) {
            return 'month';
        }

        return 'year';
    }

    private function buildChartBuckets(Carbon $start, Carbon $end, string $grouping): array
    {
        $buckets = [];
        $labelsByIsoDay = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
        $monthLabels = [
            1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr', 5 => 'Mai', 6 => 'Juin',
            7 => 'Juil', 8 => 'Août', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc',
        ];

        if ($grouping === 'day') {
            $includeDate = $start->diffInDays($end) > 6;

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $key = $date->toDateString();
                $buckets[$key] = $this->emptyChartBucket(
                    label: ($labelsByIsoDay[$date->isoWeekday()] ?? $date->format('d/m')) . ($includeDate ? ' ' . $date->format('d/m') : ''),
                    date: $key
                );
            }

            return $buckets;
        }

        if ($grouping === 'week') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $index = 1;

            while ($cursor->lte($end)) {
                $weekStart = $cursor->copy();
                $weekEnd = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
                $key = $weekStart->format('o-\WW');
                $buckets[$key] = $this->emptyChartBucket(
                    label: 'S' . $index . ' · ' . $weekStart->format('d/m'),
                    date: $weekStart->toDateString() . ' → ' . ($weekEnd->gt($end) ? $end : $weekEnd)->toDateString()
                );
                $cursor->addWeek();
                $index++;
            }

            return $buckets;
        }

        if ($grouping === 'month') {
            $cursor = $start->copy()->startOfMonth();
            $multiYear = $start->year !== $end->year;

            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m');
                $buckets[$key] = $this->emptyChartBucket(
                    label: ($monthLabels[(int) $cursor->month] ?? $cursor->format('M')) . ($multiYear ? ' ' . $cursor->format('Y') : ''),
                    date: $cursor->toDateString()
                );
                $cursor->addMonth();
            }

            return $buckets;
        }

        $cursor = $start->copy()->startOfYear();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y');
            $buckets[$key] = $this->emptyChartBucket(
                label: $cursor->format('Y'),
                date: $cursor->toDateString()
            );
            $cursor->addYear();
        }

        return $buckets;
    }

    private function emptyChartBucket(string $label, string $date): array
    {
        return [
            'label' => $label,
            'date' => $date,
            'expected' => 0.0,
            'paid' => 0.0,
            'remaining' => 0.0,
            'rate' => 0,
        ];
    }

    private function chartBucketKey(Carbon $date, string $grouping): string
    {
        return match ($grouping) {
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->format('o-\WW'),
            'month' => $date->format('Y-m'),
            'year' => $date->format('Y'),
            default => $date->toDateString(),
        };
    }

    private function chartGroupingLabel(string $grouping): string
    {
        return match ($grouping) {
            'week' => 'par semaine',
            'month' => 'par mois',
            'year' => 'par année',
            default => 'par jour',
        };
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

        /**
         * Nouvelle logique dashboard :
         * - une coupure "planifiée" est une queue réelle du jour ;
         * - une règle active n'est pas une coupure planifiée, c'est seulement
         *   une autorisation métier pour que le cron puisse planifier si le
         *   lease exact est NON_PAYE ;
         * - COMMAND_SENT ne veut pas dire coupure confirmée.
         */
        return [
            'planned' => $queue->where('status', 'PENDING')->count(),
            'waiting_stop' => $queue->where('status', 'WAITING_STOP')->count(),
            'command_sent' => $queue->where('status', 'COMMAND_SENT')->count() + $histories->where('status', 'COMMAND_SENT')->count(),
            'confirmed' => $histories->where('status', 'CUT_OFF')->count(),
            'failed' => $queue->where('status', 'FAILED')->count() + $histories->where('status', 'FAILED')->count(),
            'cancelled_paid' => $histories->whereIn('status', ['CANCELLED_PAID', 'CANCELLED'])->count(),
            'forgiven_before_cut' => $histories->where('status', 'CANCELLED_FORGIVEN_BEFORE_CUT')->count(),
            'reactivated' => $histories->whereIn('status', ['REACTIVATION_SENT', 'REACTIVATED'])->count(),
            'active_rules' => (int) ($cutoffData['active_rules_count'] ?? 0),
            'disabled_rules' => (int) ($cutoffData['disabled_rules_count'] ?? 0),
            'contracts_without_rule' => (int) ($cutoffData['contracts_without_rule_count'] ?? 0),
        ];
    }

    private function getLocalCutoffData(int $partnerId, array $period): array
    {
        $start = Carbon::parse($period['start_date'])->startOfDay();
        $end = Carbon::parse($period['end_date'])->endOfDay();

        $queue = LeaseCutoffQueue::query()
            ->with(['vehicle', 'contractRule', 'contractLink'])
            ->where('partner_id', $partnerId)
            ->whereBetween('lease_date_echeance', [$start->toDateString(), $end->toDateString()])
            ->get();

        $histories = LeaseCutoffHistory::query()
            ->with(['vehicle', 'contractRule', 'contractLink'])
            ->where('partner_id', $partnerId)
            ->whereBetween('lease_date_echeance', [$start->toDateString(), $end->toDateString()])
            ->get();

        $rules = LeaseCutoffContractRule::query()
            ->with(['vehicle', 'contractLink'])
            ->where('partner_id', $partnerId)
            ->whereNotNull('contract_link_id')
            ->get();

        $links = LeaseContractLink::query()
            ->with(['vehicle', 'cutoffRule'])
            ->where('partner_id', $partnerId)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->get();

        $linkIdsWithRule = $rules->pluck('contract_link_id')->filter()->unique();

        return [
            'queue' => $queue,
            'active_queue' => $queue->whereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])->values(),
            'histories' => $histories,
            'rules' => $rules,
            'links' => $links,
            'active_rules_count' => $rules->where('is_enabled', true)->count(),
            'disabled_rules_count' => $rules->where('is_enabled', false)->count(),
            'contracts_without_rule_count' => $links->filter(fn (LeaseContractLink $link) => ! $linkIdsWithRule->contains($link->id))->count(),
        ];
    }

    private function buildDriversRiskTable(array $leases, array $cutoffData): array
    {
        /**
         * Source de vérité : la règle est reliée à lease_contract_links.id.
         * L'ancien affichage utilisait surtout source_contract_id ; lorsque la
         * règle active avait seulement contract_link_id, le dashboard affichait
         * à tort « Sans règle ».
         */
        $rulesByContractLinkId = collect($cutoffData['rules'] ?? [])
            ->filter(fn (LeaseCutoffContractRule $rule) => $rule->contract_link_id)
            ->keyBy(fn (LeaseCutoffContractRule $rule) => (int) $rule->contract_link_id);

        $rulesBySourceContractId = collect($cutoffData['rules'] ?? [])
            ->filter(fn (LeaseCutoffContractRule $rule) => $rule->source_contract_id)
            ->keyBy(fn (LeaseCutoffContractRule $rule) => (int) $rule->source_contract_id);

        $linksBySourceContractId = collect($cutoffData['links'] ?? [])
            ->filter(fn (LeaseContractLink $link) => $link->source_contract_id)
            ->keyBy(fn (LeaseContractLink $link) => (int) $link->source_contract_id);

        $mainLinksBySourceContractId = collect($cutoffData['links'] ?? [])
            ->filter(fn (LeaseContractLink $link) => $link->contract_kind !== LeaseContractLink::KIND_SUB)
            ->filter(fn (LeaseContractLink $link) => $link->source_contract_id)
            ->keyBy(fn (LeaseContractLink $link) => (int) $link->source_contract_id);

        return collect($leases)
            ->groupBy(fn ($row) => (string) ($row['chauffeur'] ?? 'Chauffeur'))
            ->map(function (Collection $rows, string $driver) use ($rulesByContractLinkId, $rulesBySourceContractId, $linksBySourceContractId, $mainLinksBySourceContractId) {
                $unpaid = $rows->filter(fn ($row) => ($row['statut'] ?? null) === 'unpaid');
                $amountDue = $unpaid->sum(fn ($row) => $this->leaseRemaining($row));
                $types = $unpaid->map(fn ($row) => $this->displayTypeLabel($row))->filter()->unique()->implode(', ');

                /**
                 * Colonne Véhicule : on affiche toujours l’immatriculation du
                 * véhicule porté par le contrat principal. Un sous-contrat peut
                 * déclencher le suivi/coupure, mais l’utilisateur doit voir la
                 * moto réelle liée au contrat principal.
                 */
                $vehicle = $this->resolveMainContractVehicleForDriverRows($rows, $mainLinksBySourceContractId);

                $matchingRules = $unpaid
                    ->map(function ($row) use ($rulesByContractLinkId, $rulesBySourceContractId, $linksBySourceContractId) {
                        $contractLinkId = (int) ($row['contract_link_id'] ?? 0);
                        if ($contractLinkId > 0 && $rulesByContractLinkId->has($contractLinkId)) {
                            return $rulesByContractLinkId->get($contractLinkId);
                        }

                        $sourceContractId = (int) (
                            $row['source_contrat_id']
                            ?? $row['contrat_id']
                            ?? $row['contract_id']
                            ?? data_get($row, 'contrat.id')
                            ?? 0
                        );

                        if ($sourceContractId > 0 && $rulesBySourceContractId->has($sourceContractId)) {
                            return $rulesBySourceContractId->get($sourceContractId);
                        }

                        $link = $sourceContractId > 0 ? $linksBySourceContractId->get($sourceContractId) : null;
                        return $link ? $rulesByContractLinkId->get((int) $link->id) : null;
                    })
                    ->filter();

                $hasEnabledRule = $matchingRules->contains(fn (LeaseCutoffContractRule $rule) => (bool) $rule->is_enabled);
                $hasDisabledRule = $matchingRules->contains(fn (LeaseCutoffContractRule $rule) => ! (bool) $rule->is_enabled);

                $cutoffStatus = match (true) {
                    $hasEnabledRule => ['label' => 'Coupure active', 'badge' => 'danger'],
                    $hasDisabledRule => ['label' => 'Règle désactivée', 'badge' => 'muted'],
                    $amountDue > 0 => ['label' => 'Sans règle', 'badge' => 'warning'],
                    default => ['label' => '—', 'badge' => 'muted'],
                };

                return [
                    'driver' => $driver ?: '—',
                    'vehicle' => $vehicle,
                    'unpaid_count' => $unpaid->count(),
                    'amount_due' => $amountDue,
                    'types' => $types ?: '—',
                    'status' => $amountDue > 0 ? ['label' => 'À suivre', 'badge' => 'warning'] : ['label' => 'À jour', 'badge' => 'success'],
                    'cutoff_status' => $cutoffStatus,
                    'action' => $amountDue > 0 ? 'Suivre' : 'OK',
                    'last_info' => $this->latestLeaseInfo($rows),
                    'search' => mb_strtolower($driver . ' ' . $vehicle . ' ' . $types . ' ' . $cutoffStatus['label'], 'UTF-8'),
                ];
            })
            ->filter(fn ($row) => ($row['unpaid_count'] ?? 0) > 0)
            ->sortByDesc('amount_due')
            ->values()
            ->all();
    }

    /**
     * Ardoise globale par chauffeur : impayés RÉELS, toutes échéances
     * confondues, indépendamment de la période affichée en haut du dashboard.
     *
     * Pourquoi un bloc séparé :
     * Le tableau « Chauffeurs à suivre » ne regarde que les échéances de la
     * période sélectionnée. Sur « Aujourd'hui », une dette accumulée depuis
     * 3 semaines devient invisible tant qu'on ne change pas la période. Un
     * gestionnaire doit voir cette dette en permanence, sans manipulation.
     */
    private function buildOverdueLedger(array $contracts, array $chauffeurs, array $cutoffData): array
    {
        try {
            $unpaidRows = $this->leaseApiService->fetchLeases('NON_PAYE', $contracts)['data'] ?? [];
        } catch (Throwable $e) {
            report($e);
            Log::warning('[LEASE_DASHBOARD_OVERDUE_LEDGER_FAILED]', ['error' => $e->getMessage()]);

            return $this->emptyOverdueLedger();
        }

        $today = Carbon::today();

        $mainLinksBySourceContractId = collect($cutoffData['links'] ?? [])
            ->filter(fn (LeaseContractLink $link) => $link->contract_kind !== LeaseContractLink::KIND_SUB)
            ->filter(fn (LeaseContractLink $link) => $link->source_contract_id)
            ->keyBy(fn (LeaseContractLink $link) => (int) $link->source_contract_id);

        $drivers = collect($unpaidRows)
            ->filter(fn ($row) => is_array($row) && ($row['statut'] ?? null) === 'unpaid')
            ->groupBy(fn ($row) => (string) ($row['chauffeur'] ?? 'Chauffeur'))
            ->map(function (Collection $rows, string $driver) use ($today, $mainLinksBySourceContractId) {
                $amountDue = $rows->sum(fn ($row) => $this->leaseRemaining($row));
                $oldestDue = $rows->pluck('date_echeance')->filter()->map(fn ($d) => $this->safeCarbon($d))->filter()->sort()->first();
                $daysLate = $oldestDue ? max(0, $oldestDue->diffInDays($today)) : 0;
                $types = $rows->map(fn ($row) => $this->displayTypeLabel($row))->filter()->unique()->implode(', ');
                $vehicle = $this->resolveMainContractVehicleForDriverRows($rows, $mainLinksBySourceContractId);

                return [
                    'driver' => $driver ?: '—',
                    'vehicle' => $vehicle,
                    'unpaid_count' => $rows->count(),
                    'amount_due' => $amountDue,
                    'types' => $types ?: '—',
                    'oldest_due_date' => $oldestDue?->format('d/m/Y'),
                    'days_late' => $daysLate,
                    'urgency' => match (true) {
                        $daysLate >= 7 => ['label' => $daysLate . ' j. de retard', 'badge' => 'danger'],
                        $daysLate >= 1 => ['label' => $daysLate . ' j. de retard', 'badge' => 'warning'],
                        default => ['label' => 'Échéance du jour', 'badge' => 'info'],
                    },
                    'search' => mb_strtolower($driver . ' ' . $vehicle . ' ' . $types, 'UTF-8'),
                ];
            })
            ->filter(fn ($row) => $row['amount_due'] > 0)
            ->sortByDesc('days_late')
            ->values()
            ->all();

        $overdueDriverNames = collect($drivers)->pluck('driver')->map(fn ($n) => mb_strtolower(trim($n), 'UTF-8'))->filter()->unique();

        $allDriverNames = collect($chauffeurs)
            ->pluck('nom_complet')
            ->filter()
            ->map(fn ($n) => mb_strtolower(trim($n), 'UTF-8'))
            ->unique();

        $upToDateCount = $allDriverNames->isNotEmpty()
            ? $allDriverNames->diff($overdueDriverNames)->count()
            : null;

        return [
            'drivers' => $drivers,
            'overdue_count' => count($drivers),
            'up_to_date_count' => $upToDateCount,
            'total_due' => collect($drivers)->sum('amount_due'),
            'oldest_overdue_days' => (int) (collect($drivers)->max('days_late') ?: 0),
        ];
    }

    private function emptyOverdueLedger(): array
    {
        return [
            'drivers' => [],
            'overdue_count' => 0,
            'up_to_date_count' => null,
            'total_due' => 0,
            'oldest_overdue_days' => 0,
        ];
    }

    private function resolveMainContractVehicleForDriverRows(Collection $rows, Collection $mainLinksBySourceContractId): string
    {
        $mainRow = $rows->first(function ($row) {
            $kind = mb_strtoupper((string) ($row['contract_kind'] ?? $row['contrat_kind'] ?? ''), 'UTF-8');
            $parentContractId = (int) ($row['parent_contract_id'] ?? 0);

            return $kind === 'MAIN' || $kind === 'PRINCIPAL' || $parentContractId <= 0;
        });

        $vehicle = $this->rowVehicleLabel($mainRow);
        if ($vehicle !== '—') {
            return $vehicle;
        }

        $parentContractIds = $rows
            ->map(fn ($row) => (int) ($row['parent_contract_id'] ?? data_get($row, 'parent.id') ?? 0))
            ->filter()
            ->unique();

        foreach ($parentContractIds as $parentContractId) {
            /** @var LeaseContractLink|null $link */
            $link = $mainLinksBySourceContractId->get($parentContractId);
            if (! $link) {
                continue;
            }

            $vehicle = optional($link->vehicle)->immatriculation
                ?: $link->immatriculation
                ?: $link->vin
                ?: null;

            if ($vehicle) {
                return (string) $vehicle;
            }
        }

        return $this->rowVehicleLabel($rows->first());
    }

    private function rowVehicleLabel(mixed $row): string
    {
        if (! is_array($row)) {
            return '—';
        }

        return (string) (
            $row['vehicule']
            ?? $row['immatriculation']
            ?? data_get($row, 'vehicle.immatriculation')
            ?? data_get($row, 'voiture.immatriculation')
            ?? '—'
        );
    }


    private function buildContractRulesTable(array $cutoffData): array
    {
        $links = collect($cutoffData['links'] ?? []);
        $rulesByLinkId = collect($cutoffData['rules'] ?? [])->keyBy('contract_link_id');

        return $links
            ->map(function (LeaseContractLink $link) use ($rulesByLinkId) {
                /** @var LeaseCutoffContractRule|null $rule */
                $rule = $rulesByLinkId->get($link->id);
                $vehicle = optional($link->vehicle)->immatriculation
                    ?: $link->immatriculation
                    ?: $link->vin
                    ?: '—';
                $kind = $link->contract_kind === LeaseContractLink::KIND_SUB ? 'Sous-contrat' : 'Contrat principal';
                $status = $rule
                    ? ((bool) $rule->is_enabled ? ['label' => 'Active', 'badge' => 'success'] : ['label' => 'Inactive', 'badge' => 'muted'])
                    : ['label' => 'Aucune règle', 'badge' => 'warning'];

                return [
                    'contract' => '#' . $link->source_contract_id,
                    'vehicle' => $vehicle,
                    'type' => $this->cleanLabel((string) $link->type_contrat_label, $kind),
                    'kind' => $kind,
                    'rule_status' => $status,
                    'cutoff_time' => $rule?->effectiveCutoffTime() ?: '—',
                    'grace_days' => $rule?->grace_days ?? 0,
                    'only_when_stopped' => $rule ? (bool) $rule->only_when_stopped : true,
                    'search' => mb_strtolower($vehicle . ' ' . $link->source_contract_id . ' ' . $link->type_contrat_label . ' ' . $kind . ' ' . $status['label'], 'UTF-8'),
                ];
            })
            ->sortBy(fn ($row) => ($row['rule_status']['label'] === 'Aucune règle' ? '0' : '1') . $row['vehicle'] . $row['type'])
            ->take(500) // même plafond que la page (500 max), au lieu de 12
            ->values()
            ->all();
    }

    /**
     * Trésorerie réelle de la période : basée sur `date_paiement` (quand le
     * cash est vraiment rentré), jamais sur `date_echeance` (quand il était
     * dû). C'est délibérément une lecture différente des KPI « Montant
     * attendu / Reste à payer » du haut de page, qui restent basés sur
     * l'échéance — les deux répondent à des questions différentes et ne
     * doivent jamais être fusionnés en un seul chiffre.
     */
    private function buildPaymentsSummary(array $payments): array
    {
        $rows = collect($payments)->filter(fn ($row) => is_array($row));

        $byDriver = $rows
            ->groupBy(fn ($row) => (string) ($row['chauffeur_nom_complet'] ?? 'Chauffeur'))
            ->map(function (Collection $driverPayments, string $driver) {
                $lastPaymentAt = $driverPayments
                    ->pluck('date_paiement')
                    ->filter()
                    ->map(fn ($d) => $this->safeCarbon($d))
                    ->filter()
                    ->sort()
                    ->last();

                return [
                    'driver' => $driver ?: '—',
                    'amount_total' => $driverPayments->sum(fn ($row) => $this->toFloat($row['montant'] ?? 0)),
                    'payments_count' => $driverPayments->count(),
                    'last_payment_at' => $lastPaymentAt?->format('d/m/Y H:i'),
                    'search' => mb_strtolower($driver, 'UTF-8'),
                ];
            })
            ->sortByDesc('amount_total')
            ->values()
            ->all();

        return [
            'total_amount' => $rows->sum(fn ($row) => $this->toFloat($row['montant'] ?? 0)),
            'payments_count' => $rows->count(),
            'drivers_count' => count($byDriver),
            'by_driver' => $byDriver,
        ];
    }

    private function buildPaymentsTable(array $payments, array $selectedLeases = [], array $contracts = [], array $cutoffData = []): array
    {
        /**
         * Source normale : /paiements/. Fallback : leases du jour déjà payés.
         * Ce fallback est volontaire : certains retours API de paiement ne
         * renvoient pas toujours date_paiement ou chauffeur_nom_complet, alors
         * que /leases/ contient déjà le lease payé, son chauffeur et ses montants.
         */
        $leasesById = collect($selectedLeases)
            ->filter(fn ($lease) => ! empty($lease['id']) || ! empty($lease['source_lease_id']))
            ->keyBy(fn ($lease) => (int) ($lease['id'] ?? $lease['source_lease_id']));

        $paymentsByLeaseId = collect($payments)
            ->filter(fn ($payment) => ! empty($payment['lease_id']))
            ->keyBy(fn ($payment) => (int) $payment['lease_id']);

        $fallbackPaidLeases = collect($selectedLeases)
            ->filter(fn ($lease) => ($lease['statut'] ?? null) === 'paid')
            ->reject(fn ($lease) => $paymentsByLeaseId->has((int) ($lease['id'] ?? $lease['source_lease_id'] ?? 0)))
            ->map(function ($lease) {
                return [
                    'id' => (int) ($lease['paiement_id'] ?? 0),
                    'lease_id' => (int) ($lease['id'] ?? $lease['source_lease_id'] ?? 0),
                    'montant' => $this->leasePaid($lease),
                    'methode' => $lease['methode'] ?? null,
                    'methode_label' => $lease['methode_label'] ?? null,
                    'statut' => $lease['paiement_statut'] ?? 'SUCCESS',
                    'date_paiement' => $lease['date_paiement'] ?? $lease['date_echeance'] ?? $lease['date'] ?? null,
                    'chauffeur_nom_complet' => $lease['chauffeur'] ?? '',
                    'vehicule' => $lease['vehicule'] ?? null,
                    'immatriculation' => $lease['vehicule'] ?? null,
                    'type_contrat_label' => $lease['type_contrat_label'] ?? $lease['contrat_type'] ?? null,
                    'contract_kind' => $lease['contract_kind'] ?? $lease['contrat_kind'] ?? null,
                    'raw' => $lease,
                ];
            });

        /**
         * Véhicule par chauffeur — MÊME logique que le bloc « Chauffeurs à suivre » :
         * on affiche l'immatriculation portée par le CONTRAT PRINCIPAL du chauffeur,
         * résolue via resolveMainContractVehicleForDriverRows() (leases du jour +
         * liens locaux lease_contract_links). On mappe par nom de chauffeur car le
         * lease précis qui a été payé n'appartient pas forcément à la période
         * affichée, alors que le véhicule du chauffeur, lui, reste le même.
         */
        $mainLinksBySourceContractId = collect($cutoffData['links'] ?? [])
            ->filter(fn (LeaseContractLink $link) => $link->contract_kind !== LeaseContractLink::KIND_SUB)
            ->filter(fn (LeaseContractLink $link) => $link->source_contract_id)
            ->keyBy(fn (LeaseContractLink $link) => (int) $link->source_contract_id);

        $vehicleByDriver = collect($selectedLeases)
            ->filter(fn ($l) => is_array($l))
            ->groupBy(fn ($l) => (string) ($l['chauffeur'] ?? 'Chauffeur'))
            ->map(fn (Collection $rows) => $this->resolveMainContractVehicleForDriverRows($rows, $mainLinksBySourceContractId));

        /**
         * Type de contrat réel (Moto, Casque, Phone, Caution, Royal care…) indexé
         * par id de contrat, depuis la liste des contrats. Indispensable car le
         * paiement/lease affiché ne porte pas toujours un libellé de type exploitable
         * (sinon on retombe sur « Contrat principal » par défaut).
         */
        $typeByContractId = collect($contracts)
            ->filter(fn ($c) => is_array($c))
            ->mapWithKeys(fn ($c) => [
                (int) ($c['source_contrat_id'] ?? $c['id'] ?? 0) => $this->displayTypeLabel($c),
            ]);

        /**
         * Type de contrat par lease_id. Les paiements ne portent que `lease_id` ;
         * on résout d'abord depuis les leases du jour déjà chargés, puis — pour les
         * paiements dont le lease n'est pas dans la période (ex. échéance passée) —
         * via un index léger lease_id => libellé récupéré à la demande.
         */
        $typeByLeaseId = collect($selectedLeases)
            ->filter(fn ($l) => is_array($l) && (int) ($l['id'] ?? $l['source_lease_id'] ?? 0) > 0)
            ->mapWithKeys(fn ($l) => [(int) ($l['id'] ?? $l['source_lease_id']) => $this->displayTypeLabel($l)]);

        $missingLeaseIds = collect($payments)
            ->map(fn ($p) => (int) ($p['lease_id'] ?? 0))
            ->filter(fn ($id) => $id > 0 && ! $typeByLeaseId->has($id))
            ->unique();

        if ($missingLeaseIds->isNotEmpty()) {
            try {
                foreach ($this->leaseApiService->fetchLeaseTypeLabels() as $lid => $label) {
                    if (! $typeByLeaseId->has((int) $lid)) {
                        $typeByLeaseId->put((int) $lid, $this->cleanLabel((string) $label, 'Sous-contrat'));
                    }
                }
            } catch (Throwable $e) {
                Log::warning('[LEASE_DASHBOARD_PAYMENT_TYPE_INDEX_FAILED]', ['error' => $e->getMessage()]);
            }
        }

        return collect($payments)
            ->concat($fallbackPaidLeases)
            ->sortByDesc(fn ($payment) => optional($this->safeCarbon($payment['date_paiement'] ?? null))->timestamp ?? 0)
            ->map(function ($payment) use ($leasesById, $vehicleByDriver, $typeByContractId, $typeByLeaseId) {
                $leaseId = (int) ($payment['lease_id'] ?? data_get($payment, 'raw.id') ?? 0);
                $lease = $leaseId > 0 ? $leasesById->get($leaseId) : null;
                $raw = is_array($payment['raw'] ?? null) ? $payment['raw'] : [];

                $driver = $payment['chauffeur_nom_complet']
                    ?? $payment['chauffeur']
                    ?? data_get($payment, 'raw.chauffeur')
                    ?? data_get($lease, 'chauffeur')
                    ?? '—';

                // Véhicule : immatriculation du contrat principal du chauffeur
                // (identique au bloc « Chauffeurs à suivre »).
                $vehicle = $vehicleByDriver->get((string) $driver);
                if (! $vehicle || $vehicle === '—') {
                    $vehicle = $this->rowVehicleLabel($lease ?: $raw);
                }

                // Type : nom réel du type de contrat.
                // 1) via lease_id (leases du jour + index léger) ;
                // 2) sinon via l'id de contrat ;
                // 3) sinon repli sur le libellé porté par le lease/paiement.
                $type = $leaseId > 0 ? $typeByLeaseId->get($leaseId) : null;

                if (! $type || $type === '') {
                    $contractId = (int) (
                        $payment['contrat_id']
                        ?? data_get($lease, 'source_contrat_id')
                        ?? data_get($payment, 'raw.contrat_id')
                        ?? data_get($payment, 'raw.contrat.id')
                        ?? 0
                    );
                    $type = $contractId > 0 ? $typeByContractId->get($contractId) : null;
                }

                if (! $type || $type === '') {
                    $type = $lease ? $this->displayTypeLabel($lease) : $this->displayTypeLabel($raw);
                }

                $method = $payment['methode_label'] ?? $payment['methode'] ?? $payment['method'] ?? '—';

                return [
                    'date_paiement' => $this->formatDateTime($payment['date_paiement'] ?? null),
                    'driver' => $driver ?: '—',
                    'lease' => $vehicle !== '—' ? $vehicle : '—',
                    'contract_type' => $type ?: '—',
                    'amount' => $this->toFloat($payment['montant'] ?? $payment['amount'] ?? 0),
                    'method' => $method ?: '—',
                    'status' => $this->paymentStatusLabel($payment['statut'] ?? $payment['status'] ?? ''),
                    'search' => mb_strtolower(($driver ?? '') . ' ' . ($vehicle ?? '') . ' ' . ($type ?? '') . ' ' . ($leaseId ?: '') . ' ' . ($method ?? ''), 'UTF-8'),
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
                $date = $this->safeCarbon(
                    $payment['date_paiement']
                    ?? $payment['created_at']
                    ?? $payment['updated_at']
                    ?? data_get($payment, 'raw.date_paiement')
                    ?? data_get($payment, 'raw.created_at')
                    ?? null
                );

                return $date && $date->betweenIncluded($start, $end);
            })
            ->values()
            ->all();
    }

    private function leaseFiltersForPeriod(array $period): array
    {
        if ($period['start_date'] === $period['end_date']) {
            return ['date_echeance' => $period['start_date']];
        }

        return [
            'date_echeance_start' => $period['start_date'],
            'date_echeance_end' => $period['end_date'],
        ];
    }

    private function paymentFiltersForPeriod(array $period): array
    {
        if ($period['start_date'] === $period['end_date']) {
            return ['date_paiement' => $period['start_date']];
        }

        return [
            'date_paiement_start' => $period['start_date'],
            'date_paiement_end' => $period['end_date'],
        ];
    }

    private function resolvePeriod(array|string $filters = []): array
    {
        $timezone = config('app.timezone') ?: 'Africa/Douala';
        $today = now($timezone)->startOfDay();
        $filters = is_array($filters) ? $filters : ['period' => $filters];
        $key = (string) ($filters['period'] ?? 'today');

        $period = match ($key) {
            'yesterday' => [
                'key' => 'yesterday',
                'label' => 'Hier',
                'start' => $today->copy()->subDay(),
                'end' => $today->copy()->subDay(),
            ],
            'week', 'this_week' => [
                'key' => 'week',
                'label' => 'Cette semaine',
                'start' => $today->copy()->startOfWeek(Carbon::MONDAY),
                'end' => $today->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            'month', 'this_month' => [
                'key' => 'month',
                'label' => 'Ce mois',
                'start' => $today->copy()->startOfMonth(),
                'end' => $today->copy()->endOfMonth(),
            ],
            'year', 'this_year' => [
                'key' => 'year',
                'label' => 'Cette année',
                'start' => $today->copy()->startOfYear(),
                'end' => $today->copy()->endOfYear(),
            ],
            'date' => $this->resolveSpecificDatePeriod($filters, $today),
            'range' => $this->resolveRangePeriod($filters, $today),
            default => [
                'key' => 'today',
                'label' => 'Aujourd’hui',
                'start' => $today->copy(),
                'end' => $today->copy(),
            ],
        };

        $start = $period['start']->copy()->startOfDay();
        $end = $period['end']->copy()->startOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'key' => $period['key'],
            'label' => $period['label'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'date' => $period['key'] === 'date' ? $start->toDateString() : null,
        ];
    }

    private function resolveSpecificDatePeriod(array $filters, Carbon $today): array
    {
        $date = $this->safeCarbon($filters['date'] ?? null)?->startOfDay() ?: $today->copy();

        return [
            'key' => 'date',
            'label' => 'Date spécifique · ' . $date->format('d/m/Y'),
            'start' => $date,
            'end' => $date->copy(),
        ];
    }

    private function resolveRangePeriod(array $filters, Carbon $today): array
    {
        $start = $this->safeCarbon($filters['start_date'] ?? null)?->startOfDay() ?: $today->copy();
        $end = $this->safeCarbon($filters['end_date'] ?? null)?->startOfDay() ?: $start->copy();

        return [
            'key' => 'range',
            'label' => 'Plage · ' . $start->format('d/m/Y') . ' → ' . $end->format('d/m/Y'),
            'start' => $start,
            'end' => $end,
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
            'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardon avant coupure : non replanifiable.',
            'REACTIVATION_SENT' => 'Relance demandée après pardon.',
            'REACTIVATED' => 'Véhicule relancé après pardon.',
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
            'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardonné avant',
            'REACTIVATION_SENT' => 'Relance envoyée',
            'REACTIVATED' => 'Relancé',
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
            'CANCELLED_PAID', 'CANCELLED', 'CANCELLED_FORGIVEN_BEFORE_CUT' => 'muted',
            'REACTIVATION_SENT', 'REACTIVATED' => 'success',
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

    private function formatDateTime(?string $value): string
    {
        $date = $this->safeCarbon($value);
        return $date ? $date->format('d/m/Y H:i') : '—';
    }
}
