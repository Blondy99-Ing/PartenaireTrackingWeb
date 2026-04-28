<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffQueue;
use App\Models\User;
use App\Models\Voiture;
use App\Services\Gps\GpsCommandDispatcherService;
use App\Services\GpsControlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LeaseForgivenessService
{
    public function __construct(
        private readonly LeaseApiClientService $leaseApi,
        private readonly GpsControlService $gps,
        private readonly GpsCommandDispatcherService $dispatcher
    ) {
    }

    /**
     * Pardon intelligent d’un lease.
     *
     * Cas pris en charge :
     *
     * 1. Lease NON_PAYE pardonné avant coupure :
     *    - annule la queue si elle existe
     *    - écrit l’historique CANCELLED_FORGIVEN_BEFORE_CUT
     *    - empêche le planner de recréer une coupure pour le même lease
     *
     * 2. Lease déjà PAYE mais véhicule encore coupé :
     *    - considéré comme paiement en retard
     *    - envoie une commande de rallumage
     *    - écrit REACTIVATED_AFTER_FORGIVENESS /
     *      REACTIVATION_REQUESTED_AFTER_FORGIVENESS /
     *      REACTIVATION_FAILED_AFTER_FORGIVENESS
     *
     * 3. Lease NON_PAYE déjà coupé :
     *    - pardon après coupure
     *    - envoie une commande de rallumage
     */
    public function forgive(User $actor, int $leaseId, ?string $reason = null): array
    {
        $partnerId = $this->resolvePartnerId($actor);

        Log::info('[LEASE_FORGIVENESS] Début pardon', [
            'actor_id' => $actor->id,
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

        $contracts = $this->leaseApi->fetchContractsIndexedById();
        $contract = $contracts[$contractId] ?? null;

        if (! is_array($contract)) {
            throw new RuntimeException("Contrat {$contractId} introuvable côté recouvrement.");
        }

        $immat = trim((string) ($contract['immatriculation'] ?? ''));

        if ($immat === '') {
            throw new RuntimeException("Immatriculation introuvable pour le contrat {$contractId}.");
        }

        $vehicle = Voiture::query()
            ->where('immatriculation', $immat)
            ->first();

        if (! $vehicle) {
            throw new RuntimeException("Véhicule local introuvable pour l’immatriculation {$immat}.");
        }

        $queue = LeaseCutoffQueue::query()
            ->with(['history'])
            ->where('partner_id', $partnerId)
            ->where('lease_id', $leaseId)
            ->where('vehicle_id', $vehicle->id)
            ->orderByDesc('id')
            ->first();

        $history = $queue?->history;

        if (! $history) {
            $history = LeaseCutoffHistory::query()
                ->where('partner_id', $partnerId)
                ->where('lease_id', $leaseId)
                ->where('vehicle_id', $vehicle->id)
                ->orderByDesc('id')
                ->first();
        }

        $engineState = $this->getEngineState($vehicle);
        $wasAlreadyCut = $this->wasAlreadyCut($history, $engineState);
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
            'queue_id' => $queue?->id,
            'queue_status' => $queue?->status,
            'history_id' => $history?->id,
            'history_status' => $history?->status,
        ]);

        if ($wasAlreadyCut) {
            return $this->forgiveAfterCut(
                partnerId: $partnerId,
                vehicle: $vehicle,
                contractId: $contractId,
                leaseId: $leaseId,
                queue: $queue,
                history: $history,
                lease: $lease,
                engineState: $engineState,
                reason: $reason
            );
        }

        return $this->forgiveBeforeCut(
            partnerId: $partnerId,
            vehicle: $vehicle,
            contractId: $contractId,
            leaseId: $leaseId,
            queue: $queue,
            history: $history,
            lease: $lease,
            engineState: $engineState,
            reason: $reason
        );
    }

    private function forgiveBeforeCut(
        int $partnerId,
        Voiture $vehicle,
        int $contractId,
        int $leaseId,
        ?LeaseCutoffQueue $queue,
        ?LeaseCutoffHistory $history,
        array $lease,
        string $engineState,
        ?string $reason
    ): array {
        $businessReason = $this->normalizeReason(
            $reason,
            'Pardon préventif : le véhicule ne doit pas être coupé malgré le lease impayé.'
        );

        return DB::transaction(function () use (
            $partnerId,
            $vehicle,
            $contractId,
            $leaseId,
            $queue,
            $history,
            $lease,
            $engineState,
            $businessReason
        ) {
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
                ]);
            }

            if (! $history) {
                $history = LeaseCutoffHistory::create([
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'rule_id' => $queue?->rule_id,
                    'scheduled_for' => $queue?->scheduled_for ?? now(),
                    'detected_at' => now(),
                    'status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                    'reason' => $businessReason,
                    'ignition_state' => $engineState,
                    'payment_status_snapshot' => $this->buildPaymentSnapshot(
                        lease: $lease,
                        customStatus: 'PARDONNE_AVANT_COUPURE',
                        reason: $businessReason
                    ),
                    'notes' => 'Pardon préventif : aucune commande de coupure ni de rallumage nécessaire. Le planner ne doit plus replanifier ce lease.',
                ]);
            } else {
                $history->update([
                    'status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                    'reason' => $businessReason,
                    'ignition_state' => $engineState,
                    'payment_status_snapshot' => $this->buildPaymentSnapshot(
                        lease: $lease,
                        customStatus: 'PARDONNE_AVANT_COUPURE',
                        reason: $businessReason
                    ),
                    'notes' => 'Événement clôturé : pardon avant coupure. Le véhicule n’a pas été coupé pour une raison métier documentée.',
                ]);
            }

            Log::info('[LEASE_FORGIVENESS] Pardon avant coupure enregistré', [
                'history_id' => $history->id,
                'lease_id' => $leaseId,
                'vehicle_id' => $vehicle->id,
                'status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                'reason' => $businessReason,
            ]);

            return [
                'status' => 'forgiven_before_cut',
                'history_status' => 'CANCELLED_FORGIVEN_BEFORE_CUT',
                'message' => 'Pardon préventif enregistré. Le véhicule ne sera pas coupé pour ce lease.',
                'was_cut_before_forgiveness' => false,
                'reason' => $businessReason,
            ];
        });
    }

    private function forgiveAfterCut(
        int $partnerId,
        Voiture $vehicle,
        int $contractId,
        int $leaseId,
        ?LeaseCutoffQueue $queue,
        ?LeaseCutoffHistory $history,
        array $lease,
        string $engineState,
        ?string $reason
    ): array {
        $macId = trim((string) $vehicle->mac_id_gps);

        if ($macId === '') {
            throw new RuntimeException("Impossible de rallumer : mac_id_gps vide pour le véhicule {$vehicle->immatriculation}.");
        }

        $businessReason = $this->normalizeReason(
            $reason,
            'Pardon après coupure : le véhicule doit être rallumé.'
        );

        Log::info('[LEASE_FORGIVENESS] Rallumage demandé après pardon', [
            'lease_id' => $leaseId,
            'vehicle_id' => $vehicle->id,
            'immatriculation' => $vehicle->immatriculation,
            'mac_id_gps' => $macId,
            'reason' => $businessReason,
        ]);

        $command = $this->dispatcher->dispatchRestoreByMacId($macId);
        $commandStatus = (string) ($command['status'] ?? 'FAILED');

        $finalHistoryStatus = match ($commandStatus) {
            'SENT' => 'REACTIVATED_AFTER_FORGIVENESS',
            'PENDING_VERIFICATION' => 'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
            default => 'REACTIVATION_FAILED_AFTER_FORGIVENESS',
        };

        $uiStatus = match ($commandStatus) {
            'SENT' => 'forgiven_after_cut',
            'PENDING_VERIFICATION' => 'forgiven_reactivation_pending',
            default => 'forgiven_reactivation_failed',
        };

        DB::transaction(function () use (
            $partnerId,
            $vehicle,
            $contractId,
            $leaseId,
            $queue,
            $history,
            $lease,
            $engineState,
            $command,
            $finalHistoryStatus,
            $businessReason
        ) {
            if ($queue) {
                $queue->update([
                    'status' => $finalHistoryStatus === 'REACTIVATION_FAILED_AFTER_FORGIVENESS'
                        ? 'FAILED'
                        : 'PROCESSED',
                    'last_checked_at' => now(),
                    'next_check_at' => null,
                ]);
            }

            $historyPayload = [
                'status' => $finalHistoryStatus,
                'reason' => $businessReason,
                'ignition_state' => $engineState,
                'payment_status_snapshot' => $this->buildPaymentSnapshot(
                    lease: $lease,
                    customStatus: 'PARDONNE_APRES_COUPURE',
                    reason: $businessReason
                ),
                'command_response' => $command,
                'notes' => $finalHistoryStatus === 'REACTIVATION_FAILED_AFTER_FORGIVENESS'
                    ? 'Pardon après coupure : tentative de rallumage échouée.'
                    : 'Pardon après coupure : commande de rallumage déclenchée automatiquement.',
            ];

            if (! $history) {
                $history = LeaseCutoffHistory::create(array_merge($historyPayload, [
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'rule_id' => $queue?->rule_id,
                    'scheduled_for' => $queue?->scheduled_for ?? now(),
                    'detected_at' => now(),
                ]));
            } else {
                $history->update($historyPayload);
            }

            Log::info('[LEASE_FORGIVENESS] Pardon après coupure historisé', [
                'history_id' => $history->id,
                'lease_id' => $leaseId,
                'vehicle_id' => $vehicle->id,
                'history_status' => $finalHistoryStatus,
            ]);
        });

        return [
            'status' => $uiStatus,
            'history_status' => $finalHistoryStatus,
            'message' => match ($uiStatus) {
                'forgiven_after_cut' => 'Pardon enregistré. Le véhicule était déjà coupé et une commande de rallumage a été envoyée.',
                'forgiven_reactivation_pending' => 'Pardon enregistré. Le véhicule était déjà coupé ; le rallumage est en attente de confirmation.',
                default => 'Pardon enregistré, mais le rallumage automatique a échoué.',
            },
            'was_cut_before_forgiveness' => true,
            'reason' => $businessReason,
            'command' => $command,
        ];
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
            || $history->cutoff_executed_at !== null
            || $history->status === 'COMMAND_SENT';
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

    private function findLeaseFromApi(int $leaseId): ?array
    {
        $leases = $this->leaseApi->fetchLeases();

        foreach ($leases as $lease) {
            if (! is_array($lease)) {
                continue;
            }

            if ((int) ($lease['id'] ?? 0) === $leaseId) {
                return $lease;
            }
        }

        return null;
    }

    private function buildPaymentSnapshot(array $lease, string $customStatus, ?string $reason = null): array
    {
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
            'forgiven_at' => now()->toDateTimeString(),
        ];
    }

    private function normalizeReason(?string $reason, string $fallback): string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : $fallback;
    }

    private function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }
}