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
        /**
         * COMMAND_SENT_UNCONFIRMED / REACTIVATION_SENT_UNCONFIRMED : la
         * commande a bien été envoyée au véhicule, seule sa confirmation
         * manque après la fenêtre de vérification — volontairement classé
         * "en attente", jamais "échec" (voir LeaseCutoffQueueProcessorService).
         */
        'pending' => [
            'PENDING', 'WAITING_STOP', 'COMMAND_SENT', 'COMMAND_SENT_UNCONFIRMED',
            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS', 'REACTIVATION_SENT_UNCONFIRMED',
        ],
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
        'REACTIVATION_SENT_UNCONFIRMED',
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

        $query = $this->automaticBaseQuery($partner, $filters)->with(['vehicle', 'contractLink', 'events']);

        return $query->orderByDesc('scheduled_for')
            ->limit(500)
            ->get()
            ->map(function (LeaseCutoffHistory $h) {
                $timestamp = $h->cutoff_executed_at ?? $h->cutoff_requested_at ?? $h->scheduled_for ?? $h->detected_at;
                $direction = in_array($h->status, self::AUTO_ALLUMAGE_STATUSES, true) ? 'ALLUMAGE' : 'COUPURE';

                $snapshot = is_array($h->payment_status_snapshot) ? $h->payment_status_snapshot : [];
                $contractTypeLabel = $this->resolveContractTypeLabel($h);

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
                    // Détail lease — pour ne pas devoir naviguer vers la page "Historique Coupure" pour ces infos.
                    'contract_type_label' => $contractTypeLabel,
                    'contract_kind_label' => $h->contract_kind === 'SUB' ? 'Sous-contrat' : 'Contrat principal',
                    'lease_due_date' => $h->lease_date_echeance,
                    'driver_name' => $snapshot['chauffeur_nom_complet'] ?? null,
                    'montant_du' => $snapshot['reste_a_payer'] ?? null,
                    'speed_at_check' => $h->speed_at_check,
                    'ignition_state' => $h->ignition_state,
                    'cmd_no' => null,
                    // Horodatage complet du cycle : quand la non-conformité a été
                    // détectée, planifiée, commandée puis confirmée.
                    'detected_at' => $h->detected_at,
                    'scheduled_for' => $h->scheduled_for,
                    'cutoff_requested_at' => $h->cutoff_requested_at,
                    'cutoff_executed_at' => $h->cutoff_executed_at,
                    // Journal complet du cycle (une ligne par vérification réelle : offline,
                    // en mouvement, commande envoyée...) — pour expliquer un écart entre
                    // l'heure planifiée et l'heure réelle de coupure sans changer de page.
                    'events' => $h->events,
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
            ->with([
                'vehicule',
                'vehicule.chauffeurActuelPartner.chauffeur:id,nom,prenom',
                'user:id,nom,prenom',
                'employe:id,nom,prenom',
            ]);

        return $query->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(function (Commande $c) {
                $direction = $c->type_commande === 'ALLUMAGE' ? 'ALLUMAGE' : 'COUPURE';
                $actor = $c->user
                    ? trim(($c->user->prenom ?? '') . ' ' . ($c->user->nom ?? ''))
                    : ($c->employe ? trim(($c->employe->prenom ?? '') . ' ' . ($c->employe->nom ?? '')) . ' (support)' : 'Utilisateur inconnu');

                [$statusGroup, $tone, $actionLabel] = $this->manualStatusPresentation($c, $direction);

                $chauffeur = $c->vehicule?->chauffeurActuelPartner?->chauffeur;

                return [
                    'timestamp' => $c->created_at,
                    'source' => 'MANUEL',
                    'direction' => $direction,
                    'status_group' => $statusGroup,
                    'vehicle_label' => $c->vehicule->immatriculation ?? '—',
                    'vehicle_sub' => trim(($c->vehicule->marque ?? '') . ' ' . ($c->vehicule->model ?? '')),
                    'actor' => $actor !== '' ? $actor : 'Utilisateur inconnu',
                    'action_label' => $actionLabel,
                    'tone' => $tone,
                    'reason' => $c->notes,
                    // Pas de lease pour une commande manuelle : seul le chauffeur actuel du véhicule est pertinent.
                    'contract_type_label' => null,
                    'contract_kind_label' => null,
                    'lease_due_date' => null,
                    'driver_name' => $chauffeur ? trim(($chauffeur->prenom ?? '') . ' ' . ($chauffeur->nom ?? '')) : null,
                    'montant_du' => null,
                    'speed_at_check' => null,
                    'ignition_state' => null,
                    'cmd_no' => $c->CmdNo,
                ];
            });
    }

    /**
     * type_contrat_label (colonne plate, sur l'historique ou le lien de
     * contrat) reste souvent bloqué sur le replai technique "Type #N" — bug
     * de sync corrigé le 19/08/2026 (LeaseContractLinkService::extractTypeContractLabel
     * ne vérifiait jamais la clé plate "type_contrat_libelle" renvoyée par
     * l'API Recouvrement), mais les lignes déjà en base restent affectées
     * tant qu'elles ne sont pas resynchronisées. On retombe donc sur
     * last_snapshot du lien de contrat, qui porte le vrai libellé même
     * quand la colonne plate ne l'a jamais eu — même logique que
     * LeaseCutoffPlannerService::cleanContractTypeLabel().
     */
    private function resolveContractTypeLabel(LeaseCutoffHistory $h): string
    {
        $fallback = $h->contract_kind === 'SUB' ? 'Sous-contrat' : 'Contrat principal';

        $candidates = [
            data_get(optional($h->contractLink)->last_snapshot, 'type_contrat_libelle'),
            data_get(optional($h->contractLink)->last_snapshot, 'type_contrat_label'),
            data_get(optional($h->contractLink)->last_snapshot, 'type_contrat.libelle'),
            data_get(optional($h->contractLink)->last_snapshot, 'type_contrat.label'),
            $h->type_contrat_label,
            optional($h->contractLink)->type_contrat_label,
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! preg_match('/^(type|contrat|sous-contrat)\s*#?\d+$/i', $candidate)) {
                return $candidate;
            }
        }

        return $fallback;
    }

    /**
     * Statut affiché d'une commande manuelle, basé sur la confirmation
     * réelle par l'état moteur (ManualCommandConfirmationService), pas
     * seulement l'accusé de réception du provider.
     *
     * @return array{0: string, 1: string, 2: string} [status_group, tone, action_label]
     */
    private function manualStatusPresentation(Commande $c, string $direction): array
    {
        $labels = $direction === 'COUPURE'
            ? [
                'CONFIRMED' => 'Coupure manuelle confirmée',
                'UNCONFIRMED' => 'Coupure manuelle envoyée, non confirmée par le GPS',
                'QUEUED_OFFLINE' => 'Coupure manuelle en file d’attente (véhicule hors-ligne)',
                'SENT' => 'Coupure manuelle envoyée, en attente de confirmation',
            ]
            : [
                'CONFIRMED' => 'Rallumage manuel confirmé',
                'UNCONFIRMED' => 'Rallumage manuel envoyé, non confirmé par le GPS',
                'QUEUED_OFFLINE' => 'Rallumage manuel en file d’attente (véhicule hors-ligne)',
                'SENT' => 'Rallumage manuel envoyé, en attente de confirmation',
            ];

        return match ($c->confirmation_status) {
            'CONFIRMED' => ['success', 'success', $labels['CONFIRMED']],
            'UNCONFIRMED' => ['pending', 'pending', $labels['UNCONFIRMED']],
            default => $c->status === 'QUEUED_OFFLINE'
                ? ['pending', 'waiting', $labels['QUEUED_OFFLINE']]
                : ['pending', 'sent', $labels['SENT']],
        };
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

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('contract_id', 'like', "%{$search}%")
                    ->orWhere('lease_id', 'like', "%{$search}%")
                    ->orWhere('type_contrat_label', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payment_status_snapshot, '$.chauffeur_nom_complet')) like ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payment_status_snapshot, '$.date_echeance')) like ?", ["%{$search}%"])
                    ->orWhereHas('vehicle', function (Builder $vq) use ($search) {
                        $vq->where('immatriculation', 'like', "%{$search}%")
                            ->orWhere('mac_id_gps', 'like', "%{$search}%");
                    })
                    ->orWhereHas('contractLink', function (Builder $cq) use ($search) {
                        $cq->where('type_contrat_label', 'like', "%{$search}%");
                    });
            });
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

        /**
         * Statut basé sur confirmation_status (ManualCommandConfirmationService),
         * pas sur l'accusé de réception provider seul : "success" = vraiment
         * confirmé par l'état moteur ; "pending" couvre aussi bien "en cours
         * de vérification" que "jamais confirmé" (UNCONFIRMED), puisque
         * cette dernière n'est volontairement jamais un échec.
         */
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'pending') {
            $query->where(fn ($q) => $q->whereNull('confirmation_status')->orWhere('confirmation_status', 'UNCONFIRMED'));
        } elseif ($status === 'success') {
            $query->where('confirmation_status', 'CONFIRMED');
        } elseif (in_array($status, ['failed', 'cancelled'], true)) {
            // Aucune commande manuelle échouée/annulée n'est journalisée aujourd'hui.
            $query->whereRaw('1 = 0');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhere('CmdNo', 'like', "%{$search}%")
                    ->orWhereHas('vehicule', function (Builder $vq) use ($search) {
                        $vq->where('immatriculation', 'like', "%{$search}%")
                            ->orWhere('mac_id_gps', 'like', "%{$search}%");
                    })
                    ->orWhereHas('vehicule.chauffeurActuelPartner.chauffeur', function (Builder $cq) use ($search) {
                        $cq->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%");
                    })
                    ->orWhereHas('employe', function (Builder $eq) use ($search) {
                        $eq->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%");
                    });
            });
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
