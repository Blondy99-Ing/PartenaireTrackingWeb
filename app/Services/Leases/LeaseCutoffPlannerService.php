<?php

namespace App\Services\Leases;

use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffQueue;
use App\Models\LeaseCutoffRule;
use App\Models\LeaseCutoffRuleContractType;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Planificateur de coupure automatique lease.
 *
 * Nouvelle logique métier :
 * 1. Recouvrement fournit les leases NON_PAYE pour une date d'échéance.
 * 2. Chaque lease est un déclencheur autonome.
 * 3. Tracking résout le contrat / sous-contrat, puis le véhicule du contrat parent.
 * 4. Tracking vérifie la règle générale véhicule + la règle du type déclencheur.
 * 5. Tracking crée une queue + un historique lisible.
 *
 * Cette classe ne coupe pas le véhicule. Elle prépare seulement une demande sûre
 * qui sera ensuite traitée par LeaseCutoffQueueProcessorService.
 */
class LeaseCutoffPlannerService
{
    /**
     * Statuts history considérés comme terminaux métier.
     * Une fois terminal, le même lease_id ne doit plus être replanifié.
     */
    private const HISTORY_TERMINAL_STATUSES = [
        'CUT_OFF',
        'CANCELLED_PAID',
        'CANCELLED_FORGIVEN_BEFORE_CUT',
        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
        'REACTIVATED_AFTER_FORGIVENESS',
        'REACTIVATION_FAILED_AFTER_FORGIVENESS',
    ];

    /**
     * Statuts queue actifs.
     */
    private const QUEUE_ACTIVE_STATUSES = [
        'PENDING',
        'WAITING_STOP',
        'COMMAND_SENT',
    ];

    /**
     * Statuts queue clôturés.
     */
    private const QUEUE_TERMINAL_STATUSES = [
        'PROCESSED',
        'CANCELLED',
        'FAILED',
    ];

    public function __construct(
        private readonly LeaseApiClientService $leaseApi
    ) {
    }

