<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffContractRule;
use App\Models\LeaseCutoffQueue;
use App\Services\Gps\GpsCommandDispatcherService;
use App\Services\GpsControlService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Traite la queue de coupure lease.
 *
 * Sécurité métier :
 * - le cron automatique traite uniquement les queues de la date du jour ;
 * - une queue d'hier ne sera jamais reprise automatiquement aujourd'hui ;
 * - une ancienne date se traite uniquement avec --date=YYYY-MM-DD ;
 * - on revérifie le lease exact avec lease_id + date_echeance ;
 * - on revérifie la règle spécifique avant tout nouvel envoi GPS ;
 * - on ne coupe pas si le véhicule roule, est offline, ou si l'état est incertain ;
 * - on ne renvoie jamais une commande déjà envoyée.
 */
class LeaseCutoffQueueProcessorService
{
    private const DEFAULT_CONFIRM_MAX_CHECKS = 6;
    private const DEFAULT_CONFIRM_DELAY_SECONDS = 20;
    private const DEFAULT_WAITING_DELAY_MINUTES = 1;

    public function __construct(
        private readonly LeaseApiClientService $leaseApi,
        private readonly GpsControlService $gps,
        private readonly GpsCommandDispatcherService $dispatcher,
        private readonly LeaseForgivenessService $forgiveness
    ) {
    }

