<?php

namespace App\Services\Leases;

use App\Exceptions\LeaseApiException;
use App\Models\LeaseCutoffHistory;
use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffContractRule;
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
     * Rôle :
     * Récupère les contrats depuis recouvrement et applique les filtres envoyés
     * par la console Tracking.
     *
     * Contexte :
     * La documentation recouvrement indique que /contrats/ accepte notamment :
     * - search ;
     * - statut, statut__in ;
     * - frequence, frequence__in ;
     * - type_contrat_id ;
     * - dates ;
     * - montant_restant_min / montant_restant_max.
     *
     * Cette méthode garde Tracking comme simple consommateur :
     * Recouvrement reste la source de vérité des contrats.
     */
    public function fetchContracts(array $filters = []): array
    {
        $query = collect($filters)
            ->only([
                'search',
                'statut',
                'statut__in',
                'frequence',
                'frequence__in',
                'date_debut_start',
                'date_debut_end',
                'date_fin_start',
                'date_fin_end',
                'prochaine_echeance_start',
                'prochaine_echeance_end',
                'montant_restant_min',
                'montant_restant_max',
                'type_contrat_id',
                'page',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        /**
         * On charge TOUTES les pages de l'API de recouvrement afin que le
         * filtrage et la pagination côté client (jusqu'à 500 lignes par page)
         * portent sur l'intégralité des contrats, et non sur la seule page DRF
         * par défaut (25 lignes). Un contrat parent et ses sous-contrats peuvent
         * en effet se retrouver sur des pages API différentes : ne charger que la
         * première page casserait aussi le regroupement parent/enfants.
         * Le paramètre `page` éventuel est ignoré : c'est la vue qui pagine.
         */
        unset($query['page']);

        /**
         * On demande la page maximale supportée par l'API (500) afin de ramener
         * l'intégralité des contrats en un minimum de requêtes. Cela réduit aussi
         * fortement le risque de pagination instable : lorsque plusieurs contrats
         * partagent la même valeur de tri (ex. `created_at` identique après un
         * import en masse), l'ordre des lignes n'est pas garanti entre deux
         * requêtes de page distinctes, ce qui peut dupliquer une ligne et en
         * sauter une autre. Le dédoublonnage par id ci-dessous fournit un
         * garde-fou supplémentaire.
         */
        $query['page_size'] = 500;

        Log::info('[LEASE_API_FETCH_CONTRACTS_START]', [
            'query' => $query,
        ]);

        $rows        = [];
        $apiCount    = 0;
        $pageNumber  = 1;
        $pagesLoaded = 0;
        $maxPages    = 400; // garde-fou : ~200 000 contrats au maximum

        while ($pageNumber <= $maxPages) {
            $json = $this->get('/contrats/', $query + ['page' => $pageNumber]);

            if ($pageNumber === 1) {
                $apiCount = (int) ($json['count'] ?? 0);
            }

            $pageRows = $this->unwrapApiRows($json);

            if (empty($pageRows)) {
                break;
            }

            foreach ($pageRows as $pageRow) {
                $rows[] = $pageRow;
            }

            $pagesLoaded = $pageNumber;

            // API non paginée (liste brute) ou dernière page atteinte.
            if (empty($json['next'])) {
                break;
            }

            $pageNumber++;
        }

        Log::info('[LEASE_API_FETCH_CONTRACTS_ROWS]', [
            'rows_count' => count($rows),
            'api_count' => $apiCount,
            'pages_loaded' => $pagesLoaded,
        ]);

        $contracts = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => $this->normalizeContract($row))
            // Garde-fou anti-doublons : une pagination instable côté API peut
            // renvoyer deux fois la même ligne. On conserve la première occurrence
            // de chaque id (les contrats sans id exploitable sont conservés tels quels).
            ->unique(fn (array $row) => ! empty($row['id']) ? 'id:' . $row['id'] : spl_object_id((object) $row))
            ->values()
            ->all();

        Log::info('[LEASE_API_FETCH_CONTRACTS_DONE]', [
            'contracts_count' => count($contracts),
            'rows_before_dedup' => count($rows),
            'api_count' => $apiCount,
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
     * Endpoint API recouvrement : GET /type-contrats/
     *
     * Rôle :
     * Récupère les types de contrats réels du recouvrement afin de remplacer
     * les types codés en dur dans la vue.
     *
     * est_principal = true : contrat parent, généralement le véhicule.
     * est_principal = false : sous-contrat, par exemple téléphone, parasol,
     * crédit ou kit de sécurité.
     */
/**
 * Récupère dynamiquement les types de contrats depuis recouvrement.
 *
 * Contexte :
 * La vue ne doit pas contenir de types statiques.
 * Les types doivent venir de l'API recouvrement.
 *
 * Important :
 * L'endpoint peut être absent, vide ou filtré selon le compte.
 * On ne doit donc pas faire planter toute la page.
 *
 * Comportement :
 * - si l'API répond 200 : on retourne les types ;
 * - si l'API répond 404 : on retourne une liste vide ;
 * - si autre erreur : on laisse remonter l'erreur.
 */
public function fetchContractTypes(): array
{
    logger()->info('[LEASE_API_FETCH_CONTRACT_TYPES_START]');

    try {
        $response = $this->get('/type-contrats/');

        $rows = $this->extractRows($response);

        logger()->info('[LEASE_API_FETCH_CONTRACT_TYPES_DONE]', [
            'types_count' => count($rows),
            'sample_ids' => collect($rows)->pluck('id')->take(5)->values()->all(),
        ]);

        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                return [
                    'id' => $row['id'] ?? $row['type_contrat'] ?? $row['type_contrat_id'] ?? null,
                    'label' => $row['nom'] ?? $row['label'] ?? $row['libelle'] ?? $row['name'] ?? null,
                    'code' => $row['code'] ?? $row['slug'] ?? null,
                    'description' => $row['description'] ?? null,
                    'is_main' => $row['is_main'] ?? $row['est_principal'] ?? $row['principal'] ?? false,
                    'raw' => $row,
                ];
            })
            ->filter(fn ($row) => ! empty($row['id']) && ! empty($row['label']))
            ->values()
            ->all();

    } catch (\RuntimeException $e) {
        /**
         * Cas connu :
         * L'API recouvrement n'expose pas encore /type-contrats/.
         * On ne bloque pas toute la page.
         */
        if (str_contains($e->getMessage(), 'GET /type-contrats/')
            && str_contains($e->getMessage(), '[404]')) {
            logger()->warning('[LEASE_API_CONTRACT_TYPES_ENDPOINT_MISSING]', [
                'endpoint' => '/type-contrats/',
                'message' => 'Endpoint absent côté recouvrement. La création de contrat sera désactivée tant que les types ne sont pas disponibles.',
            ]);

            return [];
        }

        throw $e;
    }
}

    /**
     * Crée un type de contrat ou de sous-contrat côté recouvrement.
     *
     * Source de vérité : recouvrement.
     * Tracking ne crée pas de type local ; il récupère ensuite les types avec
     * GET /type-contrats/ et stocke uniquement les règles de coupure associées.
     */
    public function createContractType(array $payload): array
    {
        $apiPayload = [
            'libelle' => trim((string) ($payload['libelle'] ?? '')),
            'code' => mb_strtoupper(trim((string) ($payload['code'] ?? '')), 'UTF-8'),
            'est_principal' => filter_var($payload['est_principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if ($apiPayload['libelle'] === '') {
            throw new RuntimeException('Le libellé du type de contrat est obligatoire.');
        }

        if ($apiPayload['code'] === '') {
            $apiPayload['code'] = mb_strtoupper(
                preg_replace('/[^A-Z0-9]+/i', '_', $apiPayload['libelle']),
                'UTF-8'
            );
        }

        Log::info('[LEASE_API_CREATE_CONTRACT_TYPE_START]', [
            'payload' => $apiPayload,
        ]);

        $response = $this->post('/type-contrats/', $apiPayload);

        Log::info('[LEASE_API_CREATE_CONTRACT_TYPE_DONE]', [
            'type_id' => $response['id'] ?? null,
            'libelle' => $response['libelle'] ?? $apiPayload['libelle'],
            'code' => $response['code'] ?? $apiPayload['code'],
        ]);

        return $response;
    }




/**
 * Extrait une liste de lignes depuis une réponse API.
 *
 * Compatible avec plusieurs formats :
 * - [...]
 * - {"results": [...]}
 * - {"data": [...]}
 * - {"items": [...]}
 */
private function extractRows(mixed $response): array
{
    if (is_array($response)) {
        if (array_is_list($response)) {
            return $response;
        }

        foreach (['results', 'data', 'items', 'rows'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }
    }

    return [];
}

    /**
     * Récupère les véhicules locaux du partenaire connecté.
     *
     * Sert au select "immatriculation" dans le formulaire contrat.
     */
 public function fetchPartnerVehiclesForContracts(): array
{
    $user = auth()->user();
    $partnerId = (int) ($user->partner_id ?: $user->id);

    return \App\Models\Voiture::query()
        ->select([
            'voitures.id',
            'voitures.immatriculation',
            'voitures.marque',
            'voitures.model',
            'voitures.mac_id_gps',

            /**
             * Adapte ici si ton champ VIN a un autre nom :
             * - vin
             * - numero_chassis
             * - chassis
             */
            'voitures.vin',
        ])
        ->join('association_user_voitures', 'association_user_voitures.voiture_id', '=', 'voitures.id')
        ->where('association_user_voitures.user_id', $partnerId)
        ->orderBy('voitures.immatriculation')
        ->get()
        ->map(function ($vehicle) {
            $labelParts = array_filter([
                $vehicle->immatriculation,
                trim(($vehicle->marque ?? '') . ' ' . ($vehicle->model ?? '')),
            ]);

            return [
                'id' => (int) $vehicle->id,
                'vehicle_id' => (int) $vehicle->id,
                'immatriculation' => (string) ($vehicle->immatriculation ?? ''),
                'vin' => (string) ($vehicle->vin ?? ''),
                'marque' => (string) ($vehicle->marque ?? ''),
                'model' => (string) ($vehicle->model ?? ''),
                'mac_id_gps' => (string) ($vehicle->mac_id_gps ?? ''),
                'label' => implode(' — ', $labelParts),
            ];
        })
        ->values()
        ->all();
}

    /**
     * Endpoint API recouvrement : POST /contrats/
     *
     * Rôle :
     * Crée un contrat principal et ses sous-contrats dans recouvrement.
     *
     * Contexte :
     * La documentation actuelle accepte :
     * - type_contrat ;
     * - immatriculation ;
     * - vin ;
     * - specificites ;
     * - sous_contrats[] optionnel.
     *
     * Important :
     * Les règles de coupure ne sont pas envoyées ici. Elles restent dans
     * Tracking via lease_cutoff_rules et lease_cutoff_rule_contract_types.
     */
    public function createContract(array $payload): array
    {
        $apiPayload = $this->buildContractPayload($payload, includeStatus: false, includeRemaining: false);

        Log::info('[LEASE_API_CREATE_CONTRACT_START]', [
            'payload' => $apiPayload,
            'sub_contracts_count' => count($apiPayload['sous_contrats'] ?? []),
        ]);

        $response = $this->post('/contrats/', $apiPayload);

        Log::info('[LEASE_API_CREATE_CONTRACT_DONE]', [
            'response_shape' => $this->describeArrayShape($response),
            'response_id' => data_get($response, 'id') ?? data_get($response, 'data.id'),
        ]);

        return $response;
    }

    /**
     * Endpoint API recouvrement : POST /contrats/{parent}/sous-contrats/
     *
     * Rôle :
     * Crée un sous-contrat lorsque le contrat principal existe déjà côté
     * recouvrement. Tracking garde ensuite le lien local dans
     * lease_contract_links via LeaseContractLinkService.
     */
    public function createSubContract(int $parentContractId, array $payload): array
    {
        if ($parentContractId <= 0) {
            throw new RuntimeException('ID contrat parent invalide pour la création du sous-contrat.');
        }

        $apiPayload = [
            'type_contrat' => (int) $payload['type_contrat'],
            'montant_total' => (string) $payload['montant_total'],
            'montant_paye' => (string) ($payload['montant_paye'] ?? 0),
            'montant_par_paiement' => (string) $payload['montant_par_paiement'],
            'frequence' => mb_strtoupper((string) $payload['frequence'], 'UTF-8'),
            'date_debut' => (string) $payload['date_debut'],
            'date_fin' => (string) $payload['date_fin'],
            'prochaine_echeance' => (string) $payload['prochaine_echeance'],
            'specificites' => $payload['specificites'] ?? new \stdClass(),
        ];

        Log::info('[LEASE_API_CREATE_SUB_CONTRACT_START]', [
            'parent_contract_id' => $parentContractId,
            'payload' => $apiPayload,
        ]);

        $response = $this->post("/contrats/{$parentContractId}/sous-contrats/", $apiPayload);

        Log::info('[LEASE_API_CREATE_SUB_CONTRACT_DONE]', [
            'parent_contract_id' => $parentContractId,
            'response_shape' => $this->describeArrayShape($response),
            'response_id' => data_get($response, 'id') ?? data_get($response, 'data.id'),
        ]);

        return $response;
    }

    /**
     * Endpoint API recouvrement : PUT /contrats/{id}/
     *
     * Rôle :
     * Met à jour complètement un contrat ou un sous-contrat existant.
     *
     * Pourquoi PUT et non PATCH ?
     * La documentation fournie décrit la modification comme une mise à jour
     * complète via PUT /contrats/{id}/.
     */
    public function updateContract(int $contractId, array $payload): array
    {
        if ($contractId <= 0) {
            throw new RuntimeException('ID contrat invalide pour la modification.');
        }

        $apiPayload = $this->buildContractPayload($payload, includeStatus: true, includeRemaining: true);

        Log::info('[LEASE_API_UPDATE_CONTRACT_START]', [
            'contract_id' => $contractId,
            'payload' => $apiPayload,
        ]);

        $response = $this->put("/contrats/{$contractId}/", $apiPayload);

        Log::info('[LEASE_API_UPDATE_CONTRACT_DONE]', [
            'contract_id' => $contractId,
            'response_shape' => $this->describeArrayShape($response),
            'response_id' => data_get($response, 'id') ?? data_get($response, 'data.id'),
        ]);

        return $response;
    }

    /**
     * Endpoint API recouvrement : DELETE /contrats/{id}/
     *
     * Rôle :
     * Supprime un contrat côté recouvrement. À utiliser avec prudence.
     * Tracking devra aussi supprimer/désactiver le lien local correspondant.
     */
    public function deleteContract(int $contractId): void
    {
        if ($contractId <= 0) {
            throw new RuntimeException('ID contrat invalide pour la suppression.');
        }

        Log::warning('[LEASE_API_DELETE_CONTRACT_START]', [
            'contract_id' => $contractId,
        ]);

        $this->delete("/contrats/{$contractId}/");

        Log::warning('[LEASE_API_DELETE_CONTRACT_DONE]', [
            'contract_id' => $contractId,
        ]);
    }

    /**
     * Endpoint API recouvrement : GET /leases/
     *
     * Source principale de la page paiements.
     *
     * Cette méthode applique les filtres documentés par l'API, puis enrichit
     * chaque échéance avec :
     * - son contrat ou sous-contrat recouvrement ;
     * - son type de contrat ;
     * - la règle de coupure Tracking applicable au véhicule ET au type.
     */
    public function fetchLeases(?string $status = null, array $contracts = [], array $filters = []): array
    {
        $query = collect($filters)
            ->only([
                'search',
                'statut',
                'statut__in',
                'date_echeance',
                'date_echeance_start',
                'date_echeance_end',
                'created_at',
                'start_date',
                'end_date',
                'page',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if ($status) {
            $apiStatus = $this->mapUiStatusToApiStatus($status);

            if ($apiStatus) {
                $query['statut'] = $apiStatus;
            }
        }

        /**
         * On charge TOUTES les pages de l'API de recouvrement afin que le
         * filtrage et la pagination côté client portent sur l'intégralité des
         * échéances, et non uniquement sur une page de 25 lignes. Le paramètre
         * `page` éventuel est ignoré : c'est la vue qui pagine ensuite.
         */
        unset($query['page']);

        Log::info('[LEASE_API_FETCH_LEASES_START]', [
            'status' => $status,
            'query' => $query,
            'contracts_count_for_enrichment' => count($contracts),
        ]);

        $rows        = [];
        $apiCount    = 0;
        $pageNumber  = 1;
        $pagesLoaded = 0;
        $maxPages    = 400; // garde-fou : ~10 000 échéances au maximum

        while ($pageNumber <= $maxPages) {
            $json = $this->get('/leases/', $query + ['page' => $pageNumber]);

            if ($pageNumber === 1) {
                $apiCount = (int) ($json['count'] ?? 0);
            }

            $pageRows = $this->unwrapApiRows($json);

            if (empty($pageRows)) {
                break;
            }

            foreach ($pageRows as $pageRow) {
                $rows[] = $pageRow;
            }

            $pagesLoaded = $pageNumber;

            if (empty($json['next'])) {
                break;
            }

            $pageNumber++;
        }

        $pageSize    = 25;
        $currentPage = 1;
        $totalPages  = 1;

        Log::info('[LEASE_API_FETCH_LEASES_ROWS]', [
            'rows_count' => count($rows),
            'api_count' => $apiCount,
            'pages_loaded' => $pagesLoaded,
            'first_row_keys' => isset($rows[0]) && is_array($rows[0])
                ? array_keys($rows[0])
                : [],
        ]);

        try {
            $payments = $this->fetchPayments([
                // On ne filtre pas par date_paiement ici : un lease d'une échéance donnée
                // peut être payé à une date différente. On limite seulement aux paiements non annulés.
                'est_annule' => 'false',
            ]);
        } catch (\Throwable $e) {
            report($e);

            Log::error('[LEASE_API_FETCH_PAYMENTS_FOR_LEASES_FAILED]', [
                'error' => $e->getMessage(),
            ]);

            $payments = [];
        }

        $paymentMetaByLeaseId = $this->getPaymentMetaByLeaseId($payments);

        $contractsById = collect($contracts)
            ->filter(fn ($row) => is_array($row))
            ->keyBy(fn (array $row) => (int) ($row['source_contrat_id'] ?? $row['id'] ?? 0));

        $cutoffMetaByContractId = $this->getPartnerCutoffMetaByContractId();
        $forgivenessMetaByLeaseId = $this->getForgivenessMetaByLeaseId();
        $cutoffStatusMetaByLeaseId = $this->getCutoffStatusMetaByLeaseId();

        $leases = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) use (
                $contractsById,
                $cutoffMetaByContractId,
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

                /**
                 * Nouvelle règle métier :
                 * on enrichit le lease avec la règle spécifique du contrat
                 * ou sous-contrat exact, pas avec une règle générale véhicule.
                 */
                $cutoffMeta = $contractId > 0
                    ? ($cutoffMetaByContractId[$contractId] ?? null)
                    : null;

                $leaseId = (int) ($row['id'] ?? 0);

                return $this->normalizeLease(
                    row: $row,
                    contract: $contract,
                    cutoffMeta: $cutoffMeta,
                    forgivenessMeta: $leaseId > 0 ? ($forgivenessMetaByLeaseId[$leaseId] ?? null) : null,
                    paymentMeta: $leaseId > 0 ? ($paymentMetaByLeaseId[$leaseId] ?? null) : null,
                    cutoffStatusMeta: $leaseId > 0 ? ($cutoffStatusMetaByLeaseId[$leaseId] ?? null) : null
                );
            })
            ->values()
            ->all();

        Log::info('[LEASE_API_FETCH_LEASES_DONE]', [
            'leases_count' => count($leases),
            'payments_count' => count($payments),
            'payments_linked_count' => count($paymentMetaByLeaseId),
            'api_count' => $apiCount,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'sample_ids' => collect($leases)->pluck('id')->take(5)->values()->all(),
            'sample_statuses' => collect($leases)->pluck('statut')->take(10)->values()->all(),
            'sample_contract_types' => collect($leases)->pluck('type_contrat_label')->take(10)->values()->all(),
        ]);

        return [
            'data'         => $leases,
            'count'        => $apiCount,
            'current_page' => $currentPage,
            'page_size'    => $pageSize,
            'total_pages'  => $totalPages,
            // Toutes les pages sont désormais chargées côté serveur : la
            // navigation entre pages se fait entièrement côté client.
            'has_next'     => false,
            'has_previous' => false,
        ];
    }

    /**
     * Index léger : lease_id => libellé du type de contrat, toutes échéances
     * confondues, SANS l'enrichissement coûteux de fetchLeases (paiements,
     * coupures, pardons…).
     *
     * Sert au dashboard « Paiements du jour » : un paiement ne porte que
     * `lease_id`, et le lease payé n'appartient pas forcément à la période
     * affichée. Sans cet index, le type retomberait sur « Contrat principal ».
     *
     * @return array<int,string> lease_id => type_contrat_libelle
     */
    public function fetchLeaseTypeLabels(): array
    {
        $map         = [];
        $pageNumber  = 1;
        $maxPages    = 400; // garde-fou

        while ($pageNumber <= $maxPages) {
            $json = $this->get('/leases/', ['page' => $pageNumber, 'page_size' => 500]);
            $rows = $this->unwrapApiRows($json);

            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $leaseId = (int) ($row['id'] ?? 0);
                if ($leaseId <= 0) {
                    continue;
                }

                $map[$leaseId] = (string) (
                    $row['type_contrat_libelle']
                    ?? $row['type_contrat_label']
                    ?? ''
                );
            }

            if (empty($json['next'])) {
                break;
            }

            $pageNumber++;
        }

        Log::info('[LEASE_API_FETCH_LEASE_TYPE_LABELS_DONE]', ['count' => count($map)]);

        return $map;
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
    public function fetchPayments(array $filters = []): array
    {
        $query = collect($filters)
        ->only([
            'search',
            'statut',
            'statut__in',
            'methode',
            'methode__in',
            'est_annule',
            'date_paiement',
            'date_paiement_start',
            'date_paiement_end',
            'created_at_start',
            'created_at_end',
            'montant_min',
            'montant_max',
            'contrat_id',
            'lease_id',
            'session_id',
            'enregistre_par_id',
            'chauffeur_id',
            'page',
        ])
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->all();

    /**
     * Comme pour les contrats, on ignore la pagination demandée par la vue.
     * La vue paginera côté client si nécessaire.
     *
     * Objectif :
     * éviter que /paiements/ ne retourne seulement les 25 lignes par défaut
     * de l’API recouvrement.
     */
    unset($query['page']);

    /**
     * L’API recouvrement supporte 500 éléments par page.
     * On force donc page_size=500 pour réduire le nombre d’appels API.
     */
    $query['page_size'] = 500;

    Log::info('[LEASE_API_FETCH_PAYMENTS_START]', [
        'query' => $query,
    ]);

    $rows        = [];
    $apiCount    = 0;
    $pageNumber  = 1;
    $pagesLoaded = 0;
    $maxPages    = 400; // garde-fou : ~200 000 paiements maximum

    while ($pageNumber <= $maxPages) {
        $json = $this->get('/paiements/', $query + ['page' => $pageNumber]);

        if ($pageNumber === 1) {
            $apiCount = (int) ($json['count'] ?? 0);
        }

        $pageRows = $this->unwrapApiRows($json);

        if (empty($pageRows)) {
            break;
        }

        foreach ($pageRows as $pageRow) {
            $rows[] = $pageRow;
        }

        $pagesLoaded = $pageNumber;

        /**
         * Si l’API n’est pas paginée, ou si on est à la dernière page,
         * on arrête la boucle.
         */
        if (empty($json['next'])) {
            break;
        }

        $pageNumber++;
    }

    Log::info('[LEASE_API_FETCH_PAYMENTS_ROWS]', [
        'raw_rows_count' => count($rows),
        'api_count' => $apiCount,
        'pages_loaded' => $pagesLoaded,
        'first_row_keys' => isset($rows[0]) && is_array($rows[0])
            ? array_keys($rows[0])
            : [],
    ]);

    $payments = collect($rows)
        ->filter(fn ($row) => is_array($row))
        ->map(fn (array $row) => $this->normalizePayment($row))
        ->filter(fn (array $row) => ! empty($row['lease_id']))

        /**
         * Garde-fou anti-doublons :
         * si l’API renvoie deux fois le même paiement à cause d’une pagination instable,
         * on conserve une seule occurrence par id.
         */
        ->unique(fn (array $row) => ! empty($row['id']) ? 'id:' . $row['id'] : spl_object_id((object) $row))
        ->values()
        ->all();

    Log::info('[LEASE_API_FETCH_PAYMENTS_DONE]', [
        'query' => $query,
        'payments_count' => count($payments),
        'rows_before_dedup' => count($rows),
        'api_count' => $apiCount,
        'pages_loaded' => $pagesLoaded,
        'sample_ids' => collect($payments)->pluck('id')->take(5)->values()->all(),
        'sample_lease_ids' => collect($payments)->pluck('lease_id')->take(5)->values()->all(),
    ]);

    return $payments;
    }

    /**
     * Endpoint API recouvrement : POST /paiements/
     *
     * Documentation :
     * {
     *   "lease_id": 97,
     *   "montant": "100"
     * }
     */
    public function registerCashPayment(array $payload): array
    {
        $apiPayload = [
            'lease_id' => (int) $payload['lease_id'],
            'montant' => (string) $payload['montant'],
        ];

        Log::info('[LEASE_API_REGISTER_CASH_PAYMENT_START]', [
            'payload' => $apiPayload,
            'recorded_by' => $payload['recorded_by'] ?? null,
            'recorded_by_name' => $payload['recorded_by_name'] ?? null,
        ]);

        $response = $this->post('/paiements/', $apiPayload);

        Log::info('[LEASE_API_REGISTER_CASH_PAYMENT_DONE]', [
            'lease_id' => $apiPayload['lease_id'],
            'montant' => $apiPayload['montant'],
            'recorded_by' => $payload['recorded_by'] ?? null,
            'recorded_by_name' => $payload['recorded_by_name'] ?? null,
            'response_shape' => $this->describeArrayShape($response),
        ]);

        return $response;
    }

    /**
     * Endpoint API recouvrement : POST /initier-paiement/
     * Paiement mobile multi-leases.
     */
    public function initiateMobilePayment(array $lines, string $phoneNumber): array
    {
        $apiPayload = [
            'lignes' => collect($lines)
                ->map(fn (array $line) => [
                    'lease_id' => (int) $line['lease_id'],
                    'montant' => (string) $line['montant'],
                ])
                ->values()
                ->all(),
            'phone_number' => trim($phoneNumber),
        ];

        Log::info('[LEASE_API_INITIATE_MOBILE_PAYMENT_START]', [
            'lines_count' => count($apiPayload['lignes']),
            'lease_ids' => collect($apiPayload['lignes'])->pluck('lease_id')->values()->all(),
            'phone_preview' => substr($apiPayload['phone_number'], 0, 3) . '***',
        ]);

        $response = $this->post('/initier-paiement/', $apiPayload);

        Log::info('[LEASE_API_INITIATE_MOBILE_PAYMENT_DONE]', [
            'response_shape' => $this->describeArrayShape($response),
        ]);

        return $response;
    }

    /**
     * Annule immédiatement les queues actives après paiement confirmé côté recouvrement.
     *
     * La coupure ne doit pas attendre le prochain cron si l'utilisateur vient
     * d'enregistrer un paiement cash depuis Tracking. On conserve une trace dans
     * l'historique et on retire les lignes actives de la queue locale.
     */
    public function cancelActiveCutoffQueuesAfterPayment(int $leaseId, ?int $actorId = null, ?string $actorName = null): int
    {
        if ($leaseId <= 0) {
            return 0;
        }

        $partnerId = $this->resolvePartnerId();
        $now = now(config('app.timezone', 'Africa/Douala'));
        $activeStatuses = ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'];
        $cancelled = 0;

        DB::transaction(function () use ($leaseId, $partnerId, $now, $activeStatuses, $actorId, $actorName, &$cancelled) {
            $queues = DB::table('lease_cutoff_queue')
                ->where('partner_id', $partnerId)
                ->where('lease_id', $leaseId)
                ->whereIn('status', $activeStatuses)
                ->lockForUpdate()
                ->get();

            foreach ($queues as $queue) {
                if (! empty($queue->history_id)) {
                    LeaseCutoffHistory::query()
                        ->where('id', $queue->history_id)
                        ->where('partner_id', $partnerId)
                        ->update([
                            'status' => 'CANCELLED_PAID',
                            'reason' => 'Paiement enregistré : coupure automatique annulée immédiatement.',
                            'forgiven_by_user_id' => null,
                            'forgiven_by_name' => null,
                            'forgiven_at' => null,
                            'notes' => trim((string) (($queue->status ?? '') . ' annulé après paiement cash par ' . ($actorName ?: ('Utilisateur #' . $actorId)))) ?: null,
                            'updated_at' => $now,
                        ]);
                }

                DB::table('lease_cutoff_queue')
                    ->where('id', $queue->id)
                    ->delete();

                $cancelled++;
            }
        });

        Log::info('[LEASE_CASH_PAYMENT_CANCEL_ACTIVE_CUTOFF_QUEUES_DONE]', [
            'partner_id' => $partnerId,
            'lease_id' => $leaseId,
            'cancelled_count' => $cancelled,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
        ]);

        return $cancelled;
    }

    /**
     * Construit les indicateurs du bloc coupure automatique sur la page paiements.
     */
    public function buildPaymentCutoffHub(array $leaseData): array
    {
        $partnerId = $this->resolvePartnerId();
        $now = now('Africa/Douala');

        Log::info('[LEASE_CUTOFF_HUB_BUILD_START]', [
            'partner_id' => $partnerId,
            'lease_rows_count' => count($leaseData),
        ]);

        /**
         * Source de vérité du hub : les règles spécifiques existantes.
         * On ne lit plus lease_cutoff_rules / lease_cutoff_rule_contract_types,
         * car ces tables portent l'ancienne logique globale véhicule + type.
         */
        $rules = LeaseCutoffContractRule::query()
            ->where('partner_id', $partnerId)
            ->whereNotNull('contract_link_id')
            ->get();

        $enabledRules = $rules
            ->filter(fn (LeaseCutoffContractRule $rule) => (bool) $rule->is_enabled)
            ->values();

        $rulesWithTime = $enabledRules
            ->filter(fn (LeaseCutoffContractRule $rule) => ! empty($rule->cutoff_time))
            ->values();

        $globalEnabled = $enabledRules->isNotEmpty();

        $mostCommonTime = $rulesWithTime
            ->pluck('cutoff_time')
            ->filter()
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->countBy()
            ->sortDesc()
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

        if (empty($upcomingTimes)) {
            $upcomingTimes = $rulesWithTime
                ->pluck('cutoff_time')
                ->filter()
                ->map(fn ($time) => substr((string) $time, 0, 5))
                ->unique()
                ->sortBy(fn ($time) => $this->minutesUntil($time))
                ->values()
                ->all();
        }

        /**
         * Une queue active a priorité sur le chrono théorique : si la coupure
         * est déjà due mais reportée pour GPS offline / véhicule en mouvement,
         * l'utilisateur doit voir l'attente sécurité, pas juste 00:00:00.
         */
        $waitingQueues = DB::table('lease_cutoff_queue as q')
            ->leftJoin('voitures as v', 'v.id', '=', 'q.vehicle_id')
            ->leftJoin('lease_cutoff_histories as h', 'h.id', '=', 'q.history_id')
            ->where('q.partner_id', $partnerId)
            ->whereIn('q.status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])
            ->orderBy('q.next_check_at')
            ->limit(5)
            ->get([
                'q.id',
                'q.status',
                'q.vehicle_id',
                'v.immatriculation',
                'q.lease_id',
                'q.contract_id',
                'q.contract_link_id',
                'q.next_check_at',
                'h.reason',
                'h.ignition_state',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'status' => (string) $row->status,
                'status_label' => $this->queueStatusLabel((string) $row->status),
                'vehicle_id' => (int) $row->vehicle_id,
                'immatriculation' => (string) ($row->immatriculation ?? '—'),
                'lease_id' => $row->lease_id ? (int) $row->lease_id : null,
                'contract_id' => $row->contract_id ? (int) $row->contract_id : null,
                'contract_link_id' => $row->contract_link_id ? (int) $row->contract_link_id : null,
                'next_check_at' => $row->next_check_at ? Carbon::parse($row->next_check_at)->format('H:i') : null,
                'reason' => $this->shortUserCutoffReason((string) ($row->reason ?? ''), (string) ($row->ignition_state ?? '')),
                'ignition_state' => (string) ($row->ignition_state ?? ''),
            ])
            ->values()
            ->all();

        $processedToday = LeaseCutoffHistory::query()
            ->where('partner_id', $partnerId)
            ->whereIn('status', ['CUT_OFF', 'COMMAND_SENT'])
            ->whereDate('updated_at', $now->toDateString())
            ->count();

        $hub = [
            'global_enabled' => $globalEnabled,
            'global_time' => $mostCommonTime,
            'next_cutoff_time' => $upcomingTimes[0] ?? null,
            'upcoming_cutoff_times' => $upcomingTimes,

            'rules_total' => $rules->count(),
            'rules_enabled' => $enabledRules->count(),
            'rules_disabled' => max(0, $rules->count() - $enabledRules->count()),

            /** Compatibilité avec l'ancien JavaScript. */
            'active_rules_count' => $enabledRules->count(),
            'active_type_rules_count' => $enabledRules->count(),

            'eligible_unpaid_count' => $eligibleUnpaid->count(),
            'waiting_queues_count' => count($waitingQueues),
            'waiting_queues' => $waitingQueues,
            'processed_today' => $processedToday,
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

        if ($enabled && empty($cutoffTime)) {
            throw new RuntimeException("L'heure de coupure est obligatoire pour activer les règles spécifiques.");
        }

        $rulesQuery = LeaseCutoffContractRule::query()
            ->where('partner_id', $partnerId)
            ->whereNotNull('contract_link_id');

        $rulesCount = (clone $rulesQuery)->count();

        if ($rulesCount === 0) {
            throw new RuntimeException(
                "Aucune règle spécifique n'existe encore pour les contrats/sous-contrats de ce partenaire. " .
                "Allez d'abord dans l'écran de paramétrage des règles par contrat."
            );
        }

        $updates = [
            'is_enabled' => $enabled,
            'updated_by' => $userId,
            'updated_at' => now(),
        ];

        /**
         * Quand on désactive en masse, on conserve l'heure existante pour ne
         * pas perdre le paramétrage métier. Quand on active, on applique
         * l'heure choisie uniquement aux règles existantes.
         */
        if ($enabled) {
            $updates['cutoff_time'] = $cutoffTime;
            $updates['grace_days'] = 0;
        }

        (clone $rulesQuery)->update($updates);

        Log::info('[LEASE_CUTOFF_GLOBAL_APPLY_RULES_UPDATED]', [
            'partner_id' => $partnerId,
            'rules_count' => $rulesCount,
            'enabled' => $enabled,
        ]);

        $contracts = $this->fetchContracts();
        // fetchLeases() renvoie une structure paginée : on ne garde que les lignes.
        $leaseData = $this->fetchLeases(null, $contracts)['data'] ?? [];

        $result = [
            'hub' => $this->buildPaymentCutoffHub($leaseData),
        ];

        Log::info('[LEASE_CUTOFF_GLOBAL_APPLY_DONE]', $result['hub']);

        return $result;
    }

    /**
     * Active ou désactive uniquement les règles spécifiques déjà existantes.
     *
     * Important métier : cette méthode ne crée aucune règle. Elle agit seulement
     * sur les lignes déjà présentes dans lease_cutoff_contract_rules pour le
     * partenaire connecté. Les contrats, leases et paiements restent maîtrisés
     * par Recouvrement.
     */
    public function bulkToggleExistingContractCutoffRules(bool $enabled, ?string $cutoffTime): array
    {
        $partnerId = $this->resolvePartnerId();
        $userId = Auth::id();
        $now = now();

        if ($enabled && empty($cutoffTime)) {
            throw new RuntimeException("L'heure de coupure est obligatoire pour activer les règles spécifiques.");
        }

        $cutoffTime = $enabled && $cutoffTime
            ? substr((string) $cutoffTime, 0, 5)
            : null;

        $rulesQuery = LeaseCutoffContractRule::query()
            ->where('partner_id', $partnerId)
            ->whereHas('contractLink', function ($query) use ($partnerId) {
                $query->where('partner_id', $partnerId)
                    ->where(function ($q) {
                        $q->whereNull('status')
                            ->orWhere('status', '!=', 'DELETED');
                    });
            });

        $rulesCount = (clone $rulesQuery)->count();

        $updates = [
            'is_enabled' => $enabled,
            'updated_by' => $userId,
            'updated_at' => $now,
        ];

        if ($enabled) {
            $updates['cutoff_time'] = $cutoffTime;
        }

        DB::transaction(function () use ($rulesQuery, $updates) {
            (clone $rulesQuery)->update($updates);
        });

        Log::info('[LEASE_CONTRACT_RULES_BULK_TOGGLE_DONE_SERVICE]', [
            'partner_id' => $partnerId,
            'rules_count' => $rulesCount,
            'enabled' => $enabled,
            'cutoff_time' => $cutoffTime,
        ]);

        $contracts = $this->fetchContracts();
        // fetchLeases() renvoie une structure paginée : on ne garde que les lignes.
        $leaseData = $this->fetchLeases(null, $contracts)['data'] ?? [];

        return [
            'hub' => $this->buildPaymentCutoffHub($leaseData),
            'rules_count' => $rulesCount,
        ];
    }

    /**
     * Récupère les métadonnées de coupure locale par immatriculation.
     *
     * Nouvelle règle métier : la coupure dépend de la règle véhicule ET de la
     * règle du type de contrat. Sans règle active pour le type impayé,
     * Tracking ne coupe pas.
     */
    protected function getPartnerCutoffMetaByContractId(): array
    {
        $partnerId = $this->resolvePartnerId();

        /**
         * Index par contrat recouvrement exact. C'est la seule clé fiable pour
         * savoir si le lease impayé peut déclencher une coupure.
         */
        $rows = LeaseContractLink::query()
            ->from('lease_contract_links as l')
            ->leftJoin('lease_cutoff_contract_rules as r', 'r.contract_link_id', '=', 'l.id')
            ->where('l.partner_id', $partnerId)
            ->where(function ($query) {
                $query->whereNull('l.status')
                    ->orWhere('l.status', '!=', 'DELETED');
            })
            ->get([
                'l.id as contract_link_id',
                'l.partner_id',
                'l.vehicle_id',
                'l.driver_id',
                'l.source_contract_id',
                'l.source_parent_contract_id',
                'l.contract_kind',
                'l.type_contrat_id',
                'l.type_contrat_label',
                'l.immatriculation',
                'r.id as contract_rule_id',
                'r.is_enabled as rule_is_enabled',
                'r.cutoff_time',
                'r.grace_days',
                'r.only_when_stopped',
                'r.notify_before_cutoff',
                'r.timezone',
            ]);

        $meta = $rows
            ->filter(fn ($row) => ! empty($row->source_contract_id))
            ->mapWithKeys(function ($row) {
                $cutoffTime = $row->cutoff_time ? substr((string) $row->cutoff_time, 0, 5) : null;
                $ruleConfigured = ! empty($row->contract_rule_id);
                $ruleEnabled = $ruleConfigured && (bool) $row->rule_is_enabled;

                return [
                    (int) $row->source_contract_id => [
                        'partner_id' => (int) $row->partner_id,
                        'vehicle_id' => $row->vehicle_id ? (int) $row->vehicle_id : null,
                        'driver_id' => $row->driver_id ? (int) $row->driver_id : null,
                        'contract_link_id' => (int) $row->contract_link_id,
                        'source_contract_id' => (int) $row->source_contract_id,
                        'source_parent_contract_id' => $row->source_parent_contract_id ? (int) $row->source_parent_contract_id : null,
                        'contract_kind' => (string) ($row->contract_kind ?: ($row->source_parent_contract_id ? 'SUB' : 'MAIN')),
                        'type_contrat_id' => $row->type_contrat_id ? (int) $row->type_contrat_id : null,
                        'type_contrat_label' => $this->sanitizeContractLabel((string) ($row->type_contrat_label ?? ''), $row->source_parent_contract_id ? 'Sous-contrat' : 'Contrat principal'),
                        'immatriculation' => (string) ($row->immatriculation ?? ''),

                        'contract_rule_id' => $ruleConfigured ? (int) $row->contract_rule_id : null,
                        'rule_configured' => $ruleConfigured,
                        'rule_enabled' => $ruleEnabled,
                        'coupure_auto' => $ruleEnabled,
                        'heure_coupure' => $cutoffTime,
                        'grace_days' => (int) ($row->grace_days ?? 0),
                        'only_when_stopped' => (bool) ($row->only_when_stopped ?? true),
                        'notify_before_cutoff' => (bool) ($row->notify_before_cutoff ?? false),
                        'timezone' => (string) ($row->timezone ?: 'Africa/Douala'),
                    ],
                ];
            })
            ->all();

        Log::debug('[LEASE_CUTOFF_META_BY_CONTRACT_ID]', [
            'partner_id' => $partnerId,
            'count' => count($meta),
            'contract_ids' => array_slice(array_keys($meta), 0, 20),
        ]);

        return $meta;
    }


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
                    'ignition_state' => $row->ignition_state,

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
    $meta = [];

    /**
     * 1) Source prioritaire : queue active réelle.
     * Une coupure planifiée doit venir d'ici, pas seulement d'une règle active.
     */
    $activeQueues = DB::table('lease_cutoff_queue as q')
        ->leftJoin('lease_cutoff_histories as h', 'h.id', '=', 'q.history_id')
        ->where('q.partner_id', $partnerId)
        ->whereNotNull('q.lease_id')
        ->whereIn('q.status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT'])
        ->orderByDesc('q.id')
        ->get([
            'q.id as queue_id',
            'q.history_id',
            'q.lease_id',
            'q.vehicle_id',
            'q.contract_id',
            'q.contract_link_id',
            'q.parent_contract_id',
            'q.type_contrat_id',
            'q.type_contrat_label',
            'q.contract_kind',
            'q.trigger_label',
            'q.trigger_payload',
            'q.status',
            'q.scheduled_for',
            'q.next_check_at',
            'h.reason',
            'h.ignition_state',
            'h.detected_at',
            'h.cutoff_executed_at',
            'h.forgiven_by_user_id',
            'h.forgiven_by_name',
            'h.forgiven_at',
            'q.updated_at',
            'q.created_at',
        ]);

    foreach ($activeQueues as $row) {
        $leaseId = (int) $row->lease_id;

        if ($leaseId <= 0 || isset($meta[$leaseId])) {
            continue;
        }

        $status = (string) $row->status;

        $meta[$leaseId] = [
            'queue_id' => $row->queue_id ? (int) $row->queue_id : null,
            'history_id' => $row->history_id ? (int) $row->history_id : null,
            'lease_id' => $leaseId,
            'vehicle_id' => $row->vehicle_id ? (int) $row->vehicle_id : null,
            'contract_id' => $row->contract_id ? (int) $row->contract_id : null,
            'contract_link_id' => $row->contract_link_id ? (int) $row->contract_link_id : null,
            'parent_contract_id' => $row->parent_contract_id ? (int) $row->parent_contract_id : null,
            'type_contrat_id' => $row->type_contrat_id ? (int) $row->type_contrat_id : null,
            'type_contrat_label' => $row->type_contrat_label,
            'contract_kind' => $row->contract_kind,
            'trigger_label' => $row->trigger_label,
            'trigger_payload' => is_string($row->trigger_payload)
                ? json_decode($row->trigger_payload, true)
                : $row->trigger_payload,

            'status' => $status,
            'label' => $this->cutoffStatusLabel($status),
            'ui_type' => $this->cutoffStatusUiType($status),
            'reason' => $row->reason,
            'ignition_state' => $row->ignition_state,

            'scheduled_for' => $row->scheduled_for ? Carbon::parse($row->scheduled_for)->toDateTimeString() : null,
            'next_check_at' => $row->next_check_at ? Carbon::parse($row->next_check_at)->toDateTimeString() : null,
            'detected_at' => $row->detected_at ? Carbon::parse($row->detected_at)->toDateTimeString() : null,
            'cutoff_executed_at' => $row->cutoff_executed_at ? Carbon::parse($row->cutoff_executed_at)->toDateTimeString() : null,
            'forgiven_by_user_id' => $row->forgiven_by_user_id,
            'forgiven_by_name' => $row->forgiven_by_name,
            'forgiven_at' => $row->forgiven_at ? Carbon::parse($row->forgiven_at)->toDateTimeString() : null,
            'updated_at' => $row->updated_at ? Carbon::parse($row->updated_at)->toDateTimeString() : null,
            'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toDateTimeString() : null,
        ];
    }

    /**
     * 2) Historique : seulement pour les leases sans queue active.
     */
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

    $historyRows = LeaseCutoffHistory::query()
        ->where('partner_id', $partnerId)
        ->whereNotNull('lease_id')
        ->whereIn('status', $terminalAndProgressStatuses)
        ->orderByDesc('id')
        ->get([
            'id',
            'lease_id',
            'vehicle_id',
            'contract_id',
            'contract_link_id',
            'parent_contract_id',
            'type_contrat_id',
            'type_contrat_label',
            'contract_kind',
            'trigger_label',
            'trigger_payload',
            'status',
            'reason',
            'ignition_state',
            'scheduled_for',
            'detected_at',
            'cutoff_executed_at',
            'forgiven_by_user_id',
            'forgiven_by_name',
            'forgiven_at',
            'updated_at',
            'created_at',
        ]);

    foreach ($historyRows as $row) {
        $leaseId = (int) $row->lease_id;

        if ($leaseId <= 0 || isset($meta[$leaseId])) {
            continue;
        }

        $status = (string) $row->status;

        $meta[$leaseId] = [
            'history_id' => (int) $row->id,
            'lease_id' => $leaseId,
            'vehicle_id' => $row->vehicle_id,
            'contract_id' => $row->contract_id,
            'contract_link_id' => $row->contract_link_id,
            'parent_contract_id' => $row->parent_contract_id,
            'type_contrat_id' => $row->type_contrat_id,
            'type_contrat_label' => $row->type_contrat_label,
            'contract_kind' => $row->contract_kind,
            'trigger_label' => $row->trigger_label,
            'trigger_payload' => $row->trigger_payload,
            'status' => $status,
            'label' => $this->cutoffStatusLabel($status),
            'ui_type' => $this->cutoffStatusUiType($status),
            'reason' => $row->reason,
            'ignition_state' => $row->ignition_state,
            'scheduled_for' => $row->scheduled_for ? Carbon::parse($row->scheduled_for)->toDateTimeString() : null,
            'detected_at' => $row->detected_at ? Carbon::parse($row->detected_at)->toDateTimeString() : null,
            'cutoff_executed_at' => $row->cutoff_executed_at ? Carbon::parse($row->cutoff_executed_at)->toDateTimeString() : null,
            'forgiven_by_user_id' => $row->forgiven_by_user_id,
            'forgiven_by_name' => $row->forgiven_by_name,
            'forgiven_at' => $row->forgiven_at ? Carbon::parse($row->forgiven_at)->toDateTimeString() : null,
            'updated_at' => $row->updated_at?->toDateTimeString(),
            'created_at' => $row->created_at?->toDateTimeString(),
        ];
    }

    Log::debug('[LEASE_CUTOFF_STATUS_META_BY_LEASE_ID]', [
        'partner_id' => $partnerId,
        'count' => count($meta),
        'lease_ids' => array_slice(array_keys($meta), 0, 20),
    ]);

    return $meta;
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

            $this->throwLeaseApiException('GET', $endpoint, $url, $response);
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
    protected function patch(string $endpoint, array $payload = []): array
    {
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');
        $timeout = (int) config('services.partner_lease_api.timeout', 20);

        if ($baseUrl === '') {
            throw new RuntimeException("PARTNER_LEASE_API_BASE_URL est vide ou non chargé.");
        }

        $url = $baseUrl . $endpoint;

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $trackingToken = $tokenManager->getValidAccessToken(60);

        Log::info('[LEASE_API_PATCH_REQUEST]', [
            'url' => $url,
            'payload' => $payload,
            'has_token' => true,
            'token_preview' => substr($trackingToken, 0, 16) . '...',
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($trackingToken)
            ->patch($url, $payload);

        if ($response->status() === 401) {
            Log::warning('[LEASE_API_PATCH_401_REFRESH_RETRY]', [
                'url' => $url,
                'endpoint' => $endpoint,
            ]);

            $trackingToken = $tokenManager->forceRefresh('api_lease_patch_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($trackingToken)
                ->patch($url, $payload);
        }

        Log::info('[LEASE_API_PATCH_RESPONSE]', [
            'url' => $url,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 1200),
        ]);

        if (! $response->successful()) {
            if ($response->status() === 401) {
                throw new AuthenticationException(
                    "Session Keycloak expirée pour PATCH {$endpoint}."
                );
            }

            $this->throwLeaseApiException('PATCH', $endpoint, $url, $response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Appel PUT générique vers recouvrement.
     *
     * Utilisé pour la mise à jour complète d’un contrat selon la documentation.
     */
    protected function put(string $endpoint, array $payload = []): array
    {
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');
        $timeout = (int) config('services.partner_lease_api.timeout', 20);

        if ($baseUrl === '') {
            throw new RuntimeException("PARTNER_LEASE_API_BASE_URL est vide ou non chargé.");
        }

        $url = $baseUrl . $endpoint;

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $trackingToken = $tokenManager->getValidAccessToken(60);

        Log::info('[LEASE_API_PUT_REQUEST]', [
            'url' => $url,
            'payload' => $payload,
            'has_token' => true,
            'token_preview' => substr($trackingToken, 0, 16) . '...',
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($trackingToken)
            ->put($url, $payload);

        if ($response->status() === 401) {
            Log::warning('[LEASE_API_PUT_401_REFRESH_RETRY]', [
                'url' => $url,
                'endpoint' => $endpoint,
            ]);

            $trackingToken = $tokenManager->forceRefresh('api_lease_put_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($trackingToken)
                ->put($url, $payload);
        }

        Log::info('[LEASE_API_PUT_RESPONSE]', [
            'url' => $url,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 1200),
        ]);

        if (! $response->successful()) {
            if ($response->status() === 401) {
                throw new AuthenticationException(
                    "Session Keycloak expirée pour PUT {$endpoint}."
                );
            }

            $this->throwLeaseApiException('PUT', $endpoint, $url, $response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Appel DELETE générique vers recouvrement.
     */
    protected function delete(string $endpoint): void
    {
        $baseUrl = rtrim((string) config('services.partner_lease_api.base_url'), '/');
        $timeout = (int) config('services.partner_lease_api.timeout', 20);

        if ($baseUrl === '') {
            throw new RuntimeException("PARTNER_LEASE_API_BASE_URL est vide ou non chargé.");
        }

        $url = $baseUrl . $endpoint;

        $tokenManager = app(KeycloakSessionTokenManager::class);
        $trackingToken = $tokenManager->getValidAccessToken(60);

        Log::warning('[LEASE_API_DELETE_REQUEST]', [
            'url' => $url,
            'has_token' => true,
            'token_preview' => substr($trackingToken, 0, 16) . '...',
        ]);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($trackingToken)
            ->delete($url);

        if ($response->status() === 401) {
            Log::warning('[LEASE_API_DELETE_401_REFRESH_RETRY]', [
                'url' => $url,
                'endpoint' => $endpoint,
            ]);

            $trackingToken = $tokenManager->forceRefresh('api_lease_delete_401');

            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withToken($trackingToken)
                ->delete($url);
        }

        Log::warning('[LEASE_API_DELETE_RESPONSE]', [
            'url' => $url,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_preview' => mb_substr($response->body(), 0, 1200),
        ]);

        if (! $response->successful() && $response->status() !== 204) {
            if ($response->status() === 401) {
                throw new AuthenticationException(
                    "Session Keycloak expirée pour DELETE {$endpoint}."
                );
            }

            $this->throwLeaseApiException('DELETE', $endpoint, $url, $response);
        }
    }

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

            $this->throwLeaseApiException('POST', $endpoint, $url, $response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Transforme une réponse HTTP non réussie en exception métier.
     *
     * Le body complet est conservé dans les logs développeur, mais le client ne
     * verra qu'un message personnalisé via le contrôleur.
     */
    protected function throwLeaseApiException(string $method, string $endpoint, string $url, $response): never
    {
        $exception = LeaseApiException::fromResponse($method, $endpoint, $response);

        Log::error('[LEASE_API_CALL_FAILED]', [
            'request_id' => $exception->requestId,
            'method' => $method,
            'endpoint' => $endpoint,
            'url' => $url,
            'status' => $exception->status,
            'api_message' => $exception->apiMessage,
            'body_preview' => $exception->bodyPreview,
        ]);

        throw $exception;
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
     * Normalise un contrat API vers la structure attendue par la console contrat.
     *
     * Rôle :
     * La vue ne doit pas dépendre directement de tous les formats possibles de
     * recouvrement. Cette méthode convertit la réponse API en structure stable.
     *
     * Elle conserve aussi raw pour ne jamais perdre les données originales.
     */
    protected function normalizeContract(array $row): array
    {
        $total = (float) ($row['montant_total'] ?? 0);
        $remaining = (float) ($row['montant_restant'] ?? max(0, $total));
        $paid = max(0, $total - $remaining);

        $typeId = (int) ($row['type_contrat'] ?? data_get($row, 'type_contrat.id') ?? 1);
        /**
         * /contrats/ renvoie le libellé sous 'type_contrat_libelle' (français,
         * champ plat) — jamais sous 'type_contrat_label' ni sous un objet
         * imbriqué 'type_contrat.libelle' (type_contrat est un entier ici, pas
         * un objet). L'ancien ordre de repli ne testait jamais le vrai champ
         * et retombait donc systématiquement sur "Véhicule" pour tous les
         * contrats. Même champ que /leases/ (voir normalizeLease()), qui lui
         * fonctionnait déjà correctement.
         */
        $typeLabel = (string) (
            $row['type_contrat_libelle']
            ?? $row['type_contrat_label']
            ?? data_get($row, 'type_contrat.libelle')
            ?? data_get($row, 'type_contrat.label')
            ?? 'Véhicule'
        );

        $subContracts = collect(
            $row['sous_contrats']
            ?? $row['sousContrats']
            ?? $row['sub_contracts']
            ?? []
        )
            ->filter(fn ($sub) => is_array($sub))
            ->map(fn (array $sub) => $this->normalizeSubContract($sub, (int) ($row['id'] ?? 0)))
            ->values()
            ->all();

        $createdAt = $this->parseDateTime($row['created_at'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'source_contrat_id' => (int) ($row['id'] ?? 0),
            'source_parent_contract_id' => $row['parent'] ?? data_get($row, 'parent.id'),
            'ref' => (string) ($row['reference'] ?? $row['ref'] ?? ('CTR-' . str_pad((string) ($row['id'] ?? 0), 5, '0', STR_PAD_LEFT))),

            'chauffeur' => (string) (
                $row['chauffeur_nom_complet']
                ?? data_get($row, 'chauffeur.nom_complet')
                ?? $row['nom_complet']
                ?? '—'
            ),
            'chauffeur_id' => (int) ($row['chauffeur'] ?? data_get($row, 'chauffeur.id') ?? 0),

            'phone_ch' => (string) (
                $row['chauffeur_phone']
                ?? $row['telephone_chauffeur']
                ?? data_get($row, 'chauffeur.phone')
                ?? $row['phone_ch']
                ?? ''
            ),

            'vehicule' => (string) ($row['immatriculation'] ?? '—'),
            'vehicule_id' => $row['vehicule_id'] ?? data_get($row, 'vehicule.id'),
            'vehicle_id' => $row['vehicle_id']
                ?? $row['vehicule_id']
                ?? data_get($row, 'vehicle.id')
                ?? data_get($row, 'vehicule.id')
                ?? null,
            'vin' => (string) ($row['vin'] ?? ''),
            'marque' => (string) data_get($row, 'specificites.marque', '—'),

            'type_contrat' => $typeId,
            'type_contrat_label' => $typeLabel,
            'type_label' => $typeLabel,

            'partenaire' => Auth::user()?->full_name
                ?? Auth::user()?->nom
                ?? 'Partenaire connecté',

            'montant_total' => $total,
            'montant_restant' => $remaining,
            'total_paye' => $paid,
            'versement' => (float) ($row['montant_par_paiement'] ?? 0),
            'apport_initial' => 0,

            'frequence' => $this->normalizeFrequencyFromApi($row['frequence'] ?? ''),
            'frequence_label_api' => (string) ($row['frequence'] ?? ''),

            'date_debut' => $row['date_debut'] ?? $createdAt?->toDateString(),
            'date_fin_prevue' => $row['date_fin'] ?? null,
            'date_fin' => $row['date_fin'] ?? null,
            'premiere_echeance' => $row['prochaine_echeance'] ?? null,
            'prochaine_echeance' => $row['prochaine_echeance'] ?? null,
            'heure_limite' => '18:00',

            'statut' => $this->normalizeContractStatusFromApi($row['statut'] ?? $row['status'] ?? ''),
            'pardon_auto' => false,
            'nb_paiements' => $this->estimatePaymentCount(
                $paid,
                (float) ($row['montant_par_paiement'] ?? 0)
            ),

            'specificites' => $row['specificites'] ?? null,
            'sous_contrats' => $subContracts,
            'sub_contracts' => $subContracts,

            'notes' => 'Contrat synchronisé depuis l’API recouvrement.',
            'enregistre_par_nom_complet' => (string) ($row['enregistre_par_nom_complet'] ?? data_get($row, 'enregistre_par.nom_complet') ?? '—'),

            'raw' => $row,
        ];
    }

    /**
     * Normalise un sous-contrat recouvrement pour la vue et le lien local.
     */
    protected function normalizeSubContract(array $row, ?int $parentContractId = null): array
    {
        $total = (float) ($row['montant_total'] ?? 0);
        $remaining = (float) ($row['montant_restant'] ?? max(0, $total));
        $paid = max(0, $total - $remaining);

        $typeId = (int) ($row['type_contrat'] ?? data_get($row, 'type_contrat.id') ?? 0);
        $typeLabel = (string) (
            $row['type_contrat_libelle']
            ?? $row['type_contrat_label']
            ?? data_get($row, 'type_contrat.libelle')
            ?? data_get($row, 'type_contrat.label')
            ?? 'Sous-contrat'
        );

        return [
            'id' => (int) ($row['id'] ?? 0),
            'source_contract_id' => (int) ($row['id'] ?? 0),
            'source_parent_contract_id' => $row['parent'] ?? $parentContractId,
            'type_contrat' => $typeId,
            'type_contrat_id' => $typeId,
            'type_label' => $typeLabel,
            'type_contrat_label' => $typeLabel,
            'montant_total' => $total,
            'montant_restant' => $remaining,
            'total_paye' => $paid,
            'montant_par_paiement' => (float) ($row['montant_par_paiement'] ?? 0),
            'frequence' => $this->normalizeFrequencyFromApi($row['frequence'] ?? ''),
            'date_debut' => $row['date_debut'] ?? null,
            'date_fin' => $row['date_fin'] ?? null,
            'prochaine_echeance' => $row['prochaine_echeance'] ?? null,
            'statut' => $this->normalizeContractStatusFromApi($row['statut'] ?? $row['status'] ?? ''),
            'specificites' => $row['specificites'] ?? null,
            'raw' => $row,
        ];
    }

    /**
     * Normalise une ligne type de contrat.
     */
    protected function normalizeContractType(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'libelle' => (string) ($row['libelle'] ?? $row['label'] ?? $row['name'] ?? ''),
            'label' => (string) ($row['libelle'] ?? $row['label'] ?? $row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'est_principal' => (bool) ($row['est_principal'] ?? false),
            'raw' => $row,
        ];
    }

    /**
     * Construit le payload conforme à l’API recouvrement pour POST/PUT contrat.
     */
    protected function buildContractPayload(array $payload, bool $includeStatus = false, bool $includeRemaining = false): array
    {
        $apiPayload = [
            'chauffeur' => (int) $payload['chauffeur'],
            'type_contrat' => (int) ($payload['type_contrat'] ?? 1),
            'immatriculation' => (string) $payload['immatriculation'],
            'vin' => (string) ($payload['vin'] ?? ''),
            'montant_total' => (string) $payload['montant_total'],
            'montant_paye' => (string) ($payload['montant_paye'] ?? 0),
            'montant_par_paiement' => (string) $payload['montant_par_paiement'],
            'frequence' => mb_strtoupper((string) $payload['frequence'], 'UTF-8'),
            'date_debut' => (string) $payload['date_debut'],
            'date_fin' => (string) $payload['date_fin'],
            'prochaine_echeance' => (string) $payload['prochaine_echeance'],
            'specificites' => $payload['specificites'] ?? new \stdClass(),
        ];

        if (array_key_exists('parent', $payload)) {
            $apiPayload['parent'] = $payload['parent'] !== '' ? $payload['parent'] : null;
        }

        if (! empty($payload['nom_complet'])) {
            $apiPayload['nom_complet'] = (string) $payload['nom_complet'];
        }

        if ($includeRemaining && array_key_exists('montant_restant', $payload)) {
            $apiPayload['montant_restant'] = (string) $payload['montant_restant'];
        }

        if ($includeStatus && ! empty($payload['statut'])) {
            $apiPayload['statut'] = mb_strtoupper((string) $payload['statut'], 'UTF-8');
        }

        $subContracts = collect($payload['sous_contrats'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                return [
                    'type_contrat' => (int) $row['type_contrat'],
                    'montant_total' => (string) $row['montant_total'],
                    'montant_paye' => (string) ($row['montant_paye'] ?? 0),
                    'montant_par_paiement' => (string) $row['montant_par_paiement'],
                    'frequence' => mb_strtoupper((string) $row['frequence'], 'UTF-8'),
                    'date_debut' => (string) $row['date_debut'],
                    'date_fin' => (string) $row['date_fin'],
                    'prochaine_echeance' => (string) $row['prochaine_echeance'],
                    'specificites' => $row['specificites'] ?? new \stdClass(),
                ];
            })
            ->values()
            ->all();

        if (! empty($subContracts)) {
            $apiPayload['sous_contrats'] = $subContracts;
        }

        return $apiPayload;
    }

    protected function normalizeContractStatusFromApi(?string $status): string
    {
        return match (mb_strtoupper((string) $status, 'UTF-8')) {
            'ACTIF' => 'actif',
            'SUSPENDU' => 'suspendu',
            'SOLDE' => 'solde',
            'CONTENTIEUX' => 'contentieux',
            'RETARD' => 'retard',
            default => 'actif',
        };
    }

    protected function normalizeFrequencyFromApi(?string $frequency): string
    {
        return match (mb_strtoupper(trim((string) $frequency), 'UTF-8')) {
            'JOURNALIER', 'QUOTIDIEN' => 'JOURNALIER',
            'HEBDOMADAIRE' => 'HEBDOMADAIRE',
            'MENSUEL' => 'MENSUEL',
            default => 'JOURNALIER',
        };
    }

    /**
     * Normalise une ligne paiement API.
     */
    protected function normalizePayment(array $row): array
    {
        $status = mb_strtoupper((string) ($row['statut'] ?? $row['status'] ?? ''), 'UTF-8');
        $rawMethod = (string) ($row['methode'] ?? $row['method'] ?? $row['mode_paiement'] ?? '');
        $methodFamily = $this->normalizePaymentMethodFamily($rawMethod);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'lease_id' => (int) ($row['lease'] ?? $row['lease_id'] ?? 0),

            'montant' => (float) ($row['montant'] ?? 0),

            /**
             * La vue ne présente que deux familles métier :
             * - CASH ;
             * - MOBILE_MONEY.
             * Les valeurs techniques comme MOBILE, MTN, ORANGE, MOMO restent
             * conservées dans methode_raw pour le debug développeur.
             */
            'methode' => $methodFamily,
            'methode_label' => $methodFamily ? $this->paymentMethodLabel($methodFamily) : null,
            'methode_raw' => $rawMethod,

            // Le champ `reference` n'est plus renvoyé par l'API /paiements/ : retiré.
            'transaction_id' => (string) ($row['transaction_id'] ?? ''),
            'statut' => $status,

            'date_paiement' => $row['date_paiement'] ?? $row['paid_at'] ?? $row['created_at'] ?? $row['updated_at'] ?? null,
            'chauffeur_nom_complet' => (string) (
                $row['chauffeur_nom_complet']
                ?? $row['nom_complet_search']
                ?? data_get($row, 'contrat.chauffeur.nom_complet')
                ?? data_get($row, 'lease.contrat.chauffeur.nom_complet')
                ?? ''
            ),
            'enregistre_par' => (string) (
                $row['enregistre_par']
                ?? $row['enregistre_par_nom_complet']
                ?? data_get($row, 'enregistre_par.nom_complet')
                ?? ''
            ),

            // Données utiles au bloc "Paiements du jour" lorsque /paiements/
            // renvoie le contrat ou le lease imbriqué.
            'contrat_id' => (int) (
                $row['contrat_id']
                ?? data_get($row, 'contrat.id')
                ?? data_get($row, 'lease.contrat.id')
                ?? 0
            ),
            'vehicule' => (string) (
                $row['immatriculation']
                ?? $row['vehicule']
                ?? data_get($row, 'contrat.immatriculation')
                ?? data_get($row, 'lease.contrat.immatriculation')
                ?? ''
            ),
            'type_contrat_label' => (string) (
                $row['type_contrat_libelle']
                ?? $row['type_contrat_label']
                ?? data_get($row, 'contrat.type_contrat.libelle')
                ?? data_get($row, 'contrat.type_contrat_label')
                ?? data_get($row, 'lease.contrat.type_contrat.libelle')
                ?? ''
            ),
            'contract_kind' => data_get($row, 'contrat.parent') || data_get($row, 'lease.contrat.parent') ? 'SUB' : 'MAIN',

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
     * Regroupe toutes les valeurs techniques de recouvrement en deux familles
     * lisibles pour l'utilisateur : CASH ou MOBILE_MONEY.
     */
    protected function normalizePaymentMethodFamily(mixed $method): ?string
    {
        $value = mb_strtoupper(trim((string) $method), 'UTF-8');

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'CASH') || str_contains($value, 'ESPECE') || str_contains($value, 'ESPÈCE')) {
            return 'CASH';
        }

        if (
            str_contains($value, 'MOBILE')
            || str_contains($value, 'MOMO')
            || str_contains($value, 'MTN')
            || str_contains($value, 'ORANGE')
            || str_contains($value, 'OM')
            || str_contains($value, 'MAVIANCE')
        ) {
            return 'MOBILE_MONEY';
        }

        return $value;
    }

    protected function paymentMethodLabel(?string $methodFamily): ?string
    {
        return match ($methodFamily) {
            'CASH' => 'Cash',
            'MOBILE_MONEY' => 'Mobile Money',
            null, '' => null,
            default => $methodFamily,
        };
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

        /**
         * Le pardon est une information de coupure, pas le statut principal du paiement.
         * Si l'API recouvrement indique PAYE, la ligne doit rester PAYE même s'il existe
         * un ancien historique de pardon/coupure sur cette échéance.
         */
        if ($status !== 'paid' && $forgivenessMeta) {
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

        $contractTypeId = (int) (
            $row['type_contrat_id']
            ?? $row['type_contrat']
            ?? data_get($row, 'contrat.type_contrat')
            ?? $contract['type_contrat']
            ?? 0
        );

        $contractTypeLabel = $this->bestContractTypeLabel(
            apiLabel: (string) (
                $row['type_contrat_libelle']
                ?? $row['type_contrat_label']
                ?? data_get($row, 'contrat.type_contrat.libelle')
                ?? data_get($row, 'contrat.type_contrat_label')
                ?? ''
            ),
            contractLabel: (string) ($contract['type_contrat_label'] ?? $contract['type_label'] ?? ''),
            localLabel: '',
            kind: 'MAIN'
        );

        $parentContractId = $row['parent']
            ?? data_get($row, 'contrat.parent')
            ?? $contract['source_parent_contract_id']
            ?? null;

        $contractKind = $parentContractId ? 'SUB' : 'MAIN';

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

        /**
         * Nouvelle logique métier :
         * un lease n'est éligible que si le contrat ou sous-contrat réel
         * possède une règle spécifique active dans lease_cutoff_contract_rules.
         */
        $contractLinkId = $cutoffMeta['contract_link_id'] ?? null;
        $contractRuleId = $cutoffMeta['contract_rule_id'] ?? null;
        $ruleConfigured = (bool) ($cutoffMeta['rule_configured'] ?? ! empty($contractRuleId));
        $ruleEnabled = (bool) ($cutoffMeta['rule_enabled'] ?? $cutoffMeta['coupure_auto'] ?? false);
        $effectiveCutoffTime = $cutoffMeta['heure_coupure'] ?? null;

        if (! empty($cutoffMeta['type_contrat_id'])) {
            $contractTypeId = (int) $cutoffMeta['type_contrat_id'];
        }

        $contractTypeLabel = $this->bestContractTypeLabel(
            apiLabel: (string) (
                $row['type_contrat_libelle']
                ?? $row['type_contrat_label']
                ?? data_get($row, 'contrat.type_contrat.libelle')
                ?? data_get($row, 'contrat.type_contrat_label')
                ?? ''
            ),
            contractLabel: (string) ($contract['type_contrat_label'] ?? $contract['type_label'] ?? ''),
            localLabel: (string) ($cutoffMeta['type_contrat_label'] ?? ''),
            kind: (string) ($cutoffMeta['contract_kind'] ?? $contractKind)
        );

        if (! empty($cutoffMeta['source_parent_contract_id'])) {
            $parentContractId = (int) $cutoffMeta['source_parent_contract_id'];
        }

        if (! empty($cutoffMeta['contract_kind'])) {
            $contractKind = (string) $cutoffMeta['contract_kind'];
        }

        $coupureAuto = $status === 'unpaid'
            && $ruleConfigured
            && $ruleEnabled
            && ! empty($effectiveCutoffTime);

        $heureCoupure = $coupureAuto ? substr((string) $effectiveCutoffTime, 0, 5) : null;

        $cutoffEligibilityReason = match (true) {
            $status !== 'unpaid' => 'Échéance réglée, pardonnée ou non impayée.',
            $cutoffMeta === null => 'Aucun lien local n’est trouvé pour ce contrat ou sous-contrat.',
            ! $ruleConfigured => 'Aucune règle de coupure n’est configurée pour ce contrat ou sous-contrat.',
            ! $ruleEnabled => 'La règle de coupure de ce contrat ou sous-contrat est désactivée.',
            empty($effectiveCutoffTime) => 'Heure de coupure absente malgré la règle active.',
            default => sprintf(
                'Éligible : %s autorise la coupure à %s.',
                $contractKind === 'SUB' ? 'ce sous-contrat' : 'ce contrat',
                substr((string) $effectiveCutoffTime, 0, 5)
            ),
        };

        /**
         * Données venant prioritairement de /paiements/.
         */
        $paymentMethodRaw = $paymentMeta['methode_raw']
            ?? $paymentMeta['methode']
            ?? $row['methode']
            ?? $row['method']
            ?? $row['mode_paiement']
            ?? $row['payment_method']
            ?? null;

        $paymentMethodFamily = $this->normalizePaymentMethodFamily($paymentMethodRaw);

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

        $paymentTransactionId = $paymentMeta['transaction_id']
            ?? $row['transaction_id']
            ?? null;

        return [
            'id' => $leaseId,
            'source_lease_id' => $leaseId,
            'source_contrat_id' => $contractId,
            'parent_contract_id' => $parentContractId ? (int) $parentContractId : null,
            'contract_kind' => $contractKind,
            'type_contrat_id' => $contractTypeId,
            'type_contrat_label' => $contractTypeLabel,

            /**
             * Date affichée dans le tableau = date d’échéance du lease.
             */
            'date' => $dateEcheance,
            'date_echeance' => $dateEcheance,

            'vehicule' => $immat,
            'vehicle_id' => (int) ($cutoffMeta['vehicle_id'] ?? 0) ?: null,
            'contract_link_id' => $contractLinkId ? (int) $contractLinkId : null,
            'contract_rule_id' => $contractRuleId ? (int) $contractRuleId : null,
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
            'contrat_type' => $contractTypeLabel,
            'contrat_kind' => $contractKind,

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
            'paye_par' => $paymentMethodFamily === 'CASH'
                ? ($paymentRecordedBy ?: null)
                : null,

            /**
             * Méthode :
             * source prioritaire = /paiements/.methode.
             */
            'methode' => $paymentMethodFamily ?: null,
            'methode_label' => $paymentMethodFamily ? $this->paymentMethodLabel($paymentMethodFamily) : null,
            'methode_raw' => $paymentMethodRaw,

            /**
             * Champs détaillés paiement.
             */
            'paiement_id' => $paymentMeta['id'] ?? null,
            'paiement_statut' => $paymentStatus,
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
            'vehicle_cutoff_enabled' => $ruleEnabled,
            'cutoff_rule_id' => $contractRuleId,
            'cutoff_contract_rule_id' => $contractRuleId,
            'type_cutoff_enabled' => $ruleEnabled,
            'cutoff_type_rule_configured' => $ruleConfigured,
            'cutoff_type_rule_label' => $contractTypeLabel,
            'cutoff_eligibility_reason' => $cutoffEligibilityReason,

            'coupure_status' => $cutoffStatusMeta['status'] ?? null,
            'coupure_label' => $cutoffStatusMeta['label'] ?? (
                $coupureAuto
                    ? 'Règle active'
                    : ($ruleConfigured && ! $ruleEnabled ? 'Règle inactive' : 'Aucune coupure')
            ),
            'coupure_ui_type' => $cutoffStatusMeta['ui_type'] ?? (
                $coupureAuto
                    ? 'warning'
                    : ($ruleConfigured && ! $ruleEnabled ? 'muted' : 'muted')
            ),
            'coupure_reason' => $this->shortUserCutoffReason((string) ($cutoffStatusMeta['reason'] ?? $cutoffEligibilityReason), (string) ($cutoffStatusMeta['ignition_state'] ?? '')),
            'coupure_history_id' => $cutoffStatusMeta['history_id'] ?? null,
            'coupure_scheduled_for' => $cutoffStatusMeta['scheduled_for'] ?? null,
            'coupure_detected_at' => $cutoffStatusMeta['detected_at'] ?? null,
            'coupure_executed_at' => $cutoffStatusMeta['cutoff_executed_at'] ?? null,
            'coupure_trigger_label' => null,
            'coupure_trigger_payload' => null,

            /**
             * Compatibilité ancienne vue :
             * coupe = true uniquement si historique CUT_OFF.
             */
            'coupe' => ($cutoffStatusMeta['status'] ?? null) === 'CUT_OFF',
                        /**
             * Heure paiement : ne jamais afficher created_at du lease comme
             * heure d'enregistrement, car c'est trompeur pour l'utilisateur.
             */
            'heure_paiement' => $this->parseDateTime($paymentDate)?->format('H:i:s'),
            'heure_enreg' => $this->parseDateTime($paymentDate)?->format('H:i:s'),

            /**
             * Référence technique conservée pour compatibilité interne, mais
             * la vue principale ne doit plus l'afficher.
             */
            'contrat_ref' => $contract['ref'] ?? null,

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

    protected function queueStatusLabel(string $status): string
    {
        return match (strtoupper($status)) {
            'PENDING' => 'Planifiée',
            'WAITING_STOP' => 'En attente sécurité',
            'COMMAND_SENT' => 'Commande envoyée',
            default => 'En attente',
        };
    }

    protected function bestContractTypeLabel(string $apiLabel = '', string $contractLabel = '', string $localLabel = '', string $kind = 'MAIN'): string
    {
        foreach ([$apiLabel, $contractLabel, $localLabel] as $candidate) {
            $clean = $this->sanitizeContractLabel($candidate, '');

            if ($clean !== '') {
                return $clean;
            }
        }

        return strtoupper($kind) === 'SUB' ? 'Sous-contrat' : 'Contrat principal';
    }

    protected function sanitizeContractLabel(?string $value, string $fallback = 'Contrat'): string
    {
        $label = trim((string) $value);

        if ($label === '' || preg_match('/^type\s*#?\s*\d+$/i', $label)) {
            return $fallback;
        }

        if (preg_match('/^(contrat|sous-contrat)\s*#?\s*\d+$/i', $label)) {
            return $fallback;
        }

        return $label;
    }

    protected function shortUserCutoffReason(?string $reason, ?string $ignitionState = null): string
    {
        $reason = trim((string) $reason);
        $ignitionState = strtoupper(trim((string) $ignitionState));
        $haystack = mb_strtolower($reason . ' ' . $ignitionState, 'UTF-8');

        return match (true) {
            str_contains($haystack, 'offline') => 'Coupure reportée : GPS offline.',
            str_contains($haystack, 'mouvement') || str_contains($haystack, 'moving') || str_contains($haystack, 'roule') => 'Coupure reportée : véhicule en mouvement.',
            str_contains($haystack, 'incertain') || str_contains($haystack, 'unknown') => 'Coupure reportée : état GPS incertain.',
            str_contains($haystack, 'déjà confirmé coupé') || str_contains($haystack, 'deja confirme coupe') || str_contains($haystack, 'déjà coupé') => 'Moteur déjà coupé confirmé.',
            str_contains($haystack, 'commande') && str_contains($haystack, 'envoy') => 'Commande envoyée, confirmation en attente.',
            str_contains($haystack, 'paiement') || str_contains($haystack, 'régularisé') || str_contains($haystack, 'regularise') => 'Coupure annulée : paiement régularisé.',
            str_contains($haystack, 'règle') || str_contains($haystack, 'regle') => $reason !== '' ? $reason : 'Règle de coupure indisponible.',
            $reason !== '' => mb_strlen($reason) > 120 ? mb_substr($reason, 0, 117) . '...' : $reason,
            default => 'État de coupure non renseigné.',
        };
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
     * Normalise les immatriculations pour matcher Tracking ↔ Recouvrement.
     */
    protected function normalizeImmatriculation(?string $value): string
    {
        return mb_strtoupper(
            preg_replace('/\s+/', '', trim((string) $value)),
            'UTF-8'
        );
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