    /**
     * Planifie les coupures depuis les leases NON_PAYE d'une date précise.
     *
     * @param string|null $dateEcheance Format YYYY-MM-DD. Si null, on utilise env LEASE_CUTOFF_DUE_DATE_OFFSET_DAYS.
     * @param int|null $offsetDays Nombre de jours à soustraire à aujourd'hui si $dateEcheance est absent.
     */
    public function plan(?string $dateEcheance = null, ?int $offsetDays = null): array
    {
        $targetDate = $this->resolveTargetDueDate($dateEcheance, $offsetDays);

        Log::info('[LEASE_CUTOFF_PLAN] Début de planification depuis les leases NON_PAYE.', [
            'target_date_echeance' => $targetDate,
        ]);

        $contractsById = $this->leaseApi->fetchContractsIndexedById();
        $nonPaidLeases = $this->leaseApi->fetchNonPaidLeasesForDate($targetDate);

        $rulesByVehicleId = LeaseCutoffRule::query()
            ->with(['vehicle', 'contractTypeRules'])
            ->where('is_enabled', true)
            ->get()
            ->groupBy('vehicle_id');

        Log::info('[LEASE_CUTOFF_PLAN] Données chargées.', [
            'contracts_count' => count($contractsById),
            'non_paid_leases_count' => count($nonPaidLeases),
            'active_rule_vehicles_count' => $rulesByVehicleId->count(),
        ]);

        $created = 0;
        $reused = 0;
        $skipped = 0;

        $skipReasons = [
            'lease_invalid' => 0,
            'lease_not_non_paid' => 0,
            'contract_not_found' => 0,
            'vehicle_not_resolved' => 0,
            'vehicle_rule_missing_or_disabled' => 0,
            'type_id_missing' => 0,
            'type_rule_missing_or_disabled' => 0,
            'cutoff_time_missing' => 0,
            'time_not_due' => 0,
            'already_terminal_history' => 0,
            'already_active_queue' => 0,
            'already_terminal_queue' => 0,
            'history_in_progress_without_active_queue' => 0,
        ];

        foreach ($nonPaidLeases as $lease) {
            if (!is_array($lease)) {
                $skipped++;
                $skipReasons['lease_invalid']++;
                continue;
            }

            $leaseId = $this->leaseApi->extractLeaseId($lease);
            $contractId = $this->leaseApi->extractLeaseContractId($lease);

            $ctx = [
                'target_date_echeance' => $targetDate,
                'lease_id' => $leaseId ?: null,
                'lease_contract_id' => $contractId ?: null,
                'lease_status' => $lease['statut'] ?? null,
                'lease_due_date' => $lease['date_echeance'] ?? null,
                'lease_reste_a_payer' => $lease['reste_a_payer'] ?? null,
                'lease_type_label' => $lease['type_contrat_libelle'] ?? null,
                'chauffeur' => $lease['chauffeur_nom_complet'] ?? null,
            ];

            if ($leaseId <= 0 || $contractId <= 0) {
                $skipped++;
                $skipReasons['lease_invalid']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : ID lease ou contract_id absent.', $ctx);
                continue;
            }

            if (!$this->leaseApi->isNonPaidLeaseRow($lease)) {
                $skipped++;
                $skipReasons['lease_not_non_paid']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : il n’est pas confirmé NON_PAYE.', $ctx);
                continue;
            }

            $contract = $contractsById[$contractId] ?? null;
            if (!is_array($contract)) {
                $skipped++;
                $skipReasons['contract_not_found']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : contrat déclencheur introuvable dans GET /contrats/.', $ctx);
                continue;
            }

            $contractContext = $this->buildContractContext($contract, $contractsById, $lease);
            $ctx = array_merge($ctx, $contractContext['log']);

            $vehicle = $this->resolveVehicleForTrigger($contractContext);
            if (!$vehicle) {
                $skipped++;
                $skipReasons['vehicle_not_resolved']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : véhicule Tracking introuvable pour le contrat ou son parent.', $ctx);
                continue;
            }

            $ctx['vehicle_id'] = $vehicle->id;
            $ctx['vehicle_immatriculation'] = $vehicle->immatriculation ?? null;
            $ctx['vehicle_mac_id_gps'] = $vehicle->mac_id_gps ?? null;

            /** @var LeaseCutoffRule|null $rule */
            $rule = $rulesByVehicleId->get((int) $vehicle->id)?->first();
            if (!$rule || !(bool) $rule->is_enabled) {
                $skipped++;
                $skipReasons['vehicle_rule_missing_or_disabled']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : règle générale véhicule absente ou désactivée.', $ctx);
                continue;
            }

            $typeId = $contractContext['type_contrat_id'];
            $typeLabel = $contractContext['type_contrat_label'];

            if ($typeId <= 0 && $typeLabel === '') {
                $skipped++;
                $skipReasons['type_id_missing']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : type de contrat déclencheur impossible à identifier.', array_merge($ctx, [
                    'rule_id' => $rule->id,
                ]));
                continue;
            }

            $typeRule = $this->findTypeRule($rule, $typeId, $typeLabel);
            if (!$typeRule || !(bool) $typeRule->is_enabled) {
                $skipped++;
                $skipReasons['type_rule_missing_or_disabled']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : le type de contrat déclencheur n’est pas autorisé à couper.', array_merge($ctx, [
                    'rule_id' => $rule->id,
                    'type_contrat_id' => $typeId ?: null,
                    'type_contrat_label' => $typeLabel ?: null,
                ]));
                continue;
            }

            $effectiveTime = $this->resolveEffectiveCutoffTime($rule, $typeRule);
            if (!$effectiveTime) {
                $skipped++;
                $skipReasons['cutoff_time_missing']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : aucune heure de coupure configurée.', array_merge($ctx, [
                    'rule_id' => $rule->id,
                    'type_rule_id' => $typeRule->id,
                ]));
                continue;
            }

            $scheduledFor = $this->resolveScheduledDateTimeFromLease($lease, $rule, $typeRule);
            $ctx['rule_id'] = $rule->id;
            $ctx['type_rule_id'] = $typeRule->id;
            $ctx['effective_cutoff_time'] = $effectiveTime;
            $ctx['effective_grace_days'] = $this->resolveEffectiveGraceDays($rule, $typeRule);
            $ctx['scheduled_for'] = $scheduledFor->toDateTimeString();

            if (!$this->isDueNow($scheduledFor, $rule)) {
                $skipped++;
                $skipReasons['time_not_due']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : heure/date de coupure pas encore atteinte.', $ctx);
                continue;
            }

            $contractLink = $this->resolveContractLinkForTrigger($rule, $contractContext);
            $trigger = $this->buildTriggerContext(
                lease: $lease,
                contractContext: $contractContext,
                vehicle: $vehicle,
                rule: $rule,
                typeRule: $typeRule,
                scheduledFor: $scheduledFor
            );

            DB::transaction(function () use (
                $rule,
                $typeRule,
                $vehicle,
                $lease,
                $leaseId,
                $contractId,
                $contractLink,
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
                    ->where('partner_id', $rule->partner_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->lockForUpdate()
                    ->first();

                $queue = LeaseCutoffQueue::query()
                    ->where('partner_id', $rule->partner_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->lockForUpdate()
                    ->first();

                if ($history && in_array($history->status, self::HISTORY_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['already_terminal_history']++;
                    Log::info('[LEASE_CUTOFF_PLAN] Événement ignoré : history déjà terminale.', array_merge($ctx, [
                        'history_id' => $history->id,
                        'history_status' => $history->status,
                    ]));
                    return;
                }

                if ($queue && in_array($queue->status, self::QUEUE_ACTIVE_STATUSES, true)) {
                    $reused++;
                    $skipReasons['already_active_queue']++;
                    Log::info('[LEASE_CUTOFF_PLAN] Événement réutilisé : queue active déjà existante.', array_merge($ctx, [
                        'queue_id' => $queue->id,
                        'queue_status' => $queue->status,
                        'history_id' => $queue->history_id,
                    ]));
                    return;
                }

                if ($queue && in_array($queue->status, self::QUEUE_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['already_terminal_queue']++;
                    Log::warning('[LEASE_CUTOFF_PLAN] Événement ignoré : queue terminale déjà existante, réactivation automatique interdite.', array_merge($ctx, [
                        'queue_id' => $queue->id,
                        'queue_status' => $queue->status,
                        'history_id' => $queue->history_id,
                    ]));
                    return;
                }

                if ($history && !in_array($history->status, self::HISTORY_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['history_in_progress_without_active_queue']++;
                    Log::warning('[LEASE_CUTOFF_PLAN] Événement ignoré : history non terminale sans queue active.', array_merge($ctx, [
                        'history_id' => $history->id,
                        'history_status' => $history->status,
                    ]));
                    return;
                }

                $history = LeaseCutoffHistory::create([
                    'partner_id' => $rule->partner_id,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'contract_link_id' => $contractLink?->id,
                    'parent_contract_id' => $contractContext['parent_contract_id'],
                    'type_contrat_id' => $contractContext['type_contrat_id'] ?: $typeRule->type_contrat_id,
                    'type_contrat_label' => $contractContext['type_contrat_label'] ?: $typeRule->type_contrat_label,
                    'contract_kind' => $contractContext['contract_kind'],
                    'trigger_label' => $trigger['trigger_label'],
                    'trigger_payload' => $trigger['trigger_payload'],
                    'rule_id' => $rule->id,
                    'scheduled_for' => $scheduledFor,
                    'detected_at' => now(),
                    'status' => 'PENDING',
                    'reason' => $trigger['reason'],
                    'payment_status_snapshot' => $trigger['payment_status_snapshot'],
                    'notes' => 'Événement créé automatiquement depuis les leases NON_PAYE. Aucune commande GPS n’a encore été envoyée.',
                ]);

                $queue = LeaseCutoffQueue::create([
                    'partner_id' => $rule->partner_id,
                    'vehicle_id' => $vehicle->id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId,
                    'contract_link_id' => $contractLink?->id,
                    'parent_contract_id' => $contractContext['parent_contract_id'],
                    'type_contrat_id' => $contractContext['type_contrat_id'] ?: $typeRule->type_contrat_id,
                    'type_contrat_label' => $contractContext['type_contrat_label'] ?: $typeRule->type_contrat_label,
                    'contract_kind' => $contractContext['contract_kind'],
                    'trigger_label' => $trigger['trigger_label'],
                    'trigger_payload' => $trigger['trigger_payload'],
                    'rule_id' => $rule->id,
                    'history_id' => $history->id,
                    'scheduled_for' => $scheduledFor,
                    'status' => 'PENDING',
                    'retry_count' => 0,
                    'last_checked_at' => null,
                    'next_check_at' => now(),
                ]);

                $created++;

                Log::info('[LEASE_CUTOFF_PLAN] Queue + historique créés pour lease NON_PAYE.', array_merge($ctx, [
                    'history_id' => $history->id,
                    'queue_id' => $queue->id,
                    'user_message' => $trigger['reason'],
                ]));
            });
        }

        Log::info('[LEASE_CUTOFF_PLAN] Fin de planification.', [
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

    /**
     * Date traitée par le cron.
     *
     * Par défaut, on traite J-1 car dans ton exemple les leases du 2026-05-10
     * sont générés le 2026-05-11 à 02h. Mets LEASE_CUTOFF_DUE_DATE_OFFSET_DAYS=0
     * si Recouvrement fournit les impayés du jour courant.
     */
    private function resolveTargetDueDate(?string $dateEcheance, ?int $offsetDays): string
    {
        $timezone = config('app.timezone', 'Africa/Douala');

        if ($dateEcheance && trim($dateEcheance) !== '') {
            return Carbon::parse($dateEcheance, $timezone)->toDateString();
        }

        $offset = $offsetDays ?? (int) env('LEASE_CUTOFF_DUE_DATE_OFFSET_DAYS', 1);

        return Carbon::now($timezone)->subDays(max(0, $offset))->toDateString();
    }

    /**
     * Résout contrat/sous-contrat + parent + type déclencheur.
     */
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
        $contractReference = (string) ($contract['reference'] ?? $contract['nom_complet'] ?? ('Contrat #' . $contractId));
        $parentReference = $parent ? (string) ($parent['reference'] ?? $parent['nom_complet'] ?? ('Contrat parent #' . $parentId)) : null;

        $triggerImmatriculation = (string) ($contract['immatriculation'] ?? '');
        $parentImmatriculation = $parent ? (string) ($parent['immatriculation'] ?? '') : '';
        $effectiveImmatriculation = $triggerImmatriculation !== '' ? $triggerImmatriculation : $parentImmatriculation;

        return [
            'contract' => $contract,
            'parent' => $parent,
            'contract_id' => $contractId,
            'parent_contract_id' => $parentId > 0 ? $parentId : null,
            'contract_kind' => $contractKind,
            'contract_reference' => $contractReference,
            'parent_reference' => $parentReference,
            'type_contrat_id' => $typeId,
            'type_contrat_label' => $typeLabel,
            'trigger_immatriculation' => $triggerImmatriculation,
            'parent_immatriculation' => $parentImmatriculation,
            'effective_immatriculation' => $effectiveImmatriculation,
            'log' => [
                'trigger_contract_id' => $contractId,
                'parent_contract_id' => $parentId > 0 ? $parentId : null,
                'contract_kind' => $contractKind,
                'contract_reference' => $contractReference,
                'parent_reference' => $parentReference,
                'type_contrat_id' => $typeId ?: null,
                'type_contrat_label' => $typeLabel ?: null,
                'effective_immatriculation' => $effectiveImmatriculation ?: null,
            ],
        ];
    }

    /**
     * Résout le véhicule à couper.
     *
     * Ordre de priorité :
     * 1. lien local du contrat déclencheur ;
     * 2. lien local du contrat parent ;
     * 3. immatriculation du contrat ou du parent.
     */
    private function resolveVehicleForTrigger(array $contractContext): ?Voiture
    {
        $triggerId = (int) $contractContext['contract_id'];
        $parentId = (int) ($contractContext['parent_contract_id'] ?? 0);

        $link = LeaseContractLink::query()
            ->where('source_contract_id', $triggerId)
            ->whereNotNull('vehicle_id')
            ->where('status', '!=', 'DELETED')
            ->orderByDesc('updated_at')
            ->first();

        if (!$link && $parentId > 0) {
            $link = LeaseContractLink::query()
                ->where('source_contract_id', $parentId)
                ->whereNotNull('vehicle_id')
                ->where('status', '!=', 'DELETED')
                ->orderByDesc('updated_at')
                ->first();
        }

        if ($link && $link->vehicle_id) {
            $vehicle = Voiture::query()->find((int) $link->vehicle_id);
            if ($vehicle) {
                return $vehicle;
            }
        }

        $immat = $this->normalizeImmatriculation((string) ($contractContext['effective_immatriculation'] ?? ''));
        if ($immat === '') {
            return null;
        }

        return Voiture::query()
            ->whereRaw('REPLACE(UPPER(immatriculation), " ", "") = ?', [$immat])
            ->first();
    }

    private function resolveContractLinkForTrigger(LeaseCutoffRule $rule, array $contractContext): ?LeaseContractLink
    {
        $query = LeaseContractLink::query()
            ->where('partner_id', $rule->partner_id)
            ->where('source_contract_id', (int) $contractContext['contract_id'])
            ->where('status', '!=', 'DELETED')
            ->orderByDesc('updated_at');

        $link = $query->first();
        if ($link) {
            return $link;
        }

        $parentId = (int) ($contractContext['parent_contract_id'] ?? 0);
        if ($parentId <= 0) {
            return null;
        }

        return LeaseContractLink::query()
            ->where('partner_id', $rule->partner_id)
            ->where('source_contract_id', $parentId)
            ->where('status', '!=', 'DELETED')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function findTypeRule(LeaseCutoffRule $rule, int $typeId, string $typeLabel): ?LeaseCutoffRuleContractType
    {
        if ($typeId > 0) {
            $byId = $rule->contractTypeRules->firstWhere('type_contrat_id', $typeId);
            if ($byId) {
                return $byId;
            }
        }

        $target = $this->normalizeLabel($typeLabel);
        if ($target === '') {
            return null;
        }

        return $rule->contractTypeRules->first(function (LeaseCutoffRuleContractType $row) use ($target) {
            return $this->normalizeLabel((string) $row->type_contrat_label) === $target;
        });
    }

    private function resolveScheduledDateTimeFromLease(
        array $lease,
        LeaseCutoffRule $rule,
        LeaseCutoffRuleContractType $typeRule
    ): Carbon {
        $timezone = $rule->timezone ?: config('app.timezone', 'Africa/Douala');
        $dueDate = Carbon::parse((string) ($lease['date_echeance'] ?? now($timezone)->toDateString()), $timezone);
        $effectiveTime = $this->resolveEffectiveCutoffTime($rule, $typeRule) ?: '00:00';
        $graceDays = $this->resolveEffectiveGraceDays($rule, $typeRule);

        return Carbon::parse($dueDate->addDays($graceDays)->format('Y-m-d') . ' ' . $effectiveTime, $timezone)
            ->setTimezone(config('app.timezone'));
    }

    private function isDueNow(Carbon $scheduledFor, LeaseCutoffRule $rule): bool
    {
        $timezone = $rule->timezone ?: config('app.timezone', 'Africa/Douala');

        return Carbon::now($timezone)->greaterThanOrEqualTo($scheduledFor->copy()->setTimezone($timezone));
    }

    private function resolveEffectiveCutoffTime(LeaseCutoffRule $rule, ?LeaseCutoffRuleContractType $typeRule = null): ?string
    {
        if ($typeRule && $typeRule->cutoff_time) {
            return substr((string) $typeRule->cutoff_time, 0, 5);
        }

        return $rule->cutoff_time ? substr((string) $rule->cutoff_time, 0, 5) : null;
    }

    private function resolveEffectiveGraceDays(LeaseCutoffRule $rule, ?LeaseCutoffRuleContractType $typeRule = null): int
    {
        if ($typeRule && $typeRule->grace_days !== null) {
            return max(0, (int) $typeRule->grace_days);
        }

        return max(0, (int) ($rule->grace_days ?? 0));
    }

    /**
     * Message utilisateur clair + payload développeur.
     */
    private function buildTriggerContext(
        array $lease,
        array $contractContext,
        Voiture $vehicle,
        LeaseCutoffRule $rule,
        LeaseCutoffRuleContractType $typeRule,
        Carbon $scheduledFor
    ): array {
        $leaseId = (int) ($lease['id'] ?? 0);
        $contractId = (int) $contractContext['contract_id'];
        $parentId = $contractContext['parent_contract_id'];
        $contractKind = $contractContext['contract_kind'];
        $isSub = $contractKind === LeaseContractLink::KIND_SUB;
        $typeLabel = (string) ($contractContext['type_contrat_label'] ?: $typeRule->type_contrat_label ?: 'Type de contrat');
        $dueDate = (string) ($lease['date_echeance'] ?? 'date inconnue');
        $reste = $lease['reste_a_payer'] ?? $lease['montant_attendu'] ?? null;
        $vehicleLabel = trim((string) ($vehicle->immatriculation ?? ('véhicule #' . $vehicle->id)));
        $triggerName = $isSub ? 'sous-contrat' : 'contrat principal';
        $parentText = $isSub && $parentId
            ? ' rattaché au contrat principal #' . $parentId
            : '';

        $amountText = $reste !== null && $reste !== ''
            ? ' avec un reste à payer de ' . $reste . ' FCFA'
            : '';

        $triggerLabel = sprintf(
            '%s %s #%d%s',
            $triggerName,
            $typeLabel,
            $contractId,
            $parentText
        );

        $reason = sprintf(
            'Le type de contrat "%s" a causé la coupure de %s car le lease #%d du %s #%d%s, échéance du %s, est NON_PAYE%s. La règle générale du véhicule et la règle du type "%s" sont actives. Coupure planifiée pour %s.',
            $typeLabel,
            $vehicleLabel,
            $leaseId,
            $triggerName,
            $contractId,
            $parentText,
            $dueDate,
            $amountText,
            $typeLabel,
            $scheduledFor->format('Y-m-d H:i')
        );

        $triggerPayload = [
            'lease_id' => $leaseId ?: null,
            'contract_id' => $contractId ?: null,
            'parent_contract_id' => $parentId,
            'contract_kind' => $contractKind,
            'contract_reference' => $contractContext['contract_reference'],
            'parent_reference' => $contractContext['parent_reference'],
            'type_contrat_id' => $contractContext['type_contrat_id'] ?: $typeRule->type_contrat_id,
            'type_contrat_label' => $typeLabel,
            'immatriculation' => $vehicleLabel,
            'date_echeance' => $dueDate,
            'montant_attendu' => $lease['montant_attendu'] ?? null,
            'montant_paye' => $lease['montant_paye'] ?? null,
            'reste_a_payer' => $reste,
            'statut' => $lease['statut'] ?? null,
            'chauffeur_nom_complet' => $lease['chauffeur_nom_complet'] ?? null,
            'rule_id' => $rule->id,
            'type_rule_id' => $typeRule->id,
            'rule_cutoff_time' => $rule->cutoff_time,
            'type_rule_cutoff_time' => $typeRule->cutoff_time,
            'effective_grace_days' => $this->resolveEffectiveGraceDays($rule, $typeRule),
            'scheduled_for' => $scheduledFor->toDateTimeString(),
        ];

        return [
            'trigger_label' => $triggerLabel,
            'trigger_payload' => $triggerPayload,
            'payment_status_snapshot' => $triggerPayload,
            'reason' => $reason,
        ];
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

        return trim($label) !== '' ? trim($label) : ($typeId > 0 ? 'Type #' . $typeId : '');
    }

    private function normalizeImmatriculation(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
    }

    private function normalizeLabel(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_strtoupper($value, 'UTF-8');
    }
}
