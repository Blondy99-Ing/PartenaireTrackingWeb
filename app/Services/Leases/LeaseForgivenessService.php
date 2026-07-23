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
    public function forgive(User $actor, int $leaseId, ?string $reason = null): array
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

        $queue = LeaseCutoffQueue::query()
            ->with(['history'])
            ->where('partner_id', $partnerId)
            ->where('lease_id', $leaseId)
            ->where('vehicle_id', $vehicle->id)
            ->when($contractLink, fn ($query) => $query->where('contract_link_id', $contractLink->id))
            ->when($dueDate, fn ($query) => $query->whereDate('lease_date_echeance', $dueDate))
            ->orderByDesc('id')
            ->first();

        $history = $queue?->history;

        if (! $history) {
            $history = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('lease_id', $leaseId)
                ->where('vehicle_id', $vehicle->id)
                ->when($contractLink, fn ($query) => $query->where('contract_link_id', $contractLink->id))
                ->when($dueDate, fn ($query) => $query->whereDate('lease_date_echeance', $dueDate))
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
                reason: $reason
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
            reason: $reason
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
        ?string $reason
    ): array {
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
            $businessReason
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
                    'Pardon préventif : aucune commande de coupure ni de rallumage nécessaire. Le planner ne doit plus replanifier ce lease.'
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
                    'contract_kind' => $contractLink?->contract_kind,
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
                'message' => 'Pardon préventif enregistré. Le véhicule ne sera pas coupé pour ce lease.',
                'was_cut_before_forgiveness' => false,
                'reason' => $businessReason,
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now()->toDateTimeString(),
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
        ?string $reason
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
         * encore impayé sur ce même véhicule.
         */
        $blockingSiblings = $this->findBlockingSiblingContracts($partnerId, $vehicle, $contractLink?->id ?? 0);

        if ($blockingSiblings->isNotEmpty()) {
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
                createQueueIfMissing: false
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
            notes: 'Pardon après coupure : commande de rallumage transmise au provider. En attente de la confirmation réelle du moteur avant de conclure.',
            commandResponse: $command,
            uiStatus: 'forgiven_reactivation_pending',
            message: 'Pardon enregistré. Commande de rallumage envoyée, en attente de confirmation du moteur.',
            createQueueIfMissing: true
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
        bool $createQueueIfMissing
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
            $createQueueIfMissing
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
                    'contract_kind' => $contractLink?->contract_kind,
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
                $queue->update([
                    'status' => $queueStatus,
                    'last_checked_at' => now(),
                    'retry_count' => $queueStatus === 'COMMAND_SENT' ? $queue->retry_count + 1 : $queue->retry_count,
                    'next_check_at' => $queueNextCheckAt,
                    'history_id' => $history->id,
                ]);
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
                    'contract_kind' => $contractLink?->contract_kind,
                    'trigger_label' => $contractLink?->displayTypeLabel(),
                    'trigger_payload' => [
                        'source_contract_id' => $contractId,
                        'lease_id' => $leaseId,
                        'date_echeance' => $dueDate,
                        'origin' => 'manual_forgiveness_after_cut_reactivation_tracking',
                    ],
                    'contract_rule_id' => $contractLink?->cutoffRule?->id,
                    'history_id' => $history->id,
                    'scheduled_for' => now(),
                    'status' => $queueStatus,
                    'retry_count' => 1,
                    'last_checked_at' => now(),
                    'next_check_at' => $queueNextCheckAt,
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

        return [
            'status' => $uiStatus,
            'history_status' => $historyStatus,
            'message' => $message,
            'was_cut_before_forgiveness' => true,
            'reason' => $businessReason,
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
            'forgiven_at' => now()->toDateTimeString(),
            'command' => $commandResponse,
        ];
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
            uiStatus: 'forgiven_reactivation_failed',
            message: 'Pardon enregistré, mais le rallumage a été refusé : un autre contrat sur ce véhicule est toujours impayé.',
            createQueueIfMissing: false
        );
    }

    /**
     * Retourne les contrats/sous-contrats frères sur le MÊME véhicule dont la
     * dernière décision de coupure locale est toujours active (planifiée, en
     * attente, commande envoyée ou confirmée coupée) — c'est-à-dire toujours
     * non résolue par un paiement ou un pardon. Tant que l'un d'eux existe,
     * envoyer une commande de rallumage serait contredire une coupure encore
     * légitime.
     */
    private function findBlockingSiblingContracts(int $partnerId, Voiture $vehicle, int $excludeContractLinkId): Collection
    {
        $siblings = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('vehicle_id', $vehicle->id)
            ->where('id', '!=', $excludeContractLinkId)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->get();

        $blocking = collect();

        foreach ($siblings as $sibling) {
            $latestHistory = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('contract_link_id', $sibling->id)
                ->orderByDesc('id')
                ->first();

            if ($latestHistory && in_array($latestHistory->status, self::SIBLING_STILL_BLOCKING_STATUSES, true)) {
                $blocking->push([
                    'contract_link' => $sibling,
                    'label' => $sibling->displayTypeLabel(),
                    'history_status' => $latestHistory->status,
                ]);
            }
        }

        return $blocking;
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
                ->when($dueDate, fn ($q) => $q->whereDate('lease_date_echeance', $dueDate))
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
                ->when($dueDate, fn ($q) => $q->whereDate('lease_date_echeance', $dueDate))
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
        return $vehicle->immatriculation ?: ('véhicule #' . $vehicle->id);
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

    private function contractTypeLabel(?LeaseContractLink $contractLink): string
    {
        return $contractLink ? strtolower($contractLink->displayTypeLabel()) : 'contrat';
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
            'Le véhicule %s assigné au chauffeur %s a été pardonné pour son lease %s par %s : commande de rallumage envoyée, en attente de confirmation moteur (vérification %d/%d).',
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
            'Le véhicule %s assigné au chauffeur %s a été pardonné pour son lease %s par %s, mais le rallumage a échoué : le GPS n’a pas accepté la commande (%s).',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $this->contractTypeLabel($contractLink),
            $forgivenByName,
            $providerMessage
        );
    }

    private function reasonAfterCutNotConfirmed(Voiture $vehicle, ?LeaseContractLink $contractLink, string $forgivenByName, int $maxChecks, ?string $deviceDiagnostic): string
    {
        $diagnosticText = $deviceDiagnostic
            ? sprintf(' Diagnostic renvoyé par le boîtier lui-même : « %s ».', $deviceDiagnostic)
            : '';

        return sprintf(
            'Le véhicule %s assigné au chauffeur %s a été pardonné pour son lease %s par %s, mais le rallumage n’a jamais été confirmé après %d vérifications : le boîtier rapporte toujours le moteur coupé.%s',
            $this->vehicleLabel($vehicle),
            $this->vehicleDriverLabel($contractLink),
            $this->contractTypeLabel($contractLink),
            $forgivenByName,
            $maxChecks,
            $diagnosticText
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
