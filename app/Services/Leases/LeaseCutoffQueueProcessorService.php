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
        private readonly GpsCommandDispatcherService $dispatcher
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
        $nonPaidByDate = [];

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

                if (! isset($nonPaidByDate[$dueDate])) {
                    $nonPaidByDate[$dueDate] = $this->leaseApi->fetchNonPaidLeasesForDateIndexedByLeaseId($dueDate);

                    Log::info('[LEASE_CUTOFF_PROCESS] Revalidation NON_PAYE par date', array_merge($ctx, [
                        'date_echeance' => $dueDate,
                        'received_lease_ids' => array_keys($nonPaidByDate[$dueDate]),
                        'received_count' => count($nonPaidByDate[$dueDate]),
                    ]));
                }

                $stillNonPaidLease = $nonPaidByDate[$dueDate][$leaseId] ?? null;

                if (! $stillNonPaidLease) {
                    $this->markCancelledPaid(
                        $item,
                        sprintf(
                            'Le lease #%d n’est plus retourné comme NON_PAYE pour l’échéance du %s. La coupure est annulée par sécurité, car le paiement peut avoir été régularisé avant exécution.',
                            $leaseId,
                            $dueDate
                        )
                    );
                    $cancelled++;
                    Log::warning('[LEASE_CUTOFF_PROCESS] Queue annulée : lease exact absent des NON_PAYE', array_merge($ctx, [
                        'date_echeance' => $dueDate,
                        'expected_lease_id' => $leaseId,
                        'received_lease_ids' => array_keys($nonPaidByDate[$dueDate]),
                    ]));
                    continue;
                }

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

                $movingThreshold = (float) env('GPS_MOVING_THRESHOLD', 5.0);
                $vehicleState = $this->gps->getVehicleStateByMacId($macId, $movingThreshold);

                if (! ($vehicleState['success'] ?? false)) {
                    $this->markWaiting($item, 'La coupure est reportée : l’état GPS du véhicule est indisponible.', null, null);
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

                    if ($engineState === 'CUT') {
                        $this->markProcessedCutOff(
                            $item,
                            ['source' => 'post_send_verification', 'message' => 'La commande précédemment envoyée est confirmée par l’état moteur live.'],
                            $speed,
                            $uiStatus,
                            'La commande de coupure avait déjà été envoyée ; le moteur est maintenant confirmé coupé après vérification différée.'
                        );
                        $processed++;
                        Log::info('[LEASE_CUTOFF_PROCESS] Succès : commande confirmée après vérification différée', $ctx);
                        continue;
                    }

                    if ($item->retry_count >= $maxChecks) {
                        $this->markFailed($item, 'La commande de coupure a déjà été envoyée, mais le moteur n’a pas pu être confirmé coupé après plusieurs vérifications différées. Échec final de confirmation.');
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
                    $this->markWaiting($item, 'La coupure est reportée : le véhicule est actuellement offline côté provider GPS.', $speed, $uiStatus);
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : véhicule offline', $ctx);
                    continue;
                }

                if ($isMoving === null) {
                    $this->markWaiting($item, 'La coupure est reportée : l’état de mouvement du véhicule est incertain.', $speed, $uiStatus);
                    $waiting++;
                    Log::info('[LEASE_CUTOFF_PROCESS] Attente : mouvement incertain', $ctx);
                    continue;
                }

                if ($isMoving === true) {
                    $this->markWaiting($item, 'La coupure est reportée : le véhicule est encore en mouvement.', $speed, $uiStatus);
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
                    $this->markFailed($item, 'Le provider GPS a refusé ou échoué l’envoi de la commande.');
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
                        ? 'La commande de coupure a été transmise, mais le provider GPS demande une vérification différée avant confirmation définitive.'
                        : 'La commande de coupure a été envoyée. Le système attend la confirmation du moteur.'
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
                    'notes' => 'Événement clôturé sans coupure : le lease exact n’est plus confirmé NON_PAYE au moment de l’exécution.',
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