<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\LeaseCutoffHistory;
use App\Models\User;
use App\Services\Leases\LeaseCutoffHistoryService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Fusionne, dans une seule chronologie, les coupures automatiques (leases,
 * table lease_cutoff_histories) et les commandes moteur manuelles (table
 * commands) — pour que le partenaire ait une vue unique de tout ce qui a
 * coupé ou rallumé ses véhicules, quelle qu'en soit l'origine.
 *
 * Scope volontairement par la FLOTTE du tenant (véhicules associés au
 * partenaire, voir Voiture::utilisateur()), pas par l'utilisateur qui a
 * déclenché chaque commande — même correction que VoitureController::index()
 * et AffectationChauffeurVoitureController::tenantPartner() : un staff doit
 * voir tout l'historique de la flotte, pas seulement ses propres actions.
 *
 * Deux vocabulaires de statut coexistent volontairement :
 * - "tone" (fin) : hérité de LeaseCutoffHistoryService::getStatusTone(),
 *   utilisé uniquement pour la couleur du badge dans la vue.
 * - "status_group" (grossier, 4 valeurs) : success/pending/failed/cancelled,
 *   dénominateur commun entre l'automatique et le manuel, utilisé pour le
 *   filtre "Statut" et pour les compteurs KPI. Ne JAMAIS filtrer sur "tone"
 *   directement : CUT_OFF a pour tone "cut", pas "success" — filtrer sur
 *   tone==='success' fait disparaître silencieusement toutes les coupures
 *   automatiques confirmées (bug trouvé et corrigé le 18/08/2026).
 */
