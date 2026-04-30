<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffHistory;
use App\Models\LeaseCutoffRule;
use App\Models\Voiture;
use App\Services\Keycloak\KeycloakSessionTokenManager;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PartnerLeaseApiService
{
    /**
     * Endpoint API recouvrement : GET /contrats/
     *
     * Sert à récupérer les contrats et à enrichir l'affichage local.
     */
    public function fetchContracts(): array
    {
        Log::info('[LEASE_API_FETCH_CONTRACTS_START]');

        $json = $this->get('/contrats/');
        $rows = $this->unwrapApiRows($json);

        Log::info('[LEASE_API_FETCH_CONTRACTS_ROWS]', [
            'raw_type' => $this->describeArrayShape($json),
            'rows_count' => count($rows),
        ]);

        $contracts = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $this->normalizeContract($row))
            ->values()
            ->all();

        Log::info('[LEASE_API_FETCH_CONTRACTS_DONE]', [
            'contracts_count' => count($contracts),
            'sample_ids' => collect($contracts)->pluck('id')->take(5)->values()->all(),
        ]);

        return $contracts;
    }

    /**
     * Endpoint API recouvrement : GET /accounts/chauffeurs/
     *
     * Sert au formulaire de création contrat.
     */
    public function fetchChauffeurs(): array
    {
        Log::info('[LEASE_API_FETCH_CHAUFFEURS_START]');

        $json = $this->get('/accounts/chauffeurs/');
        $rows = $this->unwrapApiRows($json);

        Log::info('[LEASE_API_FETCH_CHAUFFEURS_ROWS]', [
            'raw_type' => $this->describeArrayShape($json),
            'rows_count' => count($rows),
        ]);

        $chauffeurs = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                $nomComplet = trim((string) (
                    $row['nom_complet']
                    ?? $row['full_name']
                    ?? $row['name']
                    ?? ''
                ));

                if ($nomComplet === '') {
                    $nomComplet = trim((string) ($row['email'] ?? ''));
                }

                if ($nomComplet === '') {
                    $nomComplet = 'Chauffeur #' . ($row['id'] ?? '');
                }

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'keycloak_id' => (string) ($row['keycloak_id'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'nom_complet' => $nomComplet,
                    'label' => trim($nomComplet . (! empty($row['email']) ? ' — ' . $row['email'] : '')),
                    'is_active' => (bool) ($row['is_active'] ?? $row['enabled'] ?? true),
                    'raw' => $row,
                ];
            })
            ->filter(fn ($row) => ! empty($row['id']))
            ->values()
            ->all();

        Log::info('[LEASE_API_FETCH_CHAUFFEURS_DONE]', [
            'chauffeurs_count' => count($chauffeurs),
            'sample_ids' => collect($chauffeurs)->pluck('id')->take(5)->values()->all(),
        ]);

        return $chauffeurs;
    }

    /**
     * Récupère les véhicules locaux du partenaire connecté.
     *
     * Sert au select "immatriculation" dans le formulaire contrat.
     */
    public function fetchPartnerVehiclesForContracts(): array
    {
        $partnerId = $this->resolvePartnerId();

        Log::info('[LEASE_LOCAL_FETCH_PARTNER_VEHICLES_START]', [
            'partner_id' => $partnerId,
        ]);

        $vehicles = Voiture::query()
            ->select([
                'voitures.id',
                'voitures.immatriculation',
                'voitures.marque',
                'voitures.model',
                'voitures.mac_id_gps',
            ])
            ->join(
                'association_user_voitures',
                'association_user_voitures.voiture_id',
                '=',
                'voitures.id'
            )
            ->where('association_user_voitures.user_id', $partnerId)
            ->orderBy('voitures.immatriculation')
            ->get()
            ->map(function ($vehicle) {
                $immat = trim((string) $vehicle->immatriculation);
                $marque = trim((string) $vehicle->marque);
                $model = trim((string) $vehicle->model);

                $details = trim($marque . ' ' . $model);

                return [
                    'id' => (int) $vehicle->id,
                    'immatriculation' => $immat,
                    'label' => $details !== ''
                        ? "{$immat} — {$details}"
                        : $immat,
                    'mac_id_gps' => $vehicle->mac_id_gps,
                ];
            })
            ->filter(fn ($row) => $row['immatriculation'] !== '')
            ->values()
            ->all();

        Log::info('[LEASE_LOCAL_FETCH_PARTNER_VEHICLES_DONE]', [
            'partner_id' => $partnerId,
            'vehicles_count' => count($vehicles),
            'sample_immats' => collect($vehicles)->pluck('immatriculation')->take(5)->values()->all(),
        ]);

        return $vehicles;
    }

    /**
     * Endpoint API recouvrement : POST /contrats/
     *
     * Format attendu :
     * {
     *   "chauffeur": 43,
     *   "montant_total": "1500000",
     *   "immatriculation": "PROXYM MERC1",
     *   "montant_par_paiement": "25000",
     *   "frequence": "HEBDOMADAIRE",
     *   "date_debut": "2026-04-20",
     *   "date_fin": "2027-04-20",
     *   "prochaine_echeance": "2026-04-20"
     * }
     */
    public function createContract(array $payload): array
    {
        $apiPayload = [
            'chauffeur' => (int) $payload['chauffeur'],
            'montant_total' => (string) $payload['montant_total'],
            'immatriculation' => (string) $payload['immatriculation'],
            'montant_par_paiement' => (string) $payload['montant_par_paiement'],
            'frequence' => mb_strtoupper((string) $payload['frequence'], 'UTF-8'),
            'date_debut' => (string) $payload['date_debut'],
            'date_fin' => (string) $payload['date_fin'],
            'prochaine_echeance' => (string) $payload['prochaine_echeance'],
        ];

        Log::info('[LEASE_API_CREATE_CONTRACT_START]', [
            'payload' => $apiPayload,
        ]);

        $response = $this->post('/contrats/', $apiPayload);

        Log::info('[LEASE_API_CREATE_CONTRACT_DONE]', [
            'response_shape' => $this->describeArrayShape($response),
            'response_id' => data_get($response, 'id') ?? data_get($response, 'data.id'),
        ]);

        return $response;
    }

    /**
     * Endpoint API recouvrement : GET /leases/
     *
     * Source principale de la page paiements.
     *
     * Cette méthode enrichit chaque lease avec GET /paiements/.
     *
     * Mapping :
     * - leases.id = paiements.lease
     *
     * /leases/ donne :
     * - date_echeance
     * - montant_attendu
     * - montant_paye
     * - reste_a_payer
     * - statut PAYE / NON_PAYE
     *
     * /paiements/ donne :
     * - methode
     * - reference
     * - transaction_id
     * - statut paiement réel
     * - date_paiement
     * - enregistre_par
     */
    public function fetchLeases(?string $status = null, array $contracts = []): array
    {
        $query = [];

        if ($status) {
            $apiStatus = $this->mapUiStatusToApiStatus($status);

            if ($apiStatus) {
                $query['statut'] = $apiStatus;
            }
        }

        Log::info('[LEASE_API_FETCH_LEASES_START]', [
            'status' => $status,
            'query' => $query,
            'contracts_count_for_enrichment' => count($contracts),
        ]);

        $json = $this->get('/leases/', $query);
        $rows = $this->unwrapApiRows($json);

        Log::info('[LEASE_API_FETCH_LEASES_ROWS]', [
            'raw_type' => $this->describeArrayShape($json),
            'rows_count' => count($rows),
            'first_row_keys' => isset($rows[0]) && is_array($rows[0])
                ? array_keys($rows[0])
                : [],
        ]);

        /**
         * Nouvelle source d'enrichissement.
         *
         * Si /paiements/ échoue, on ne bloque pas l'affichage des leases.
         * On garde /leases/ comme source principale.
         */
        try {
            $payments = $this->fetchPayments();
        } catch (\Throwable $e) {
            report($e);

            Log::error('[LEASE_API_FETCH_PAYMENTS_FOR_LEASES_FAILED]', [
                'error' => $e->getMessage(),
            ]);

            $payments = [];
        }

       $paymentMetaByLeaseId = $this->getPaymentMetaByLeaseId($payments);
        $contractsById = collect($contracts)->keyBy('source_contrat_id');

        /**
         * Configuration de coupure par véhicule :
         * règle activée + heure prévue.
         */
        $cutoffMetaByImmat = $this->getPartnerCutoffMetaByImmat();

        /**
         * Historique de pardon.
         */
        $forgivenessMetaByLeaseId = $this->getForgivenessMetaByLeaseId();

        /**
         * Historique réel de coupure par lease.
         */
        $cutoffStatusMetaByLeaseId = $this->getCutoffStatusMetaByLeaseId();

        $leases = collect($rows)
            ->filter(fn ($row) => is_array($row))
           ->map(function (array $row) use (
                $contractsById,
                $cutoffMetaByImmat,
                $forgivenessMetaByLeaseId,
                $paymentMetaByLeaseId,
                $cutoffStatusMetaByLeaseId
            ) {
                $contractId = (int) (
                    $row['contrat_id']
                    ?? $row['contrat']
                    ?? data_get($row, 'contrat.id')
                    ?? 0
                );

                $linkedContract = $contractId > 0
                    ? $contractsById->get($contractId)
                    : null;

                $contract = is_array($linkedContract) ? $linkedContract : null;

                $immat = mb_strtoupper(trim((string) (
                    $row['immatriculation']
                    ?? $row['vehicule']
                    ?? $row['vehicle']
                    ?? data_get($row, 'contrat.immatriculation')
                    ?? $contract['vehicule']
                    ?? ''
                )));

                $cutoffMeta = $immat !== ''
                    ? ($cutoffMetaByImmat[$immat] ?? null)
                    : null;

                $leaseId = (int) ($row['id'] ?? 0);

                $forgivenessMeta = $leaseId > 0
                    ? ($forgivenessMetaByLeaseId[$leaseId] ?? null)
                    : null;

                $paymentMeta = $leaseId > 0
                    ? ($paymentMetaByLeaseId[$leaseId] ?? null)
                    : null;

                $cutoffStatusMeta = $leaseId > 0
                    ? ($cutoffStatusMetaByLeaseId[$leaseId] ?? null)
                    : null;

               return $this->normalizeLease(
                    row: $row,
                    contract: $contract,
                    cutoffMeta: $cutoffMeta,
                    forgivenessMeta: $forgivenessMeta,
                    paymentMeta: $paymentMeta,
                    cutoffStatusMeta: $cutoffStatusMeta
                );
            })
            ->values()
            ->all();

        Log::info('[LEASE_API_FETCH_LEASES_DONE]', [
            'leases_count' => count($leases),
            'payments_count' => count($payments),
            'payments_linked_count' => count($paymentMetaByLeaseId),
            'sample_ids' => collect($leases)->pluck('id')->take(5)->values()->all(),
            'sample_statuses' => collect($leases)->pluck('statut')->take(10)->values()->all(),
            'sample_payment_methods' => collect($leases)->pluck('methode')->take(10)->values()->all(),
        ]);

        return $leases;
    }

    /**
     * Endpoint API recouvrement : GET /paiements/
     *
     * Sert à enrichir les lignes de lease avec :
     * - méthode de paiement
     * - référence
     * - transaction_id
     * - statut paiement
     * - date paiement
     * - encaissé / enregistré par
     *
     * IMPORTANT :
     * /leases/ reste la source principale pour :
     * - échéance
     * - montant attendu
     * - montant payé
     * - reste à payer
     * - statut PAYE / NON_PAYE
     */
    public function fetchPayments(): array
    {
        Log::info('[LEASE_API_FETCH_PAYMENTS_START]');

        $json = $this->get('/paiements/');
        $rows = $this->unwrapApiRows($json);

        Log::info('[LEASE_API_FETCH_PAYMENTS_ROWS]', [
            'raw_type' => $this->describeArrayShape($json),
            'rows_count' => count($rows),
            'first_row_keys' => isset($rows[0]) && is_array($rows[0])
                ? array_keys($rows[0])
                : [],
        ]);

        $payments = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $this->normalizePayment($row))
            ->filter(fn (array $row) => ! empty($row['lease_id']))
            ->values()
            ->all();

        Log::info('[LEASE_API_FETCH_PAYMENTS_DONE]', [
            'payments_count' => count($payments),
            'sample_ids' => collect($payments)->pluck('id')->take(5)->values()->all(),
            'sample_lease_ids' => collect($payments)->pluck('lease_id')->take(5)->values()->all(),
        ]);

        return $payments;
    }

    /**
     * Endpoint API recouvrement : POST /paiements/
     *
     * Format attendu :
     * {
     *   "lease": 14,
     *   "montant": 2500
     * }
     */
    public function registerCashPayment(array $payload): array
    {
        /**
         * Important :
         * L’API recouvrement attend officiellement :
         * {
         *   "lease": 14,
         *   "montant": 2500
         * }
         *
         * On ne rajoute pas recorded_by dans le POST pour ne pas casser l’API.
         * L’enregistreur doit être identifié côté recouvrement via le token Keycloak.
         */
        $apiPayload = [
            'lease' => (int) $payload['lease_id'],
            'montant' => (float) $payload['montant'],
        ];

        Log::info('[LEASE_API_REGISTER_CASH_PAYMENT_START]', [
            'payload' => $apiPayload,
            'recorded_by' => $payload['recorded_by'] ?? null,
            'recorded_by_name' => $payload['recorded_by_name'] ?? null,
        ]);

        $response = $this->post('/paiements/', $apiPayload);

        Log::info('[LEASE_API_REGISTER_CASH_PAYMENT_DONE]', [
            'lease_id' => $apiPayload['lease'],
            'montant' => $apiPayload['montant'],
            'recorded_by' => $payload['recorded_by'] ?? null,
            'recorded_by_name' => $payload['recorded_by_name'] ?? null,
            'response_shape' => $this->describeArrayShape($response),
        ]);

        return $response;
    }

    /**
     * Construit les indicateurs du bloc coupure automatique sur la page paiements.
     */
    public function buildPaymentCutoffHub(array $leaseData): array
    {
        $partnerId = $this->resolvePartnerId();

        Log::info('[LEASE_CUTOFF_HUB_BUILD_START]', [
            'partner_id' => $partnerId,
            'lease_rows_count' => count($leaseData),
        ]);

        $activeRules = LeaseCutoffRule::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->whereNotNull('cutoff_time')
            ->get(['vehicle_id', 'cutoff_time']);

        $globalEnabled = $activeRules->isNotEmpty();

        $mostCommonTime = $activeRules
            ->groupBy(fn ($rule) => substr((string) $rule->cutoff_time, 0, 5))
            ->sortByDesc(fn ($rows) => count($rows))
            ->keys()
            ->first();

        $eligibleUnpaid = collect($leaseData)
            ->filter(fn ($row) =>
                ($row['statut'] ?? null) === 'unpaid'
                && ! empty($row['coupure_auto'])
                && ! empty($row['heure_coupure'])
            )
            ->values();

        $upcomingTimes = $eligibleUnpaid
            ->pluck('heure_coupure')
            ->filter()
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->unique()
            ->sortBy(fn ($time) => $this->minutesUntil($time))
            ->values()
            ->all();

        $hub = [
            'global_enabled' => $globalEnabled,
            'global_time' => $mostCommonTime,
            'next_cutoff_time' => $upcomingTimes[0] ?? null,
            'upcoming_cutoff_times' => $upcomingTimes,
            'active_rules_count' => $activeRules->count(),
            'eligible_unpaid_count' => $eligibleUnpaid->count(),
        ];

        Log::info('[LEASE_CUTOFF_HUB_BUILD_DONE]', $hub);

        return $hub;
    }

    /**
     * Applique une règle globale de coupure sur tous les véhicules locaux du partenaire.
     */
    public function applyGlobalCutoffRule(bool $enabled, ?string $cutoffTime): array
    {
        $partnerId = $this->resolvePartnerId();
        $userId = Auth::id();

        Log::info('[LEASE_CUTOFF_GLOBAL_APPLY_START]', [
            'partner_id' => $partnerId,
            'user_id' => $userId,
            'enabled' => $enabled,
            'cutoff_time' => $cutoffTime,
        ]);

        $vehicleIds = Voiture::query()
            ->join(
                'association_user_voitures',
                'association_user_voitures.voiture_id',
                '=',
                'voitures.id'
            )
            ->where('association_user_voitures.user_id', $partnerId)
            ->pluck('voitures.id')
            ->unique()
            ->values();

        DB::transaction(function () use ($vehicleIds, $partnerId, $userId, $enabled, $cutoffTime) {
            foreach ($vehicleIds as $vehicleId) {
                $existingRule = LeaseCutoffRule::query()
                    ->where('partner_id', $partnerId)
                    ->where('vehicle_id', $vehicleId)
                    ->first();

                if ($existingRule) {
                    $existingRule->update([
                        'is_enabled' => $enabled,
                        'cutoff_time' => $enabled ? $cutoffTime : null,
                        'updated_by' => $userId,
                    ]);
                } else {
                    LeaseCutoffRule::query()->create([
                        'partner_id' => $partnerId,
                        'vehicle_id' => $vehicleId,
                        'is_enabled' => $enabled,
                        'cutoff_time' => $enabled ? $cutoffTime : null,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }
            }
        });

        Log::info('[LEASE_CUTOFF_GLOBAL_APPLY_RULES_SAVED]', [
            'partner_id' => $partnerId,
            'vehicles_count' => $vehicleIds->count(),
        ]);

        $contracts = $this->fetchContracts();
        $leaseData = $this->fetchLeases(null, $contracts);

        $result = [
            'hub' => $this->buildPaymentCutoffHub($leaseData),
        ];

        Log::info('[LEASE_CUTOFF_GLOBAL_APPLY_DONE]', $result['hub']);

        return $result;
    }

    /**
     * Récupère les métadonnées de coupure locale par immatriculation.
     */
    protected function getPartnerCutoffMetaByImmat(): array
    {
        $partnerId = $this->resolvePartnerId();

        $rows = Voiture::query()
            ->select([
                'voitures.id',
                'voitures.immatriculation',
                'lease_cutoff_rules.is_enabled',
                'lease_cutoff_rules.cutoff_time',
            ])
            ->join(
                'association_user_voitures',
                'association_user_voitures.voiture_id',
                '=',
                'voitures.id'
            )
            ->leftJoin('lease_cutoff_rules', function ($join) use ($partnerId) {
                $join->on('lease_cutoff_rules.vehicle_id', '=', 'voitures.id')
                    ->where('lease_cutoff_rules.partner_id', '=', $partnerId);
            })
            ->where('association_user_voitures.user_id', $partnerId)
            ->get()
            ->mapWithKeys(function ($row) {
                $immat = mb_strtoupper(trim((string) $row->immatriculation));

                return [
                    $immat => [
                        'vehicle_id' => (int) $row->id,
                        'coupure_auto' => (bool) ($row->is_enabled ?? false),
                        'heure_coupure' => $row->cutoff_time
                            ? substr((string) $row->cutoff_time, 0, 5)
                            : null,
                    ],
                ];
            })
            ->all();

        Log::debug('[LEASE_CUTOFF_META_BY_IMMAT]', [
            'partner_id' => $partnerId,
            'count' => count($rows),
            'immats' => array_slice(array_keys($rows), 0, 10),
        ]);

        return $rows;
    }

 
    /**
 * Récupère la dernière information locale de pardon pour chaque lease.
 *
 * Sert à afficher dans la vue :
 * - le statut de pardon ;
 * - la raison ;
 * - qui a pardonné ;
 * - quand le pardon a été accordé.
 */
protected function getForgivenessMetaByLeaseId(): array
{
    $partnerId = $this->resolvePartnerId();

    $rows = LeaseCutoffHistory::query()
        ->where('partner_id', $partnerId)
        ->whereIn('status', [
            'CANCELLED_FORGIVEN_BEFORE_CUT',
            'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
            'REACTIVATED_AFTER_FORGIVENESS',
            'REACTIVATION_FAILED_AFTER_FORGIVENESS',
        ])
        ->whereNotNull('lease_id')
        ->orderByDesc('id')
        ->get([
            'lease_id',
            'status',
            'reason',
            'forgiven_by_user_id',
            'forgiven_by_name',
            'forgiven_at',
            'updated_at',
        ])
        ->unique('lease_id')
        ->mapWithKeys(function (LeaseCutoffHistory $row) {
            return [
                (int) $row->lease_id => [
                    'history_status' => $row->status,
                    'reason' => $row->reason,

                    'forgiven_by_user_id' => $row->forgiven_by_user_id,
                    'forgiven_by_name' => $row->forgiven_by_name,
                    'forgiven_at' => $row->forgiven_at
                        ? \Illuminate\Support\Carbon::parse($row->forgiven_at)->toDateTimeString()
                        : null,

                    'updated_at' => $row->updated_at?->toDateTimeString(),
                ],
            ];
        })
        ->all();

    Log::debug('[LEASE_FORGIVENESS_META_BY_LEASE_ID]', [
        'partner_id' => $partnerId,
        'count' => count($rows),
        'lease_ids' => array_slice(array_keys($rows), 0, 10),
    ]);

    return $rows;
}


/**
 * Récupère le dernier statut réel de coupure par lease.
 *
 * Cette donnée sert à afficher dans la colonne "Coupure" :
 * - si le véhicule a été coupé ;
 * - si la commande a été envoyée ;
 * - si la coupure a échoué ;
 * - si la coupure attend l’arrêt du véhicule ;
 * - si elle a été annulée par paiement ;
 * - si elle a été annulée par pardon ;
 * - si un rallumage a été demandé ou effectué après pardon.
 *
 * Source locale :
 * lease_cutoff_histories
 */
protected function getCutoffStatusMetaByLeaseId(): array
{
    $partnerId = $this->resolvePartnerId();

    $terminalAndProgressStatuses = [
        'PENDING',
        'WAITING_STOP',
        'COMMAND_SENT',
        'CUT_OFF',
        'CANCELLED_PAID',
        'FAILED',

        'CANCELLED_FORGIVEN_BEFORE_CUT',
        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS',
        'REACTIVATED_AFTER_FORGIVENESS',
        'REACTIVATION_FAILED_AFTER_FORGIVENESS',
    ];

    $rows = LeaseCutoffHistory::query()
        ->where('partner_id', $partnerId)
        ->whereNotNull('lease_id')
        ->whereIn('status', $terminalAndProgressStatuses)
        ->orderByDesc('id')
        ->get([
            'id',
            'lease_id',
            'vehicle_id',
            'contract_id',
            'status',
            'reason',
            'scheduled_for',
            'detected_at',
            'cutoff_executed_at',
            'forgiven_by_user_id',
            'forgiven_by_name',
            'forgiven_at',
            'updated_at',
            'created_at',
        ])
        ->unique('lease_id')
        ->mapWithKeys(function (LeaseCutoffHistory $row) {
            $status = (string) $row->status;

            return [
                (int) $row->lease_id => [
                    'history_id' => $row->id,
                    'lease_id' => (int) $row->lease_id,
                    'vehicle_id' => $row->vehicle_id,
                    'contract_id' => $row->contract_id,

                    'status' => $status,
                    'label' => $this->cutoffStatusLabel($status),
                    'ui_type' => $this->cutoffStatusUiType($status),

                    'reason' => $row->reason,

                    'scheduled_for' => $row->scheduled_for
                        ? Carbon::parse($row->scheduled_for)->toDateTimeString()
                        : null,

                    'detected_at' => $row->detected_at
                        ? Carbon::parse($row->detected_at)->toDateTimeString()
                        : null,

                    'cutoff_executed_at' => $row->cutoff_executed_at
                        ? Carbon::parse($row->cutoff_executed_at)->toDateTimeString()
                        : null,

                    'forgiven_by_user_id' => $row->forgiven_by_user_id,
                    'forgiven_by_name' => $row->forgiven_by_name,

                    'forgiven_at' => $row->forgiven_at
                        ? Carbon::parse($row->forgiven_at)->toDateTimeString()
                        : null,

                    'updated_at' => $row->updated_at?->toDateTimeString(),
                    'created_at' => $row->created_at?->toDateTimeString(),
                ],
            ];
        })
        ->all();

    Log::debug('[LEASE_CUTOFF_STATUS_META_BY_LEASE_ID]', [
        'partner_id' => $partnerId,
        'count' => count($rows),
        'lease_ids' => array_slice(array_keys($rows), 0, 20),
    ]);

    return $rows;
}



/**
 * Label humain pour la colonne Coupure.
 */
protected function cutoffStatusLabel(?string $status): string
{
    $status = strtoupper((string) $status);

    return match ($status) {
        'PENDING' => 'Planifiée',
        'WAITING_STOP' => 'En attente arrêt',
        'COMMAND_SENT' => 'Commande envoyée',
        'CUT_OFF' => 'Coupé',
        'CANCELLED_PAID' => 'Annulé paiement',
        'FAILED' => 'Échec coupure',

        'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardon avant coupure',
        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'Rallumage demandé',
        'REACTIVATED_AFTER_FORGIVENESS' => 'Rallumé après pardon',
        'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'Échec rallumage',

        default => 'Aucune coupure',
    };
}

/**
 * Type UI pour colorer le badge côté Blade/JS.
 */
protected function cutoffStatusUiType(?string $status): string
{
    $status = strtoupper((string) $status);

    return match ($status) {
        'CUT_OFF' => 'danger',
        'FAILED',
        'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'error',

        'COMMAND_SENT',
        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'warning',

        'WAITING_STOP',
        'PENDING' => 'info',

        'CANCELLED_PAID',
        'CANCELLED_FORGIVEN_BEFORE_CUT',
        'REACTIVATED_AFTER_FORGIVENESS' => 'success',

        default => 'muted',
    };
}



    /**
     * Résout le partenaire courant.
     *
     * Si l'utilisateur connecté est un chauffeur/secondaire, on utilise partner_id.
     * Sinon, on utilise son propre id.
     */
    protected function resolvePartnerId(): int
    {
        $user = auth()->user();

        if (! $user) {
            throw new RuntimeException("Aucun utilisateur authentifié.");
        }

        return (int) ($user->partner_id ?: $user->id);
    }

    /**
     * Appel GET générique vers recouvrement.
     */
    protected function get(string $endpoint, array $query = []): array
    {
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');
        $timeout = (int) config('services.partner_lease_api.timeout', 20);

        if ($baseUrl === '') {
            throw new RuntimeException("PARTNER_LEASE_API_BASE_URL est vide ou non chargé.");
        }

        $url = $baseUrl . $endpoint;

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $trackingToken = $tokenManager->getValidAccessToken(60);

        Log::info('[LEASE_API_GET_REQUEST]', [
            'url' => $url,
            'query' => $query,
            'has_token' => true,
            'token_preview' => substr($trackingToken, 0, 16) . '...',
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($trackingToken)
            ->get($url, $query);

        /**
         * Si le token a expiré côté API malgré notre calcul local,
         * on force un refresh et on rejoue l’appel une seule fois.
         */
        if ($response->status() === 401) {
            Log::warning('[LEASE_API_GET_401_REFRESH_RETRY]', [
                'url' => $url,
                'endpoint' => $endpoint,
            ]);

            $trackingToken = $tokenManager->forceRefresh('api_lease_get_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withToken($trackingToken)
                ->get($url, $query);
        }

        Log::info('[LEASE_API_GET_RESPONSE]', [
            'url' => $url,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 1200),
        ]);

        if (! $response->successful()) {
            if ($response->status() === 401) {
                throw new AuthenticationException(
                    "Session Keycloak expirée pour GET {$endpoint}."
                );
            }

            throw new RuntimeException(
                "Échec de l'appel API lease GET {$endpoint} [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException("Réponse API lease invalide pour GET {$endpoint}.");
        }

        return $json;
    }

    /**
     * Appel POST générique vers recouvrement.
     */
    protected function post(string $endpoint, array $payload = []): array
    {
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');
        $timeout = (int) config('services.partner_lease_api.timeout', 20);

        if ($baseUrl === '') {
            throw new RuntimeException("PARTNER_LEASE_API_BASE_URL est vide ou non chargé.");
        }

        $url = $baseUrl . $endpoint;

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $trackingToken = $tokenManager->getValidAccessToken(60);

        Log::info('[LEASE_API_POST_REQUEST]', [
            'url' => $url,
            'payload' => $payload,
            'has_token' => true,
            'token_preview' => substr($trackingToken, 0, 16) . '...',
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($trackingToken)
            ->post($url, $payload);

        if ($response->status() === 401) {
            Log::warning('[LEASE_API_POST_401_REFRESH_RETRY]', [
                'url' => $url,
                'endpoint' => $endpoint,
            ]);

            $trackingToken = $tokenManager->forceRefresh('api_lease_post_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($trackingToken)
                ->post($url, $payload);
        }

        Log::info('[LEASE_API_POST_RESPONSE]', [
            'url' => $url,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 1200),
        ]);

        if (! $response->successful()) {
            if ($response->status() === 401) {
                throw new AuthenticationException(
                    "Session Keycloak expirée pour POST {$endpoint}."
                );
            }

            throw new RuntimeException(
                "Échec POST API lease {$endpoint} [{$response->status()}] : " . $response->body()
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Supporte les formats API :
     * - liste directe : [ {...}, {...} ]
     * - pagination : { results: [...] }
     * - enveloppe : { data: [...] }
     * - enveloppe : { items: [...] }
     */
    protected function unwrapApiRows(array $json): array
    {
        if (array_is_list($json)) {
            return $json;
        }

        foreach (['results', 'data', 'items'] as $key) {
            if (isset($json[$key]) && is_array($json[$key])) {
                return $json[$key];
            }
        }

        Log::warning('[LEASE_API_UNWRAP_EMPTY]', [
            'shape' => $this->describeArrayShape($json),
            'keys' => array_keys($json),
        ]);

        return [];
    }

    /**
     * Mappe les statuts UI vers les statuts attendus par l'API.
     */
    protected function mapUiStatusToApiStatus(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        return match (strtolower($status)) {
            'paid', 'paye', 'payé' => 'PAYE',
            'unpaid', 'non_paye', 'non_payé', 'non-paye', 'non-payé' => 'NON_PAYE',
            'forgiven', 'pardonne', 'pardonné' => 'PARDONNE',
            default => mb_strtoupper($status, 'UTF-8'),
        };
    }

    /**
     * Normalise un contrat API vers la structure attendue par les vues existantes.
     */
    protected function normalizeContract(array $row): array
    {
        $total = (float) ($row['montant_total'] ?? 0);
        $remaining = (float) ($row['montant_restant'] ?? 0);
        $paid = max(0, $total - $remaining);

        $status = match (strtoupper((string) ($row['statut'] ?? ''))) {
            'ACTIF' => 'actif',
            'RETARD' => 'retard',
            'TERMINE' => 'termine',
            'SUSPENDU' => 'suspendu',
            default => 'actif',
        };

        $frequenceApi = (string) ($row['frequence'] ?? '');
        $frequenceNormalized = mb_strtoupper(trim($frequenceApi), 'UTF-8');

        $frequence = match ($frequenceNormalized) {
            'JOURNALIER', 'QUOTIDIEN' => 'JOURNALIER',
            'HEBDOMADAIRE' => 'HEBDOMADAIRE',
            'MENSUEL' => 'MENSUEL',
            default => 'HEBDOMADAIRE',
        };

        $createdAt = $this->parseDateTime($row['created_at'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'source_contrat_id' => (int) ($row['id'] ?? 0),
            'ref' => 'CTR-' . str_pad((string) ($row['id'] ?? 0), 5, '0', STR_PAD_LEFT),

            'chauffeur' => (string) (
                $row['chauffeur_nom_complet']
                ?? $row['nom_complet']
                ?? '—'
            ),
            'chauffeur_id' => (string) ($row['chauffeur'] ?? ''),

            'phone_ch' => (string) (
                $row['chauffeur_phone']
                ?? $row['telephone_chauffeur']
                ?? $row['phone_ch']
                ?? $row['phone']
                ?? $row['telephone']
                ?? ''
            ),

            'vehicule' => (string) ($row['immatriculation'] ?? '—'),
            'vehicule_id' => (string) ($row['immatriculation'] ?? ''),
            'marque' => '—',

            'partenaire' => Auth::user()?->full_name
                ?? Auth::user()?->nom
                ?? 'Partenaire connecté',

            'montant_total' => $total,
            'montant_restant' => $remaining,
            'total_paye' => $paid,
            'versement' => (float) ($row['montant_par_paiement'] ?? 0),
            'apport_initial' => 0,

            'frequence' => $frequence,
            'frequence_label_api' => $frequenceApi,

            'date_debut' => $row['date_debut'] ?? $createdAt?->toDateString(),
            'date_fin_prevue' => $row['date_fin'] ?? null,
            'premiere_echeance' => $row['prochaine_echeance'] ?? null,
            'prochaine_echeance' => $row['prochaine_echeance'] ?? null,
            'heure_limite' => '18:00',

            'statut' => $status,
            'pardon_auto' => false,
            'nb_paiements' => $this->estimatePaymentCount(
                $paid,
                (float) ($row['montant_par_paiement'] ?? 0)
            ),

            'notes' => 'Contrat synchronisé depuis l’API lease.',
            'enregistre_par_nom_complet' => (string) ($row['enregistre_par_nom_complet'] ?? '—'),

            'raw' => $row,
        ];
    }

    /**
     * Normalise une ligne paiement API.
     */
    protected function normalizePayment(array $row): array
    {
        $status = mb_strtoupper((string) ($row['statut'] ?? $row['status'] ?? ''), 'UTF-8');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'lease_id' => (int) ($row['lease'] ?? $row['lease_id'] ?? 0),

            'montant' => (float) ($row['montant'] ?? 0),
            'methode' => (string) ($row['methode'] ?? $row['method'] ?? $row['mode_paiement'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'transaction_id' => (string) ($row['transaction_id'] ?? ''),
            'statut' => $status,

            'date_paiement' => $row['date_paiement'] ?? null,
            'chauffeur_nom_complet' => (string) ($row['chauffeur_nom_complet'] ?? ''),
            'enregistre_par' => (string) ($row['enregistre_par'] ?? ''),

            'raw' => $row,
        ];
    }

    /**
     * Construit un index des paiements par lease_id.
     *
     * Pour chaque lease, on choisit le paiement le plus pertinent :
     * 1. paiement réussi / validé si disponible ;
     * 2. sinon paiement en attente ;
     * 3. sinon dernière tentative échouée ;
     * 4. sinon dernier paiement connu.
     */
    protected function getPaymentMetaByLeaseId(array $payments): array
    {
        $indexed = collect($payments)
            ->filter(fn (array $payment) => ! empty($payment['lease_id']))
            ->groupBy('lease_id')
            ->map(function ($leasePayments) {
                return collect($leasePayments)
                    ->sortByDesc(function (array $payment) {
                        $priority = $this->paymentStatusPriority((string) ($payment['statut'] ?? ''));
                        $timestamp = $payment['date_paiement']
                            ? (int) strtotime((string) $payment['date_paiement'])
                            : 0;
                        $id = (int) ($payment['id'] ?? 0);

                        return ($priority * 1000000000000) + ($timestamp * 100000) + $id;
                    })
                    ->first();
            })
            ->filter()
            ->mapWithKeys(function (array $payment, $leaseId) {
                return [(int) $leaseId => $payment];
            })
            ->all();

        Log::debug('[LEASE_PAYMENT_META_BY_LEASE_ID]', [
            'count' => count($indexed),
            'lease_ids' => array_slice(array_keys($indexed), 0, 20),
        ]);

        return $indexed;
    }

    /**
     * Priorité de sélection d'un paiement pour l'affichage.
     */
    protected function paymentStatusPriority(string $status): int
    {
        $status = mb_strtoupper(trim($status), 'UTF-8');

        return match ($status) {
            'PAYE',
            'PAYÉ',
            'PAID',
            'SUCCESS',
            'SUCCES',
            'SUCCÈS',
            'REUSSI',
            'RÉUSSI',
            'VALIDE',
            'VALIDÉ',
            'CONFIRME',
            'CONFIRMÉ' => 100,

            'EN_ATTENTE',
            'PENDING',
            'PROCESSING' => 50,

            'ECHEC',
            'ÉCHEC',
            'FAILED',
            'FAIL' => 10,

            default => 1,
        };
    }

    /**
     * Normalise un lease API vers la structure attendue par la page paiements.
     */
        protected function normalizeLease(
            array $row,
            ?array $contract = null,
            ?array $cutoffMeta = null,
            ?array $forgivenessMeta = null,
            ?array $paymentMeta = null,
            ?array $cutoffStatusMeta = null
        ): array {
        $statusApi = strtoupper((string) (
            $row['statut']
            ?? $row['status']
            ?? $row['etat']
            ?? ''
        ));

        $status = match ($statusApi) {
            'PAYE', 'PAID' => 'paid',
            'NON_PAYE', 'NON PAYE', 'UNPAID' => 'unpaid',
            'PARDONNE', 'PARDONNÉ', 'FORGIVEN' => 'forgiven',
            default => 'unpaid',
        };

        if ($forgivenessMeta) {
            $status = match ($forgivenessMeta['history_status'] ?? '') {
                'CANCELLED_FORGIVEN_BEFORE_CUT' => 'forgiven_before_cut',
                'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'forgiven_reactivation_pending',
                'REACTIVATED_AFTER_FORGIVENESS' => 'forgiven_after_cut',
                'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'forgiven_reactivation_failed',
                default => $status,
            };
        }

        $contractId = (int) (
            $row['contrat_id']
            ?? $row['contrat']
            ?? data_get($row, 'contrat.id')
            ?? $contract['source_contrat_id']
            ?? 0
        );

        $leaseId = (int) ($row['id'] ?? 0);

        $immat = (string) (
            $row['immatriculation']
            ?? $row['vehicule']
            ?? $row['vehicle']
            ?? data_get($row, 'contrat.immatriculation')
            ?? $contract['vehicule']
            ?? '—'
        );

        $chauffeur = (string) (
            $row['chauffeur_nom_complet']
            ?? $row['nom_complet']
            ?? $row['driver_name']
            ?? data_get($row, 'chauffeur.nom_complet')
            ?? $contract['chauffeur']
            ?? '—'
        );

        $dateEcheance = (string) (
            $row['date_echeance']
            ?? $row['prochaine_echeance']
            ?? $row['echeance']
            ?? data_get($row, 'contrat.prochaine_echeance')
            ?? $contract['prochaine_echeance']
            ?? ''
        );

        $expected = (float) (
            $row['montant_attendu']
            ?? $row['montant_requis']
            ?? $row['montant_par_paiement']
            ?? data_get($row, 'contrat.montant_par_paiement')
            ?? $contract['versement']
            ?? 0
        );

        $paid = (float) (
            $row['montant_paye']
            ?? $row['montant_payé']
            ?? $row['paid_amount']
            ?? 0
        );

        $reste = (float) (
            $row['reste_a_payer']
            ?? $row['montant_restant']
            ?? max(0, $expected - $paid)
        );

        $coupureAuto = $status === 'unpaid'
            ? (bool) ($cutoffMeta['coupure_auto'] ?? false)
            : false;

        $heureCoupure = $status === 'unpaid'
            ? ($cutoffMeta['heure_coupure'] ?? null)
            : null;

        /**
         * Données venant prioritairement de /paiements/.
         */
        $paymentMethod = $paymentMeta['methode']
            ?? $row['methode']
            ?? $row['method']
            ?? $row['mode_paiement']
            ?? $row['payment_method']
            ?? null;

        $paymentRecordedBy = $paymentMeta['enregistre_par']
            ?? $row['enregistre_par']
            ?? $row['enregistre_par_nom_complet']
            ?? null;

        $paymentDate = $paymentMeta['date_paiement']
            ?? $row['date_paiement']
            ?? null;

        $paymentStatus = $paymentMeta['statut']
            ?? $row['paiement_statut']
            ?? null;

        $paymentReference = $paymentMeta['reference']
            ?? $row['reference']
            ?? null;

        $paymentTransactionId = $paymentMeta['transaction_id']
            ?? $row['transaction_id']
            ?? null;

        return [
            'id' => $leaseId,
            'source_lease_id' => $leaseId,
            'source_contrat_id' => $contractId,

            /**
             * Date affichée dans le tableau = date d’échéance du lease.
             */
            'date' => $dateEcheance,
            'date_echeance' => $dateEcheance,

            'vehicule' => $immat,
            'chauffeur' => $chauffeur,

            'phone' => (string) (
                $row['phone_number']
                ?? $row['telephone_chauffeur']
                ?? $row['chauffeur_phone']
                ?? $row['phone']
                ?? $contract['phone_ch']
                ?? ''
            ),

            'agence' => '—',

            'partenaire' => Auth::user()?->full_name
                ?? Auth::user()?->nom
                ?? 'Partenaire connecté',

            /**
             * Montants : source officielle = /leases/.
             */
            'montant_requis' => $expected,
            'montant_paye' => $paid,
            'reste_a_payer' => $reste,

            /**
             * Encaissé / enregistré par :
             * source prioritaire = /paiements/.enregistre_par.
             */
            'paye_par' => $paymentRecordedBy
                ?: ($paid > 0 ? $chauffeur : null),

            /**
             * Méthode :
             * source prioritaire = /paiements/.methode.
             */
            'methode' => $paymentMethod ?: null,

            /**
             * Champs détaillés paiement.
             */
            'paiement_id' => $paymentMeta['id'] ?? null,
            'paiement_statut' => $paymentStatus,
            'paiement_reference' => $paymentReference,
            'paiement_transaction_id' => $paymentTransactionId,
            'date_paiement' => $paymentDate,

          
            /**
             * Pardon.
             */
            'pardonne_par' => $forgivenessMeta['forgiven_by_name'] ?? null,
            'pardonne_par_user_id' => $forgivenessMeta['forgiven_by_user_id'] ?? null,
            'pardonne_le' => $forgivenessMeta['forgiven_at'] ?? null,

            'forgiveness_history_status' => $forgivenessMeta['history_status'] ?? null,
            'forgiveness_reason' => $forgivenessMeta['reason'] ?? null,

            'statut' => $status,

                        /**
             * Coupure automatique :
             * - coupure_auto / heure_coupure = configuration de la règle locale ;
             * - coupure_status_* = résultat réel issu de lease_cutoff_histories.
             */
            'coupure_auto' => $coupureAuto,
            'heure_coupure' => $heureCoupure,

            'coupure_status' => $cutoffStatusMeta['status'] ?? null,
            'coupure_label' => $cutoffStatusMeta['label'] ?? (
                $coupureAuto
                    ? 'Planifiée'
                    : 'Aucune coupure'
            ),
            'coupure_ui_type' => $cutoffStatusMeta['ui_type'] ?? (
                $coupureAuto
                    ? 'info'
                    : 'muted'
            ),
            'coupure_reason' => $cutoffStatusMeta['reason'] ?? null,
            'coupure_history_id' => $cutoffStatusMeta['history_id'] ?? null,
            'coupure_scheduled_for' => $cutoffStatusMeta['scheduled_for'] ?? null,
            'coupure_detected_at' => $cutoffStatusMeta['detected_at'] ?? null,
            'coupure_executed_at' => $cutoffStatusMeta['cutoff_executed_at'] ?? null,

            /**
             * Compatibilité ancienne vue :
             * coupe = true uniquement si historique CUT_OFF.
             */
            'coupe' => ($cutoffStatusMeta['status'] ?? null) === 'CUT_OFF',
                        /**
             * H. enreg. :
             * si paiement trouvé, on utilise date_paiement ;
             * sinon created_at du lease.
             */
            'heure_enreg' => $this->parseDateTime($paymentDate ?? $row['created_at'] ?? null)?->format('H:i:s'),

            'contrat_ref' => $contract['ref']
                ?? ('CTR-' . str_pad((string) $contractId, 5, '0', STR_PAD_LEFT)),

            'prochaine_echeance' => $dateEcheance,

            'raw' => $row,
            'payment_raw' => $paymentMeta['raw'] ?? null,
        ];
    }

    protected function estimatePaymentCount(float $paid, float $amountPerPayment): int
    {
        if ($amountPerPayment <= 0) {
            return 0;
        }

        return (int) floor($paid / $amountPerPayment);
    }

    protected function parseDateTime(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function minutesUntil(string $time): int
    {
        $now = now();

        [$hour, $minute] = array_map('intval', explode(':', $time));

        $target = now()->setTime($hour, $minute, 0);

        if ($target->lessThanOrEqualTo($now)) {
            $target->addDay();
        }

        return $now->diffInMinutes($target);
    }

    /**
     * Petit helper pour les logs.
     */
    protected function describeArrayShape(array $array): string
    {
        if (array_is_list($array)) {
            return 'list[' . count($array) . ']';
        }

        return 'assoc{' . implode(',', array_slice(array_keys($array), 0, 10)) . '}';
    }
}