    public function process(?string $dateEcheance = null): array
    {
        $targetDate = $this->resolveProcessingDueDate($dateEcheance);

        Log::info('[LEASE_CUTOFF_PROCESS] Début du traitement de queue du jour', [
            'target_date_echeance' => $targetDate,
        ]);

        $activeStatuses = ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'];

        $items = LeaseCutoffQueue::query()
            ->with(['vehicle', 'history', 'contractRule', 'contractLink'])
            ->whereIn('status', $activeStatuses)
            ->whereDate('lease_date_echeance', $targetDate)
            ->where(function ($q) {
                $q->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->get();

        Log::info('[LEASE_CUTOFF_PROCESS] Queues sélectionnées pour la date du jour', [
            'target_date_echeance' => $targetDate,
            'items_count' => $items->count(),
            'queue_ids' => $items->pluck('id')->values()->all(),
        ]);

        $processed = 0;
        $waiting = 0;
        $cancelled = 0;
        $failed = 0;

        foreach ($items as $item) {
            $ctx = [
                'queue_id' => $item->id,
                'history_id' => $item->history_id,
                'partner_id' => $item->partner_id,
                'vehicle_id' => $item->vehicle_id,
                'contract_id' => $item->contract_id,
                'lease_id' => $item->lease_id,
                'lease_date_echeance' => optional($item->lease_date_echeance)->toDateString(),
                'contract_link_id' => $item->contract_link_id,
                'contract_rule_id' => $item->contract_rule_id,
                'type_contrat_label' => $item->type_contrat_label,
                'contract_kind' => $item->contract_kind,
                'queue_status' => $item->status,
                'retry_count' => $item->retry_count,
                'scheduled_for' => optional($item->scheduled_for)->toDateTimeString(),
                'next_check_at' => optional($item->next_check_at)->toDateTimeString(),
            ];

            try {
                $leaseId = (int) $item->lease_id;
                $dueDate = $this->extractDueDateFromQueue($item);

                if ($leaseId <= 0) {
                    $this->markFailed($item, 'Impossible de revalider le paiement : lease_id manquant dans la queue de coupure.');
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : lease_id manquant', $ctx);
                    continue;
                }

                if (! $dueDate) {
                    $this->markFailed($item, 'Impossible de revalider le paiement : date_echeance manquante dans la queue de coupure.');
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : date_echeance manquante', $ctx);
                    continue;
                }

                /**
                 * Verrou jour par jour.
                 *
                 * Même si la requête SQL filtre déjà lease_date_echeance=$targetDate,
                 * on conserve cette vérification pour éviter qu'une queue incohérente
                 * ou un payload ancien ne fasse traiter une autre date.
                 */
                if ($dueDate !== $targetDate) {
                    Log::warning('[LEASE_CUTOFF_PROCESS] Queue ignorée : date_echeance différente de la date traitée.', array_merge($ctx, [
                        'target_date_echeance' => $targetDate,
                        'queue_due_date' => $dueDate,
                    ]));
                    continue;
                }

                /**
                 * Une queue de confirmation de RALLUMAGE après pardon ne doit
                 * jamais repasser par la revérification "toujours impayé" ni par
                 * la revérification de règle active : ces deux contrôles ont un
                 * sens pour décider si on doit COUPER, pas pour confirmer qu'un
                 * rallumage déjà décidé par un employé a bien pris effet. Sans ce
                 * garde-fou, un lease entre-temps réglé ferait écraser à tort le
                 * statut REACTIVATION_REQUESTED_AFTER_FORGIVENESS par
                 * CANCELLED_PAID/CANCELLED_UNVERIFIED.
                 */
                $isReactivationConfirmation = $item->history?->status === 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS';

                if (! $isReactivationConfirmation) {
                    /**
                     * Re-vérification du paiement PAR LEASE (et non par date figée).
                     *
                     * Pourquoi : la date_echeance d'un lease impayé « roule » côté
                     * recouvrement (ex. 2026-07-15 -> 2026-07-16) alors que la queue
                     * garde la date d'origine. L'ancienne vérification, filtrée sur
                     * cette date figée, concluait à tort « payé » et annulait la coupure
                     * d'un lease pourtant toujours NON_PAYE (reste > 0, aucun paiement).
                     *
                     * On vérifie donc le lease par son id, et on n'écrit « payé » que si
                     * un paiement RÉEL existe. L'audit reste ainsi fidèle à la réalité.
                     */
                    $leaseNow = $this->leaseApi->fetchLeaseById($leaseId);

                    if ($leaseNow === null) {
                        $this->markCancelledUnverified(
                            $item,
                            sprintf('Le lease #%d est introuvable côté recouvrement au moment de l’exécution. Coupure annulée par prudence, SANS confirmation de paiement.', $leaseId)
                        );
                        $cancelled++;
                        Log::warning('[LEASE_CUTOFF_PROCESS] Queue annulée : lease introuvable côté recouvrement (aucune preuve de paiement)', $ctx);
                        continue;
                    }

                    if (! $this->leaseApi->isNonPaidLeaseRow($leaseNow)) {
                        /**
                         * Le lease n'est réellement plus NON_PAYE. On EXIGE une preuve de
                         * paiement avant d'écrire CANCELLED_PAID, sinon on trace un statut
                         * distinct « à vérifier » plutôt que d'affirmer un paiement faux.
                         */
                        $payment = $this->leaseApi->findPaymentForLease($leaseId);

                        if ($payment) {
                            $this->markCancelledPaid(
                                $item,
                                sprintf('Le lease #%d est réglé (paiement #%s, montant %s). Coupure automatique annulée.',
                                    $leaseId,
                                    (string) ($payment['id'] ?? '?'),
                                    (string) ($payment['montant'] ?? '?')
                                ),
                                $payment
                            );
                            Log::info('[LEASE_CUTOFF_PROCESS] Queue annulée : paiement réel confirmé', array_merge($ctx, [
                                'payment_id' => $payment['id'] ?? null,
                                'montant' => $payment['montant'] ?? null,
                            ]));
                        } else {
                            $this->markCancelledUnverified(
                                $item,
                                sprintf('Le lease #%d n’est plus retourné comme NON_PAYE, mais AUCUN paiement n’a été trouvé (échéance probablement modifiée côté recouvrement). Coupure annulée à vérifier — non confirmée comme payée.', $leaseId)
                            );
                            Log::warning('[LEASE_CUTOFF_PROCESS] Queue annulée : plus NON_PAYE mais sans paiement trouvé (à vérifier)', array_merge($ctx, [
                                'lease_statut' => $leaseNow['statut'] ?? null,
                                'reste_a_payer' => $leaseNow['reste_a_payer'] ?? null,
                            ]));
                        }

                        $cancelled++;
                        continue;
                    }

                    /**
                     * Ici le lease est TOUJOURS NON_PAYE (reste > 0), quelle que soit sa
                     * date_echeance actuelle : on poursuit vers la coupure.
                     */
                    Log::info('[LEASE_CUTOFF_PROCESS] Lease toujours NON_PAYE (vérifié par id) : poursuite de la coupure', array_merge($ctx, [
                        'lease_statut' => $leaseNow['statut'] ?? null,
                        'reste_a_payer' => $leaseNow['reste_a_payer'] ?? null,
                        'date_echeance_actuelle' => $leaseNow['date_echeance'] ?? null,
                        'date_echeance_queue' => $dueDate,
                    ]));

                    /**
                     * Revérification de la règle spécifique avant commande GPS.
                     *
                     * Si la queue n'a pas encore envoyé de commande, la règle doit toujours
                     * être active sur le même contrat/sous-contrat réel.
                     *
                     * Si la queue est déjà COMMAND_SENT, on ne renvoie pas la commande :
                     * on vérifie seulement la confirmation moteur.
                     */
                    if ($item->status !== 'COMMAND_SENT') {
                        $activeRule = $this->resolveActiveContractRule($item);

                        if (! $activeRule) {
                            $this->markCancelledRule(
                                $item,
                                'CANCELLED_RULE_MISSING',
                                'La coupure est annulée : aucune règle active n’est plus configurée sur ce contrat/sous-contrat spécifique au moment de l’exécution.'
                            );
                            $cancelled++;
                            Log::warning('[LEASE_CUTOFF_PROCESS] Queue annulée : règle spécifique absente ou désactivée', $ctx);
                            continue;
                        }

                        if (! $activeRule->effectiveCutoffTime()) {
                            $this->markCancelledRule(
                                $item,
                                'CANCELLED_RULE_DISABLED',
                                'La coupure est annulée : la règle spécifique du contrat/sous-contrat n’a plus d’heure de coupure valide.'
                            );
                            $cancelled++;
                            Log::warning('[LEASE_CUTOFF_PROCESS] Queue annulée : règle spécifique sans heure', array_merge($ctx, [
                                'active_contract_rule_id' => $activeRule->id,
                            ]));
                            continue;
                        }

                        if ((int) $item->contract_rule_id !== (int) $activeRule->id) {
                            $item->forceFill(['contract_rule_id' => $activeRule->id])->save();
                        }
                    }
                }

                $vehicle = $item->vehicle;
                if (! $vehicle) {
                    $this->markFailed($item, 'Le véhicule lié à cette queue est introuvable localement ; traitement impossible.');
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : véhicule local introuvable', $ctx);
                    continue;
                }

                if (empty($vehicle->mac_id_gps)) {
                    $this->markFailed($item, 'Impossible d’envoyer la commande : aucun identifiant GPS n’est associé au véhicule.');
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : mac_id_gps manquant', array_merge($ctx, [
                        'immatriculation' => $vehicle->immatriculation ?? null,
                    ]));
                    continue;
                }

                $macId = trim((string) $vehicle->mac_id_gps);
                $ctx['mac_id_gps'] = $macId;
                $ctx['immatriculation'] = $vehicle->immatriculation ?? null;

                $movingThreshold = (float) config('gps.moving_threshold', 5.0);
                $vehicleState = $this->gps->getVehicleStateByMacId($macId, $movingThreshold);

                if (! ($vehicleState['success'] ?? false)) {
                    $this->markWaiting($item, 'Commande EN ATTENTE : impossible de lire l’état du boîtier GPS (état inconnu). Aucune commande n’a été envoyée — la coupure sera tentée automatiquement dès que le boîtier répondra.', null, null);
                    $waiting++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Attente : état véhicule indisponible', array_merge($ctx, [
                        'vehicle_state' => $vehicleState,
                    ]));
                    continue;
                }

                $speed = isset($vehicleState['speed']) && is_numeric($vehicleState['speed']) ? (float) $vehicleState['speed'] : null;
                $uiStatus = (string) ($vehicleState['ui_status'] ?? 'UNKNOWN');
                $isMoving = $vehicleState['is_moving'] ?? null;
                $isOnline = $vehicleState['is_online'] ?? null;
                $rawStatus = $vehicleState['raw_status'] ?? null;
                $decoded = $this->gps->decodeEngineStatus($rawStatus);
                $engineState = (string) ($decoded['engineState'] ?? 'UNKNOWN');

                Log::info('[LEASE_CUTOFF_PROCESS] État live du véhicule', array_merge($ctx, [
                    'speed' => $speed,
                    'is_online' => $isOnline,
                    'is_moving' => $isMoving,
                    'ui_status' => $uiStatus,
                    'raw_status' => $rawStatus,
                    'engine_state' => $engineState,
                    'moving_threshold' => $movingThreshold,
                ]));

                if ($item->status === 'COMMAND_SENT') {
                    $maxChecks = (int) env('LEASE_CUTOFF_CONFIRM_MAX_CHECKS', self::DEFAULT_CONFIRM_MAX_CHECKS);

                    /**
                     * Anomalie corrigée : le rallumage envoyé après un pardon
                     * n'avait auparavant AUCUNE boucle de confirmation — le statut
                     * "rallumé" était écrit dès l'acceptation de la commande par
                     * le provider, jamais vérifié sur l'état moteur réel. On
                     * applique ici exactement la même rigueur que pour la
                     * coupure : condition inversée (succès quand le moteur n'est
                     * PLUS coupé), même mécanisme de nouvelles tentatives et de
                     * diagnostic boîtier en cas d'échec.
                     */
                    if ($item->history?->status === 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS') {
                        if ($engineState !== 'CUT') {
                            $this->markReactivationConfirmed($item, $speed, $uiStatus);
                            $processed++;
                            Log::info('[LEASE_CUTOFF_PROCESS] Succès : rallumage après pardon confirmé', $ctx);
                            continue;
                        }

                        if ($item->retry_count >= $maxChecks) {
                            $deviceDiagnostic = $this->describeDeviceDiagnostic($item, $macId);
                            $this->markReactivationFailed($item, $deviceDiagnostic, $maxChecks);
                            $failed++;
                            Log::warning('[LEASE_CUTOFF_PROCESS] Échec : rallumage après pardon non confirmé après plusieurs vérifications', array_merge($ctx, [
                                'device_diagnostic' => $deviceDiagnostic,
                            ]));
                            continue;
                        }

                        $this->markReactivationStillPending($item, $engineState, $maxChecks);
                        $waiting++;
                        Log::info('[LEASE_CUTOFF_PROCESS] Attente : rallumage après pardon déjà envoyé, pas de renvoi', $ctx);
                        continue;
                    }

                    if ($engineState === 'CUT') {
                        $this->markProcessedCutOff(
                            $item,
                            ['source' => 'post_send_verification', 'message' => 'La commande précédemment envoyée est confirmée par l’état moteur live.'],
                            $speed,
                            $uiStatus,
                            sprintf('Coupure CONFIRMÉE : le boîtier rapporte le moteur coupé (relais ouvert), après %d vérification(s). La commande a bien pris effet.', (int) $item->retry_count)
                        );
                        $processed++;
                        Log::info('[LEASE_CUTOFF_PROCESS] Succès : commande confirmée après vérification différée', $ctx);
                        continue;
                    }

                    if ($item->retry_count >= $maxChecks) {
                        $deviceDiagnostic = $this->describeDeviceDiagnostic($item, $macId);

                        $reason = $deviceDiagnostic
                            ? sprintf(
                                'Commande ENVOYÉE mais NON CONFIRMÉE après %d vérifications : le boîtier rapporte toujours le moteur « %s » (relais non coupé). Diagnostic renvoyé par le boîtier lui-même : « %s ». Aucune preuve que le véhicule soit réellement coupé.',
                                $maxChecks,
                                $engineState,
                                $deviceDiagnostic
                            )
                            : sprintf(
                                'Commande ENVOYÉE mais NON CONFIRMÉE après %d vérifications : le boîtier rapporte toujours le moteur « %s » (relais non coupé). Le boîtier n’a pas exécuté la coupure — causes probables : mot de passe commande du boîtier incorrect, mot-clé de commande inadapté au modèle, ou boîtier injoignable. Aucune preuve que le véhicule soit réellement coupé.',
                                $maxChecks,
                                $engineState
                            );

                        $this->markFailed($item, $reason);
                        $failed++;
                        Log::warning('[LEASE_CUTOFF_PROCESS] Échec : commande non confirmée après plusieurs vérifications', array_merge($ctx, [
                            'device_diagnostic' => $deviceDiagnostic,
                        ]));
                        continue;
                    }

                    $this->markCommandStillPending(
                        $item,
                        sprintf('Commande ENVOYÉE, en attente de confirmation moteur — vérification %d/%d. Le boîtier rapporte pour l’instant le moteur « %s » (pas encore confirmé coupé).', (int) $item->retry_count, $maxChecks, $engineState),
                        $speed,
                        $uiStatus
                    );
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : commande déjà envoyée, pas de renvoi', $ctx);
                    continue;
                }

                if ($engineState === 'CUT') {
                    $this->markProcessedCutOff(
                        $item,
                        ['source' => 'live_engine_state_before_send', 'message' => 'Le moteur apparaît déjà coupé dans l’état live du provider GPS avant tout nouvel envoi.'],
                        $speed,
                        $uiStatus,
                        'Le moteur est déjà confirmé coupé lors de la vérification live ; aucune nouvelle commande n’a été nécessaire.'
                    );
                    $processed++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Succès : véhicule déjà coupé avant envoi', $ctx);
                    continue;
                }

                if ($isOnline === false) {
                    $this->markWaiting($item, sprintf('Commande EN ATTENTE : boîtier HORS-LIGNE (%s). Aucune commande envoyée — la coupure sera tentée dès le retour en ligne du boîtier.', $uiStatus), $speed, $uiStatus);
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : véhicule offline', $ctx);
                    continue;
                }

                if ($isMoving === null) {
                    $this->markWaiting($item, 'Commande EN ATTENTE : état de mouvement INCERTAIN (donnée GPS ambiguë). Coupure différée par sécurité jusqu’à un état fiable.', $speed, $uiStatus);
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : mouvement incertain', $ctx);
                    continue;
                }

                if ($isMoving === true) {
                    $this->markWaiting($item, sprintf('Commande EN ATTENTE : véhicule EN MOUVEMENT (%s km/h). Par sécurité, la coupure n’est envoyée qu’à l’arrêt du véhicule.', $speed !== null ? $speed : '?'), $speed, $uiStatus);
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : véhicule en mouvement', $ctx);
                    continue;
                }

                $command = $this->dispatcher->dispatchCutByMacId($macId);
                Log::info('[LEASE_CUTOFF_PROCESS] Résultat envoi commande', array_merge($ctx, [
                    'command_result' => $command,
                ]));

                $commandStatus = (string) ($command['status'] ?? 'FAILED');
                if ($commandStatus === 'FAILED') {
                    $this->markFailed($item, sprintf('Commande REFUSÉE par le provider GPS : %s. La coupure n’a pas été transmise au boîtier.', (string) ($command['message'] ?? $command['return_msg'] ?? 'raison non précisée par le provider')));
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : provider a rejeté la commande', array_merge($ctx, [
                        'command_result' => $command,
                    ]));
                    continue;
                }

                $cmdNo = $command['cmd_no'] ?? null;
                $providerMsg = (string) ($command['message'] ?? 'commande acceptée');
                $cmdRef = $cmdNo
                    ? 'réf. commande ' . $cmdNo
                    : 'AUCUN numéro de commande renvoyé par le provider — la délivrance réelle au boîtier n’est pas garantie';

                $this->markCommandSent(
                    $item,
                    $command,
                    $speed,
                    $uiStatus,
                    $commandStatus === 'PENDING_VERIFICATION'
                        ? sprintf('Commande de coupure TRANSMISE au provider (%s ; %s), mais le provider demande une vérification différée avant confirmation. En attente de la confirmation moteur.', $providerMsg, $cmdRef)
                        : sprintf('Commande de coupure ENVOYÉE et acceptée par le provider (%s ; %s). En attente de la confirmation réelle du moteur (relais coupé) avant de conclure.', $providerMsg, $cmdRef)
                );
                $waiting++;
                Log::info('[LEASE_CUTOFF_PROCESS] Commande envoyée, passage en COMMAND_SENT', array_merge($ctx, [
                    'command_status' => $commandStatus,
                ]));
            } catch (\Throwable $e) {
                $this->markFailed($item, 'Une exception technique est survenue pendant le traitement de la queue : ' . $e->getMessage());
                $failed++;
                Log::error('[LEASE_CUTOFF_PROCESS] Exception pendant le traitement', array_merge($ctx, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]));
            }
        }

        Log::info('[LEASE_CUTOFF_PROCESS] Fin du traitement de queue', array_merge(['target_date_echeance' => $targetDate], compact('processed', 'waiting', 'cancelled', 'failed')));

        return [
            'success' => true,
            'target_date_echeance' => $targetDate,
            'processed' => $processed,
            'waiting' => $waiting,
            'cancelled' => $cancelled,
            'failed' => $failed,
        ];
    }

    private function resolveProcessingDueDate(?string $dateEcheance): string
    {
        $timezone = config('app.timezone', 'Africa/Douala');

        if ($dateEcheance && trim($dateEcheance) !== '') {
            return Carbon::parse($dateEcheance, $timezone)->toDateString();
        }

        return Carbon::now($timezone)->toDateString();
    }

    private function resolveActiveContractRule(LeaseCutoffQueue $item): ?LeaseCutoffContractRule
    {
        if (
            $item->contractRule
            && (bool) $item->contractRule->is_enabled
            && (int) $item->contractRule->contract_link_id === (int) $item->contract_link_id
        ) {
            return $item->contractRule;
        }

        if ($item->contract_link_id) {
            return LeaseCutoffContractRule::query()
                ->where('contract_link_id', $item->contract_link_id)
                ->where('partner_id', $item->partner_id)
                ->where('is_enabled', true)
                ->first();
        }

        return null;
    }

    private function markCancelledPaid(LeaseCutoffQueue $item, string $reason, ?array $payment = null): void
    {
        DB::transaction(function () use ($item, $reason, $payment) {
            $item->update([
                'status' => 'CANCELLED',
                'last_checked_at' => now(),
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'CANCELLED_PAID',
                    'reason' => $reason,
                    'command_response' => $payment ? [
                        'source' => 'payment_verified',
                        'payment_id' => $payment['id'] ?? null,
                        'montant' => $payment['montant'] ?? null,
                        'date_paiement' => $payment['date_paiement'] ?? $payment['created_at'] ?? null,
                    ] : null,
                    'notes' => $payment
                        ? 'Coupure annulée : paiement réel confirmé côté recouvrement.'
                        : 'Coupure annulée après confirmation que le lease n’est plus dû.',
                ]);
            }
        });
    }

    /**
     * Annulation SANS preuve de paiement.
     *
     * Cas : lease introuvable, ou lease qui n'est plus NON_PAYE mais sans paiement
     * réel trouvé (typiquement une date_echeance modifiée côté recouvrement). On
     * n'affirme JAMAIS « payé » sans preuve : statut distinct pour un audit honnête.
     */
    private function markCancelledUnverified(LeaseCutoffQueue $item, string $reason): void
    {
        DB::transaction(function () use ($item, $reason) {
            $item->update([
                'status' => 'CANCELLED',
                'last_checked_at' => now(),
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'CANCELLED_UNVERIFIED',
                    'reason' => $reason,
                    'notes' => 'Coupure annulée sans preuve de paiement : à vérifier manuellement. Le lease peut être encore dû (échéance modifiée côté recouvrement).',
                ]);
            }
        });
    }

    private function markCancelledRule(LeaseCutoffQueue $item, string $historyStatus, string $reason): void
    {
        DB::transaction(function () use ($item, $historyStatus, $reason) {
            $item->update([
                'status' => 'CANCELLED',
                'last_checked_at' => now(),
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => $historyStatus,
                    'reason' => $reason,
                    'notes' => 'Événement clôturé sans commande GPS : la règle spécifique du contrat/sous-contrat n’autorise plus la coupure.',
                ]);
            }
        });
    }

    private function markWaiting(LeaseCutoffQueue $item, string $reason, ?float $speed, ?string $uiStatus): void
    {
        $delayMinutes = (int) env('LEASE_CUTOFF_WAITING_DELAY_MINUTES', self::DEFAULT_WAITING_DELAY_MINUTES);

        DB::transaction(function () use ($item, $reason, $speed, $uiStatus, $delayMinutes) {
            $item->update([
                'status' => 'WAITING_STOP',
                'last_checked_at' => now(),
                'retry_count' => $item->retry_count + 1,
                'next_check_at' => now()->addMinutes($delayMinutes),
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'WAITING_STOP',
                    'reason' => $reason,
                    'speed_at_check' => $speed,
                    'ignition_state' => $uiStatus,
                    'notes' => 'Traitement maintenu en attente ; aucune commande de coupure n’a été envoyée à ce stade.',
                ]);
            }
        });
    }

    private function markCommandSent(LeaseCutoffQueue $item, array $commandResponse, ?float $speed, ?string $uiStatus, string $reason): void
    {
        $delay = (int) env('LEASE_CUTOFF_CONFIRM_DELAY_SECONDS', self::DEFAULT_CONFIRM_DELAY_SECONDS);

        DB::transaction(function () use ($item, $commandResponse, $speed, $uiStatus, $delay, $reason) {
            $item->update([
                'status' => 'COMMAND_SENT',
                'last_checked_at' => now(),
                'retry_count' => $item->retry_count + 1,
                'next_check_at' => now()->addSeconds($delay),
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'COMMAND_SENT',
                    'reason' => $reason,
                    'cutoff_requested_at' => now(),
                    'speed_at_check' => $speed,
                    'ignition_state' => $uiStatus,
                    'command_response' => $commandResponse,
                    'notes' => 'Commande de coupure transmise ; attente d’une confirmation moteur réelle avant clôture.',
                ]);
            }
        });
    }

    private function markCommandStillPending(LeaseCutoffQueue $item, string $reason, ?float $speed, ?string $uiStatus): void
    {
        $delay = (int) env('LEASE_CUTOFF_CONFIRM_DELAY_SECONDS', self::DEFAULT_CONFIRM_DELAY_SECONDS);

        DB::transaction(function () use ($item, $reason, $speed, $uiStatus, $delay) {
            $item->update([
                'status' => 'COMMAND_SENT',
                'last_checked_at' => now(),
                'retry_count' => $item->retry_count + 1,
                'next_check_at' => now()->addSeconds($delay),
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'COMMAND_SENT',
                    'reason' => $reason,
                    'speed_at_check' => $speed,
                    'ignition_state' => $uiStatus,
                    'notes' => 'Aucun renvoi de commande effectué ; le système attend encore une confirmation live du moteur.',
                ]);
            }
        });
    }

    private function markProcessedCutOff(LeaseCutoffQueue $item, array $commandResponse, ?float $speed, ?string $uiStatus, string $reason): void
    {
        DB::transaction(function () use ($item, $commandResponse, $speed, $uiStatus, $reason) {
            $item->update([
                'status' => 'PROCESSED',
                'last_checked_at' => now(),
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'CUT_OFF',
                    'reason' => $reason,
                    'cutoff_executed_at' => now(),
                    'speed_at_check' => $speed,
                    'ignition_state' => $uiStatus,
                    'command_response' => $commandResponse,
                    'notes' => 'Coupure moteur confirmée ; événement clôturé avec succès.',
                ]);
            }
        });
    }

    private function markReactivationConfirmed(LeaseCutoffQueue $item, ?float $speed, ?string $uiStatus): void
    {
        DB::transaction(function () use ($item, $speed, $uiStatus) {
            $item->update([
                'status' => 'PROCESSED',
                'last_checked_at' => now(),
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $vehicle = $item->vehicle;
                $forgivenByName = $item->history->forgiven_by_name ?: 'un employé';

                $reason = $vehicle
                    ? $this->forgiveness->describeReactivationConfirmed($vehicle, $item->contractLink, $forgivenByName)
                    : 'Rallumage confirmé après pardon.';

                $item->history->update([
                    'status' => 'REACTIVATED_AFTER_FORGIVENESS',
                    'reason' => $reason,
                    'speed_at_check' => $speed,
                    'ignition_state' => $uiStatus,
                    'notes' => 'Rallumage confirmé par l’état moteur live après pardon ; événement clôturé avec succès.',
                ]);
            }
        });
    }

    private function markReactivationStillPending(LeaseCutoffQueue $item, string $engineState, int $maxChecks): void
    {
        $delay = (int) env('LEASE_CUTOFF_CONFIRM_DELAY_SECONDS', self::DEFAULT_CONFIRM_DELAY_SECONDS);

        DB::transaction(function () use ($item, $engineState, $maxChecks, $delay) {
            $item->update([
                'status' => 'COMMAND_SENT',
                'last_checked_at' => now(),
                'retry_count' => $item->retry_count + 1,
                'next_check_at' => now()->addSeconds($delay),
            ]);

            if ($item->history) {
                $item->history->update([
                    'reason' => sprintf(
                        'Rallumage transmis après pardon, en attente de confirmation moteur — vérification %d/%d. Le boîtier rapporte pour l’instant le moteur « %s » (pas encore confirmé rallumé).',
                        (int) $item->retry_count,
                        $maxChecks,
                        $engineState
                    ),
                    'notes' => 'Aucun renvoi de commande effectué ; le système attend encore une confirmation live du moteur après pardon.',
                ]);
            }
        });
    }

    private function markReactivationFailed(LeaseCutoffQueue $item, ?string $deviceDiagnostic, int $maxChecks): void
    {
        DB::transaction(function () use ($item, $deviceDiagnostic, $maxChecks) {
            $item->update([
                'status' => 'FAILED',
                'last_checked_at' => now(),
                'retry_count' => $item->retry_count + 1,
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $vehicle = $item->vehicle;
                $forgivenByName = $item->history->forgiven_by_name ?: 'un employé';

                $reason = $vehicle
                    ? $this->forgiveness->describeReactivationNotConfirmed($vehicle, $item->contractLink, $forgivenByName, $maxChecks, $deviceDiagnostic)
                    : 'Rallumage après pardon jamais confirmé par le boîtier.';

                $item->history->update([
                    'status' => 'REACTIVATION_FAILED_AFTER_FORGIVENESS',
                    'reason' => $reason,
                    'notes' => 'Échec final de la confirmation de rallumage après pardon ; aucune nouvelle tentative automatique ne sera lancée par cette queue.',
                ]);
            }
        });
    }

    /**
     * Interroge le boîtier lui-même (GetCommandResults, via le cmd_no de la
     * commande envoyée) pour obtenir le VRAI diagnostic du provider — plus
     * fiable qu'une liste de causes probables. Ex. observé en prod : le
     * provider accepte la commande (SEND_OK) mais le boîtier répond ensuite
     * "Not responding!" à GetCommandResults, preuve qu'il n'a jamais exécuté
     * la commande malgré l'accusé de réception initial.
     */
    private function describeDeviceDiagnostic(LeaseCutoffQueue $item, string $macId): ?string
    {
        $cmdNo = $item->history?->command_response['cmd_no'] ?? null;

        if (! is_string($cmdNo) || trim($cmdNo) === '' || str_starts_with($cmdNo, '00000000-0000')) {
            return null;
        }

        try {
            $result = $this->gps->getCommandResults($macId, trim($cmdNo));
        } catch (\Throwable $e) {
            Log::warning('[LEASE_CUTOFF_PROCESS] Lecture diagnostic boîtier impossible', [
                'mac_id_gps' => $macId,
                'cmd_no' => $cmdNo,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $row = $result['data'][0] ?? null;
        $msg = trim((string) ($row['ResponseMsg'] ?? $row['Msg'] ?? ''));

        return $msg !== '' ? $msg : null;
    }

    private function markFailed(LeaseCutoffQueue $item, string $reason): void
    {
        DB::transaction(function () use ($item, $reason) {
            $item->update([
                'status' => 'FAILED',
                'last_checked_at' => now(),
                'retry_count' => $item->retry_count + 1,
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'FAILED',
                    'reason' => $reason,
                    'notes' => 'Échec final du traitement automatique de coupure ; aucune nouvelle tentative automatique ne sera lancée par cette queue.',
                ]);
            }
        });
    }

    private function extractDueDateFromQueue(LeaseCutoffQueue $item): ?string
    {
        if ($item->lease_date_echeance) {
            return $item->lease_date_echeance->toDateString();
        }

        $payload = $item->trigger_payload;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($payload)) {
            $payload = [];
        }

        $date = $payload['date_echeance'] ?? null;
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}