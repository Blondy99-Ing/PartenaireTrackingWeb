<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffQueue;
use App\Services\Gps\GpsCommandDispatcherService;
use App\Services\GpsControlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaseCutoffQueueProcessorService
{
    /**
     * Nombre maximum de vérifications différées après envoi de commande,
     * avant de conclure à un échec final de confirmation moteur.
     */
    private const DEFAULT_CONFIRM_MAX_CHECKS = 6;

    /**
     * Délai standard avant recontrôle après une commande envoyée.
     */
    private const DEFAULT_CONFIRM_DELAY_SECONDS = 20;

    /**
     * Délai standard avant nouvelle tentative de lecture d'état live.
     */
    private const DEFAULT_WAITING_DELAY_MINUTES = 1;

    public function __construct(
        private readonly LeaseApiClientService $leaseApi,
        private readonly GpsControlService $gps,
        private readonly GpsCommandDispatcherService $dispatcher
    ) {
    }

    /**
     * Traite la queue de coupure sans suppression physique.
     *
     * Principes de sûreté :
     * - un événement déjà planifié est suivi par sa queue et son history
     * - la queue n'est jamais supprimée, elle change de statut
     * - la coupure n'est jamais envoyée si le véhicule est en mouvement
     * - si le provider GPS est lent / ambigu, on attend et on recontrôle
     * - après envoi de commande, on privilégie la confirmation réelle du moteur
     * - FAILED ne doit arriver qu'en cas d'échec explicite ou après vérifications suffisantes
     */
    public function process(): array
    {
        Log::info('[LEASE_CUTOFF_PROCESS] Début du traitement de queue');

        $nonPaidByContractId = $this->leaseApi->fetchLatestNonPaidLeasesIndexedByContractId();

        $items = LeaseCutoffQueue::query()
            ->with(['vehicle', 'history', 'rule'])
            ->whereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])
            ->where(function ($q) {
                $q->whereNull('next_check_at')
                    ->orWhere('next_check_at', '<=', now());
            })
            ->orderBy('scheduled_for')
            ->get();

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
                'queue_status' => $item->status,
                'retry_count' => $item->retry_count,
            ];

            try {
                /**
                 * 1) Vérification métier : le lease est-il toujours NON_PAYE ?
                 * ------------------------------------------------------------
                 * Si le paiement a été régularisé entre-temps, on annule définitivement
                 * sans jamais envoyer de coupure.
                 */
                $stillNonPaid = isset($nonPaidByContractId[(int) $item->contract_id]);

                if (!$stillNonPaid) {
                    $this->markCancelledPaid(
                        $item,
                        'Le lease n’est plus NON_PAYE au moment du traitement : paiement régularisé avant la coupure automatique.'
                    );
                    $cancelled++;

                    Log::info('[LEASE_CUTOFF_PROCESS] Queue annulée : paiement reçu entre-temps', $ctx);
                    continue;
                }

                /**
                 * 2) Validation du véhicule et du mac_id_gps
                 * ------------------------------------------
                 * Sans mac_id_gps, il est impossible d'interroger le provider GPS.
                 */
                $vehicle = $item->vehicle;

                if (!$vehicle) {
                    $this->markFailed(
                        $item,
                        'Le véhicule lié à cette queue est introuvable localement ; traitement impossible.'
                    );
                    $failed++;

                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : véhicule local introuvable', $ctx);
                    continue;
                }

                if (empty($vehicle->mac_id_gps)) {
                    $this->markFailed(
                        $item,
                        'Le véhicule ne possède aucun mac_id_gps ; impossible d’interroger le provider GPS pour vérifier l’état moteur.'
                    );
                    $failed++;

                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : mac_id_gps manquant', $ctx);
                    continue;
                }

                $macId = trim((string) $vehicle->mac_id_gps);
                $ctx['mac_id_gps'] = $macId;
                $ctx['immatriculation'] = $vehicle->immatriculation ?? null;

                /**
                 * 3) Lecture de l’état live du véhicule
                 * -------------------------------------
                 * On coupe uniquement si :
                 * - l'état live est lisible
                 * - le véhicule n'est pas en mouvement
                 */
                $movingThreshold = (float) env('GPS_MOVING_THRESHOLD', 5.0);
                $vehicleState = $this->gps->getVehicleStateByMacId($macId, $movingThreshold);

                if (!($vehicleState['success'] ?? false)) {
                    $this->markWaiting(
                        $item,
                        'Lecture de l’état live impossible auprès du provider GPS ; coupure reportée en attente d’un nouvel essai.',
                        null,
                        null
                    );
                    $waiting++;

                    Log::warning('[LEASE_CUTOFF_PROCESS] Attente : état véhicule indisponible', array_merge($ctx, [
                        'vehicle_state' => $vehicleState,
                    ]));
                    continue;
                }

                $speed = isset($vehicleState['speed']) && is_numeric($vehicleState['speed'])
                    ? (float) $vehicleState['speed']
                    : null;

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

                /**
                 * 4) Si le moteur est déjà coupé, on termine proprement
                 * -----------------------------------------------------
                 * Cela couvre :
                 * - une coupure déjà effective
                 * - une action externe déjà réalisée
                 * - une confirmation moteur antérieure
                 */
                if ($engineState === 'CUT') {
                    $this->markProcessedCutOff(
                        $item,
                        [
                            'source' => 'live_engine_state',
                            'message' => 'Le moteur apparaît déjà coupé dans l’état live du provider GPS.',
                        ],
                        $speed,
                        $uiStatus,
                        'Le moteur est déjà confirmé coupé lors de la vérification live ; aucune nouvelle commande n’a été nécessaire.'
                    );

                    $processed++;

                    Log::info('[LEASE_CUTOFF_PROCESS] Succès : véhicule déjà coupé', $ctx);
                    continue;
                }

                /**
                 * 5) Si une commande a déjà été envoyée, on ne renvoie pas
                 * --------------------------------------------------------
                 * On évite tout renvoi en boucle et on attend seulement
                 * la confirmation réelle de l'état moteur.
                 */
                if ($item->status === 'COMMAND_SENT') {
                    $maxChecks = (int) env('LEASE_CUTOFF_CONFIRM_MAX_CHECKS', self::DEFAULT_CONFIRM_MAX_CHECKS);

                    if ($engineState === 'CUT') {
                        $this->markProcessedCutOff(
                            $item,
                            [
                                'source' => 'post_send_verification',
                                'message' => 'La commande précédemment envoyée est désormais confirmée par l’état moteur live.',
                            ],
                            $speed,
                            $uiStatus,
                            'La commande de coupure avait déjà été envoyée ; le moteur est maintenant confirmé coupé après vérification différée.'
                        );

                        $processed++;

                        Log::info('[LEASE_CUTOFF_PROCESS] Succès : commande confirmée après vérification différée', $ctx);
                        continue;
                    }

                    if ($item->retry_count >= $maxChecks) {
                        $this->markFailed(
                            $item,
                            'La commande de coupure a déjà été envoyée, mais le moteur n’a pas pu être confirmé coupé après plusieurs vérifications différées. Échec final de confirmation.'
                        );
                        $failed++;

                        Log::warning('[LEASE_CUTOFF_PROCESS] Échec : commande non confirmée après plusieurs vérifications', $ctx);
                        continue;
                    }

                    $this->markCommandStillPending(
                        $item,
                        'La commande de coupure a déjà été envoyée ; le système attend encore la confirmation réelle de l’état moteur avant de conclure.',
                        $speed,
                        $uiStatus
                    );
                    $waiting++;

                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : commande déjà envoyée, pas de renvoi', $ctx);
                    continue;
                }

                /**
                 * 6) Cas d’attente : véhicule offline ou état de mouvement non fiable
                 * -------------------------------------------------------------------
                 * On ne coupe jamais si l’information de sécurité n’est pas suffisamment fiable.
                 */
                if ($isOnline === false) {
                    $this->markWaiting(
                        $item,
                        'Le véhicule est actuellement offline côté provider GPS ; la coupure automatique est reportée jusqu’à récupération d’un état live exploitable.',
                        $speed,
                        $uiStatus
                    );
                    $waiting++;

                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : véhicule offline', $ctx);
                    continue;
                }

                if ($isMoving === null) {
                    $this->markWaiting(
                        $item,
                        'L’état de mouvement du véhicule est incertain ou ambigu ; la coupure automatique est suspendue par sécurité en attendant une lecture plus fiable.',
                        $speed,
                        $uiStatus
                    );
                    $waiting++;

                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : état de mouvement incertain', $ctx);
                    continue;
                }

                /**
                 * 7) Si le véhicule roule encore, on attend son arrêt
                 * ---------------------------------------------------
                 * Règle de sécurité métier absolue.
                 */
                if ($isMoving === true) {
                    $this->markWaiting(
                        $item,
                        'Le véhicule est encore en mouvement ; la coupure moteur est volontairement différée jusqu’à l’arrêt complet pour des raisons de sécurité.',
                        $speed,
                        $uiStatus
                    );
                    $waiting++;

                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : véhicule en mouvement', $ctx);
                    continue;
                }

                /**
                 * 8) Ici, le véhicule est considéré à l’arrêt et exploitable
                 * ----------------------------------------------------------
                 * On envoie la commande une seule fois, puis on attend
                 * la confirmation réelle du moteur.
                 */
                $command = $this->dispatcher->dispatchCutByMacId($macId);

                Log::info('[LEASE_CUTOFF_PROCESS] Résultat envoi commande', array_merge($ctx, [
                    'command_result' => $command,
                ]));

                $commandStatus = (string) ($command['status'] ?? 'FAILED');

                if ($commandStatus === 'FAILED') {
                    $this->markFailed(
                        $item,
                        'Le provider GPS a explicitement rejeté ou échoué l’envoi de la commande de coupure moteur.'
                    );
                    $failed++;

                    Log::warning('[LEASE_CUTOFF_PROCESS] Échec : provider a rejeté explicitement la commande', $ctx);
                    continue;
                }

                /**
                 * Cas SENT ou PENDING_VERIFICATION :
                 * - on ne conclut pas à un échec
                 * - on mémorise que la commande a été envoyée
                 * - on recontrôle l’état moteur plus tard
                 */
                $this->markCommandSent(
                    $item,
                    $command,
                    $speed,
                    $uiStatus,
                    $commandStatus === 'PENDING_VERIFICATION'
                        ? 'La commande de coupure a été transmise, mais le provider GPS demande une vérification différée avant confirmation définitive.'
                        : 'La commande de coupure a été envoyée au provider GPS ; le système attend maintenant la confirmation réelle du moteur.'
                );

                $waiting++;

                Log::info('[LEASE_CUTOFF_PROCESS] Commande envoyée, passage en COMMAND_SENT', $ctx);
            } catch (\Throwable $e) {
                $this->markFailed(
                    $item,
                    'Une exception technique est survenue pendant le traitement de la queue : ' . $e->getMessage()
                );
                $failed++;

                Log::error('[LEASE_CUTOFF_PROCESS] Exception pendant le traitement', array_merge($ctx, [
                    'error' => $e->getMessage(),
                ]));
            }
        }

        Log::info('[LEASE_CUTOFF_PROCESS] Fin du traitement de queue', [
            'processed' => $processed,
            'waiting' => $waiting,
            'cancelled' => $cancelled,
            'failed' => $failed,
        ]);

        return [
            'success' => true,
            'processed' => $processed,
            'waiting' => $waiting,
            'cancelled' => $cancelled,
            'failed' => $failed,
        ];
    }

    /**
     * Paiement reçu : l'événement est annulé proprement sans suppression.
     */
    private function markCancelledPaid(LeaseCutoffQueue $item, string $reason): void
    {
        DB::transaction(function () use ($item, $reason) {
            $item->update([
                'status' => 'CANCELLED',
                'last_checked_at' => now(),
                'next_check_at' => null,
            ]);

            if ($item->history) {
                $item->history->update([
                    'status' => 'CANCELLED_PAID',
                    'reason' => $reason,
                    'notes' => 'Événement clôturé sans coupure : le lease a été régularisé avant l’exécution de la coupure automatique.',
                ]);
            }
        });
    }

    /**
     * Cas d’attente opérationnelle :
     * - véhicule en mouvement
     * - véhicule offline
     * - état live indisponible
     * - état de mouvement ambigu
     */
    private function markWaiting(
        LeaseCutoffQueue $item,
        string $reason,
        ?float $speed,
        ?string $uiStatus
    ): void {
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

    /**
     * Une commande a été envoyée.
     * On ne conclut pas encore à une coupure effective sans confirmation moteur.
     */
    private function markCommandSent(
        LeaseCutoffQueue $item,
        array $commandResponse,
        ?float $speed,
        ?string $uiStatus,
        string $reason
    ): void {
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

    /**
     * La commande a déjà été envoyée : on ne renvoie pas,
     * on attend simplement encore une confirmation live.
     */
    private function markCommandStillPending(
        LeaseCutoffQueue $item,
        string $reason,
        ?float $speed,
        ?string $uiStatus
    ): void {
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

    /**
     * Succès final confirmé.
     * La queue est conservée avec le statut PROCESSED.
     */
    private function markProcessedCutOff(
        LeaseCutoffQueue $item,
        array $commandResponse,
        ?float $speed,
        ?string $uiStatus,
        string $reason
    ): void {
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
                    'notes' => 'Coupure moteur considérée comme effective et confirmée ; événement clôturé avec succès.',
                ]);
            }
        });
    }

    /**
     * Échec final.
     * À utiliser seulement pour :
     * - rejet provider explicite
     * - impossibilité technique définitive
     * - absence de confirmation après plusieurs vérifications
     * - exception technique non récupérable dans ce cycle
     */
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
}