class UnifiedCutoffHistoryService
{
    /** Statuts lease_cutoff_histories.status classés par status_group unifié. */
    private const AUTO_STATUS_GROUPS = [
        'success' => ['CUT_OFF', 'REACTIVATED_AFTER_FORGIVENESS'],
        'pending' => ['PENDING', 'WAITING_STOP', 'COMMAND_SENT', 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS'],
        'failed' => ['FAILED', 'REACTIVATION_FAILED_AFTER_FORGIVENESS'],
        'cancelled' => [
            'CANCELLED_PAID', 'CANCELLED_UNVERIFIED', 'CANCELLED_RULE_MISSING',
            'CANCELLED_RULE_DISABLED', 'CANCELLED_FORGIVEN_BEFORE_CUT', 'CANCELLED_DAY_EXPIRED',
        ],
    ];

    /** Statuts lease_cutoff_histories.status considérés comme un rallumage (le reste = coupure). */
    private const AUTO_ALLUMAGE_STATUSES = [
        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
        'REACTIVATED_AFTER_FORGIVENESS',
        'REACTIVATION_FAILED_AFTER_FORGIVENESS',
    ];

    public function __construct(
        private readonly LeaseCutoffHistoryService $leaseHistoryService,
    ) {}

    public function getAvailableDirections(): array
    {
        return [
            '' => 'Tous les types',
            'COUPURE' => 'Coupure',
            'ALLUMAGE' => 'Rallumage',
        ];
    }

    public function getAvailableStatuses(): array
    {
        return [
            '' => 'Tous les statuts',
            'success' => 'Confirmé / Réussi',
            'pending' => 'En attente / en cours',
            'failed' => 'Échec',
            'cancelled' => 'Annulé',
        ];
    }

    private function resolveTenantPartner(User $user): User
    {
        return $user->partner_id
            ? (User::find($user->partner_id) ?? $user)
            : $user;
    }

    private function tenantVehicleIds(User $partner): Collection
    {
        return $partner->voitures()->pluck('voitures.id');
    }

    public function getMergedHistory(User $actor, array $filters): LengthAwarePaginator
    {
        $partner = $this->resolveTenantPartner($actor);
        $vehicleIds = $this->tenantVehicleIds($partner);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);

        $rows = collect();

        if ($actor->hasPermission('lease.view') || is_null($actor->partner_id)) {
            $rows = $rows->concat($this->fetchAutomaticRows($partner, $filters));
        }

        if ($actor->hasPermission('engine.control') || is_null($actor->partner_id)) {
            $rows = $rows->concat($this->fetchManualRows($vehicleIds, $filters));
        }

        $rows = $rows->sortByDesc('timestamp')->values();

        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Compteurs KPI — requêtes COUNT dédiées, indépendantes du plafond de
     * 500 lignes par source utilisé pour la liste paginée. Sans ça, les KPI
     * affichaient au mieux 1000 (500+500) alors que la flotte peut avoir des
     * milliers d'événements réels (bug trouvé et corrigé le 18/08/2026).
     */
    public function getSummary(User $actor, array $filters): array
    {
        $partner = $this->resolveTenantPartner($actor);
        $vehicleIds = $this->tenantVehicleIds($partner);

        $canAuto = $actor->hasPermission('lease.view') || is_null($actor->partner_id);
        $canManual = $actor->hasPermission('engine.control') || is_null($actor->partner_id);

        $source = trim((string) ($filters['source'] ?? ''));
        $includeAuto = $canAuto && $source !== 'MANUEL';
        $includeManual = $canManual && $source !== 'AUTOMATIQUE';

        $autoQuery = $includeAuto ? $this->automaticBaseQuery($partner, $filters) : null;
        $manualQuery = $includeManual ? $this->manualBaseQuery($vehicleIds, $filters) : null;

        $automatique = $autoQuery ? (clone $autoQuery)->count() : 0;
        $manuel = $manualQuery ? (clone $manualQuery)->count() : 0;

        $coupures = ($autoQuery ? (clone $autoQuery)->whereNotIn('status', self::AUTO_ALLUMAGE_STATUSES)->count() : 0)
            + ($manualQuery ? (clone $manualQuery)->where(fn ($q) => $q->where('type_commande', '!=', 'ALLUMAGE')->orWhereNull('type_commande'))->count() : 0);

        $allumages = ($autoQuery ? (clone $autoQuery)->whereIn('status', self::AUTO_ALLUMAGE_STATUSES)->count() : 0)
            + ($manualQuery ? (clone $manualQuery)->where('type_commande', 'ALLUMAGE')->count() : 0);

        $echecs = $autoQuery
            ? (clone $autoQuery)->whereIn('status', self::AUTO_STATUS_GROUPS['failed'])->count()
            : 0; // les commandes manuelles échouées ne sont pas journalisées côté "commands"

        return [
            'total' => $automatique + $manuel,
            'automatique' => $automatique,
            'manuel' => $manuel,
            'coupures' => $coupures,
            'allumages' => $allumages,
            'echecs' => $echecs,
        ];
    }

    /**
     * @return Collection<int, array>
     */
    private function fetchAutomaticRows(User $partner, array $filters): Collection
    {
        $source = trim((string) ($filters['source'] ?? ''));
        if ($source === 'MANUEL') {
            return collect();
        }

        $query = $this->automaticBaseQuery($partner, $filters)->with('vehicle');

        return $query->orderByDesc('scheduled_for')
            ->limit(500)
            ->get()
            ->map(function (LeaseCutoffHistory $h) {
                $timestamp = $h->cutoff_executed_at ?? $h->cutoff_requested_at ?? $h->scheduled_for ?? $h->detected_at;
                $direction = in_array($h->status, self::AUTO_ALLUMAGE_STATUSES, true) ? 'ALLUMAGE' : 'COUPURE';

                return [
                    'timestamp' => $timestamp,
                    'source' => 'AUTOMATIQUE',
                    'direction' => $direction,
                    'status_group' => $this->autoStatusGroup($h->status),
                    'vehicle_label' => $h->vehicle->immatriculation ?? '—',
                    'vehicle_sub' => trim(($h->vehicle->marque ?? '') . ' ' . ($h->vehicle->model ?? '')),
                    'actor' => 'Système automatique',
                    'action_label' => $this->leaseHistoryService->getStatusLabel($h->status),
                    'tone' => $this->leaseHistoryService->getStatusTone($h->status),
                    'reason' => $h->reason,
                ];
            });
    }

    /**
     * @return Collection<int, array>
     */
    private function fetchManualRows(Collection $vehicleIds, array $filters): Collection
    {
        $source = trim((string) ($filters['source'] ?? ''));
        if ($source === 'AUTOMATIQUE' || $vehicleIds->isEmpty()) {
            return collect();
        }

        $query = $this->manualBaseQuery($vehicleIds, $filters)
            ->with(['vehicule', 'user:id,nom,prenom', 'employe:id,nom,prenom']);

        return $query->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(function (Commande $c) {
                $direction = $c->type_commande === 'ALLUMAGE' ? 'ALLUMAGE' : 'COUPURE';
                $actor = $c->user
                    ? trim(($c->user->prenom ?? '') . ' ' . ($c->user->nom ?? ''))
                    : ($c->employe ? trim(($c->employe->prenom ?? '') . ' ' . ($c->employe->nom ?? '')) . ' (support)' : 'Utilisateur inconnu');

                return [
                    'timestamp' => $c->created_at,
                    'source' => 'MANUEL',
                    'direction' => $direction,
                    'status_group' => $c->status === 'QUEUED_OFFLINE' ? 'pending' : 'success',
                    'vehicle_label' => $c->vehicule->immatriculation ?? '—',
                    'vehicle_sub' => trim(($c->vehicule->marque ?? '') . ' ' . ($c->vehicule->model ?? '')),
                    'actor' => $actor !== '' ? $actor : 'Utilisateur inconnu',
                    'action_label' => $direction === 'COUPURE' ? 'Coupure manuelle envoyée' : 'Rallumage manuel envoyé',
                    'tone' => $c->status === 'QUEUED_OFFLINE' ? 'waiting' : 'success',
                    'reason' => $c->notes,
                ];
            });
    }

    private function autoStatusGroup(?string $status): string
    {
        foreach (self::AUTO_STATUS_GROUPS as $group => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $group;
            }
        }

        return 'pending';
    }

