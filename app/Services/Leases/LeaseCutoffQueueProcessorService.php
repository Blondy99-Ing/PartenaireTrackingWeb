<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffContractRule;
use App\Models\LeaseCutoffEvent;
use App\Models\LeaseCutoffHistory;
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
    /**
     * Fenêtre de confirmation (commande envoyée -> confirmation moteur réelle) :
     * ~20 minutes. Le cron lease:cutoff:process tourne une fois par minute
     * (withoutOverlapping), donc le vrai intervalle entre deux vérifications
     * est borné par cette cadence, pas par CONFIRM_DELAY_SECONDS lui-même —
     * DEFAULT_CONFIRM_MAX_CHECKS * ~1 passage/minute ≈ 20 minutes réelles.
     */
    private const DEFAULT_CONFIRM_MAX_CHECKS = 20;
    private const DEFAULT_CONFIRM_DELAY_SECONDS = 60;
    private const DEFAULT_WAITING_DELAY_MINUTES = 1;

    /**
     * Garde-fou : plafonne le nombre de véhicules traités en un seul passage.
     * Sans ça, un pic d'activité (ex. 200+ véhicules dus le même jour) peut
     * faire durer un passage bien au-delà de la minute suivante — les items
     * les plus anciens (triés par scheduled_for) restent prioritaires, le
     * reste attend simplement le passage suivant au lieu de tout retarder.
     */
    private const MAX_ITEMS_PER_RUN = 300;

    /**
     * Seuil d'alerte sur la durée d'un passage. Volontairement très en dessous
     * de l'expiration du verrou anti-chevauchement (30 min, routes/console.php)
     * pour laisser le temps de réagir : un passage dure normalement moins d'une
     * minute.
     */
    private const RUN_DURATION_ALERT_SECONDS = 240;

    public function __construct(
        private readonly LeaseApiClientService $leaseApi,
        private readonly GpsControlService $gps,
        private readonly GpsCommandDispatcherService $dispatcher,
        private readonly LeaseForgivenessService $forgiveness
    ) {
    }

    public function process(?string $dateEcheance = null): array
    {
        /**
         * Mesure de durée du passage : c'est le signal d'alerte de l'anomalie
         * du 21/08/2026 (voir le commentaire du verrou dans routes/console.php).
         * Tant qu'un passage reste très en dessous de l'expiration du verrou
         * anti-chevauchement, deux passages ne peuvent pas se marcher dessus.
         * Au-delà du seuil d'alerte ci-dessous, on trace un avertissement
         * explicite AVANT que le scénario ne redevienne possible.
         */
        $startedAt = microtime(true);

        /**
         * Règle stricte demandée : chaque échéance est traitée indépendamment
         * de celles des jours précédents, sans exception. Sans ce nettoyage,
         * une ligne encore active (véhicule jamais à l'arrêt, commande jamais
         * confirmée) au moment où le jour change reste bloquée en silence
         * pour toujours — invisible au traitement (la requête ci-dessous ne
         * sélectionne que la date du jour), mais jamais marquée comme
         * abandonnée non plus. On l'expire donc explicitement ici, avec une
         * trace claire, dès que le cron réel (pas une reprise --date=)
         * détecte qu'elle appartient à une date déjà passée.
         */
        if ($dateEcheance === null) {
            $expiredCount = $this->expireStaleQueueItems();

            if ($expiredCount > 0) {
                Log::warning('[LEASE_CUTOFF_PROCESS] Lignes d’échéances passées expirées (aucune coupure rétroactive).', [
                    'count' => $expiredCount,
                ]);
            }
        }

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
            ->limit(self::MAX_ITEMS_PER_RUN)
            ->get();

        Log::info('[LEASE_CUTOFF_PROCESS] Queues sélectionnées pour la date du jour', [
            'target_date_echeance' => $targetDate,
            'items_count' => $items->count(),
            'queue_ids' => $items->pluck('id')->values()->all(),
        ]);

        if ($items->count() >= self::MAX_ITEMS_PER_RUN) {
            Log::warning('[LEASE_CUTOFF_PROCESS] Plafond MAX_ITEMS_PER_RUN atteint : des véhicules dus restent en attente du passage suivant.', [
                'target_date_echeance' => $targetDate,
                'limit' => self::MAX_ITEMS_PER_RUN,
            ]);
        }

        /**
         * Préchargé UNE FOIS pour tout le passage (pas par véhicule) : voir
         * GpsControlService::preloadDeviceLists() pour la raison exacte.
         */
        $deviceLists = $items->isNotEmpty() ? $this->gps->preloadDeviceLists() : [];

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
                    $this->markFailed($item, 'Impossible de vérifier le paiement : le dossier de ce véhicule est incomplet.');
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : lease_id manquant', $ctx);
                    continue;
                }

                if (! $dueDate) {
                    $this->markFailed($item, 'Impossible de vérifier le paiement : la date d’échéance de ce dossier est manquante.');
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
                            'Le dossier de paiement de ce contrat est introuvable au moment de la vérification. La coupure est annulée par précaution, sans confirmation de paiement.'
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
                                sprintf('Le paiement de ce contrat a été reçu (montant : %s). La coupure automatique est annulée.',
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
                                'Ce contrat n’est plus marqué comme impayé, mais aucun paiement n’a pu être retrouvé. La coupure est annulée par précaution — à vérifier manuellement.'
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
                                'La coupure est annulée : aucune règle de coupure n’est configurée pour ce contrat.'
                            );
                            $cancelled++;
                            Log::warning('[LEASE_CUTOFF_PROCESS] Queue annulée : règle spécifique absente ou désactivée', $ctx);
                            continue;
                        }

                        if (! $activeRule->effectiveCutoffTime()) {
                            $this->markCancelledRule(
                                $item,
                                'CANCELLED_RULE_DISABLED',
                                'La coupure est annulée : aucune heure de coupure n’est définie pour ce contrat.'
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
                    $this->markFailed($item, 'Ce véhicule n’a pas été retrouvé dans le système ; le traitement est impossible.');
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : véhicule local introuvable', $ctx);
                    continue;
                }

                if (empty($vehicle->mac_id_gps)) {
                    $this->markFailed($item, 'Impossible d’envoyer l’ordre de coupure : ce véhicule n’a pas de boîtier GPS associé.');
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
                $vehicleState = $this->gps->getVehicleStateByMacId($macId, $movingThreshold, $deviceLists);

                if (! ($vehicleState['success'] ?? false)) {
                    $this->markWaiting($item, 'WAITING_STATE_UNKNOWN', 'En attente : l’état du véhicule n’a pas pu être vérifié pour le moment. La coupure sera retentée automatiquement dès que l’information sera disponible.', null, null);
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
                            $this->markReactivationSentUnconfirmed($item, $deviceDiagnostic, $maxChecks);
                            $failed++;
                            Log::warning('[LEASE_CUTOFF_PROCESS] Rallumage envoyé mais non confirmé après plusieurs vérifications', array_merge($ctx, [
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
                            ['source' => 'post_send_verification', 'message' => 'La commande précédemment envoyée est confirmée par l’état actuel du moteur.'],
                            $speed,
                            $uiStatus,
                            'Coupure confirmée : le moteur du véhicule est bien coupé. La commande a été appliquée avec succès.'
                        );
                        $processed++;
                        Log::info('[LEASE_CUTOFF_PROCESS] Succès : commande confirmée après vérification différée', $ctx);
                        continue;
                    }

                    if ($item->retry_count >= $maxChecks) {
                        $deviceDiagnostic = $this->describeDeviceDiagnostic($item, $macId);

                        $this->markCommandSentUnconfirmed($item, 'La commande de coupure a bien été envoyée au véhicule, mais elle n’a pas pu être confirmée après plusieurs tentatives : le moteur semble toujours en marche. Une vérification manuelle du véhicule est recommandée.');
                        $failed++;
                        Log::warning('[LEASE_CUTOFF_PROCESS] Commande envoyée mais non confirmée après plusieurs vérifications', array_merge($ctx, [
                            'device_diagnostic' => $deviceDiagnostic,
                        ]));
                        continue;
                    }

                    $this->markCommandStillPending(
                        $item,
                        sprintf('L’ordre de coupure a été envoyé. Le système attend la confirmation que le moteur est bien coupé (tentative %d sur %d).', (int) $item->retry_count, $maxChecks),
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
                        ['source' => 'engine_state_before_send', 'message' => 'Le moteur apparaissait déjà coupé au moment de la vérification, avant tout nouvel envoi.'],
                        $speed,
                        $uiStatus,
                        'Le moteur était déjà coupé au moment de la vérification ; aucune nouvelle commande n’a été nécessaire.'
                    );
                    $processed++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Succès : véhicule déjà coupé avant envoi', $ctx);
                    continue;
                }

                if ($isOnline === false) {
                    $this->markWaiting($item, 'WAITING_OFFLINE', 'En attente : le véhicule est injoignable pour le moment (GPS hors ligne). La coupure sera effectuée automatiquement dès qu’il sera de nouveau joignable.', $speed, $uiStatus);
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : véhicule offline', $ctx);
                    continue;
                }

                if ($isMoving === null) {
                    $this->markWaiting($item, 'WAITING_MOVEMENT_UNCERTAIN', 'En attente : impossible de confirmer si le véhicule est à l’arrêt. Par sécurité, la coupure est reportée jusqu’à ce que l’information soit fiable.', $speed, $uiStatus);
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : mouvement incertain', $ctx);
                    continue;
                }

                if ($isMoving === true) {
                    $this->markWaiting($item, 'WAITING_MOVING', sprintf('En attente : le véhicule est actuellement en circulation (%s km/h). Par sécurité, la coupure n’est effectuée qu’à l’arrêt complet du véhicule.', $speed !== null ? $speed : '?'), $speed, $uiStatus);
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
                    $this->markFailed($item, 'La commande de coupure a été refusée par le système GPS. Elle n’a pas pu être transmise au véhicule.');
                    $failed++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : provider a rejeté la commande', array_merge($ctx, [
                        'command_result' => $command,
                    ]));
                    continue;
                }

                $this->markCommandSent(
                    $item,
                    $command,
                    $speed,
                    $uiStatus,
                    $commandStatus === 'PENDING_VERIFICATION'
                        ? 'L’ordre de coupure a été transmis. Une vérification supplémentaire est nécessaire avant confirmation. Le système attend la confirmation que le moteur est bien coupé.'
                        : 'L’ordre de coupure a été envoyé et accepté. Le système attend la confirmation que le moteur est bien coupé avant de conclure.'
                );
                $waiting++;
                Log::info('[LEASE_CUTOFF_PROCESS] Commande envoyée, passage en COMMAND_SENT', array_merge($ctx, [
                    'command_status' => $commandStatus,
                ]));
            } catch (\Throwable $e) {
                $this->markFailed($item, 'Une erreur inattendue est survenue pendant le traitement de ce véhicule. L’équipe technique a été notifiée.');
                $failed++;
                Log::error('[LEASE_CUTOFF_PROCESS] Exception pendant le traitement', array_merge($ctx, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]));
            }
        }

        $durationSeconds = round(microtime(true) - $startedAt, 1);

        Log::info('[LEASE_CUTOFF_PROCESS] Fin du traitement de queue', array_merge(
            ['target_date_echeance' => $targetDate, 'duration_seconds' => $durationSeconds],
            compact('processed', 'waiting', 'cancelled', 'failed')
        ));

        /**
         * Le verrou anti-chevauchement expire à 30 min (routes/console.php).
         * On alerte bien avant : un passage qui approche cette durée annonce
         * le retour possible du scénario de doublons du 21/08/2026.
         */
        if ($durationSeconds >= self::RUN_DURATION_ALERT_SECONDS) {
            Log::warning('[LEASE_CUTOFF_PROCESS] Passage anormalement long : risque de chevauchement avec le passage suivant.', [
                'target_date_echeance' => $targetDate,
                'duration_seconds' => $durationSeconds,
                'alert_threshold_seconds' => self::RUN_DURATION_ALERT_SECONDS,
                'items_traites' => $processed + $waiting + $cancelled + $failed,
            ]);
        }

        return [
            'success' => true,
            'target_date_echeance' => $targetDate,
            'duration_seconds' => $durationSeconds,
            'processed' => $processed,
            'waiting' => $waiting,
            'cancelled' => $cancelled,
            'failed' => $failed,
        ];
    }

    /**
     * Abandonne définitivement toute ligne de queue encore active
     * (PENDING/WAITING_STOP/COMMAND_SENT) dont l'échéance n'est plus celle
     * d'aujourd'hui — jamais de coupure rétroactive pour une échéance
     * passée, jamais de ligne orpheline qui reste bloquée en silence.
     * Verrouillée ligne par ligne (comme le reste du service) pour éviter
     * toute course avec le pardon ou une autre exécution du cron.
     */
    public function expireStaleQueueItems(): int
    {
        $timezone = config('app.timezone', 'Africa/Douala');
        $today = Carbon::now($timezone)->toDateString();

        $staleIds = LeaseCutoffQueue::query()
            ->whereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])
            ->whereDate('lease_date_echeance', '<', $today)
            ->pluck('id');

        $expired = 0;

        foreach ($staleIds as $queueId) {
            DB::transaction(function () use ($queueId, &$expired) {
                $queue = LeaseCutoffQueue::query()->lockForUpdate()->find($queueId);

                if (! $queue || ! in_array($queue->status, ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'], true)) {
                    return;
                }

                $previousStatus = $queue->status;

                $queue->update([
                    'status' => 'CANCELLED',
                    'last_checked_at' => now(),
                    'next_check_at' => null,
                ]);

                $history = $queue->history_id
                    ? LeaseCutoffHistory::query()->lockForUpdate()->find($queue->history_id)
                    : null;

                if ($history && ! in_array($history->status, ['CUT_OFF', 'CANCELLED_DAY_EXPIRED'], true)) {
                    $history->update([
                        'status' => 'CANCELLED_DAY_EXPIRED',
                        'notes' => trim(($history->notes ? $history->notes . ' | ' : ''))
                            . "Échéance du {$queue->lease_date_echeance->toDateString()} expirée sans coupure confirmée avant le changement de jour. "
                            . 'Aucune coupure rétroactive : le jour suivant est traité indépendamment de celui-ci.',
                    ]);
                }

                $expired++;

                Log::warning('[LEASE_CUTOFF_PROCESS] Échéance expirée sans coupure confirmée.', [
                    'queue_id' => $queue->id,
                    'history_id' => $history?->id,
                    'lease_id' => $queue->lease_id,
                    'vehicle_id' => $queue->vehicle_id,
                    'lease_date_echeance' => $queue->lease_date_echeance?->toDateString(),
                    'previous_status' => $previousStatus,
                ]);
            });
        }

        return $expired;
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

    /**
     * Ajoute une ligne au journal chronologique du cycle (lease_cutoff_events),
     * SANS jamais rien écraser — contrairement à lease_cutoff_histories.reason
     * /notes, qui ne reflètent que le dernier état. C'est cette table qui
     * permet de retracer après coup pourquoi une coupure prévue à 12h n'a été
     * confirmée qu'à 16h (offline ? en mouvement ? commande en attente ?).
     */
    private function recordEvent(
        LeaseCutoffQueue $item,
        string $eventType,
        string $message,
        ?float $speed = null,
        ?string $uiStatus = null
    ): void {
        if (! $item->history_id) {
            return;
        }

        LeaseCutoffEvent::create([
            'history_id' => $item->history_id,
            'queue_id' => $item->id,
            'event_type' => $eventType,
            'message' => $message,
            'speed_at_check' => $speed,
            'ignition_state' => $uiStatus,
            'retry_count' => $item->retry_count,
            'occurred_at' => now(),
        ]);
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
                        ? 'Coupure annulée : le paiement a été confirmé.'
                        : 'Coupure annulée : ce contrat n’est plus dû.',
                ]);
            }

            $this->recordEvent($item, 'CANCELLED_PAID', $reason);
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
                    'notes' => 'Coupure annulée sans preuve de paiement : à vérifier manuellement. Ce contrat pourrait être encore dû.',
                ]);
            }

            $this->recordEvent($item, 'CANCELLED_UNVERIFIED', $reason);
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
                    'notes' => 'Aucune commande n’a été envoyée : la règle de coupure ne l’autorise plus pour ce contrat.',
                ]);
            }

            $this->recordEvent($item, $historyStatus, $reason);
        });
    }

    private function markWaiting(LeaseCutoffQueue $item, string $eventType, string $reason, ?float $speed, ?string $uiStatus): void
    {
        /**
         * Un rallumage après pardon en cours de confirmation (queue.status déjà
         * COMMAND_SENT, history.status REACTIVATION_REQUESTED_AFTER_FORGIVENESS)
         * ne doit JAMAIS transiter par ce chemin générique de coupure : ça
         * écraserait son statut (queue ET history) par WAITING_STOP, et le
         * garde $isReactivationConfirmation du passage suivant ne le
         * reconnaîtrait plus comme un rallumage — la ligne retomberait dans le
         * flux de coupure normal et pourrait recouper un véhicule qu'un
         * employé vient de pardonner. Trouvé et corrigé le 21/08/2026 (état
         * GPS indisponible pendant la confirmation d'un rallumage = seul point
         * d'entrée non protégé).
         */
        if ($item->history?->status === 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS') {
            $this->markReactivationStillPending(
                $item,
                'UNKNOWN',
                (int) env('LEASE_CUTOFF_CONFIRM_MAX_CHECKS', self::DEFAULT_CONFIRM_MAX_CHECKS)
            );

            return;
        }

        $delayMinutes = (int) env('LEASE_CUTOFF_WAITING_DELAY_MINUTES', self::DEFAULT_WAITING_DELAY_MINUTES);

        DB::transaction(function () use ($item, $eventType, $reason, $speed, $uiStatus, $delayMinutes) {
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

            $this->recordEvent($item, $eventType, $reason, $speed, $uiStatus);
        });
    }

    private function markCommandSent(LeaseCutoffQueue $item, array $commandResponse, ?float $speed, ?string $uiStatus, string $reason): void
    {
        $delay = (int) env('LEASE_CUTOFF_CONFIRM_DELAY_SECONDS', self::DEFAULT_CONFIRM_DELAY_SECONDS);

        DB::transaction(function () use ($item, $commandResponse, $speed, $uiStatus, $delay, $reason) {
            $item->update([
                'status' => 'COMMAND_SENT',
                'last_checked_at' => now(),
                /**
                 * Repart de zéro, pas +1 : retry_count compte les cycles de
                 * confirmation POST-envoi (borné par $maxChecks côté
                 * traitement), un compteur distinct des cycles d'attente
                 * PRE-envoi (véhicule en mouvement/hors ligne) qui l'ont
                 * incrémenté jusqu'ici via markWaiting(). Sans ce reset, un
                 * véhicule ayant mis du temps à s'arrêter arrivait à l'envoi
                 * avec un compteur déjà au maximum, et la toute première
                 * vérification post-envoi déclarait l'échec de confirmation
                 * sans avoir laissé la vraie fenêtre de ~20 min s'écouler.
                 * Trouvé et corrigé le 21/08/2026.
                 */
                'retry_count' => 0,
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
                    'notes' => 'La commande de coupure a été envoyée ; en attente de confirmation que le moteur est bien coupé.',
                ]);
            }

            $this->recordEvent($item, 'COMMAND_SENT', $reason, $speed, $uiStatus);
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
                    'notes' => 'Aucune nouvelle commande n’a été envoyée ; le système attend encore la confirmation que le moteur est bien coupé.',
                ]);
            }

            $this->recordEvent($item, 'COMMAND_PENDING_CONFIRMATION', $reason, $speed, $uiStatus);
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
                /**
                 * Cohérence de la chronologie détecté -> envoyé -> confirmé :
                 * quand le moteur est trouvé déjà coupé AVANT tout envoi de
                 * commande par cette ligne (contrat frère qui a déjà coupé le
                 * même véhicule, coupure manuelle antérieure...),
                 * cutoff_requested_at n'était jamais renseigné — 64% des
                 * coupures confirmées en production (2010/3131) avaient ce
                 * trou. On le comble avec l'instant de confirmation lui-même
                 * (aucune commande distincte n'a été nécessaire), sans jamais
                 * écraser un horodatage d'envoi réel déjà enregistré —
                 * idempotent : rejouer cette méthode sur la même ligne ne
                 * change plus rien. Trouvé et corrigé le 18/08/2026.
                 */
                $item->history->update([
                    'status' => 'CUT_OFF',
                    'reason' => $reason,
                    'cutoff_requested_at' => $item->history->cutoff_requested_at ?? now(),
                    'cutoff_executed_at' => now(),
                    'speed_at_check' => $speed,
                    'ignition_state' => $uiStatus,
                    'command_response' => $commandResponse,
                    'notes' => 'La coupure du moteur a été confirmée avec succès.',
                ]);
            }

            $this->recordEvent($item, 'CUT_OFF_CONFIRMED', $reason, $speed, $uiStatus);
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
                    'notes' => 'Le rallumage après pardon a été confirmé avec succès.',
                ]);

                $this->recordEvent($item, 'REACTIVATION_CONFIRMED', $reason, $speed, $uiStatus);
            }

            $this->finalizeCascadedSiblings($item, 'REACTIVATED_AFTER_FORGIVENESS', 'Rallumage confirmé conjointement avec le contrat principal du pardon.');
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
                $reason = sprintf(
                    'Le rallumage a été transmis. Le système attend la confirmation que le moteur est bien remis en marche (tentative %d sur %d).',
                    (int) $item->retry_count,
                    $maxChecks
                );

                $item->history->update([
                    'reason' => $reason,
                    'notes' => 'Aucune nouvelle commande n’a été envoyée ; le système attend encore la confirmation que le moteur est bien remis en marche.',
                ]);

                $this->recordEvent($item, 'REACTIVATION_PENDING_CONFIRMATION', $reason);
            }
        });
    }

    /**
     * Commande de rallumage bel et bien ENVOYÉE au boîtier, mais jamais
     * confirmée par l'état moteur réel malgré la fenêtre complète de
     * vérifications. Statut volontairement distinct de "FAILED" : "échec"
     * donnerait l'impression que la commande n'est même pas partie, alors
     * qu'elle a bien été transmise — seule sa confirmation manque.
     */
    private function markReactivationSentUnconfirmed(LeaseCutoffQueue $item, ?string $deviceDiagnostic, int $maxChecks): void
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
                    : 'Le rallumage après pardon a été transmis mais n’a jamais pu être confirmé.';

                $item->history->update([
                    'status' => 'REACTIVATION_SENT_UNCONFIRMED',
                    'reason' => $reason,
                    'notes' => 'Le rallumage a bien été transmis au boîtier, mais aucune confirmation n’a été reçue ; aucune nouvelle tentative automatique ne sera lancée pour ce contrat.',
                ]);

                $this->recordEvent($item, 'REACTIVATION_SENT_UNCONFIRMED', $reason);
            }

            $this->finalizeCascadedSiblings($item, 'REACTIVATION_SENT_UNCONFIRMED', 'Rallumage transmis mais non confirmé, clôturé conjointement avec le contrat principal du pardon.');
        });
    }

    /**
     * Un pardon "cascade" (option "Pardonner tout" sur un rallumage bloqué par
     * des contrats frères) ne déclenche qu'une seule commande GPS pour le
     * véhicule ; les contrats frères pardonnés en cascade restent en attente
     * (REACTIVATION_REQUESTED_AFTER_FORGIVENESS) jusqu'à ce que CETTE queue —
     * celle du contrat d'origine — soit confirmée ou échoue. On reporte alors
     * le même dénouement sur chacun d'eux.
     */
    private function finalizeCascadedSiblings(LeaseCutoffQueue $item, string $finalStatus, string $noteSuffix): void
    {
        $payload = $item->trigger_payload;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $cascadedHistoryIds = is_array($payload) ? ($payload['cascaded_history_ids'] ?? []) : [];

        if (empty($cascadedHistoryIds)) {
            return;
        }

        LeaseCutoffHistory::query()
            ->whereIn('id', $cascadedHistoryIds)
            ->update([
                'status' => $finalStatus,
                'notes' => DB::raw("CONCAT(COALESCE(notes, ''), '\n" . addslashes($noteSuffix) . "')"),
            ]);
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
                    'notes' => 'La coupure automatique a échoué ; aucune nouvelle tentative automatique ne sera lancée pour ce véhicule.',
                ]);

                $this->recordEvent($item, 'FAILED', $reason);
            }
        });
    }

    /**
     * Commande de coupure bel et bien ENVOYÉE au véhicule, mais jamais
     * confirmée par l'état moteur réel malgré la fenêtre complète de
     * vérifications (~20 min). Statut volontairement distinct de "FAILED" :
     * "échec" donnerait l'impression que la commande n'est même pas partie,
     * alors qu'elle a bien été transmise — seule sa confirmation manque.
     */
    private function markCommandSentUnconfirmed(LeaseCutoffQueue $item, string $reason): void
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
                    'status' => 'COMMAND_SENT_UNCONFIRMED',
                    'reason' => $reason,
                    'notes' => 'La commande de coupure a bien été envoyée au véhicule, mais aucune confirmation n’a été reçue ; aucune nouvelle tentative automatique ne sera lancée pour ce véhicule.',
                ]);

                $this->recordEvent($item, 'COMMAND_SENT_UNCONFIRMED', $reason);
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