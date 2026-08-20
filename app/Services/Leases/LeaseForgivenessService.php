<?php

namespace App\Services\Leases;

use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffQueue;
use App\Models\User;
use App\Models\Voiture;
use App\Services\Gps\GpsCommandDispatcherService;
use App\Services\GpsControlService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LeaseForgivenessService
{
    private const DEFAULT_CONFIRM_MAX_CHECKS = 6;
    private const DEFAULT_CONFIRM_DELAY_SECONDS = 20;

    /** Statuts d'historique d'un contrat frère qui justifient encore que le véhicule reste coupé. */
    private const SIBLING_STILL_BLOCKING_STATUSES = ['PENDING', 'WAITING_STOP', 'COMMAND_SENT', 'CUT_OFF'];

    public function __construct(
        private readonly LeaseApiClientService $leaseApi,
        private readonly GpsControlService $gps,
        private readonly GpsCommandDispatcherService $dispatcher
    ) {
    }

    /**
     * Pardon intelligent d’un lease.
     *
     * Cas 1 : lease non payé, véhicule pas encore coupé.
     * - annule la queue ;
     * - écrit CANCELLED_FORGIVEN_BEFORE_CUT ;
     * - aucune commande GPS.
     *
     * Cas 2 : lease payé en retard ou non payé, véhicule déjà coupé.
     * - vérifie d'abord qu'aucun contrat/sous-contrat FRÈRE sur le même véhicule
     *   ne justifie encore la coupure (sinon on refuse le rallumage) ;
     * - envoie une commande GPS de rallumage ;
     * - place la queue en confirmation (comme pour la coupure) : le statut final
     *   REACTIVATED_AFTER_FORGIVENESS n'est écrit qu'après confirmation réelle de
     *   l'état moteur par LeaseCutoffQueueProcessorService, pas dès l'envoi.
     *
     * Dans tous les cas :
     * - on trace qui a pardonné, quand, pourquoi ;
     * - le message reason nomme toujours le véhicule (immatriculation), le
     *   chauffeur assigné et l'employé qui a accordé le pardon.
     */
    public function forgive(User $actor, int $leaseId, ?string $reason = null, bool $cascade = false): array
    {
        $partnerId = $this->resolvePartnerId($actor);
        $forgivenByName = $this->actorLabel($actor);

        Log::info('[LEASE_FORGIVENESS] Début pardon', [
            'actor_id' => $actor->id,
            'actor_name' => $forgivenByName,
            'partner_id' => $partnerId,
            'lease_id' => $leaseId,
            'reason' => $reason,
        ]);

        $lease = $this->findLeaseFromApi($leaseId);

        if (! $lease) {
            throw new RuntimeException("Lease introuvable côté recouvrement.");
        }

        $contractId = (int) ($lease['contrat_id'] ?? $lease['contrat'] ?? 0);

        if ($contractId <= 0) {
            throw new RuntimeException("Contrat introuvable pour ce lease.");
        }

        /**
         * Le lien local (lease_contract_links) connaît déjà le véhicule associé
         * à ce contrat, y compris pour les sous-contrats (Caution, Téléphone,
         * Royal care, ...) dont la ligne /contrats/ ne renvoie pas toujours
         * d'immatriculation directement — elle est parfois seulement portée par
         * le contrat parent. On ne retombe sur l'API que si aucun lien n'existe
         * encore localement.
         */
        $contractLink = LeaseContractLink::query()
            ->with('driver')
            ->where('partner_id', $partnerId)
            ->where('source_contract_id', $contractId)
            ->where('status', '!=', 'DELETED')
            ->latest('id')
            ->first();

        $vehicle = $contractLink?->vehicle_id
            ? Voiture::query()->find($contractLink->vehicle_id)
            : null;

        if (! $vehicle) {
            $contracts = $this->leaseApi->fetchContractsIndexedById();
            $contract = $contracts[$contractId] ?? null;

            if (! is_array($contract)) {
                throw new RuntimeException("Contrat {$contractId} introuvable côté recouvrement.");
            }

            /**
             * Ce contrat n'a aucun LeaseContractLink local : on ne peut pas
             * vérifier son partner_id via ce chemin habituel. On vérifie donc le
             * partner_id renvoyé par Recouvrement lui-même avant d'agir sur un
             * véhicule résolu par immatriculation, pour ne jamais risquer
             * d'envoyer une commande sur le véhicule d'un autre partenaire.
             */
            $apiPartnerId = $this->extractPartnerIdFromContract($contract, $lease);
            if ($apiPartnerId !== null && $apiPartnerId !== $partnerId) {
                throw new RuntimeException("Le contrat {$contractId} n'appartient pas à ce partenaire.");
            }

            $parentContractId = $this->extractParentContractId($contract);
            $parentContract = $parentContractId > 0 ? ($contracts[$parentContractId] ?? null) : null;

            $immat = trim((string) (
                $contract['immatriculation']
                ?? $contract['vehicule']
                ?? ($parentContract['immatriculation'] ?? null)
                ?? ($parentContract['vehicule'] ?? null)
                ?? ''
            ));

            if ($immat === '') {
                throw new RuntimeException("Immatriculation introuvable pour le contrat {$contractId}.");
            }

            $vehicle = Voiture::query()
                ->where('immatriculation', $immat)
                ->first();

            if (! $vehicle) {
                throw new RuntimeException("Véhicule local introuvable pour l’immatriculation {$immat}.");
            }

            $contractLink = LeaseContractLink::query()
                ->with('driver')
                ->where('partner_id', $partnerId)
                ->where('source_contract_id', $contractId)
                ->where('vehicle_id', $vehicle->id)
                ->where('status', '!=', 'DELETED')
                ->latest('id')
                ->first();
        }

        $dueDate = $this->extractLeaseDueDate($lease);

        /**
         * Bug corrigé : la date_echeance d'un lease impayé peut avancer côté
         * Recouvrement entre le moment où le planificateur a créé la ligne
         * (avec la date du jour) et le moment où le partenaire clique sur
         * "Pardonner" (qui re-demande le lease à Recouvrement et peut donc
         * recevoir une date déjà avancée). Filtrer sur cette date fraîchement
         * relue faisait alors échouer la recherche de la VRAIE ligne encore
         * PENDING/WAITING_STOP/COMMAND_SENT : le pardon écrivait une ligne
         * orpheline sur une date différente, pendant que la ligne réelle
         * continuait son traitement et coupait quand même le véhicule. Le
         * lease_id (l'identifiant précis sur lequel le partenaire a cliqué)
         * suffit déjà à retrouver la bonne ligne sans dépendre de cette date
         * mouvante.
         */
        $queue = LeaseCutoffQueue::query()
            ->with(['history'])
            ->where('partner_id', $partnerId)
            ->where('lease_id', $leaseId)
            ->where('vehicle_id', $vehicle->id)
            ->when($contractLink, fn ($query) => $query->where('contract_link_id', $contractLink->id))
            ->orderByDesc('id')
            ->first();

        $history = $queue?->history;

        if (! $history) {
            $history = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('lease_id', $leaseId)
                ->where('vehicle_id', $vehicle->id)
                ->when($contractLink, fn ($query) => $query->where('contract_link_id', $contractLink->id))
                ->orderByDesc('id')
                ->first();
        }

        $engineState = $this->getEngineState($vehicle);
        /**
         * Une commande de coupure déjà envoyée doit être traitée comme un cas
         * après coupure pour le pardon : même si l'état moteur n'est pas encore
         * confirmé CUT, le boîtier GPS peut encore exécuter la coupure.
         * Dans ce cas, on déclenche donc une commande de rallumage.
         */
        $cutCommandAlreadySent = ($queue?->status === 'COMMAND_SENT')
            || ($history?->status === 'COMMAND_SENT');

        $wasAlreadyCut = $this->wasAlreadyCut($history, $engineState) || $cutCommandAlreadySent;
        $apiStatus = strtoupper((string) ($lease['statut'] ?? ''));

        Log::info('[LEASE_FORGIVENESS] Contexte détecté', [
            'partner_id' => $partnerId,
            'lease_id' => $leaseId,
            'contract_id' => $contractId,
            'vehicle_id' => $vehicle->id,
            'immatriculation' => $vehicle->immatriculation,
            'api_status' => $apiStatus,
            'engine_state' => $engineState,
            'was_already_cut' => $wasAlreadyCut,
            'cut_command_already_sent' => $cutCommandAlreadySent,
            'queue_id' => $queue?->id,
            'queue_status' => $queue?->status,
            'history_id' => $history?->id,
            'history_status' => $history?->status,
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
        ]);

        if ($wasAlreadyCut) {
            return $this->forgiveAfterCut(
                actor: $actor,
                forgivenByName: $forgivenByName,
                partnerId: $partnerId,
                vehicle: $vehicle,
                contractId: $contractId,
                leaseId: $leaseId,
                queue: $queue,
                history: $history,
                contractLink: $contractLink,
                dueDate: $dueDate,
                lease: $lease,
                engineState: $engineState,
                reason: $reason,
                cascade: $cascade
            );
        }

        return $this->forgiveBeforeCut(
            actor: $actor,
            forgivenByName: $forgivenByName,
            partnerId: $partnerId,
            vehicle: $vehicle,
            contractId: $contractId,
            leaseId: $leaseId,
            queue: $queue,
            history: $history,
            contractLink: $contractLink,
            dueDate: $dueDate,
            lease: $lease,
            engineState: $engineState,
            reason: $reason,
            cascade: $cascade
        );
    }

    private function forgiveBeforeCut(
        User $actor,
        string $forgivenByName,
        int $partnerId,
        Voiture $vehicle,
        int $contractId,
        int $leaseId,
        ?LeaseCutoffQueue $queue,
        ?LeaseCutoffHistory $history,
        ?LeaseContractLink $contractLink,
        ?string $dueDate,
        array $lease,
        string $engineState,
        ?string $reason,
        bool $cascade = false
    ): array {
        /**
         * Anomalie corrigée : un véhicule peut porter plusieurs contrats
         * indépendants (Moto, Téléphone, Royal care...). Pardonner CE lease
         * avant sa coupure ne protège pas le véhicule si un AUTRE contrat
         * (frère) est lui aussi en cours de coupure sur ce même véhicule :
         * sa propre queue continuerait normalement et couperait quand même
         * le véhicule, en contradiction avec le pardon qui vient d'être
         * accordé. On propose donc la même option "Pardonner tout" que pour
         * le rallumage après coupure.
         */
        $blockingSiblings = $this->findBlockingSiblingContracts($partnerId, $vehicle, $contractLink, $dueDate);

        /**
         * Bug corrigé : un contrat frère dont l'heure de coupure n'est pas
         * encore passée pour CETTE échéance n'a AUCUNE ligne locale (le
         * planificateur ne le voit qu'à l'heure dite) — $blockingSiblings
         * (local uniquement) le rendait donc invisible : ni le bouton
         * "Pardonner tout" ne s'affichait, ni un simple "Pardonner" n'était
         * bloqué, alors que ce frère allait couper le véhicule plus tard le
         * même jour. On vérifie donc aussi, en direct auprès de Recouvrement,
         * les frères pas encore planifiés mais déjà impayés pour la même
         * échéance ($dueDate, pas "aujourd'hui" — un lease en retard pardonné
         * plusieurs jours après son échéance doit chercher ses frères à SA
         * date, pas à la date du clic) — la même vérification que
         * suppressUnplannedSiblingsBeforeCut() effectue déjà au moment
         * d'exécuter la cascade, mais ici en lecture seule, pour décider s'il
         * FAUT proposer/exiger la cascade.
         */
        $unplannedSiblings = $this->findUnplannedNonPaidSiblingsForDate($partnerId, $vehicle, $contractLink, $dueDate);
        $allBlockingSiblings = $blockingSiblings->concat($unplannedSiblings);

        if ($allBlockingSiblings->isNotEmpty() && ! $cascade) {
            return $this->recordForgiveBeforeCutBlockedBySiblings(
                actor: $actor,
                forgivenByName: $forgivenByName,
                vehicle: $vehicle,
                reason: $reason,
                blockingSiblings: $allBlockingSiblings
            );
        }

        if ($allBlockingSiblings->isNotEmpty() && $cascade) {
            $needsGpsAction = $blockingSiblings->contains(
                fn (array $b) => in_array($b['history_status'], ['COMMAND_SENT', 'CUT_OFF'], true)
            );

            if ($needsGpsAction) {
                /**
                 * Au moins un contrat frère a déjà une commande de coupure en
                 * vol (ou confirmée coupée) : impossible de garantir sans
                 * ambiguïté que le véhicule ne sera pas/n'est pas coupé à
                 * cause de LUI, même si ce lease-ci n'a rien envoyé. On
                 * délègue donc entièrement au chemin "après coupure", qui
                 * envoie un rallumage et le CONFIRME réellement — la même
                 * rigueur qu'un pardon après coupure classique, plutôt que
                 * de prétendre à tort qu'annuler une simple queue suffit à
                 * garder le véhicule en marche.
                 */
                return $this->forgiveAfterCut(
                    actor: $actor,
                    forgivenByName: $forgivenByName,
                    partnerId: $partnerId,
                    vehicle: $vehicle,
                    contractId: $contractId,
                    leaseId: $leaseId,
                    queue: $queue,
                    history: $history,
                    contractLink: $contractLink,
                    dueDate: $dueDate,
                    lease: $lease,
                    engineState: $engineState,
                    reason: $reason,
                    cascade: true
                );
            }
        }

        $businessReason = $this->appendEmployeeReason(
            $this->reasonBeforeCut($vehicle, $contractLink, $forgivenByName),
            $reason,
            $forgivenByName
        );

        return DB::transaction(function () use (
            $actor,
            $forgivenByName,
            $partnerId,
            $vehicle,
            $contractId,
            $leaseId,
            $queue,
            $history,
            $contractLink,
            $dueDate,
            $lease,
            $engineState,
            $businessReason,
            $blockingSiblings,
            $cascade,
            $reason
        ) {
            [$queue, $history] = $this->lockCurrentQueueAndHistory($partnerId, $vehicle, $leaseId, $contractLink, $dueDate, $queue, $history);

            if ($queue && in_array($queue->status, ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'], true)) {
                $queue->update([
                    'status' => 'CANCELLED',
                    'last_checked_at' => now(),
                    'next_check_at' => null,
                ]);

                Log::info('[LEASE_FORGIVENESS] Queue annulée avant coupure', [
                    'queue_id' => $queue->id,
                    'lease_id' => $leaseId,
                    'vehicle_id' => $vehicle->id,
                    'forgiven_by_user_id' => $actor->id,
                    'forgiven_by_name' => $forgivenByName,
                ]);
            }

            /**
             * À ce stade, s'il reste des contrats frères bloquants, aucun
             * d'eux n'a de commande de coupure en vol (le cas contraire a
             * été délégué à forgiveAfterCut plus haut) : les annuler ne
             * nécessite donc aucune commande GPS.
             */
            $cascadedHistoryIds = [];
            if ($blockingSiblings->isNotEmpty() && $cascade) {
                $cascadedHistoryIds = $this->cascadePardonSiblingsBeforeCut(
                    actor: $actor,
                    forgivenByName: $forgivenByName,
                    partnerId: $partnerId,
                    vehicle: $vehicle,
                    blockingSiblings: $blockingSiblings,
                    reason: $reason
                );
            }

            /**
             * Bug corrigé : "Pardonner tout" ne protégeait que les contrats
             * frères ayant DÉJÀ une ligne locale (donc déjà vus par le
             * planificateur aujourd'hui). Un contrat dont l'heure de coupure
             * arrive plus tard dans la journée — ou que le cron n'a simplement
             * pas encore traité — restait invisible : "Pardonner tout" ne
             * pardonnait rien pour lui, et il se faisait couper normalement
             * plus tard, sans lien avec le pardon déjà accordé. On va donc
             * directement demander à Recouvrement si CHAQUE frère (même
             * chauffeur, même véhicule) a une échéance impayée aujourd'hui,
             * et si oui on pose la suppression tout de suite — le
             * planificateur la trouvera déjà en place quand il l'évaluera.
             */
            if ($cascade) {
                $alreadyCascadedLinkIds = $blockingSiblings
                    ->map(fn (array $b) => $b['contract_link']->id)
                    ->all();

                $cascadedHistoryIds = array_merge(
                    $cascadedHistoryIds,
                    $this->suppressUnplannedSiblingsBeforeCut(
                        partnerId: $partnerId,
                        vehicle: $vehicle,
                        excludeContractLink: $contractLink,
                        alreadyHandledContractLinkIds: $alreadyCascadedLinkIds,
                        actor: $actor,
                        forgivenByName: $forgivenByName,
                        reason: $reason,
                        dueDate: $dueDate
                    )
                );
            }

            $historyPayload = [
                'status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                'reason' => $businessReason,

                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now(),

                'ignition_state' => $engineState,
                'payment_status_snapshot' => $this->buildPaymentSnapshot(
                    lease: $lease,
                    customStatus: 'PARDONNE_AVANT_COUPURE',
                    reason: $businessReason,
                    actor: $actor,
                    forgivenByName: $forgivenByName
                ),
                'notes' => $this->prependPreviousContext(
                    $history,
                    $cascadedHistoryIds
                        ? sprintf(
                            'Pardon préventif : aucune commande de coupure ni de rallumage nécessaire. Le planner ne doit plus replanifier ce lease. Pardonné en cascade avec %d contrat(s) frère(s) également en cours de coupure sur ce véhicule.',
                            count($cascadedHistoryIds)
                        )
                        : 'Pardon préventif : aucune commande de coupure ni de rallumage nécessaire. Le planner ne doit plus replanifier ce lease.'
                ),
            ];

            $history = $this->createOrUpdateHistory(
                existing: $history,
                payload: $historyPayload,
                createExtra: [
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'lease_date_echeance' => $dueDate,
                    'contract_link_id' => $contractLink?->id,
                    'parent_contract_id' => $contractLink?->source_parent_contract_id,
                    'type_contrat_id' => $contractLink?->type_contrat_id,
                    'type_contrat_label' => $contractLink?->type_contrat_label,
                    'contract_kind' => $contractLink?->contract_kind ?? 'MAIN',
                    'trigger_label' => $contractLink?->displayTypeLabel(),
                    'trigger_payload' => [
                        'source_contract_id' => $contractId,
                        'lease_id' => $leaseId,
                        'date_echeance' => $dueDate,
                        'origin' => 'manual_forgiveness_before_cut',
                    ],
                    'contract_rule_id' => $queue?->contract_rule_id ?? $contractLink?->cutoffRule?->id,
                    'scheduled_for' => $queue?->scheduled_for ?? now(),
                    'detected_at' => now(),
                ],
                lookup: [
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'lease_id' => $leaseId,
                    'contract_link_id' => $contractLink?->id,
                    'lease_date_echeance' => $dueDate,
                ]
            );

            Log::info('[LEASE_FORGIVENESS] Pardon avant coupure enregistré', [
                'history_id' => $history->id,
                'lease_id' => $leaseId,
                'vehicle_id' => $vehicle->id,
                'status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                'reason' => $businessReason,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now()->toDateTimeString(),
            ]);

            return [
                'status' => 'forgiven_before_cut',
                'history_status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                'message' => $cascadedHistoryIds
                    ? 'Pardon préventif enregistré pour ce contrat et les contrats frères associés. Le véhicule ne sera pas coupé.'
                    : 'Pardon préventif enregistré. Le véhicule ne sera pas coupé pour ce lease.',
                'was_cut_before_forgiveness' => false,
                'reason' => $businessReason,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now()->toDateTimeString(),
                'cascaded_history_ids' => $cascadedHistoryIds,
            ];
        });
    }

    private function forgiveAfterCut(
        User $actor,
        string $forgivenByName,
        int $partnerId,
        Voiture $vehicle,
        int $contractId,
        int $leaseId,
        ?LeaseCutoffQueue $queue,
        ?LeaseCutoffHistory $history,
        ?LeaseContractLink $contractLink,
        ?string $dueDate,
        array $lease,
        string $engineState,
        ?string $reason,
        bool $cascade = false
    ): array {
        $macId = trim((string) $vehicle->mac_id_gps);

        if ($macId === '') {
            throw new RuntimeException("Impossible de rallumer : mac_id_gps vide pour le véhicule {$vehicle->immatriculation}.");
        }

        /**
         * Anomalie corrigée : un véhicule peut porter plusieurs contrats
         * indépendants (Moto, Téléphone, Royal care...), chacun avec sa propre
         * règle de coupure. Pardonner UN lease ne doit jamais rallumer un
         * véhicule qui reste légitimement coupé à cause d'un AUTRE contrat
         * encore impayé sur ce même véhicule — SAUF si l'employé a
         * explicitement choisi "Pardonner tout" ($cascade), auquel cas ces
         * contrats frères sont pardonnés avec le même acteur/raison avant de
         * poursuivre le rallumage.
         */
        $blockingSiblings = $this->findBlockingSiblingContracts($partnerId, $vehicle, $contractLink, $dueDate);

        if ($blockingSiblings->isNotEmpty() && ! $cascade) {
            return $this->recordReactivationBlockedBySiblings(
                actor: $actor,
                forgivenByName: $forgivenByName,
                partnerId: $partnerId,
                vehicle: $vehicle,
                contractId: $contractId,
                leaseId: $leaseId,
                queue: $queue,
                history: $history,
                contractLink: $contractLink,
                dueDate: $dueDate,
                lease: $lease,
                engineState: $engineState,
                reason: $reason,
                blockingSiblings: $blockingSiblings
            );
        }

        $cascadedHistoryIds = [];
        if ($blockingSiblings->isNotEmpty() && $cascade) {
            $cascadedHistoryIds = $this->cascadePardonSiblings(
                actor: $actor,
                forgivenByName: $forgivenByName,
                partnerId: $partnerId,
                vehicle: $vehicle,
                blockingSiblings: $blockingSiblings,
                reason: $reason
            );
        }

        $forgivenByNameForReason = $forgivenByName;
        $businessReasonBase = fn (string $body) => $this->appendEmployeeReason($body, $reason, $forgivenByNameForReason);

        Log::info('[LEASE_FORGIVENESS] Rallumage demandé après pardon', [
            'lease_id' => $leaseId,
            'vehicle_id' => $vehicle->id,
            'immatriculation' => $vehicle->immatriculation,
            'mac_id_gps' => $macId,
            'reason' => $reason,
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
        ]);

        $command = $this->dispatcher->dispatchRestoreByMacId($macId);
        $commandStatus = (string) ($command['status'] ?? 'FAILED');

        if ($commandStatus === 'FAILED') {
            $providerMessage = (string) ($command['message'] ?? $command['return_msg'] ?? 'raison non précisée par le provider');
            $businessReason = $businessReasonBase($this->reasonAfterCutRejectedByGps($vehicle, $contractLink, $forgivenByName, $providerMessage));

            return $this->recordForgiveAfterCutOutcome(
                actor: $actor,
                forgivenByName: $forgivenByName,
                partnerId: $partnerId,
                vehicle: $vehicle,
                contractId: $contractId,
                leaseId: $leaseId,
                queue: $queue,
                history: $history,
                contractLink: $contractLink,
                dueDate: $dueDate,
                lease: $lease,
                engineState: $engineState,
                historyStatus: 'REACTIVATION_FAILED_AFTER_FORGIVENESS',
                queueStatus: 'FAILED',
                queueNextCheckAt: null,
                businessReason: $businessReason,
                notes: 'Pardon après coupure : le GPS a rejeté la commande de rallumage. Aucune confirmation ne sera tentée.',
                commandResponse: $command,
                uiStatus: 'forgiven_reactivation_failed',
                message: 'Pardon enregistré, mais le GPS a refusé la commande de rallumage.',
                createQueueIfMissing: false,
                cascadedHistoryIds: $cascadedHistoryIds
            );
        }

        /**
         * Anomalie corrigée : avant, un statut SENT/PENDING_VERIFICATION du
         * provider était immédiatement écrit comme "rallumé" (terminal), sans
         * jamais vérifier que le moteur avait réellement redémarré — contraire
         * à la coupure, qui a toute une boucle de confirmation. On applique
         * maintenant la même rigueur : la queue reste active (COMMAND_SENT) et
         * c'est LeaseCutoffQueueProcessorService qui confirmera plus tard via
         * l'état moteur live, avant d'écrire REACTIVATED_AFTER_FORGIVENESS.
         */
        $delay = (int) env('LEASE_CUTOFF_CONFIRM_DELAY_SECONDS', self::DEFAULT_CONFIRM_DELAY_SECONDS);
        $maxChecks = (int) env('LEASE_CUTOFF_CONFIRM_MAX_CHECKS', self::DEFAULT_CONFIRM_MAX_CHECKS);
        $businessReason = $businessReasonBase($this->reasonAfterCutPending($vehicle, $contractLink, $forgivenByName, 1, $maxChecks));

        return $this->recordForgiveAfterCutOutcome(
            actor: $actor,
            forgivenByName: $forgivenByName,
            partnerId: $partnerId,
            vehicle: $vehicle,
            contractId: $contractId,
            leaseId: $leaseId,
            queue: $queue,
            history: $history,
            contractLink: $contractLink,
            dueDate: $dueDate,
            lease: $lease,
            engineState: $engineState,
            historyStatus: 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
            queueStatus: 'COMMAND_SENT',
            queueNextCheckAt: now()->addSeconds($delay),
            businessReason: $businessReason,
            notes: 'Pardon après coupure : le rallumage a été demandé. En attente de la confirmation que le moteur est bien remis en marche.',
            commandResponse: $command,
            uiStatus: 'forgiven_reactivation_pending',
            message: 'Pardon enregistré. Commande de rallumage envoyée, en attente de confirmation du moteur.',
            createQueueIfMissing: true,
            cascadedHistoryIds: $cascadedHistoryIds
        );
    }

    /**
     * Écrit l'issue d'un pardon après coupure (échec GPS, en attente de
     * confirmation, ou blocage par un contrat frère) dans l'historique et,
     * si besoin, dans la queue — en verrouillant les lignes concernées pour
     * éviter toute écriture concurrente avec le cron de planification/traitement.
     */
    private function recordForgiveAfterCutOutcome(
        User $actor,
        string $forgivenByName,
        int $partnerId,
        Voiture $vehicle,
        int $contractId,
        int $leaseId,
        ?LeaseCutoffQueue $queue,
        ?LeaseCutoffHistory $history,
        ?LeaseContractLink $contractLink,
        ?string $dueDate,
        array $lease,
        string $engineState,
        string $historyStatus,
        string $queueStatus,
        ?\Carbon\Carbon $queueNextCheckAt,
        string $businessReason,
        string $notes,
        array $commandResponse,
        string $uiStatus,
        string $message,
        bool $createQueueIfMissing,
        array $cascadedHistoryIds = [],
        array $extraReturn = []
    ): array {
        DB::transaction(function () use (
            $actor,
            $forgivenByName,
            $partnerId,
            $vehicle,
            $contractId,
            $leaseId,
            $queue,
            $history,
            $contractLink,
            $dueDate,
            $lease,
            $engineState,
            $historyStatus,
            $queueStatus,
            $queueNextCheckAt,
            $businessReason,
            $notes,
            $commandResponse,
            $createQueueIfMissing,
            $cascadedHistoryIds
        ) {
            [$queue, $history] = $this->lockCurrentQueueAndHistory($partnerId, $vehicle, $leaseId, $contractLink, $dueDate, $queue, $history);

            $historyPayload = [
                'status' => $historyStatus,
                'reason' => $businessReason,

                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now(),

                'ignition_state' => $engineState,
                'payment_status_snapshot' => $this->buildPaymentSnapshot(
                    lease: $lease,
                    customStatus: 'PARDONNE_APRES_COUPURE',
                    reason: $businessReason,
                    actor: $actor,
                    forgivenByName: $forgivenByName
                ),
                'command_response' => $commandResponse,
                'notes' => $this->prependPreviousContext($history, $notes),
            ];

            $history = $this->createOrUpdateHistory(
                existing: $history,
                payload: $historyPayload,
                createExtra: [
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'lease_date_echeance' => $dueDate,
                    'contract_link_id' => $contractLink?->id,
                    'parent_contract_id' => $contractLink?->source_parent_contract_id,
                    'type_contrat_id' => $contractLink?->type_contrat_id,
                    'type_contrat_label' => $contractLink?->type_contrat_label,
                    'contract_kind' => $contractLink?->contract_kind ?? 'MAIN',
                    'trigger_label' => $contractLink?->displayTypeLabel(),
                    'trigger_payload' => [
                        'source_contract_id' => $contractId,
                        'lease_id' => $leaseId,
                        'date_echeance' => $dueDate,
                        'origin' => 'manual_forgiveness_after_cut',
                    ],
                    'contract_rule_id' => $queue?->contract_rule_id ?? $contractLink?->cutoffRule?->id,
                    'scheduled_for' => $queue?->scheduled_for ?? now(),
                    'detected_at' => now(),
                ],
                lookup: [
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'lease_id' => $leaseId,
                    'contract_link_id' => $contractLink?->id,
                    'lease_date_echeance' => $dueDate,
                ]
            );

            if ($queue) {
                $queueUpdate = [
                    'status' => $queueStatus,
                    'last_checked_at' => now(),
                    'retry_count' => $queueStatus === 'COMMAND_SENT' ? $queue->retry_count + 1 : $queue->retry_count,
                    'next_check_at' => $queueNextCheckAt,
                    'history_id' => $history->id,
                ];

                if ($queueStatus === 'COMMAND_SENT' && ! empty($cascadedHistoryIds)) {
                    $queueUpdate['trigger_payload'] = array_merge(
                        (array) ($queue->trigger_payload ?? []),
                        ['cascaded_history_ids' => $cascadedHistoryIds]
                    );
                }

                $queue->update($queueUpdate);
            } elseif ($createQueueIfMissing) {
                /**
                 * Ce lease a été coupé en dehors du pipeline automatique (ou sa
                 * queue d'origine n'existe plus) : sans ligne de queue, personne
                 * ne revérifierait jamais la confirmation du rallumage. On en
                 * crée une minimale, dédiée à ce suivi.
                 */
                LeaseCutoffQueue::create([
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'lease_date_echeance' => $dueDate,
                    'contract_link_id' => $contractLink?->id,
                    'parent_contract_id' => $contractLink?->source_parent_contract_id,
                    'type_contrat_id' => $contractLink?->type_contrat_id,
                    'type_contrat_label' => $contractLink?->type_contrat_label,
                    'contract_kind' => $contractLink?->contract_kind ?? 'MAIN',
                    'trigger_label' => $contractLink?->displayTypeLabel(),
                    'trigger_payload' => array_filter([
                        'source_contract_id' => $contractId,
                        'lease_id' => $leaseId,
                        'date_echeance' => $dueDate,
                        'origin' => 'manual_forgiveness_after_cut_reactivation_tracking',
                        'cascaded_history_ids' => $cascadedHistoryIds ?: null,
                    ], fn ($v) => $v !== null),
                    'contract_rule_id' => $contractLink?->cutoffRule?->id,
                    'history_id' => $history->id,
                    'scheduled_for' => now(),
                    'status' => $queueStatus,
                    'retry_count' => 1,
                    'last_checked_at' => now(),
                    'next_check_at' => $queueNextCheckAt,
                ]);
            }

            /**
             * Si l'issue est déjà définitive (GPS refusé, blocage frère) et que
             * des contrats frères ont été pardonnés en cascade, on ne peut pas
             * compter sur une future confirmation de queue pour les clôturer :
             * on les finalise ici avec le même statut/raison.
             */
            if ($queueStatus !== 'COMMAND_SENT' && ! empty($cascadedHistoryIds)) {
                LeaseCutoffHistory::query()
                    ->whereIn('id', $cascadedHistoryIds)
                    ->update([
                        'status' => $historyStatus,
                        'notes' => DB::raw("CONCAT(COALESCE(notes, ''), '\nRallumage clôturé conjointement avec le contrat principal du pardon (échec).')"),
                    ]);
            }

            Log::info('[LEASE_FORGIVENESS] Pardon après coupure historisé', [
                'history_id' => $history->id,
                'lease_id' => $leaseId,
                'vehicle_id' => $vehicle->id,
                'history_status' => $historyStatus,
                'queue_status' => $queueStatus,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now()->toDateTimeString(),
            ]);
        });

        return array_merge([
            'status' => $uiStatus,
            'history_status' => $historyStatus,
            'message' => $message,
            'was_cut_before_forgiveness' => true,
            'reason' => $businessReason,
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
            'forgiven_at' => now()->toDateTimeString(),
            'command' => $commandResponse,
        ], $extraReturn);
    }

    private function recordReactivationBlockedBySiblings(
        User $actor,
        string $forgivenByName,
        int $partnerId,
        Voiture $vehicle,
        int $contractId,
        int $leaseId,
        ?LeaseCutoffQueue $queue,
        ?LeaseCutoffHistory $history,
        ?LeaseContractLink $contractLink,
        ?string $dueDate,
        array $lease,
        string $engineState,
        ?string $reason,
        Collection $blockingSiblings
    ): array {
        $businessReason = $this->appendEmployeeReason(
            $this->reasonAfterCutBlockedBySiblings($vehicle, $contractLink, $forgivenByName, $blockingSiblings),
            $reason,
            $forgivenByName
        );

        Log::warning('[LEASE_FORGIVENESS] Rallumage refusé : contrat(s) frère(s) toujours impayé(s) sur ce véhicule', [
            'lease_id' => $leaseId,
            'vehicle_id' => $vehicle->id,
            'immatriculation' => $vehicle->immatriculation,
            'contract_link_id' => $contractLink?->id,
            'blocking_siblings' => $blockingSiblings->map(fn (array $b) => [
                'contract_link_id' => $b['contract_link']->id,
                'label' => $b['label'],
                'history_status' => $b['history_status'],
            ])->all(),
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
        ]);

        return $this->recordForgiveAfterCutOutcome(
            actor: $actor,
            forgivenByName: $forgivenByName,
            partnerId: $partnerId,
            vehicle: $vehicle,
            contractId: $contractId,
            leaseId: $leaseId,
            queue: $queue,
            history: $history,
            contractLink: $contractLink,
            dueDate: $dueDate,
            lease: $lease,
            engineState: $engineState,
            historyStatus: 'REACTIVATION_FAILED_AFTER_FORGIVENESS',
            queueStatus: 'FAILED',
            queueNextCheckAt: null,
            businessReason: $businessReason,
            notes: sprintf(
                'Pardon après coupure : rallumage refusé sans envoi GPS. Contrat(s) frère(s) toujours en cause : %s.',
                $blockingSiblings->pluck('label')->unique()->implode(', ')
            ),
            commandResponse: [
                'source' => 'blocked_by_sibling_contracts',
                'blocking_contract_link_ids' => $blockingSiblings->map(fn (array $b) => $b['contract_link']->id)->all(),
            ],
            uiStatus: 'forgiven_reactivation_blocked_by_siblings',
            message: 'Pardon enregistré, mais le rallumage a été refusé : un autre contrat sur ce véhicule est toujours impayé.',
            createQueueIfMissing: false,
            extraReturn: [
                'blocking_siblings' => $blockingSiblings->map(fn (array $b) => [
                    'contract_link_id' => $b['contract_link']->id,
                    'label' => $b['label'],
                ])->values()->all(),
            ]
        );
    }

    /**
     * Aperçu, pour l'interface, des VRAIS sous-contrats de ce véhicule qui
     * bloqueraient encore un rallumage — utilisé pour afficher "Pardonner
     * tout" dès l'ouverture de la modale, avec le chauffeur et le VRAI nom
     * du type de contrat (Téléphone, Moto, ...) plutôt qu'un identifiant.
     * Purement local (lease_contract_links / lease_cutoff_histories) :
     * aucun appel à l'API Recouvrement, donc disponible même quand ce
     * dernier est indisponible.
     *
     * $dueDate : échéance du lease qu'on s'apprête à pardonner (PAS la date
     * du jour) — sans ce paramètre, un lease en retard de plusieurs jours
     * cherchait ses frères à la date du clic au lieu de sa vraie échéance,
     * et le bouton "Pardonner tout" pouvait ne jamais s'afficher pour un
     * frère qui bloquait pourtant bien CETTE échéance (bug corrigé le
     * 19/08/2026).
     */
    public function previewBlockingSiblings(int $partnerId, int $contractLinkId, ?string $dueDate = null): array
    {
        $contractLink = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('id', $contractLinkId)
            ->first();

        if (! $contractLink || ! $contractLink->vehicle_id) {
            return [];
        }

        $vehicle = Voiture::query()->find($contractLink->vehicle_id);

        if (! $vehicle) {
            return [];
        }

        return $this->findBlockingSiblingContracts($partnerId, $vehicle, $contractLink, $dueDate)
            ->map(function (array $blocker) {
                /** @var LeaseContractLink $siblingLink */
                $siblingLink = $blocker['contract_link'];
                $driver = $siblingLink->driver;

                $driverName = $driver ? trim((string) (
                    $driver->nom_complet
                    ?? $driver->full_name
                    ?? trim(($driver->prenom ?? '') . ' ' . ($driver->nom ?? ''))
                )) : '';

                return [
                    'contract_link_id' => $siblingLink->id,
                    'label' => $blocker['label'],
                    'history_status' => $blocker['history_status'],
                    'driver_name' => $driverName !== '' ? $driverName : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Règle d'affichage du projet (voir LeaseCutoffRuleService) : jamais
     * "Type #4", "Contrat #40", "#7", etc. Le nom réel du type de contrat
     * (ex. "Moto Pro", "Telephone Pro") est déjà connu localement : chaque
     * lease_contract_link garde, dans last_snapshot, une copie complète du
     * contrat Recouvrement au moment de sa dernière synchronisation — pas
     * besoin d'un nouvel appel API, ni de dépendre de sa disponibilité.
     */
    private function safeSiblingContractLabel(LeaseContractLink $link): string
    {
        $candidates = [
            data_get($link->last_snapshot, 'type_contrat_libelle'),
            data_get($link->last_snapshot, 'type_contrat_label'),
            data_get($link->last_snapshot, 'type_contrat.libelle'),
            data_get($link->last_snapshot, 'type_contrat.label'),
            $link->type_contrat_label,
            $link->displayTypeLabel(),
        ];

        foreach ($candidates as $candidate) {
            $label = trim((string) $candidate);

            if (! $this->labelLooksTechnical($label)) {
                return $label;
            }
        }

        return $link->contract_kind === LeaseContractLink::KIND_MAIN ? 'Contrat principal' : 'Sous-contrat';
    }

    private function labelLooksTechnical(?string $label): bool
    {
        $label = trim((string) $label);

        if ($label === '') {
            return true;
        }

        return (bool) preg_match('/^(type|contrat|sous-contrat)\s*#?\d+$/i', $label)
            || (bool) preg_match('/^#\d+$/', $label)
            || (bool) preg_match('/^\d+$/', $label)
            || (bool) preg_match('/^CTR[-_ ]?\d+$/i', $label)
            || (bool) preg_match('/^parent\s*#?\d+$/i', $label);
    }

    /**
     * Retourne les contrats/sous-contrats frères du MÊME chauffeur sur le
     * MÊME véhicule dont la dernière décision de coupure locale est toujours
     * active (planifiée, en attente, commande envoyée ou confirmée coupée)
     * — c'est-à-dire toujours non résolue par un paiement ou un pardon. Tant
     * que l'un d'eux existe, envoyer une commande de rallumage serait
     * contredire une coupure encore légitime.
     *
     * Un même véhicule peut porter des lease_contract_links de chauffeurs
     * DIFFÉRENTS (véhicule réattribué, ancien contrat non nettoyé, etc.).
     * On ne doit jamais pardonner la dette d'un autre chauffeur au passage :
     * on limite donc les "frères" au même chauffeur que le contrat qu'on
     * est en train de pardonner. Si ce contrat n'a pas de chauffeur assigné,
     * on retombe sur l'ancien comportement (tout le véhicule), faute de
     * mieux pour le rattacher.
     *
     * $dueDate : échéance du lease qu'on est en train de pardonner (PAS la
     * date du jour). Bug corrigé le 19/08/2026 : cette méthode comparait
     * auparavant contre "aujourd'hui" (now()), donc pardonner un lease en
     * retard de plusieurs jours cherchait ses frères à la mauvaise date et
     * les laissait couper le véhicule quand même — on compare maintenant à
     * l'échéance réelle du lease cliqué.
     */
    private function findBlockingSiblingContracts(int $partnerId, Voiture $vehicle, ?LeaseContractLink $contractLink, ?string $dueDate = null): Collection
    {
        $excludeContractLinkId = $contractLink?->id ?? 0;
        $driverId = $contractLink?->driver_id;
        $targetDate = $dueDate ?: now(config('app.timezone', 'Africa/Douala'))->toDateString();

        $siblings = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('vehicle_id', $vehicle->id)
            ->where('id', '!=', $excludeContractLinkId)
            ->when($driverId, fn ($query) => $query->where('driver_id', $driverId))
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->get();

        $blocking = collect();

        foreach ($siblings as $sibling) {
            /**
             * Chaque échéance est traitée strictement indépendamment des
             * échéances précédentes : une échéance frère d'un autre jour —
             * même jamais pardonnée, même confirmée CUT_OFF — ne peut plus
             * justifier de bloquer CETTE échéance-ci. On ne regarde donc que
             * la dernière décision de la même date d'échéance ($targetDate)
             * pour ce contrat frère, jamais une décision antérieure ou
             * postérieure.
             */
            $latestHistory = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('contract_link_id', $sibling->id)
                ->whereDate('lease_date_echeance', $targetDate)
                ->orderByDesc('id')
                ->first();

            if ($latestHistory && in_array($latestHistory->status, self::SIBLING_STILL_BLOCKING_STATUSES, true)) {
                $blocking->push([
                    'contract_link' => $sibling,
                    'label' => $this->safeSiblingContractLabel($sibling),
                    'history_status' => $latestHistory->status,
                ]);
            }
        }

        return $blocking;
    }

    /**
     * Contrats/sous-contrats frères (même chauffeur, même véhicule) qui n'ont
     * ENCORE aucune ligne locale pour CETTE échéance — donc invisibles pour
     * findBlockingSiblingContracts() — mais qui ont bien une échéance
     * impayée à la même date ($dueDate) côté Recouvrement : leur heure de
     * coupure n'est simplement pas encore passée. Sans cette vérification en
     * direct, un pardon "avant coupure" pouvait sembler réussir alors qu'un
     * autre contrat sur le même véhicule allait le recouper plus tard le
     * même jour — sans jamais proposer ni exiger "Pardonner tout".
     *
     * $dueDate : échéance du lease qu'on est en train de pardonner (PAS la
     * date du jour) — bug corrigé le 19/08/2026, même cause que
     * findBlockingSiblingContracts().
     *
     * Lecture seule : ne pose rien en base. L'exécution réelle de la
     * suppression reste dans suppressUnplannedSiblingsBeforeCut(), appelée
     * uniquement une fois la cascade confirmée par l'employé.
     */
    private function findUnplannedNonPaidSiblingsForDate(int $partnerId, Voiture $vehicle, ?LeaseContractLink $contractLink, ?string $dueDate = null): Collection
    {
        $excludeContractLinkId = $contractLink?->id ?? 0;
        $driverId = $contractLink?->driver_id;

        if (! $driverId) {
            return collect();
        }

        $targetDate = $dueDate ?: now(config('app.timezone', 'Africa/Douala'))->toDateString();

        $candidateLinks = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('vehicle_id', $vehicle->id)
            ->where('driver_id', $driverId)
            ->where('id', '!=', $excludeContractLinkId)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->get();

        if ($candidateLinks->isEmpty()) {
            return collect();
        }

        $alreadyPlannedLinkIds = LeaseCutoffHistory::query()
            ->where('partner_id', $partnerId)
            ->whereIn('contract_link_id', $candidateLinks->pluck('id'))
            ->whereDate('lease_date_echeance', $targetDate)
            ->pluck('contract_link_id')
            ->all();

        $unplannedLinks = $candidateLinks->reject(
            fn (LeaseContractLink $link) => in_array($link->id, $alreadyPlannedLinkIds, true)
        );

        if ($unplannedLinks->isEmpty()) {
            return collect();
        }

        try {
            $nonPaidLeasesForDate = $this->leaseApi->fetchNonPaidLeasesForDate($targetDate);
        } catch (\Throwable $e) {
            Log::warning('[LEASE_FORGIVENESS] Vérification des frères non planifiés impossible : API Recouvrement indisponible.', [
                'partner_id' => $partnerId,
                'vehicle_id' => $vehicle->id,
                'due_date' => $targetDate,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        $contractIdsWithNonPaidLeaseForDate = collect($nonPaidLeasesForDate)
            ->map(fn (array $lease) => $this->leaseApi->extractLeaseContractId($lease))
            ->filter(fn (int $id) => $id > 0)
            ->unique();

        $unplanned = collect();

        foreach ($unplannedLinks as $link) {
            if (! $contractIdsWithNonPaidLeaseForDate->contains((int) $link->source_contract_id)) {
                continue;
            }

            $unplanned->push([
                'contract_link' => $link,
                'label' => $this->safeSiblingContractLabel($link),
                'history_status' => 'UNPLANNED_NON_PAYE_TODAY',
            ]);
        }

        return $unplanned;
    }

    /**
     * Pardonne en cascade chaque contrat frère bloquant, à la demande explicite
     * de l'employé (option "Pardonner tout" dans la modale de confirmation).
     *
     * Un seul rallumage GPS sera envoyé pour le véhicule (dans forgiveAfterCut,
     * juste après cet appel) : on ne fait donc ici que clôturer la dette et
     * l'historique de chaque contrat frère, sans envoyer de commande GPS
     * individuelle. Le statut final (rallumé confirmé ou échec) sera reporté
     * sur ces lignes en même temps que celui du contrat d'origine.
     *
     * Retourne les ids des lignes d'historique pardonnées, pour que
     * l'appelant puisse les finaliser plus tard (confirmation ou échec).
     */
    private function cascadePardonSiblings(
        User $actor,
        string $forgivenByName,
        int $partnerId,
        Voiture $vehicle,
        Collection $blockingSiblings,
        ?string $reason
    ): array {
        $historyIds = [];

        foreach ($blockingSiblings as $blocker) {
            /** @var LeaseContractLink $siblingLink */
            $siblingLink = $blocker['contract_link'];

            $siblingHistory = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('contract_link_id', $siblingLink->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $siblingHistory) {
                continue;
            }

            $narrative = $this->appendEmployeeReason(
                sprintf(
                    'Le sous-contrat %s du véhicule %s a été pardonné en cascade avec un autre contrat sur ce même véhicule, à la demande de %s.',
                    $siblingLink->displayTypeLabel(),
                    $this->vehicleLabel($vehicle),
                    $forgivenByName
                ),
                $reason,
                $forgivenByName
            );

            $siblingHistory->update([
                'status' => 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
                'reason' => $narrative,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now(),
                'notes' => $this->prependPreviousContext(
                    $siblingHistory,
                    'Pardonné en cascade : le rallumage réel du véhicule est suivi et confirmé via le contrat d’origine du pardon.'
                ),
            ]);

            $siblingQueue = LeaseCutoffQueue::query()
                ->where('partner_id', $partnerId)
                ->where('history_id', $siblingHistory->id)
                ->lockForUpdate()
                ->first();

            if ($siblingQueue) {
                $siblingQueue->update([
                    'status' => 'PROCESSED',
                    'last_checked_at' => now(),
                    'next_check_at' => null,
                ]);
            }

            Log::info('[LEASE_FORGIVENESS] Contrat frère pardonné en cascade', [
                'sibling_contract_link_id' => $siblingLink->id,
                'sibling_history_id' => $siblingHistory->id,
                'vehicle_id' => $vehicle->id,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
            ]);

            $historyIds[] = $siblingHistory->id;
        }

        return $historyIds;
    }

    /**
     * Réponse retournée quand un pardon AVANT coupure est bloqué par un ou
     * plusieurs contrats frères toujours en cause sur le même véhicule
     * (aucune commande GPS en vol parmi eux — sinon voir forgiveAfterCut).
     *
     * N'écrit volontairement rien en base : rien n'a encore été décidé, la
     * réponse ne fait qu'informer le front pour proposer "Pardonner tout"
     * dans la même modale, sans laisser de trace d'un pardon qui n'a pas
     * eu lieu.
     */
    private function recordForgiveBeforeCutBlockedBySiblings(
        User $actor,
        string $forgivenByName,
        Voiture $vehicle,
        ?string $reason,
        Collection $blockingSiblings
    ): array {
        Log::warning('[LEASE_FORGIVENESS] Pardon avant coupure incomplet : contrat(s) frère(s) toujours en cause sur ce véhicule', [
            'vehicle_id' => $vehicle->id,
            'immatriculation' => $vehicle->immatriculation,
            'blocking_siblings' => $blockingSiblings->map(fn (array $b) => [
                'contract_link_id' => $b['contract_link']->id,
                'label' => $b['label'],
                'history_status' => $b['history_status'],
            ])->all(),
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
            'reason' => $reason,
        ]);

        return [
            'status' => 'forgiven_before_cut_blocked_by_siblings',
            'message' => 'Ce véhicule a un autre contrat toujours en cours de coupure : pardonner seulement celui-ci ne suffira pas à éviter la coupure du véhicule.',
            'blocking_siblings' => $blockingSiblings->map(fn (array $b) => [
                'contract_link_id' => $b['contract_link']->id,
                'label' => $b['label'],
            ])->values()->all(),
        ];
    }

    /**
     * Pardonne en cascade, AVANT coupure, chaque contrat frère bloquant dont
     * aucune commande GPS n'a été envoyée (PENDING/WAITING_STOP, ou un
     * CUT_OFF constaté obsolète puisque le moteur vient d'être vérifié en
     * direct comme non coupé) : une simple annulation de queue suffit, sans
     * aucune commande GPS individuelle — contrairement à cascadePardonSiblings()
     * (après coupure), qui doit suivre une confirmation de rallumage réel.
     */
    private function cascadePardonSiblingsBeforeCut(
        User $actor,
        string $forgivenByName,
        int $partnerId,
        Voiture $vehicle,
        Collection $blockingSiblings,
        ?string $reason
    ): array {
        $historyIds = [];

        foreach ($blockingSiblings as $blocker) {
            /** @var LeaseContractLink $siblingLink */
            $siblingLink = $blocker['contract_link'];

            $siblingHistory = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('contract_link_id', $siblingLink->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $siblingHistory) {
                continue;
            }

            $narrative = $this->appendEmployeeReason(
                sprintf(
                    'Le contrat %s du véhicule %s a été pardonné en cascade avant sa coupure, avec un autre contrat sur ce même véhicule, à la demande de %s.',
                    $siblingLink->displayTypeLabel(),
                    $this->vehicleLabel($vehicle),
                    $forgivenByName
                ),
                $reason,
                $forgivenByName
            );

            $siblingHistory->update([
                'status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                'reason' => $narrative,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now(),
                'notes' => $this->prependPreviousContext(
                    $siblingHistory,
                    'Pardon préventif en cascade : aucune commande GPS nécessaire, ce contrat n’avait pas encore de commande de coupure envoyée.'
                ),
            ]);

            $siblingQueue = LeaseCutoffQueue::query()
                ->where('partner_id', $partnerId)
                ->where('history_id', $siblingHistory->id)
                ->lockForUpdate()
                ->first();

            if ($siblingQueue) {
                $siblingQueue->update([
                    'status' => 'CANCELLED',
                    'last_checked_at' => now(),
                    'next_check_at' => null,
                ]);
            }

            Log::info('[LEASE_FORGIVENESS] Contrat frère pardonné en cascade avant coupure', [
                'sibling_contract_link_id' => $siblingLink->id,
                'sibling_history_id' => $siblingHistory->id,
                'vehicle_id' => $vehicle->id,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
            ]);

            $historyIds[] = $siblingHistory->id;
        }

        return $historyIds;
    }

    /**
     * Complète "Pardonner tout" (avant coupure) pour les contrats frères
     * (même chauffeur, même véhicule) que le planificateur n'a PAS encore
     * évalués pour cette échéance — donc sans aucune ligne locale à annuler.
     * On demande directement à Recouvrement si chacun a une échéance impayée
     * à la même date ($dueDate) ; si oui, on pose tout de suite une ligne
     * CANCELLED_FORGIVEN_BEFORE_CUT avec son vrai lease_id, pour que le
     * planificateur la trouve déjà en place quand il l'évaluera plus tard
     * (heure de coupure différente, ou simplement pas encore passée).
     *
     * $dueDate : échéance du lease qu'on est en train de pardonner (PAS la
     * date du jour) — bug corrigé le 19/08/2026, même cause que
     * findBlockingSiblingContracts().
     *
     * Si l'appel Recouvrement échoue, on ne bloque pas le reste du pardon :
     * ces contrats resteront simplement non couverts par la cascade, comme
     * avant ce correctif.
     */
    private function suppressUnplannedSiblingsBeforeCut(
        int $partnerId,
        Voiture $vehicle,
        ?LeaseContractLink $excludeContractLink,
        array $alreadyHandledContractLinkIds,
        User $actor,
        string $forgivenByName,
        ?string $reason,
        ?string $dueDate = null
    ): array {
        $driverId = $excludeContractLink?->driver_id;

        if (! $driverId) {
            return [];
        }

        $targetDate = $dueDate ?: now(config('app.timezone', 'Africa/Douala'))->toDateString();

        $candidateLinks = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('vehicle_id', $vehicle->id)
            ->where('driver_id', $driverId)
            ->where('id', '!=', $excludeContractLink->id)
            ->whereNotIn('id', $alreadyHandledContractLinkIds)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->get();

        if ($candidateLinks->isEmpty()) {
            return [];
        }

        $alreadyPlannedLinkIds = LeaseCutoffHistory::query()
            ->where('partner_id', $partnerId)
            ->whereIn('contract_link_id', $candidateLinks->pluck('id'))
            ->whereDate('lease_date_echeance', $targetDate)
            ->pluck('contract_link_id')
            ->all();

        $unplannedLinks = $candidateLinks->reject(
            fn (LeaseContractLink $link) => in_array($link->id, $alreadyPlannedLinkIds, true)
        );

        if ($unplannedLinks->isEmpty()) {
            return [];
        }

        try {
            $nonPaidLeasesForDate = $this->leaseApi->fetchNonPaidLeasesForDate($targetDate);
        } catch (\Throwable $e) {
            Log::warning('[LEASE_FORGIVENESS] Suppression préventive des frères non planifiés : API Recouvrement indisponible.', [
                'partner_id' => $partnerId,
                'vehicle_id' => $vehicle->id,
                'due_date' => $targetDate,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $leasesByContractId = collect($nonPaidLeasesForDate)
            ->keyBy(fn (array $lease) => $this->leaseApi->extractLeaseContractId($lease));

        $historyIds = [];

        foreach ($unplannedLinks as $link) {
            $lease = $leasesByContractId->get((int) $link->source_contract_id);

            if (! $lease) {
                continue;
            }

            $siblingLeaseId = $this->leaseApi->extractLeaseId($lease);

            if ($siblingLeaseId <= 0) {
                continue;
            }

            $narrative = $this->appendEmployeeReason(
                sprintf(
                    'Le contrat %s du véhicule %s a une échéance impayée le %s mais n\'avait pas encore été planifié : pardonné préventivement en cascade à la demande de %s.',
                    $link->displayTypeLabel(),
                    $this->vehicleLabel($vehicle),
                    $targetDate,
                    $forgivenByName
                ),
                $reason,
                $forgivenByName
            );

            $history = $this->createOrUpdateHistory(
                existing: null,
                payload: [
                    'status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                    'reason' => $narrative,
                    'forgiven_by_user_id' => $actor->id,
                    'forgiven_by_name' => $forgivenByName,
                    'forgiven_at' => now(),
                    'notes' => 'Pardon préventif en cascade : contrat pas encore évalué par le planificateur au moment du clic — suppression posée à l’avance pour cette échéance.',
                ],
                createExtra: [
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => (int) $link->source_contract_id,
                    'lease_id' => $siblingLeaseId,
                    'lease_date_echeance' => $targetDate,
                    'contract_link_id' => $link->id,
                    'parent_contract_id' => $link->source_parent_contract_id,
                    'type_contrat_id' => $link->type_contrat_id,
                    'type_contrat_label' => $link->type_contrat_label,
                    'contract_kind' => $link->contract_kind,
                    'trigger_label' => $link->displayTypeLabel(),
                    'trigger_payload' => [
                        'source_contract_id' => (int) $link->source_contract_id,
                        'lease_id' => $siblingLeaseId,
                        'date_echeance' => $targetDate,
                        'origin' => 'manual_forgiveness_cascade_unplanned_sibling',
                    ],
                    'contract_rule_id' => $link->cutoffRule?->id,
                    'scheduled_for' => now(),
                    'detected_at' => now(),
                ],
                lookup: [
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'lease_id' => $siblingLeaseId,
                    'contract_link_id' => $link->id,
                    'lease_date_echeance' => $targetDate,
                ]
            );

            Log::info('[LEASE_FORGIVENESS] Contrat frère jamais planifié pour cette échéance, pardonné préventivement par anticipation', [
                'sibling_contract_link_id' => $link->id,
                'sibling_lease_id' => $siblingLeaseId,
                'history_id' => $history->id,
                'vehicle_id' => $vehicle->id,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
            ]);

            $historyIds[] = $history->id;
        }

        return $historyIds;
    }

    /**
     * Relit et verrouille (FOR UPDATE) la queue/l'historique juste avant
     * d'écrire, pour éviter une course avec le cron de planification/traitement
     * qui pourrait modifier la même ligne au même instant. Les objets passés en
     * entrée peuvent être obsolètes (lus plus tôt, hors verrou) : on les
     * relit par id s'ils existent, sinon on refait la recherche par clé
     * naturelle au cas où une ligne aurait été créée entre-temps.
     */
    private function lockCurrentQueueAndHistory(
        int $partnerId,
        Voiture $vehicle,
        int $leaseId,
        ?LeaseContractLink $contractLink,
        ?string $dueDate,
        ?LeaseCutoffQueue $queue,
        ?LeaseCutoffHistory $history
    ): array {
        if ($history) {
            $history = LeaseCutoffHistory::query()->lockForUpdate()->find($history->id) ?? $history;
        } else {
            $history = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('vehicle_id', $vehicle->id)
                ->where('lease_id', $leaseId)
                ->when($contractLink, fn ($q) => $q->where('contract_link_id', $contractLink->id))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
        }

        if ($queue) {
            $queue = LeaseCutoffQueue::query()->lockForUpdate()->find($queue->id) ?? $queue;
        } else {
            $queue = LeaseCutoffQueue::query()
                ->where('partner_id', $partnerId)
                ->where('vehicle_id', $vehicle->id)
                ->where('lease_id', $leaseId)
                ->when($contractLink, fn ($q) => $q->where('contract_link_id', $contractLink->id))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
        }

        return [$queue, $history];
    }

    /**
     * Crée la ligne d'historique si elle n'existe pas encore, sinon la met à
     * jour. La contrainte unique en base est le dernier filet de sécurité :
     * si une création concurrente vient juste de committer entre notre verrou
     * et notre insertion, on rattrape en relisant puis en mettant à jour au
     * lieu d'échouer.
     */
    private function createOrUpdateHistory(?LeaseCutoffHistory $existing, array $payload, array $createExtra, array $lookup): LeaseCutoffHistory
    {
        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        try {
            return LeaseCutoffHistory::create(array_merge($payload, $createExtra));
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            $recovered = LeaseCutoffHistory::query()
                ->where('partner_id', $lookup['partner_id'])
                ->where('vehicle_id', $lookup['vehicle_id'])
                ->where('lease_id', $lookup['lease_id'])
                ->when($lookup['contract_link_id'], fn ($q) => $q->where('contract_link_id', $lookup['contract_link_id']))
                ->when($lookup['lease_date_echeance'], fn ($q) => $q->whereDate('lease_date_echeance', $lookup['lease_date_echeance']))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $recovered) {
                throw $e;
            }

            Log::info('[LEASE_FORGIVENESS] Création concurrente détectée (contrainte unique) : mise à jour de la ligne existante à la place.', [
                'history_id' => $recovered->id,
            ]);

            $recovered->update($payload);

            return $recovered;
        }
    }

    /**
     * Anomalie corrigée : avant, un pardon écrasait purement et simplement le
     * statut/la raison d'une ligne d'historique existante (ex. un échec de
     * coupure avec son diagnostic détaillé du boîtier), perdant définitivement
     * cette information. On la conserve maintenant en tête des notes.
     */
    private function prependPreviousContext(?LeaseCutoffHistory $history, string $newNotes): string
    {
        if (! $history || ! $history->status || $history->reason === null) {
            return $newNotes;
        }

        $previous = sprintf(
            '[Contexte précédent conservé — statut : %s ; raison : %s]',
            $history->status,
            $history->reason
        );

        return trim($previous . "\n" . $newNotes);
    }

    private function wasAlreadyCut(?LeaseCutoffHistory $history, string $engineState): bool
    {
        if ($engineState === 'CUT') {
            return true;
        }

        if (! $history) {
            return false;
        }

        return $history->status === 'CUT_OFF'
            || $history->cutoff_executed_at !== null;
    }

    private function getEngineState(Voiture $vehicle): string
    {
        $macId = trim((string) $vehicle->mac_id_gps);

        if ($macId === '') {
            return 'UNKNOWN';
        }

        try {
            $status = $this->gps->getEngineStatusFromLastLocation($macId);

            if (($status['success'] ?? false) !== true) {
                return 'UNKNOWN';
            }

            return (string) ($status['decoded']['engineState'] ?? 'UNKNOWN');
        } catch (\Throwable $e) {
            Log::warning('[LEASE_FORGIVENESS] Lecture état moteur impossible', [
                'vehicle_id' => $vehicle->id,
                'mac_id_gps' => $macId,
                'error' => $e->getMessage(),
            ]);

            return 'UNKNOWN';
        }
    }

    /**
     * Recouvrement peut renvoyer le parent sous plusieurs formes :
     * parent: 37, parent: {id: 37, ...}, ou absent. Ne jamais caster un
     * tableau directement en int (PHP le transformerait en 1).
     */
    private function extractParentContractId(array $contract): int
    {
        $parent = $contract['parent'] ?? $contract['parent_id'] ?? null;

        if (is_array($parent)) {
            return (int) ($parent['id'] ?? 0);
        }

        return (int) ($parent ?: 0);
    }

    private function extractPartnerIdFromContract(array $contract, array $lease): ?int
    {
        foreach ([
            $lease['partner_id'] ?? null,
            $lease['partenaire_id'] ?? null,
            $lease['partenaire'] ?? null,
            $contract['partner_id'] ?? null,
            $contract['partenaire_id'] ?? null,
            $contract['partenaire'] ?? null,
        ] as $candidate) {
            if (is_array($candidate)) {
                $candidate = $candidate['id'] ?? $candidate['partner_id'] ?? null;
            }

            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function findLeaseFromApi(int $leaseId): ?array
    {
        // fetchLeases() renvoie désormais une liste plate déjà paginée
        // (voir LeaseApiClientService::getRows()/unwrapRows()), donc on
        // s'appuie directement sur la recherche par id déjà éprouvée par
        // le processeur de coupure automatique.
        return $this->leaseApi->fetchLeaseById($leaseId);
    }

    private function extractLeaseDueDate(array $lease): ?string
    {
        $raw = $lease['date_echeance']
            ?? $lease['prochaine_echeance']
            ?? $lease['due_date']
            ?? null;

        if (! $raw) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildPaymentSnapshot(
        array $lease,
        string $customStatus,
        ?string $reason = null,
        ?User $actor = null,
        ?string $forgivenByName = null
    ): array {
        return [
            'lease_id' => $lease['id'] ?? null,
            'contrat_id' => $lease['contrat_id'] ?? $lease['contrat'] ?? null,
            'date_echeance' => $lease['date_echeance'] ?? $lease['prochaine_echeance'] ?? null,
            'montant_attendu' => $lease['montant_attendu'] ?? null,
            'montant_paye' => $lease['montant_paye'] ?? null,
            'reste_a_payer' => $lease['reste_a_payer'] ?? null,
            'statut_api' => $lease['statut'] ?? null,
            'statut_personnalise' => $customStatus,
            'chauffeur_nom_complet' => $lease['chauffeur_nom_complet'] ?? null,
            'reason' => $reason,

            'forgiven_by_user_id' => $actor?->id,
            'forgiven_by_name' => $forgivenByName,
            'forgiven_at' => now()->toDateTimeString(),
        ];
    }

    /* ────────────────────────────────────────────────────────────────
     * Construction des messages lisibles (véhicule, chauffeur, acteur)
     * ──────────────────────────────────────────────────────────────── */

    private function vehicleLabel(Voiture $vehicle): string
    {
        return $vehicle->immatriculation ?: 'un véhicule sans plaque enregistrée';
    }

    private function vehicleDriverLabel(?LeaseContractLink $contractLink): string
    {
        $driver = $contractLink?->driver;

        if (! $driver) {
            return 'chauffeur non renseigné';
        }

        $name = trim(implode(' ', array_filter([$driver->prenom ?? null, $driver->nom ?? null])));

        return $name !== '' ? $name : (string) ($driver->email ?? 'chauffeur non renseigné');
    }

    /**
     * Ne jamais afficher "Type #4" / "Contrat #40" — on réutilise le même
     * repli propre que safeSiblingContractLabel() ci-dessus.
     */
    private function contractTypeLabel(?LeaseContractLink $contractLink): string
    {
        return $contractLink ? strtolower($this->safeSiblingContractLabel($contractLink)) : 'contrat';
    }

    private function reasonBeforeCut(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName): string
    {
        return sprintf(
            'Le véhicule %s assigné au chauffeur %s n’a pas été coupé car il a été pardonné avant coupure par %s.',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $forgivenByName
        );
    }

    private function reasonAfterCutPending(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName, int $attempt, int $maxChecks): string
    {
        return sprintf(
            'Le véhicule %s assigné au chauffeur %s a été pardonné (contrat %s) par %s : le rallumage a été demandé, le système attend la confirmation que le moteur est bien remis en marche (tentative %d sur %d).',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $this->contractTypeLabel($contractLink),
            $forgivenByName,
            $attempt,
            $maxChecks
        );
    }

    /**
     * Utilisé par LeaseCutoffQueueProcessorService une fois le rallumage
     * réellement confirmé par l'état moteur live (pas seulement accepté par
     * le provider). C'est cette méthode qui écrit la phrase "a été rallumé
     * car il a été pardonné après coupure par X".
     */
    public function describeReactivationConfirmed(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName): string
    {
        return sprintf(
            'Le véhicule %s assigné au chauffeur %s a été rallumé car il a été pardonné après coupure par %s.',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $forgivenByName
        );
    }

    /**
     * Utilisé par LeaseCutoffQueueProcessorService quand le rallumage a été
     * transmis mais jamais confirmé par le boîtier après le nombre maximal
     * de vérifications.
     */
    public function describeReactivationNotConfirmed(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName, int $maxChecks, ?string $deviceDiagnostic): string
    {
        return $this->reasonAfterCutNotConfirmed($vehicle, $contractLink, $forgivenByName, $maxChecks, $deviceDiagnostic);
    }

    private function reasonAfterCutRejectedByGps(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName, string $providerMessage): string
    {
        return sprintf(
            'Le véhicule %s assigné au chauffeur %s a été pardonné (contrat %s) par %s, mais le rallumage a échoué : le système GPS n’a pas accepté la demande. Une vérification manuelle est recommandée.',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $this->contractTypeLabel($contractLink),
            $forgivenByName
        );
    }

    private function reasonAfterCutNotConfirmed(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName, int $maxChecks, ?string $deviceDiagnostic): string
    {
        return sprintf(
            'Le véhicule %s assigné au chauffeur %s a été pardonné (contrat %s) par %s, mais le rallumage n’a jamais pu être confirmé malgré plusieurs tentatives : le moteur semble toujours coupé. Une vérification manuelle du véhicule est recommandée.',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $this->contractTypeLabel($contractLink),
            $forgivenByName
        );
    }

    private function reasonAfterCutBlockedBySiblings(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName, Collection $blockingSiblings): string
    {
        return sprintf(
            'Le véhicule %s assigné au chauffeur %s a été pardonné pour son lease %s par %s, mais le rallumage a échoué : le(s) sous-contrat(s) suivant(s) sur ce même véhicule sont toujours impayés et doivent aussi être pardonnés avant de pouvoir rallumer : %s.',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $this->contractTypeLabel($contractLink),
            $forgivenByName,
            $blockingSiblings->pluck('label')->unique()->implode(', ')
        );
    }

    private function appendEmployeeReason(string $narrative, ?string $reason, string $forgivenByName): string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            return $narrative;
        }

        return $narrative . sprintf(' Motif indiqué par %s : « %s ».', $forgivenByName, $reason);
    }

    private function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    private function actorLabel(User $actor): string
    {
        $name = trim((string) (
            $actor->nom_complet
            ?? $actor->full_name
            ?? trim(($actor->prenom ?? '') . ' ' . ($actor->nom ?? ''))
        ));

        if ($name !== '') {
            return $name;
        }

        return (string) ($actor->email ?? $actor->phone ?? 'Utilisateur connecté');
    }
}
