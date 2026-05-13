<?php

namespace App\Services\Leases;

use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffContractRule;
use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Planificateur de coupure automatique Lease.
 *
 * Cette classe NE COUPE PAS le véhicule. Elle crée seulement une ligne de queue
 * lorsque toutes les conditions métier sont réunies.
 *
 * Règle métier centrale :
 * - on récupère les leases NON_PAYE d'une date ;
 * - on résout le contrat/sous-contrat exact du lease ;
 * - on cherche la ligne lease_contract_links exacte ;
 * - on planifie seulement si cette ligne possède une règle spécifique active ;
 * - aucun type général ou sous-contrat non associé ne peut déclencher une coupure.
 */
class LeaseCutoffPlannerService
{
    private const HISTORY_TERMINAL_STATUSES = [
        'CUT_OFF',
        'CANCELLED_PAID',
        'CANCELLED_RULE_MISSING',
        'CANCELLED_RULE_DISABLED',
        'CANCELLED_FORGIVEN_BEFORE_CUT',
        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
        'REACTIVATED_AFTER_FORGIVENESS',
        'REACTIVATION_FAILED_AFTER_FORGIVENESS',
        'FAILED',
    ];

    private const QUEUE_ACTIVE_STATUSES = ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'];
    private const QUEUE_TERMINAL_STATUSES = ['PROCESSED', 'CANCELLED', 'FAILED'];

    public function __construct(
        private readonly LeaseApiClientService $leaseApi
    ) {
    }

