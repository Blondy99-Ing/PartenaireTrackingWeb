<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffQueue;
use App\Models\LeaseCutoffRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaseCutoffPlannerService
{
    /**
     * Statuts history considérés comme terminaux métier.
     * Une fois dans ces statuts, le même événement ne doit plus être replanifié.
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
     * Statuts queue considérés comme actifs opérationnellement.
     * Ils signifient qu'un traitement est déjà en cours ou en attente de vérification.
     */
    private const QUEUE_ACTIVE_STATUSES = [
        'PENDING',
        'WAITING_STOP',
        'COMMAND_SENT',
    ];

    /**
     * Statuts queue considérés comme terminaux / clos au niveau opérationnel.
     * IMPORTANT :
     * - on ne les réactive plus automatiquement dans le planner
     * - sinon on réintroduit le risque de retraiter le même événement
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
     * Planifie les véhicules à couper à partir :
     * - des règles locales lease_cutoff_rules
     * - des contrats de l'API lease
     * - des leases NON_PAYE de l'API lease
     *
     * Principes de sûreté métier :
     * - une même combinaison partner_id + vehicle_id + contract_id + lease_id
     *   représente un événement métier unique
     * - l'history est la mémoire métier de référence
     * - la queue est la mémoire opérationnelle de traitement
     * - on ne recrée pas / ne réarme pas automatiquement un événement déjà terminal
     * - on ne réactive pas une queue terminale sans décision métier explicite
     */
    public function plan(): array
    {
        Log::info('[LEASE_CUTOFF_PLAN] Début de planification');

        $contractsById = $this->leaseApi->fetchContractsIndexedById();
        $nonPaidByContractId = $this->leaseApi->fetchLatestNonPaidLeasesIndexedByContractId();

        Log::info('[LEASE_CUTOFF_PLAN] Données API chargées', [
            'contracts_count' => count($contractsById),
            'non_paid_count' => count($nonPaidByContractId),
        ]);

        $rules = LeaseCutoffRule::query()
            ->with(['vehicle'])
            ->where('is_enabled', true)
            ->get();

        Log::info('[LEASE_CUTOFF_PLAN] Règles actives chargées', [
            'rules_count' => $rules->count(),
        ]);

        $created = 0;
        $reused = 0;
        $skipped = 0;

        $skipReasons = [
            'vehicle_missing' => 0,
            'immatriculation_missing' => 0,
            'cutoff_time_missing' => 0,
            'time_not_due' => 0,
            'contract_not_found' => 0,
            'contract_invalid' => 0,
            'non_paid_not_found' => 0,

            // Idempotence / cohérence métier
            'already_terminal_history' => 0,
            'already_active_queue' => 0,
            'already_terminal_queue' => 0,
            'history_in_progress_without_active_queue' => 0,
        ];

        DB::transaction(function () use (
            $rules,
            $contractsById,
            $nonPaidByContractId,
            &$created,
            &$reused,
            &$skipped,
            &$skipReasons
        ) {
            foreach ($rules as $rule) {
                /** @var LeaseCutoffRule $rule */
                $ctx = [
                    'rule_id' => $rule->id,
                    'partner_id' => $rule->partner_id,
                    'vehicle_id' => $rule->vehicle_id,
                    'cutoff_time' => $rule->cutoff_time,
                    'timezone' => $rule->timezone,
                ];

                $vehicle = $rule->vehicle;

                /**
                 * 1) Validation locale minimale
                 */
                if (!$vehicle) {
                    $skipped++;
                    $skipReasons['vehicle_missing']++;

                    Log::warning('[LEASE_CUTOFF_PLAN] Règle ignorée : véhicule local introuvable pour cette règle.', $ctx);
                    continue;
                }

                $ctx['vehicle_immatriculation'] = $vehicle->immatriculation ?? null;
                $ctx['vehicle_mac_id_gps'] = $vehicle->mac_id_gps ?? null;

                if (empty($vehicle->immatriculation)) {
                    $skipped++;
                    $skipReasons['immatriculation_missing']++;

                    Log::warning('[LEASE_CUTOFF_PLAN] Règle ignorée : immatriculation locale vide, impossible de faire le matching contrat.', $ctx);
                    continue;
                }

                if (empty($rule->cutoff_time)) {
                    $skipped++;
                    $skipReasons['cutoff_time_missing']++;

                    Log::warning('[LEASE_CUTOFF_PLAN] Règle ignorée : heure de coupure absente sur la règle.', $ctx);
                    continue;
                }

                if (!$this->isRuleDueNow($rule)) {
                    $skipped++;
                    $skipReasons['time_not_due']++;

                    $ctx['now_app'] = now()->toDateTimeString();
                    $ctx['scheduled_for_today'] = $this->resolveScheduledDateTime($rule)->toDateTimeString();

                    Log::info('[LEASE_CUTOFF_PLAN] Règle ignorée : l’heure de coupure configurée n’est pas encore atteinte.', $ctx);
                    continue;
                }

                /**
                 * 2) Matching contrat API par immatriculation locale
                 */
                $contract = $this->findContractByImmatriculation(
                    $contractsById,
                    (string) $vehicle->immatriculation
                );

                if (!$contract) {
                    $skipped++;
                    $skipReasons['contract_not_found']++;

                    $ctx['searched_immatriculation'] = $vehicle->immatriculation;

                    Log::warning('[LEASE_CUTOFF_PLAN] Règle ignorée : aucun contrat API trouvé pour cette immatriculation.', $ctx);
                    continue;
                }

                $contractId = (int) ($contract['id'] ?? 0);
                $ctx['matched_contract_id'] = $contractId;
                $ctx['matched_contract_immatriculation'] = $contract['immatriculation'] ?? null;

                if ($contractId <= 0) {
                    $skipped++;
                    $skipReasons['contract_invalid']++;

                    Log::warning('[LEASE_CUTOFF_PLAN] Règle ignorée : le contrat API trouvé est invalide (id absent ou <= 0).', $ctx);
                    continue;
                }

                /**
                 * 3) Matching lease NON_PAYE par contract_id
                 */
                $lease = $nonPaidByContractId[$contractId] ?? null;

                if (!$lease) {
                    $skipped++;
                    $skipReasons['non_paid_not_found']++;

                    Log::info('[LEASE_CUTOFF_PLAN] Règle ignorée : aucun lease NON_PAYE en cours pour ce contrat.', $ctx);
                    continue;
                }

                $leaseId = (int) ($lease['id'] ?? 0);
                $scheduledFor = $this->resolveScheduledDateTime($rule);

                $ctx['matched_lease_id'] = $leaseId;
                $ctx['matched_lease_due_date'] = $lease['date_echeance'] ?? null;
                $ctx['matched_lease_status'] = $lease['statut'] ?? null;
                $ctx['matched_lease_reste_a_payer'] = $lease['reste_a_payer'] ?? null;
                $ctx['scheduled_for'] = $scheduledFor->toDateTimeString();

                /**
                 * 4) Recherche de l'événement métier existant
                 * -------------------------------------------
                 * La clé métier est :
                 * partner_id + vehicle_id + contract_id + lease_id
                 */
                $history = LeaseCutoffHistory::query()
                    ->where('partner_id', $rule->partner_id)
                    ->where('vehicle_id', $rule->vehicle_id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->first();

                $queue = LeaseCutoffQueue::query()
                    ->where('partner_id', $rule->partner_id)
                    ->where('vehicle_id', $rule->vehicle_id)
                    ->where('contract_id', $contractId)
                    ->where('lease_id', $leaseId)
                    ->first();

                /**
                 * 5) Cas 1 : history déjà terminale
                 * ---------------------------------
                 * L'événement métier est déjà clos définitivement.
                 */
                if ($history && in_array($history->status, self::HISTORY_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['already_terminal_history']++;

                    Log::info('[LEASE_CUTOFF_PLAN] Événement ignoré : history déjà terminale, aucune replanification autorisée.', array_merge($ctx, [
                        'history_id' => $history->id,
                        'history_status' => $history->status,
                    ]));
                    continue;
                }

                /**
                 * 6) Cas 2 : queue active déjà présente
                 * -------------------------------------
                 * L'événement est déjà en cours de traitement ou en attente.
                 */
                if ($queue && in_array($queue->status, self::QUEUE_ACTIVE_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['already_active_queue']++;

                    Log::info('[LEASE_CUTOFF_PLAN] Événement ignoré : queue déjà active, aucun doublon créé.', array_merge($ctx, [
                        'queue_id' => $queue->id,
                        'queue_status' => $queue->status,
                        'history_id' => $queue->history_id,
                    ]));
                    continue;
                }

                /**
                 * 7) Cas 3 : queue terminale déjà présente
                 * ----------------------------------------
                 * IMPORTANT :
                 * On ne la réactive plus automatiquement.
                 * Sinon le cron recrée artificiellement le même traitement.
                 */
                if ($queue && in_array($queue->status, self::QUEUE_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['already_terminal_queue']++;

                    Log::warning('[LEASE_CUTOFF_PLAN] Événement ignoré : une queue terminale existe déjà pour ce même lease. Réactivation automatique interdite pour préserver l’idempotence.', array_merge($ctx, [
                        'queue_id' => $queue->id,
                        'queue_status' => $queue->status,
                        'history_id' => $queue->history_id,
                    ]));
                    continue;
                }

                /**
                 * 8) Cas 4 : history non terminale déjà présente sans queue active
                 * ---------------------------------------------------------------
                 * Cela révèle en général :
                 * - un traitement interrompu
                 * - une incohérence entre history et queue
                 * - un besoin d'investigation ou de reprise contrôlée
                 *
                 * Pour éviter toute répétition dangereuse, on ne recrée pas automatiquement.
                 */
                if ($history && !in_array($history->status, self::HISTORY_TERMINAL_STATUSES, true)) {
                    $skipped++;
                    $skipReasons['history_in_progress_without_active_queue']++;

                    Log::warning('[LEASE_CUTOFF_PLAN] Événement ignoré : history non terminale déjà existante sans queue active. Reprise automatique volontairement bloquée pour éviter les doublons et permettre une analyse métier.', array_merge($ctx, [
                        'history_id' => $history->id,
                        'history_status' => $history->status,
                        'queue_id' => $queue?->id,
                        'queue_status' => $queue?->status,
                    ]));
                    continue;
                }

                /**
                 * 9) Création d'un nouvel événement métier
                 * ----------------------------------------
                 * On n'arrive ici que si :
                 * - pas de history terminale
                 * - pas de queue active
                 * - pas de queue terminale à réactiver
                 * - pas de history non terminale orpheline
                 *
                 * Donc création sûre d'un nouvel événement.
                 */
                $history = LeaseCutoffHistory::create([
                    'partner_id' => $rule->partner_id,
                    'vehicle_id' => $rule->vehicle_id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId ?: null,
                    'rule_id' => $rule->id,
                    'scheduled_for' => $scheduledFor,
                    'detected_at' => now(),
                    'status' => 'PENDING',
                    'reason' => 'Lease NON_PAYE détecté à l’heure de coupure configurée ; événement de coupure créé et mis en file d’attente.',
                    'payment_status_snapshot' => [
                        'lease_id' => $lease['id'] ?? null,
                        'contrat_id' => $lease['contrat_id'] ?? null,
                        'date_echeance' => $lease['date_echeance'] ?? null,
                        'montant_attendu' => $lease['montant_attendu'] ?? null,
                        'montant_paye' => $lease['montant_paye'] ?? null,
                        'reste_a_payer' => $lease['reste_a_payer'] ?? null,
                        'statut' => $lease['statut'] ?? null,
                        'chauffeur_nom_complet' => $lease['chauffeur_nom_complet'] ?? null,
                    ],
                    'notes' => 'Création automatique initiale depuis le planner de coupure lease.',
                ]);

                $queue = LeaseCutoffQueue::create([
                    'partner_id' => $rule->partner_id,
                    'vehicle_id' => $rule->vehicle_id,
                    'contract_id' => $contractId,
                    'lease_id' => $leaseId ?: null,
                    'rule_id' => $rule->id,
                    'history_id' => $history->id,
                    'scheduled_for' => $scheduledFor,
                    'status' => 'PENDING',
                    'retry_count' => 0,
                    'last_checked_at' => null,
                    'next_check_at' => now(),
                ]);

                $created++;

                Log::info('[LEASE_CUTOFF_PLAN] Événement créé avec succès : history + queue initiale.', array_merge($ctx, [
                    'history_id' => $history->id,
                    'queue_id' => $queue->id,
                ]));
            }
        });

        Log::info('[LEASE_CUTOFF_PLAN] Fin de planification', [
            'created' => $created,
            'reused' => $reused,
            'skipped' => $skipped,
            'skip_reasons' => $skipReasons,
        ]);

        return [
            'success' => true,
            'created' => $created,
            'reused' => $reused,
            'skipped' => $skipped,
            'skip_reasons' => $skipReasons,
        ];
    }

    /**
     * Vérifie si la règle est due maintenant selon son fuseau.
     *
     * Exemple :
     * - règle à 18:00
     * - timezone = Africa/Douala
     * - si maintenant >= aujourd'hui 18:00 => due
     */
    private function isRuleDueNow(LeaseCutoffRule $rule): bool
    {
        if (!$rule->cutoff_time) {
            return false;
        }

        $timezone = $rule->timezone ?: config('app.timezone', 'Africa/Douala');

        $now = Carbon::now($timezone);
        $scheduled = Carbon::parse($now->format('Y-m-d') . ' ' . $rule->cutoff_time, $timezone);

        return $now->greaterThanOrEqualTo($scheduled);
    }

    /**
     * Retourne la datetime théorique de coupure pour aujourd'hui,
     * normalisée dans le fuseau applicatif.
     */
    private function resolveScheduledDateTime(LeaseCutoffRule $rule): Carbon
    {
        $timezone = $rule->timezone ?: config('app.timezone', 'Africa/Douala');
        $now = Carbon::now($timezone);

        return Carbon::parse($now->format('Y-m-d') . ' ' . $rule->cutoff_time, $timezone)
            ->setTimezone(config('app.timezone'));
    }

    /**
     * Recherche un contrat API par immatriculation.
     *
     * Matching strict après trim + uppercase
     * pour éviter des faux positifs métier.
     */
    private function findContractByImmatriculation(array $contractsById, string $immatriculation): ?array
    {
        $target = strtoupper(trim($immatriculation));

        foreach ($contractsById as $contract) {
            $immat = strtoupper(trim((string) ($contract['immatriculation'] ?? '')));

            if ($immat !== '' && $immat === $target) {
                return $contract;
            }
        }

        return null;
    }
}