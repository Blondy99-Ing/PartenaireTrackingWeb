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
 * - le cron automatique traite uniquement la date métier du jour ;
 * - une reprise d'hier ou d'une autre date se fait uniquement avec --date=YYYY-MM-DD ;
 * - chaque lease est traité indépendamment ;
 * - Moto, Téléphone, Casque, etc. peuvent avoir chacun leur règle et leur heure ;
 * - une règle Moto désactivée ne bloque pas une règle Téléphone active ;
 * - une règle Téléphone active ne dépend pas de la règle Moto ;
 * - une coupure est planifiée seulement si le contrat/sous-contrat exact possède une règle active ;
 * - aucun type général ou sous-contrat non associé ne peut déclencher une coupure.
 */
class LeaseCutoffPlannerService
{
    private const HISTORY_TERMINAL_STATUSES = [
        'CUT_OFF',
        'CANCELLED_PAID',
        'CANCELLED_UNVERIFIED',
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
        $contractsCount = count($contractsById);

        $nonPaidLeases = $this->leaseApi->fetchNonPaidLeasesForDate($targetDate);
        $leasesCount = count($nonPaidLeases);

        Log::info('[LEASE_CUTOFF_PLAN] Données Recouvrement reçues avant application des règles.', [
            'target_date_echeance' => $targetDate,
            'contracts_indexed_count' => $contractsCount,
            'non_paid_leases_count' => $leasesCount,
            'lease_ids' => collect($nonPaidLeases)->pluck('id')->take(50)->values()->all(),
            'api_diagnostics' => $this->leaseApi->getLastDiagnostics(),
        ]);

        if ($leasesCount === 0) {
            Log::warning('[LEASE_CUTOFF_PLAN] Arrêt normal : aucun lease NON_PAYE reçu par Tracking pour cette date. Les règles ne sont donc pas évaluées.', [
                'target_date_echeance' => $targetDate,
                'contracts_indexed_count' => $contractsCount,
                'api_diagnostics' => $this->leaseApi->getLastDiagnostics(),
                'hint' => 'Comparer cette URL/token avec le navigateur : /api/v1/leases/?statut=NON_PAYE&date_echeance=' . $targetDate,
            ]);
        }

        $created = 0;
        $reused = 0;
        $skipped = 0;

        $skipReasons = [
            'lease_invalid' => 0,
            'lease_not_non_paid' => 0,
            'lease_date_mismatch' => 0,
            'contract_not_found' => 0,
            'contract_link_missing' => 0,
            'vehicle_missing' => 0,
            'contract_rule_missing_or_disabled' => 0,
            'rule_not_active_today' => 0,
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

            /**
             * Sécurité jour par jour :
             * même si l'API Recouvrement est appelée avec date_echeance=$targetDate,
             * on refuse tout lease dont la date réelle ne correspond pas exactement
             * à la journée traitée.
             *
             * Résultat :
             * - le cron du 14 ne traite que le 14 ;
             * - une queue ou un lease du 13 ne revient pas automatiquement le 14 ;
             * - une date passée se rejoue uniquement avec --date=YYYY-MM-DD.
             */
            if ($dueDate !== $targetDate) {
                $skipped++;
                $skipReasons['lease_date_mismatch']++;
                Log::warning('[LEASE_CUTOFF_PLAN] Lease ignoré : date_echeance différente de la date traitée.', $ctx);
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
             *
             * Si le lease concerne Téléphone, on cherche le contract_link Téléphone.
             * Si le lease concerne Moto, on cherche le contract_link Moto.
             *
             * On ne doit jamais utiliser uniquement le véhicule ou le contrat parent.
             */
            $contractLink = $this->resolveExactContractLink(
                sourceContractId: $contractId,
                partnerId: $this->extractPartnerIdFromContractContext($contract, $lease)
            );
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

            /**
             * Règle centrale :
             * seule la règle du contrat/sous-contrat exact compte.
             *
             * Si Moto est désactivée, cela n'empêche pas Téléphone de couper
             * si Téléphone possède sa propre règle active.
             */
            $contractRule = $this->resolveEffectiveContractRule($contractLink, $dueDate);
            if (! $contractRule) {
                $skipped++;
                $skipReasons['contract_rule_missing_or_disabled']++;
                Log::info('[LEASE_CUTOFF_PLAN] Lease ignoré : aucune règle spécifique active sur le contrat/sous-contrat exact.', array_merge($ctx, [
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

            /**
             * Chaque entité a sa propre heure :
             * - Moto peut être à 12:00 ;
             * - Téléphone peut être à 17:00 ;
             * - Casque peut être à une autre heure.
             */
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
                $leaseId,
                $contractId,
                $dueDate,
                $scheduledFor,
                $trigger,
                &$created,
                &$reused,
                &$skipped,
                &$skipReasons,
                $ctx
            ) {
                /**
                 * Idempotence par entité contractuelle :
                 * on vérifie lease_id + contract_link_id + date.
                 *
                 * On ne bloque jamais par véhicule seul, sinon Moto à 12h pourrait
                 * empêcher Téléphone à 17h de déclencher sa propre coupure.
                 */
                $history = LeaseCutoffHistory::query()
                    ->where('partner_id', $contractRule->partner_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->where('contract_link_id', $contractLink->id)
                    ->whereDate('lease_date_echeance', $dueDate)
                    ->lockForUpdate()
                    ->first();

                $queue = LeaseCutoffQueue::query()
                    ->where('partner_id', $contractRule->partner_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->where('contract_link_id', $contractLink->id)
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

                /**
                 * Le pardon manuel peut créer la même ligne d'historique juste avant
                 * ce cron (même vehicle_id + contract_link_id + lease_id + échéance).
                 * La contrainte unique en base rejette alors cette création : on le
                 * traite comme "déjà pris en charge ailleurs", pas comme une erreur.
                 */
                try {
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
                        'contract_rule_id' => $contractRule->id,
                        'scheduled_for' => $scheduledFor,
                        'detected_at' => now(),
                        'status' => 'PENDING',
                        'reason' => $trigger['reason'],
                        'payment_status_snapshot' => $trigger['payment_status_snapshot'],
                        'notes' => 'Événement créé automatiquement depuis les leases NON_PAYE du jour. Aucune commande GPS n’a encore été envoyée.',
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
                        'contract_rule_id' => $contractRule->id,
                        'history_id' => $history->id,
                        'scheduled_for' => $scheduledFor,
                        'status' => 'PENDING',
                        'retry_count' => 0,
                        'last_checked_at' => null,
                        'next_check_at' => now(),
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    if ((string) $e->getCode() !== '23000') {
                        throw $e;
                    }

                    $reused++;
                    $skipReasons['already_active_queue']++;
                    Log::info('[LEASE_CUTOFF_PLAN] Création concurrente détectée (contrainte unique) : probablement créée par un pardon manuel au même instant.', $ctx);

                    return;
                }

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
            'contracts_indexed_count' => $contractsCount,
            'non_paid_leases_count' => $leasesCount,
            'created' => $created,
            'reused' => $reused,
            'skipped' => $skipped,
            'skip_reasons' => $skipReasons,
            'api_diagnostics' => $this->leaseApi->getLastDiagnostics(),
        ]);

        return [
            'success' => true,
            'target_date_echeance' => $targetDate,
            'contracts_indexed_count' => $contractsCount,
            'non_paid_leases_count' => $leasesCount,
            'created' => $created,
            'reused' => $reused,
            'skipped' => $skipped,
            'skip_reasons' => $skipReasons,
            'api_diagnostics' => $this->leaseApi->getLastDiagnostics(),
        ];
    }

    private function resolveTargetDueDate(?string $dateEcheance, ?int $offsetDays): string
    {
        $timezone = config('app.timezone', 'Africa/Douala');

        if ($dateEcheance && trim($dateEcheance) !== '') {
            return Carbon::parse($dateEcheance, $timezone)->toDateString();
        }

        /**
         * Sans --date, le cron automatique traite uniquement aujourd'hui.
         * $offsetDays est ignoré volontairement pour empêcher les reprises
         * automatiques d'hier.
         */
        return Carbon::now($timezone)->toDateString();
    }

    /**
     * Le partner_id est obligatoire : sans lui, le filtre serait silencieusement
     * ignoré et la requête pourrait matcher un LeaseContractLink appartenant à
     * un AUTRE partenaire si jamais deux partenaires partageaient un
     * source_contract_id. On préfère ignorer le lease plutôt que risquer de
     * planifier une coupure sur le véhicule d'un tiers.
     */
    private function resolveExactContractLink(int $sourceContractId, ?int $partnerId = null): ?LeaseContractLink
    {
        if (! $partnerId || $partnerId <= 0) {
            Log::warning('[LEASE_CUTOFF_PLAN] Résolution du contrat refusée : partner_id introuvable dans le payload API.', [
                'source_contract_id' => $sourceContractId,
            ]);

            return null;
        }

        return LeaseContractLink::query()
            ->with(['vehicle', 'cutoffRule'])
            ->where('source_contract_id', $sourceContractId)
            ->where('partner_id', $partnerId)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'DELETED');
            })
            ->orderByDesc('updated_at')
            ->first();
    }

    private function extractPartnerIdFromContractContext(array $contract, array $lease): ?int
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

    /**
     * Résout la règle réellement applicable au contrat/sous-contrat exact.
     *
     * Nouvelle logique métier :
     * - le cron ne lit JAMAIS lease_cutoff_default_rules ;
     * - le cron ne crée JAMAIS de règle spécifique automatiquement ;
     * - une règle par défaut est seulement un modèle utilisé volontairement lors
     *   de la création ou du paramétrage d'un contrat ;
     * - sans ligne dans lease_cutoff_contract_rules pour ce contract_link_id,
     *   aucune coupure ne doit être planifiée.
     */
    private function resolveEffectiveContractRule(LeaseContractLink $link, string $dueDate): ?LeaseCutoffContractRule
    {
        $specificRule = LeaseCutoffContractRule::query()
            ->where('contract_link_id', $link->id)
            ->where('partner_id', $link->partner_id)
            ->first();

        if (! $specificRule) {
            Log::info('[LEASE_CUTOFF_PLAN] Aucune règle spécifique trouvée : pas de matérialisation automatique depuis les règles par défaut.', [
                'partner_id' => $link->partner_id,
                'contract_link_id' => $link->id,
                'source_contract_id' => $link->source_contract_id,
                'type_contrat_id' => $link->type_contrat_id,
                'type_contrat_label' => $link->type_contrat_label,
            ]);

            return null;
        }

        if (! $specificRule->is_enabled) {
            return null;
        }

        return $this->isRuleActiveForDueDate($specificRule, $dueDate) ? $specificRule : null;
    }

    private function isRuleActiveForDueDate(LeaseCutoffContractRule $rule, string $dueDate): bool
    {
        $days = $this->normalizeActiveDays($rule->active_days ?? []);

        if (empty($days)) {
            return false;
        }

        $day = Carbon::parse($dueDate, $rule->effectiveTimezone())->englishDayOfWeek;

        return in_array(strtolower($day), $days, true);
    }

    private function normalizeActiveDays(array $days): array
    {
        $allowed = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        return collect($days)
            ->map(fn ($day) => strtolower((string) $day))
            ->filter(fn ($day) => in_array($day, $allowed, true))
            ->unique()
            ->values()
            ->all();
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
            'Le %s "%s" a causé la planification de coupure de %s car le lease #%d du %s #%d%s, échéance du %s, est NON_PAYE%s. La règle applicable de cette entité contractuelle est active. Coupure planifiée pour %s.',
            $isSub ? 'sous-contrat' : 'contrat principal',
            $typeLabel,
            $vehicleLabel,
            $leaseId,
            $triggerName,
            $contractId,
            $parentText,
            $dueDate,
            $amountText,
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