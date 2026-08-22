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
            ->with(['vehicle', 'contractRule', 'contractLink', 'events'])
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
            'cancelled_unverified' => (clone $filteredQuery)->where('status', 'CANCELLED_UNVERIFIED')->count(),
            'cancelled_rule_missing' => (clone $filteredQuery)->where('status', 'CANCELLED_RULE_MISSING')->count(),
            'cancelled_rule_disabled' => (clone $filteredQuery)->where('status', 'CANCELLED_RULE_DISABLED')->count(),
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

            'cancelled_day_expired' => (clone $filteredQuery)
                ->where('status', 'CANCELLED_DAY_EXPIRED')
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
            'CANCELLED_UNVERIFIED' => 'Annulé : à vérifier (sans preuve de paiement)',
            'CANCELLED_RULE_MISSING' => 'Annulé : règle absente',
            'CANCELLED_RULE_DISABLED' => 'Annulé : règle désactivée ou incomplète',
            'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardonné avant coupure',

            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'Rallumage demandé après pardon',
            'REACTIVATED_AFTER_FORGIVENESS' => 'Rallumé après pardon',
            'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'Échec rallumage après pardon',
            'REACTIVATION_SENT_UNCONFIRMED' => 'Rallumage envoyé, non confirmé',

            'CANCELLED_DAY_EXPIRED' => 'Échéance expirée (jour suivant, aucune coupure rétroactive)',

            'FAILED' => 'Échec final',
            'COMMAND_SENT_UNCONFIRMED' => 'Commande envoyée, non confirmée',
        ];
    }

    /**
     * Libellé métier lisible pour l’affichage.
     */
    /**
     * @param string|null $ignitionState état moteur relevé au dernier contrôle
     *                                   (colonne ignition_state de l'historique).
     *                                   Sert uniquement à préciser POURQUOI un
     *                                   dossier est en attente — voir ci-dessous.
     */
    public function getStatusLabel(?string $status, ?string $ignitionState = null): string
    {
        /**
         * WAITING_STOP recouvre quatre situations très différentes (GPS hors
         * ligne, véhicule en circulation, mouvement non confirmé, état
         * illisible) que markWaiting() écrase toutes sous un seul statut. La
         * raison précise n'était donc lisible qu'en dépliant le journal.
         * L'information existe pourtant déjà sur la ligne : ignition_state
         * porte l'état moteur du dernier contrôle. On s'en sert pour dire
         * explicitement que la coupure est DÉCIDÉE mais que rien n'a encore
         * été envoyé au véhicule — et pourquoi. Ajouté le 22/08/2026.
         */
        if ((string) $status === 'WAITING_STOP') {
            return match ((string) $ignitionState) {
                'OFFLINE' => 'Programmée — non envoyée (GPS hors ligne)',
                'ONLINE_MOVING' => 'Programmée — non envoyée (véhicule en circulation)',
                'ONLINE_STOPPED' => 'Programmée — non envoyée (mouvement non confirmé)',
                default => 'Programmée — non envoyée (état du véhicule non vérifiable)',
            };
        }

        return match ((string) $status) {
            'PENDING' => 'En attente de traitement',
            'WAITING_STOP' => 'En attente d’arrêt',
            'COMMAND_SENT' => 'Commande envoyée',
            'CUT_OFF' => 'Coupure confirmée',

            'CANCELLED_PAID' => 'Annulé / payé',
            'CANCELLED_UNVERIFIED' => 'Annulé : à vérifier',
            'CANCELLED_RULE_MISSING' => 'Annulé : règle absente',
            'CANCELLED_RULE_DISABLED' => 'Annulé : règle désactivée ou incomplète',
            'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardonné avant coupure',

            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'Rallumage demandé après pardon',
            'REACTIVATED_AFTER_FORGIVENESS' => 'Rallumé après pardon',
            'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'Échec rallumage après pardon',
            'REACTIVATION_SENT_UNCONFIRMED' => 'Rallumage envoyé, non confirmé',

            'CANCELLED_DAY_EXPIRED' => 'Échéance expirée (jour suivant)',

            'FAILED' => 'Échec final',
            'COMMAND_SENT_UNCONFIRMED' => 'Commande envoyée, non confirmée',

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
            'CANCELLED_UNVERIFIED',
            'CANCELLED_RULE_MISSING',
            'CANCELLED_RULE_DISABLED',
            'CANCELLED_FORGIVEN_BEFORE_CUT',
            'CANCELLED_DAY_EXPIRED' => 'cancelled',

            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'sent',
            'REACTIVATED_AFTER_FORGIVENESS' => 'success',
            'REACTIVATION_SENT_UNCONFIRMED' => 'pending',

            'REACTIVATION_FAILED_AFTER_FORGIVENESS',
            'FAILED' => 'failed',
            'COMMAND_SENT_UNCONFIRMED' => 'pending',

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