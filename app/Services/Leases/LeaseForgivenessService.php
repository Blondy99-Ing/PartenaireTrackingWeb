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
     * Cas 1 : lease non payé, véhicule pas encore coupé.
     * - annule la queue ;
     * - écrit CANCELLED_FORGIVEN_BEFORE_CUT ;
     * - aucune commande GPS.
     *
     * Cas 2 : lease payé en retard ou non payé, véhicule déjà coupé.
     * - envoie une commande GPS de rallumage ;
     * - écrit REACTIVATED_AFTER_FORGIVENESS,
     *   REACTIVATION_REQUESTED_AFTER_FORGIVENESS
     *   ou REACTIVATION_FAILED_AFTER_FORGIVENESS.
     *
     * Dans tous les cas :
     * - on trace qui a pardonné ;
     * - on trace quand ;
     * - on trace la raison métier.
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
        array $lease,
        string $engineState,
        ?string $reason
    ): array {
        $businessReason = $this->normalizeReason(
            $reason,
            'Pardon préventif : le véhicule ne doit pas être coupé malgré le lease impayé.'
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
                'notes' => 'Pardon préventif : aucune commande de coupure ni de rallumage nécessaire. Le planner ne doit plus replanifier ce lease.',
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
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
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
            $actor,
            $forgivenByName,
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
                'forgiven_by_user_id' => $actor->id,
                'forgiven_by_name' => $forgivenByName,
                'forgiven_at' => now()->toDateTimeString(),
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
            'forgiven_by_user_id' => $actor->id,
            'forgiven_by_name' => $forgivenByName,
            'forgiven_at' => now()->toDateTimeString(),
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

    private function normalizeReason(?string $reason, string $fallback): string
    {
        $reason = trim((string) $reason);

        return $reason !== '' ? $reason : $fallback;
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