    public function plan(?string $dateEcheance = null, ?int $offsetDays = null): array
    {
        $targetDate = $this->resolveTargetDueDate($dateEcheance, $offsetDays);

        Log::info('[LEASE_CUTOFF_PLAN] Début planification règles spécifiques contrat.', [
            'target_date_echeance' => $targetDate,
        ]);

        $contractsById = $this->leaseApi->fetchContractsIndexedById();
        $nonPaidLeases = $this->leaseApi->fetchNonPaidLeasesForDate($targetDate);

        $created = 0;
        $reused = 0;
        $skipped = 0;

        $skipReasons = [
            'lease_invalid' => 0,
            'lease_not_non_paid' => 0,
            'contract_not_found' => 0,
            'contract_link_missing' => 0,
            'vehicle_missing' => 0,
            'contract_rule_missing_or_disabled' => 0,
            'cutoff_time_missing' => 0,
            'time_not_due' => 0,
            'already_terminal_history' => 0,
            'already_active_queue' => 0,
            'already_terminal_queue' => 0,
            'history_in_progress_without_active_queue' => 0,
        ];

        foreach ($nonPaidLeases as $lease) {
            if (! is_array($lease)) {
                $skipped++;
                $skipReasons['lease_invalid']++;
                continue;
            }

            $leaseId = $this->leaseApi->extractLeaseId($lease);
            $contractId = $this->leaseApi->extractLeaseContractId($lease);
            $dueDate = $this->extractLeaseDueDate($lease) ?: $targetDate;

            $ctx = [
                'target_date_echeance' => $targetDate,
                'lease_id' => $leaseId ?: null,
                'lease_contract_id' => $contractId ?: null,
                'lease_due_date' => $dueDate,
                'lease_status' => $lease['statut'] ?? null,
                'reste_a_payer' => $lease['reste_a_payer'] ?? null,
                'chauffeur' => $lease['chauffeur_nom_complet'] ?? null,
                'type_contrat_libelle' => $lease['type_contrat_libelle'] ?? null,
            ];

            if ($leaseId <= 0 || $contractId <= 0) {
                $skipped++;
                $skipReasons['lease_invalid']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : lease_id ou contrat_id manquant.', $ctx);
                continue;
            }

            if (! $this->leaseApi->isNonPaidLeaseRow($lease)) {
                $skipped++;
                $skipReasons['lease_not_non_paid']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : statut non NON_PAYE.', $ctx);
                continue;
            }

            $contract = $contractsById[$contractId] ?? null;
            if (! is_array($contract)) {
                $skipped++;
                $skipReasons['contract_not_found']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : contrat API introuvable.', $ctx);
                continue;
            }

            $contractContext = $this->buildContractContext($contract, $contractsById, $lease);
            $ctx = array_merge($ctx, $contractContext['log']);

            /**
             * La ligne exacte est obligatoire.
             * Si le lease concerne un sous-contrat Téléphone, il faut la ligne du
             * sous-contrat Téléphone, pas seulement la ligne du contrat Moto parent.
             */
            $contractLink = $this->resolveExactContractLink($contractId);
            if (! $contractLink) {
                $skipped++;
                $skipReasons['contract_link_missing']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : aucun LeaseContractLink exact pour ce contrat/sous-contrat.', $ctx);
                continue;
            }

            $vehicle = $contractLink->vehicle;
            if (! $vehicle) {
                $skipped++;
                $skipReasons['vehicle_missing']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : véhicule local absent sur le lien contrat.', array_merge($ctx, [
                    'contract_link_id' => $contractLink->id,
                ]));
                continue;
            }

            $contractRule = $this->resolveActiveContractRule($contractLink);
            if (! $contractRule) {
                $skipped++;
                $skipReasons['contract_rule_missing_or_disabled']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : aucune règle active sur le contrat/sous-contrat spécifique.', array_merge($ctx, [
                    'contract_link_id' => $contractLink->id,
                    'vehicle_id' => $vehicle->id,
                ]));
                continue;
            }

            $effectiveTime = $contractRule->effectiveCutoffTime();
            if (! $effectiveTime) {
                $skipped++;
                $skipReasons['cutoff_time_missing']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : règle spécifique active sans heure de coupure.', array_merge($ctx, [
                    'contract_rule_id' => $contractRule->id,
                    'contract_link_id' => $contractLink->id,
                ]));
                continue;
            }

            $scheduledFor = $this->resolveScheduledDateTimeFromLease($dueDate, $contractRule);
            if (! $this->isDueNow($scheduledFor, $contractRule)) {
                $skipped++;
                $skipReasons['time_not_due']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : heure/date de coupure pas encore atteinte.', array_merge($ctx, [
                    'contract_rule_id' => $contractRule->id,
                    'scheduled_for' => $scheduledFor->toDateTimeString(),
                ]));
                continue;
            }

            $trigger = $this->buildTriggerContext(
                lease: $lease,
                dueDate: $dueDate,
                contractLink: $contractLink,
                contractContext: $contractContext,
                contractRule: $contractRule,
                scheduledFor: $scheduledFor
            );

            DB::transaction(function () use (
                $contractLink,
                $contractRule,
                $vehicle,
                $lease,
                $leaseId,
                $contractId,
                $dueDate,
                $contractContext,
                $scheduledFor,
                $trigger,
                &$created,
                &$reused,
                &$skipped,
                &$skipReasons,
                $ctx
            ) {
                $history = LeaseCutoffHistory::query()
                    ->where('partner_id', $contractRule->partner_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->whereDate('lease_date_echeance', $dueDate)
                    ->lockForUpdate()
                    ->first();

                $queue = LeaseCutoffQueue::query()
                    ->where('partner_id', $contractRule->partner_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->whereDate('lease_date_echeance', $dueDate)
                    ->lockForUpdate()
                    ->first();

                if ($history && in_array($history->status, self::HISTORY_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['already_terminal_history']++;
                    Log::info('[LEASE_CUTOFF_PLAN] Ignoré : historique terminal existant.', array_merge($ctx, [
                        'history_id' => $history->id,
                        'history_status' => $history->status,
                    ]));
                    return;
                }

                if ($queue && in_array($queue->status, self::QUEUE_ACTIVE_STATUSES, true)) {
                    $reused++;
                    $skipReasons['already_active_queue']++;
                    Log::info('[LEASE_CUTOFF_PLAN] Réutilisé : queue active existante.', array_merge($ctx, [
                        'queue_id' => $queue->id,
                        'queue_status' => $queue->status,
                    ]));
                    return;
                }

                if ($queue && in_array($queue->status, self::QUEUE_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['already_terminal_queue']++;
                    Log::warning('[LEASE_CUTOFF_PLAN] Ignoré : queue terminale existante.', array_merge($ctx, [
                        'queue_id' => $queue->id,
                        'queue_status' => $queue->status,
                    ]));
                    return;
                }

                if ($history && ! in_array($history->status, self::HISTORY_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['history_in_progress_without_active_queue']++;
                    Log::warning('[LEASE_CUTOFF_PLAN] Ignoré : historique non terminal sans queue active.', array_merge($ctx, [
                        'history_id' => $history->id,
                        'history_status' => $history->status,
                    ]));
                    return;
                }

                $history = LeaseCutoffHistory::create([
                    'partner_id' => $contractRule->partner_id,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'lease_date_echeance' => $dueDate,
                    'contract_link_id' => $contractLink->id,
                    'parent_contract_id' => $contractLink->source_parent_contract_id,
                    'type_contrat_id' => $contractLink->type_contrat_id,
                    'type_contrat_label' => $contractLink->type_contrat_label,
                    'contract_kind' => $contractLink->contract_kind,
                    'trigger_label' => $trigger['trigger_label'],
                    'trigger_payload' => $trigger['trigger_payload'],
                    'rule_id' => null,
                    'contract_rule_id' => $contractRule->id,
                    'scheduled_for' => $scheduledFor,
                    'detected_at' => now(),
                    'status' => 'PENDING',
                    'reason' => $trigger['reason'],
                    'payment_status_snapshot' => $trigger['payment_status_snapshot'],
                    'notes' => 'Événement créé automatiquement depuis les leases NON_PAYE. Aucune commande GPS n’a encore été envoyée.',
                ]);

                LeaseCutoffQueue::create([
                    'partner_id' => $contractRule->partner_id,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'lease_date_echeance' => $dueDate,
                    'contract_link_id' => $contractLink->id,
                    'parent_contract_id' => $contractLink->source_parent_contract_id,
                    'type_contrat_id' => $contractLink->type_contrat_id,
                    'type_contrat_label' => $contractLink->type_contrat_label,
                    'contract_kind' => $contractLink->contract_kind,
                    'trigger_label' => $trigger['trigger_label'],
                    'trigger_payload' => $trigger['trigger_payload'],
                    'rule_id' => null,
                    'contract_rule_id' => $contractRule->id,
                    'history_id' => $history->id,
                    'scheduled_for' => $scheduledFor,
                    'status' => 'PENDING',
                    'retry_count' => 0,
                    'last_checked_at' => null,
                    'next_check_at' => now(),
                ]);

                $created++;

                Log::info('[LEASE_CUTOFF_PLAN] Queue + historique créés depuis règle spécifique.', array_merge($ctx, [
                    'history_id' => $history->id,
                    'contract_rule_id' => $contractRule->id,
                    'contract_link_id' => $contractLink->id,
                    'user_message' => $trigger['reason'],
                ]));
            });
        }

        Log::info('[LEASE_CUTOFF_PLAN] Fin planification.', [
            'target_date_echeance' => $targetDate,
            'created' => $created,
            'reused' => $reused,
            'skipped' => $skipped,
            'skip_reasons' => $skipReasons,
        ]);

        return [
            'success' => true,
            'target_date_echeance' => $targetDate,
            'created' => $created,
            'reused' => $reused,
            'skipped' => $skipped,
            'skip_reasons' => $skipReasons,
        ];
    }

    private function resolveTargetDueDate(?string $dateEcheance, ?int $offsetDays): string
    {
        $timezone = config('app.timezone', 'Africa/Douala');

        if ($dateEcheance && trim($dateEcheance) !== '') {
            return Carbon::parse($dateEcheance, $timezone)->toDateString();
        }

        $offset = $offsetDays ?? (int) env('LEASE_CUTOFF_DUE_DATE_OFFSET_DAYS', 1);

        return Carbon::now($timezone)->subDays(max(0, $offset))->toDateString();
    }

    private function resolveExactContractLink(int $sourceContractId): ?LeaseContractLink
    {
        return LeaseContractLink::query()
            ->with(['vehicle', 'cutoffRule'])
            ->where('source_contract_id', $sourceContractId)
            ->where('status', '!=', 'DELETED')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function resolveActiveContractRule(LeaseContractLink $link): ?LeaseCutoffContractRule
    {
        return LeaseCutoffContractRule::query()
            ->where('contract_link_id', $link->id)
            ->where('partner_id', $link->partner_id)
            ->where('is_enabled', true)
            ->first();
    }

    private function resolveScheduledDateTimeFromLease(string $dueDate, LeaseCutoffContractRule $rule): Carbon
    {
        $timezone = $rule->effectiveTimezone();
        $date = Carbon::parse($dueDate, $timezone)->addDays(max(0, (int) $rule->grace_days));
        $time = $rule->effectiveCutoffTime() ?: '00:00';

        return Carbon::parse($date->format('Y-m-d') . ' ' . $time, $timezone)
            ->setTimezone(config('app.timezone', 'Africa/Douala'));
    }

    private function isDueNow(Carbon $scheduledFor, LeaseCutoffContractRule $rule): bool
    {
        $timezone = $rule->effectiveTimezone();

        return Carbon::now($timezone)->greaterThanOrEqualTo($scheduledFor->copy()->setTimezone($timezone));
    }

    private function buildTriggerContext(
        array $lease,
        string $dueDate,
        LeaseContractLink $contractLink,
        array $contractContext,
        LeaseCutoffContractRule $contractRule,
        Carbon $scheduledFor
    ): array {
        $leaseId = (int) ($lease['id'] ?? 0);
        $contractId = (int) $contractLink->source_contract_id;
        $parentId = $contractLink->source_parent_contract_id;
        $isSub = $contractLink->contract_kind === LeaseContractLink::KIND_SUB;
        $typeLabel = $contractLink->displayTypeLabel();
        $vehicleLabel = trim((string) ($contractLink->vehicle?->immatriculation ?: $contractLink->immatriculation ?: ('véhicule #' . $contractLink->vehicle_id)));
        $triggerName = $isSub ? 'sous-contrat' : 'contrat principal';
        $parentText = $isSub && $parentId ? ' rattaché au contrat principal #' . $parentId : '';
        $reste = $lease['reste_a_payer'] ?? $lease['montant_attendu'] ?? null;
        $amountText = $reste !== null && $reste !== '' ? ' avec un reste à payer de ' . $reste . ' FCFA' : '';

        $triggerLabel = sprintf('%s %s #%d%s', $triggerName, $typeLabel, $contractId, $parentText);

        $reason = sprintf(
            'Le %s "%s" a causé la planification de coupure de %s car le lease #%d du %s #%d%s, échéance du %s, est NON_PAYE%s. La règle spécifique du contrat/sous-contrat #%d est active. Coupure planifiée pour %s.',
            $isSub ? 'sous-contrat' : 'contrat principal',
            $typeLabel,
            $vehicleLabel,
            $leaseId,
            $triggerName,
            $contractId,
            $parentText,
            $dueDate,
            $amountText,
            $contractLink->id,
            $scheduledFor->format('Y-m-d H:i')
        );

        $payload = [
            'lease_id' => $leaseId ?: null,
            'contract_id' => $contractId ?: null,
            'parent_contract_id' => $parentId,
            'contract_link_id' => $contractLink->id,
            'contract_rule_id' => $contractRule->id,
            'contract_kind' => $contractLink->contract_kind,
            'contract_reference' => $contractContext['contract_reference'] ?? null,
            'parent_reference' => $contractContext['parent_reference'] ?? null,
            'type_contrat_id' => $contractLink->type_contrat_id,
            'type_contrat_label' => $typeLabel,
            'immatriculation' => $vehicleLabel,
            'date_echeance' => $dueDate,
            'montant_attendu' => $lease['montant_attendu'] ?? null,
            'montant_paye' => $lease['montant_paye'] ?? null,
            'reste_a_payer' => $reste,
            'statut' => $lease['statut'] ?? null,
            'chauffeur_nom_complet' => $lease['chauffeur_nom_complet'] ?? null,
            'rule_cutoff_time' => $contractRule->effectiveCutoffTime(),
            'timezone' => $contractRule->effectiveTimezone(),
            'grace_days' => $contractRule->grace_days,
            'only_when_stopped' => $contractRule->only_when_stopped,
            'scheduled_for' => $scheduledFor->toDateTimeString(),
        ];

        return [
            'trigger_label' => $triggerLabel,
            'trigger_payload' => $payload,
            'payment_status_snapshot' => $payload,
            'reason' => $reason,
        ];
    }

    private function buildContractContext(array $contract, array $contractsById, array $lease): array
    {
        $contractId = $this->extractContractId($contract);
        $parentId = $this->extractParentContractId($contract);
        $parent = $parentId > 0 && isset($contractsById[$parentId]) && is_array($contractsById[$parentId])
            ? $contractsById[$parentId]
            : null;

        $typeId = $this->extractTypeContratId($contract);
        $typeLabel = $this->extractTypeContratLabel($contract, $lease, $typeId);
        $contractKind = $parentId > 0 ? LeaseContractLink::KIND_SUB : LeaseContractLink::KIND_MAIN;

        return [
            'contract_id' => $contractId,
            'parent_contract_id' => $parentId ?: null,
            'contract_kind' => $contractKind,
            'type_contrat_id' => $typeId,
            'type_contrat_label' => $typeLabel,
            'contract_reference' => $contract['reference'] ?? $contract['numero'] ?? null,
            'parent_reference' => $parent['reference'] ?? $parent['numero'] ?? null,
            'log' => [
                'trigger_contract_id' => $contractId,
                'trigger_parent_contract_id' => $parentId ?: null,
                'trigger_contract_kind' => $contractKind,
                'trigger_type_contrat_id' => $typeId ?: null,
                'trigger_type_contrat_label' => $typeLabel ?: null,
            ],
        ];
    }

    private function extractLeaseDueDate(array $lease): ?string
    {
        $value = $lease['date_echeance'] ?? null;

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractContractId(array $contract): int
    {
        return (int) ($contract['id'] ?? data_get($contract, 'contrat.id') ?? 0);
    }

    private function extractParentContractId(array $contract): int
    {
        $parent = $contract['parent'] ?? data_get($contract, 'parent.id') ?? data_get($contract, 'raw.parent') ?? null;

        if (is_array($parent)) {
            return (int) ($parent['id'] ?? 0);
        }

        return (int) ($parent ?: 0);
    }

    private function extractTypeContratId(array $contract): int
    {
        $type = $contract['type_contrat'] ?? data_get($contract, 'raw.type_contrat') ?? null;

        if (is_array($type)) {
            return (int) ($type['id'] ?? 0);
        }

        return (int) ($type ?: data_get($contract, 'type_contrat_id') ?: 0);
    }

    private function extractTypeContratLabel(array $contract, array $lease, int $typeId): string
    {
        $type = $contract['type_contrat'] ?? null;

        $label = is_array($type)
            ? (string) ($type['libelle'] ?? $type['label'] ?? $type['nom'] ?? '')
            : '';

        $label = $label
            ?: (string) ($contract['type_contrat_libelle'] ?? '')
            ?: (string) ($lease['type_contrat_libelle'] ?? '')
            ?: (string) data_get($contract, 'raw.type_contrat.libelle', '');

        return trim($label) !== '' ? trim($label) : ($typeId > 0 ? 'Type #' . $typeId : 'Type inconnu');
    }
}
