<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class LeaseCutoffHistoryService
{
    /**
     * Résout le partenaire courant :
     * - si partner_id est null : l'utilisateur connecté est le partenaire
     * - sinon : l'utilisateur dépend d'un partenaire
     */
    public function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    /**
     * Retourne l'historique paginé avec filtres.
     */
    public function getPaginatedHistory(User $user, array $filters): LengthAwarePaginator
    {
        $partnerId = $this->resolvePartnerId($user);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = LeaseCutoffHistory::query()
            ->with(['vehicle', 'rule'])
            ->where('partner_id', $partnerId)
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id');

        $this->applyStatusFilter($query, $filters);
        $this->applyPeriodFilter($query, $filters);
        $this->applySearchFilter($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Retourne les compteurs utiles pour la page.
     */
    public function getSummary(User $user, array $filters = []): array
    {
        $partnerId = $this->resolvePartnerId($user);

        $baseQuery = LeaseCutoffHistory::query()
            ->where('partner_id', $partnerId);

        $filteredQuery = LeaseCutoffHistory::query()
            ->where('partner_id', $partnerId);

        $this->applyStatusFilter($filteredQuery, $filters);
        $this->applyPeriodFilter($filteredQuery, $filters);
        $this->applySearchFilter($filteredQuery, $filters);

        return [
            'total_all' => (clone $baseQuery)->count(),
            'total_filtered' => (clone $filteredQuery)->count(),

            'cut_off' => (clone $filteredQuery)->where('status', 'CUT_OFF')->count(),
            'pending' => (clone $filteredQuery)->where('status', 'PENDING')->count(),
            'waiting_stop' => (clone $filteredQuery)->where('status', 'WAITING_STOP')->count(),
            'command_sent' => (clone $filteredQuery)->where('status', 'COMMAND_SENT')->count(),

            'cancelled_paid' => (clone $filteredQuery)->where('status', 'CANCELLED_PAID')->count(),
            'cancelled_forgiven_before_cut' => (clone $filteredQuery)
                ->where('status', 'CANCELLED_FORGIVEN_BEFORE_CUT')
                ->count(),

            'reactivation_requested_after_forgiveness' => (clone $filteredQuery)
                ->where('status', 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS')
                ->count(),

            'reactivated_after_forgiveness' => (clone $filteredQuery)
                ->where('status', 'REACTIVATED_AFTER_FORGIVENESS')
                ->count(),

            'reactivation_failed_after_forgiveness' => (clone $filteredQuery)
                ->where('status', 'REACTIVATION_FAILED_AFTER_FORGIVENESS')
                ->count(),

            'failed' => (clone $filteredQuery)->where('status', 'FAILED')->count(),
        ];
    }

    /**
     * Liste des statuts disponibles pour le filtre.
     */
    public function getAvailableStatuses(): array
    {
        return [
            '' => 'Tous les statuts',

            'PENDING' => 'En attente de traitement',
            'WAITING_STOP' => 'En attente d’arrêt',
            'COMMAND_SENT' => 'Commande envoyée / confirmation attendue',
            'CUT_OFF' => 'Coupure confirmée',

            'CANCELLED_PAID' => 'Annulé car payé',
            'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardonné avant coupure',

            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'Rallumage demandé après pardon',
            'REACTIVATED_AFTER_FORGIVENESS' => 'Rallumé après pardon',
            'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'Échec rallumage après pardon',

            'FAILED' => 'Échec final',
        ];
    }

    /**
     * Libellé métier lisible pour l’affichage.
     */
    public function getStatusLabel(?string $status): string
    {
        return match ((string) $status) {
            'PENDING' => 'En attente de traitement',
            'WAITING_STOP' => 'En attente d’arrêt',
            'COMMAND_SENT' => 'Commande envoyée',
            'CUT_OFF' => 'Coupure confirmée',

            'CANCELLED_PAID' => 'Annulé / payé',
            'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardonné avant coupure',

            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'Rallumage demandé après pardon',
            'REACTIVATED_AFTER_FORGIVENESS' => 'Rallumé après pardon',
            'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'Échec rallumage après pardon',

            'FAILED' => 'Échec final',

            default => (string) ($status ?: 'Inconnu'),
        };
    }

    /**
     * Tonalité visuelle métier pour l’interface.
     */
    public function getStatusTone(?string $status): string
    {
        return match ((string) $status) {
            'PENDING' => 'pending',
            'WAITING_STOP' => 'waiting',
            'COMMAND_SENT' => 'sent',
            'CUT_OFF' => 'cut',

            'CANCELLED_PAID',
            'CANCELLED_FORGIVEN_BEFORE_CUT' => 'cancelled',

            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'sent',
            'REACTIVATED_AFTER_FORGIVENESS' => 'success',

            'REACTIVATION_FAILED_AFTER_FORGIVENESS',
            'FAILED' => 'failed',

            default => 'pending',
        };
    }

    private function applyStatusFilter(Builder $query, array $filters): void
    {
        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $query->where('status', $status);
        }
    }

    /**
     * Recherche métier enrichie.
     */
    private function applySearchFilter(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '') {
            return;
        }

        $query->where(function (Builder $q) use ($search) {
            $q->where('contract_id', 'like', '%' . $search . '%')
                ->orWhere('lease_id', 'like', '%' . $search . '%')
                ->orWhere('status', 'like', '%' . $search . '%')
                ->orWhere('reason', 'like', '%' . $search . '%')
                ->orWhere('notes', 'like', '%' . $search . '%')
                ->orWhere('ignition_state', 'like', '%' . $search . '%')
                ->orWhereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payment_status_snapshot, '$.date_echeance')) like ?",
                    ['%' . $search . '%']
                )
                ->orWhereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payment_status_snapshot, '$.statut')) like ?",
                    ['%' . $search . '%']
                )
                ->orWhereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payment_status_snapshot, '$.chauffeur_nom_complet')) like ?",
                    ['%' . $search . '%']
                )
                ->orWhereRaw(
                    "JSON_UNQUOTE(JSON_EXTRACT(payment_status_snapshot, '$.reste_a_payer')) like ?",
                    ['%' . $search . '%']
                )
                ->orWhereHas('vehicle', function (Builder $vehicleQuery) use ($search) {
                    $vehicleQuery->where('immatriculation', 'like', '%' . $search . '%')
                        ->orWhere('mac_id_gps', 'like', '%' . $search . '%');
                });
        });
    }

    private function applyPeriodFilter(Builder $query, array $filters): void
    {
        $period = trim((string) ($filters['period'] ?? ''));
        $timezone = config('app.timezone', 'Africa/Douala');

        if ($period === '') {
            return;
        }

        $now = Carbon::now($timezone);

        switch ($period) {
            case 'today':
                $query->whereBetween('scheduled_for', [
                    $now->copy()->startOfDay(),
                    $now->copy()->endOfDay(),
                ]);
                break;

            case 'yesterday':
                $query->whereBetween('scheduled_for', [
                    $now->copy()->subDay()->startOfDay(),
                    $now->copy()->subDay()->endOfDay(),
                ]);
                break;

            case 'this_week':
                $query->whereBetween('scheduled_for', [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek(),
                ]);
                break;

            case 'this_month':
                $query->whereBetween('scheduled_for', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth(),
                ]);
                break;

            case 'this_year':
                $query->whereBetween('scheduled_for', [
                    $now->copy()->startOfYear(),
                    $now->copy()->endOfYear(),
                ]);
                break;

            case 'specific_date':
                $specificDate = trim((string) ($filters['specific_date'] ?? ''));

                if ($specificDate !== '') {
                    $date = Carbon::parse($specificDate, $timezone);

                    $query->whereBetween('scheduled_for', [
                        $date->copy()->startOfDay(),
                        $date->copy()->endOfDay(),
                    ]);
                }
                break;

            case 'range':
                $dateFrom = trim((string) ($filters['date_from'] ?? ''));
                $dateTo = trim((string) ($filters['date_to'] ?? ''));

                if ($dateFrom !== '') {
                    $query->where(
                        'scheduled_for',
                        '>=',
                        Carbon::parse($dateFrom, $timezone)->startOfDay()
                    );
                }

                if ($dateTo !== '') {
                    $query->where(
                        'scheduled_for',
                        '<=',
                        Carbon::parse($dateTo, $timezone)->endOfDay()
                    );
                }
                break;
        }
    }
}