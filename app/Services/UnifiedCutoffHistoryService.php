<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\LeaseCutoffHistory;
use App\Models\User;
use App\Services\Leases\LeaseCutoffHistoryService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
 */
class UnifiedCutoffHistoryService
{
    public function __construct(
        private readonly LeaseCutoffHistoryService $leaseHistoryService,
    ) {}

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

        $rows = $this->applySourceFilter($rows, $filters)
            ->sortByDesc('timestamp')
            ->values();

        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator($slice, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    public function getSummary(User $actor, array $filters): array
    {
        $partner = $this->resolveTenantPartner($actor);
        $vehicleIds = $this->tenantVehicleIds($partner);

        $autoRows = ($actor->hasPermission('lease.view') || is_null($actor->partner_id))
            ? $this->fetchAutomaticRows($partner, $filters)
            : collect();

        $manualRows = ($actor->hasPermission('engine.control') || is_null($actor->partner_id))
            ? $this->fetchManualRows($vehicleIds, $filters)
            : collect();

        $all = $autoRows->concat($manualRows);

        return [
            'total' => $all->count(),
            'automatique' => $autoRows->count(),
            'manuel' => $manualRows->count(),
            'coupures' => $all->where('direction', 'COUPURE')->count(),
            'allumages' => $all->where('direction', 'ALLUMAGE')->count(),
            'echecs' => $all->where('tone', 'failed')->count(),
        ];
    }

    /**
     * @return Collection<int, array>
     */
    private function fetchAutomaticRows(User $partner, array $filters): Collection
    {
        $query = LeaseCutoffHistory::query()
            ->with('vehicle')
            ->where('partner_id', $partner->id);

        $this->applyPeriodFilter($query, $filters, 'scheduled_for');

        return $query->orderByDesc('scheduled_for')
            ->limit(500)
            ->get()
            ->map(function (LeaseCutoffHistory $h) {
                $timestamp = $h->cutoff_executed_at ?? $h->cutoff_requested_at ?? $h->scheduled_for ?? $h->detected_at;
                $direction = in_array($h->status, [
                    'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
                    'REACTIVATED_AFTER_FORGIVENESS',
                    'REACTIVATION_FAILED_AFTER_FORGIVENESS',
                ], true) ? 'ALLUMAGE' : 'COUPURE';

                return [
                    'timestamp' => $timestamp,
                    'source' => 'AUTOMATIQUE',
                    'direction' => $direction,
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
        if ($vehicleIds->isEmpty()) {
            return collect();
        }

        $query = Commande::query()
            ->with(['vehicule', 'user:id,nom,prenom', 'employe:id,nom,prenom'])
            ->whereIn('vehicule_id', $vehicleIds);

        $this->applyPeriodFilter($query, $filters, 'created_at');

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
                    'vehicle_label' => $c->vehicule->immatriculation ?? '—',
                    'vehicle_sub' => trim(($c->vehicule->marque ?? '') . ' ' . ($c->vehicule->model ?? '')),
                    'actor' => $actor !== '' ? $actor : 'Utilisateur inconnu',
                    'action_label' => $direction === 'COUPURE' ? 'Coupure manuelle envoyée' : 'Rallumage manuel envoyé',
                    'tone' => $c->status === 'QUEUED_OFFLINE' ? 'waiting' : 'success',
                    'reason' => $c->notes,
                ];
            });
    }

    private function applySourceFilter(Collection $rows, array $filters): Collection
    {
        $source = trim((string) ($filters['source'] ?? ''));

        if (! in_array($source, ['AUTOMATIQUE', 'MANUEL'], true)) {
            return $rows;
        }

        return $rows->where('source', $source);
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