    private function automaticBaseQuery(User $partner, array $filters): Builder
    {
        $query = LeaseCutoffHistory::query()->where('partner_id', $partner->id);

        $this->applyPeriodFilter($query, $filters, 'scheduled_for');

        $direction = trim((string) ($filters['direction'] ?? ''));
        if ($direction === 'COUPURE') {
            $query->whereNotIn('status', self::AUTO_ALLUMAGE_STATUSES);
        } elseif ($direction === 'ALLUMAGE') {
            $query->whereIn('status', self::AUTO_ALLUMAGE_STATUSES);
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (isset(self::AUTO_STATUS_GROUPS[$status])) {
            $query->whereIn('status', self::AUTO_STATUS_GROUPS[$status]);
        }

        return $query;
    }

    private function manualBaseQuery(Collection $vehicleIds, array $filters): Builder
    {
        $query = Commande::query()->whereIn('vehicule_id', $vehicleIds->isEmpty() ? [0] : $vehicleIds);

        $this->applyPeriodFilter($query, $filters, 'created_at');

        /**
         * NULL-safe : quelques anciennes commandes ont type_commande/status
         * NULL en base. "!= 'ALLUMAGE'" en SQL exclut silencieusement les
         * NULL (ils ne matchent ni "= 'ALLUMAGE'" ni "!= 'ALLUMAGE'"), ce
         * qui les faisait disparaître à la fois de "coupures" et
         * "allumages" dans les KPI. On leur applique ici le même repli par
         * défaut que le mapping ligne par ligne ci-dessus (NULL -> coupure,
         * NULL -> succès) — trouvé et corrigé le 18/08/2026.
         */
        $direction = trim((string) ($filters['direction'] ?? ''));
        if ($direction === 'COUPURE') {
            $query->where(fn ($q) => $q->where('type_commande', '!=', 'ALLUMAGE')->orWhereNull('type_commande'));
        } elseif ($direction === 'ALLUMAGE') {
            $query->where('type_commande', 'ALLUMAGE');
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'pending') {
            $query->where('status', 'QUEUED_OFFLINE');
        } elseif ($status === 'success') {
            $query->where(fn ($q) => $q->where('status', '!=', 'QUEUED_OFFLINE')->orWhereNull('status'));
        } elseif (in_array($status, ['failed', 'cancelled'], true)) {
            // Aucune commande manuelle échouée/annulée n'est journalisée aujourd'hui.
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function applyPeriodFilter($query, array $filters, string $column): void
    {
        $period = trim((string) ($filters['period'] ?? ''));
        $timezone = config('app.display_timezone', 'Africa/Douala');

        if ($period === '') {
            return;
        }

        $now = Carbon::now($timezone);

        [$from, $to] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'specific_date' => $this->specificDateRange($filters, $timezone),
            'range' => $this->customRange($filters, $timezone),
            default => [null, null],
        };

        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }

    private function specificDateRange(array $filters, string $timezone): array
    {
        $date = trim((string) ($filters['specific_date'] ?? ''));

        if ($date === '') {
            return [null, null];
        }

        $parsed = Carbon::parse($date, $timezone);

        return [$parsed->copy()->startOfDay(), $parsed->copy()->endOfDay()];
    }

    private function customRange(array $filters, string $timezone): array
    {
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));

        return [
            $from !== '' ? Carbon::parse($from, $timezone)->startOfDay() : null,
            $to !== '' ? Carbon::parse($to, $timezone)->endOfDay() : null,
        ];
    }
}
