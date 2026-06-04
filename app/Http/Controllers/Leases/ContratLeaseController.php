<?php

namespace App\Http\Controllers\Leases;

use App\Exceptions\LeaseApiException;
use App\Http\Controllers\Controller;
use App\Models\LeaseContractLink;
use App\Services\Leases\LeaseContractCutoffRuleApplicationService;
use App\Services\Leases\LeaseContractLinkService;
use App\Services\Leases\LeaseCutoffRuleService;
use App\Services\Leases\PartnerLeaseApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use stdClass;
use Throwable;

/**
 * Console contrats recouvrement depuis Tracking.
 *
 * Responsabilités séparées :
 * - Recouvrement : contrats, sous-contrats, échéances, paiements, types de contrats.
 * - Tracking : véhicules, VIN/immatriculation locale, règles de coupure, historique.
 *
 * Cette classe ne renvoie jamais au client les erreurs HTML brutes de Django/DRF.
 * Les détails techniques sont loggés, tandis que l'interface reçoit un message clair.
 */
class ContratLeaseController extends Controller
{
    public function __construct(
        private readonly PartnerLeaseApiService $leaseApiService,
        private readonly LeaseCutoffRuleService $cutoffRuleService,
        private readonly LeaseContractLinkService $contractLinkService,
        private readonly LeaseContractCutoffRuleApplicationService $ruleApplicationService
    ) {
    }

    /**
     * Affiche la console contrats.
     *
     * Chaque source est chargée indépendamment pour éviter qu'une erreur API
     * secondaire bloque toute la page.
     */
    public function index(Request $request): View
    {
        $contracts = [];
        $chauffeursList = [];
        $vehiculesList = [];
        $contractTypes = [];
        $pageWarnings = [];

        try {
            $contracts = $this->leaseApiService->fetchContracts($request->only([
                'search',
                'statut',
                'statut__in',
                'frequence',
                'frequence__in',
                'type_contrat_id',
                'date_debut_start',
                'date_debut_end',
                'date_fin_start',
                'date_fin_end',
                'prochaine_echeance_start',
                'prochaine_echeance_end',
                'montant_restant_min',
                'montant_restant_max',
                'page',
            ]));
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_INDEX_FETCH_CONTRACTS_FAILED', $e);
            $pageWarnings[] = $this->clientErrorMessage($e, 'Les contrats recouvrement sont indisponibles pour le moment.');
        }

        try {
            $chauffeursList = $this->normalizeChauffeursForView($this->leaseApiService->fetchChauffeurs());
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_INDEX_FETCH_CHAUFFEURS_FAILED', $e);
            $pageWarnings[] = $this->clientErrorMessage($e, 'Les chauffeurs recouvrement sont indisponibles pour le moment.');
        }

        try {
            $vehiculesList = $this->leaseApiService->fetchPartnerVehiclesForContracts();
            $contracts = $this->attachTrackingVehiclesToContracts($contracts, $vehiculesList);
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_INDEX_FETCH_VEHICLES_FAILED', $e);
            $pageWarnings[] = $this->clientErrorMessage($e, 'Les véhicules Tracking sont indisponibles pour le moment.');
        }

        try {
            $contractTypes = $this->leaseApiService->fetchContractTypes();

            if (empty($contractTypes)) {
                $pageWarnings[] = 'Aucun type de contrat n’est disponible côté recouvrement. Créez d’abord les types pour activer la création dynamique des contrats.';
            }
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_INDEX_FETCH_TYPES_FAILED', $e);
            $pageWarnings[] = $this->clientErrorMessage($e, 'Les types de contrats recouvrement sont indisponibles pour le moment.');
        }

        try {
            if (! empty($contracts) && ! empty($contractTypes) && auth()->check()) {
                $contracts = $this->attachCutoffPoliciesToContracts($contracts, $contractTypes);
            }
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_ATTACH_CUTOFF_POLICIES_FAILED', $e);
            $pageWarnings[] = 'Les contrats sont affichés, mais les règles de coupure existantes n’ont pas pu être chargées.';
        }

        try {
            if (! empty($contracts) && auth()->check()) {
                /**
                 * On synchronise les lignes plates renvoyées par recouvrement.
                 * Le service sait distinguer parent=null et parent=<id>.
                 */
                $this->contractLinkService->syncFetchedContracts(auth()->user(), $contracts);
            }
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_LINK_SYNC_FETCHED_FAILED', $e);
            $pageWarnings[] = 'Les contrats sont affichés, mais la synchronisation locale Tracking n’a pas pu être finalisée.';
        }

        $contractsForView = $this->groupContractsForView($contracts);

        return view('leases.contrat', [
            'contracts' => $contractsForView,
            'chauffeurs_list' => $chauffeursList,
            'vehicules_list' => $vehiculesList,
            'contractTypesFromApi' => $contractTypes,
            'pageError' => null,
            'pageWarnings' => $pageWarnings,
        ]);
    }

    /**
     * Crée un contrat principal ou un sous-contrat.
     *
     * Documentation recouvrement POST /contrats/ :
     * - chauffeur, type_contrat, immatriculation, vin, montant_total,
     *   montant_par_paiement, frequence, date_debut, date_fin,
     *   prochaine_echeance, specificites ;
     * - parent pour rattacher un sous-contrat ;
     * - sous_contrats[] optionnel pendant la création du contrat principal.
     */
    public function store(Request $request): RedirectResponse
    {
        Log::info('[LEASE_CONTRACT_STORE_REQUEST]', [
            'input' => $request->except(['_token']),
            'actor_id' => auth()->id(),
        ]);

        $validated = $this->validateContractPayload($request, creating: true);
        $vehicleId = $validated['vehicle_id'] ?? null;
        $isSubContract = ! empty($validated['parent']);

        try {
            if ($isSubContract) {
                $apiPayload = $this->buildSubContractPayload($validated);

                Log::info('[LEASE_SUB_CONTRACT_STORE_API_PAYLOAD]', [
                    'parent_contract_id' => (int) $validated['parent'],
                    'payload' => $apiPayload,
                ]);

                $created = $this->leaseApiService->createSubContract(
                    (int) $validated['parent'],
                    $apiPayload
                );

                $this->contractLinkService->syncSubContractFromParent(
                    actor: auth()->user(),
                    parentContractId: (int) $validated['parent'],
                    payload: $apiPayload,
                    apiResponse: $created
                );
            } else {
                $apiPayload = $this->buildRecouvrementPayload($validated, updating: false);

                Log::info('[LEASE_CONTRACT_STORE_API_PAYLOAD]', [
                    'payload' => $apiPayload,
                    'vehicle_id_local_tracking' => $vehicleId,
                ]);

                $created = $this->leaseApiService->createContract($apiPayload);
                $this->syncContractLinkAfterWrite($validated, $apiPayload, $created);
            }

            $this->applyCutoffRuleAfterContractCreation($validated, $created);

            return redirect()
                ->route('lease.contrat')
                ->with('success', $isSubContract
                    ? 'Sous-contrat enregistré avec succès.'
                    : 'Contrat enregistré avec succès.');
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_STORE_FAILED', $e, [
                'validated' => $validated,
                'vehicle_id_local_tracking' => $vehicleId,
            ]);

            return back()
                ->withInput()
                ->with('error', $this->clientErrorMessage($e, 'Impossible d’enregistrer le contrat pour le moment.'));
        }
    }

    /**
     * Modifie un contrat ou sous-contrat avec PUT /contrats/{id}/.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        Log::info('[LEASE_CONTRACT_UPDATE_REQUEST]', [
            'contract_id' => $id,
            'input' => $request->except(['_token']),
            'actor_id' => auth()->id(),
        ]);

        $validated = $this->validateContractPayload($request, creating: false);
        $apiPayload = $this->buildRecouvrementPayload($validated, updating: true);

        try {
            $response = $this->leaseApiService->updateContract($id, $apiPayload);
            $this->syncContractLinkAfterWrite($validated, $apiPayload, $response ?: ['id' => $id, ...$apiPayload]);

            return redirect()
                ->route('lease.contrat')
                ->with('success', 'Contrat modifié avec succès.');
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_UPDATE_FAILED', $e, [
                'contract_id' => $id,
                'payload' => $apiPayload,
            ]);

            return back()
                ->withInput()
                ->with('error', $this->clientErrorMessage($e, 'Impossible de modifier le contrat pour le moment.'));
        }
    }

    /**
     * Suppression définitive : gardée pour compatibilité, mais l'interface utilise
     * désormais la clôture via statut SOLDE quand recouvrement bloque le hard-delete.
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->leaseApiService->deleteContract($id);
            $this->contractLinkService->markContractDeleted(auth()->user(), $id);

            return redirect()
                ->route('lease.contrat')
                ->with('success', 'Contrat supprimé avec succès.');
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_DELETE_FAILED', $e, ['contract_id' => $id]);

            return back()
                ->with('error', $this->clientErrorMessage($e, 'Impossible de supprimer définitivement ce contrat. Utilisez plutôt l’action “Clôturer”.'));
        }
    }

    /**
     * Sauvegarde les règles de coupure Tracking pour le véhicule lié au contrat.
     */
    public function updateCutoffPolicy(Request $request): RedirectResponse
    {
        $payload = $this->extractCutoffPayload($request);

        $validated = validator($payload, $this->cutoffValidationRules(), $this->cutoffValidationMessages())->validate();

        try {
            $this->cutoffRuleService->saveContractTypePolicyForVehicle(
                user: auth()->user(),
                vehicleId: (int) $validated['vehicle_id'],
                payload: Arr::except($validated, ['vehicle_id', 'contract_id'])
            );

            return redirect()
                ->route('lease.contrat')
                ->with('success', 'Règles de coupure enregistrées.');
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_CUTOFF_POLICY_FAILED', $e, ['payload' => $payload]);

            return back()
                ->withInput()
                ->with('error', $this->clientErrorMessage($e, 'Impossible d’enregistrer les règles de coupure.'));
        }
    }

    /**
     * Applique la même politique de coupure à plusieurs véhicules.
     */
    public function bulkUpdateCutoffPolicies(Request $request): RedirectResponse
    {
        $payload = $this->extractCutoffPayload($request);
        $payload['vehicle_ids'] = array_values(array_unique(array_filter(array_map('intval', $request->input('vehicle_ids', [])))));

        $validated = validator($payload, [
            'vehicle_ids' => ['required', 'array', 'min:1'],
            'vehicle_ids.*' => ['integer', 'exists:voitures,id'],
            ...Arr::except($this->cutoffValidationRules(), ['vehicle_id', 'contract_id']),
        ], $this->cutoffValidationMessages())->validate();

        try {
            $this->cutoffRuleService->saveBulkContractTypePolicies(
                user: auth()->user(),
                vehicleIds: $validated['vehicle_ids'],
                payload: Arr::except($validated, ['vehicle_ids', 'contract_id'])
            );

            return redirect()
                ->route('lease.contrat')
                ->with('success', 'Règles appliquées au lot sélectionné.');
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_BULK_CUTOFF_POLICY_FAILED', $e, ['payload' => $payload]);

            return back()
                ->withInput()
                ->with('error', $this->clientErrorMessage($e, 'Impossible d’appliquer les règles au lot.'));
        }
    }

    private function validateContractPayload(Request $request, bool $creating = true): array
    {
        $isSubContract = $request->filled('parent');

        return $request->validate([
            'vehicle_id' => [$isSubContract ? 'nullable' : 'required', 'nullable', 'integer', 'exists:voitures,id'],
            'parent' => ['nullable', 'integer'],
            'chauffeur' => ['required', 'integer'],
            'type_contrat' => ['required', 'integer'],
            'immatriculation' => ['required', 'string', 'max:100'],
            'vin' => ['nullable', 'string', 'max:100'],
            'montant_total' => ['required', 'numeric', 'min:0'],
            'montant_paye' => ['nullable', 'numeric', 'min:0'],
            'montant_restant' => ['nullable', 'numeric', 'min:0'],
            'montant_par_paiement' => ['required', 'numeric', 'min:0'],
            'frequence' => ['required', Rule::in(['JOURNALIER', 'HEBDOMADAIRE', 'MENSUEL'])],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'prochaine_echeance' => ['required', 'date'],
            'statut' => ['nullable', Rule::in(['ACTIF', 'SUSPENDU', 'SOLDE', 'CONTENTIEUX', 'actif', 'suspendu', 'solde', 'contentieux'])],
            'specificites' => ['nullable'],
            'apply_default_cutoff_rule' => ['nullable', 'boolean'],
            'customize_cutoff_rule' => ['nullable', 'boolean'],
            'custom_rule_is_enabled' => ['nullable', 'boolean'],
            'custom_rule_cutoff_time' => ['nullable', 'date_format:H:i'],
            'custom_rule_timezone' => ['nullable', 'string', 'max:64'],
            'custom_rule_grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'custom_rule_active_days' => ['nullable', 'array'],
            'custom_rule_active_days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'custom_rule_only_when_stopped' => ['nullable', 'boolean'],
            'custom_rule_notify_before_cutoff' => ['nullable', 'boolean'],
            'sous_contrats' => ['nullable', 'array'],
            'sous_contrats.*.type_contrat' => ['required_with:sous_contrats', 'integer'],
            'sous_contrats.*.montant_total' => ['required_with:sous_contrats', 'numeric', 'min:0'],
            'sous_contrats.*.montant_paye' => ['nullable', 'numeric', 'min:0'],
            'sous_contrats.*.montant_par_paiement' => ['required_with:sous_contrats', 'numeric', 'min:0'],
            'sous_contrats.*.frequence' => ['required_with:sous_contrats', Rule::in(['JOURNALIER', 'HEBDOMADAIRE', 'MENSUEL'])],
            'sous_contrats.*.date_debut' => ['required_with:sous_contrats', 'date'],
            'sous_contrats.*.date_fin' => ['required_with:sous_contrats', 'date'],
            'sous_contrats.*.prochaine_echeance' => ['required_with:sous_contrats', 'date'],
            'sous_contrats.*.specificites' => ['nullable'],
        ], [
            'vehicle_id.required' => 'Sélectionnez un véhicule Tracking pour créer un contrat principal.',
            'vehicle_id.exists' => 'Le véhicule Tracking sélectionné est introuvable.',
            'chauffeur.required' => 'Sélectionnez un chauffeur recouvrement.',
            'type_contrat.required' => 'Sélectionnez un type de contrat.',
            'immatriculation.required' => 'L’immatriculation est obligatoire.',
            'montant_total.required' => 'Le montant total est obligatoire.',
            'montant_par_paiement.required' => 'Le montant par paiement est obligatoire.',
            'frequence.in' => 'La fréquence doit être JOURNALIER, HEBDOMADAIRE ou MENSUEL.',
            'date_fin.after_or_equal' => 'La date de fin doit être supérieure ou égale à la date de début.',
            'sous_contrats.*.type_contrat.required_with' => 'Chaque sous-contrat doit avoir un type.',
            'sous_contrats.*.montant_total.required_with' => 'Chaque sous-contrat doit avoir un montant total.',
            'sous_contrats.*.montant_par_paiement.required_with' => 'Chaque sous-contrat doit avoir un montant par paiement.',
        ]);
    }

    private function buildRecouvrementPayload(array $validated, bool $updating): array
    {
        $payload = [
            'chauffeur' => (int) $validated['chauffeur'],
            'type_contrat' => (int) $validated['type_contrat'],
            'immatriculation' => (string) $validated['immatriculation'],
            'vin' => (string) ($validated['vin'] ?? ''),
            'montant_total' => (string) $validated['montant_total'],
            'montant_paye' => (string) ($validated['montant_paye'] ?? 0),
            'montant_par_paiement' => (string) $validated['montant_par_paiement'],
            'frequence' => mb_strtoupper((string) $validated['frequence'], 'UTF-8'),
            'date_debut' => (string) $validated['date_debut'],
            'date_fin' => (string) $validated['date_fin'],
            'prochaine_echeance' => (string) $validated['prochaine_echeance'],
            'specificites' => $this->normalizeSpecificites($validated['specificites'] ?? null),
        ];

        if (array_key_exists('parent', $validated) && $validated['parent'] !== null && $validated['parent'] !== '') {
            $payload['parent'] = (int) $validated['parent'];
        } elseif ($updating) {
            $payload['parent'] = null;
        }

        if ($updating) {
            $payload['montant_restant'] = (string) ($validated['montant_restant'] ?? $validated['montant_total']);
            $payload['statut'] = $this->mapUiContractStatusToApi($validated['statut'] ?? 'ACTIF');
        }

        $subContracts = collect($validated['sous_contrats'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'type_contrat' => (int) $row['type_contrat'],
                'montant_total' => (string) $row['montant_total'],
                'montant_paye' => (string) ($row['montant_paye'] ?? 0),
                'montant_par_paiement' => (string) $row['montant_par_paiement'],
                'frequence' => mb_strtoupper((string) $row['frequence'], 'UTF-8'),
                'date_debut' => (string) $row['date_debut'],
                'date_fin' => (string) $row['date_fin'],
                'prochaine_echeance' => (string) $row['prochaine_echeance'],
                'specificites' => $this->normalizeSpecificites($row['specificites'] ?? null),
            ])
            ->values()
            ->all();

        if (! empty($subContracts)) {
            $payload['sous_contrats'] = $subContracts;
        }

        return $payload;
    }

    private function buildSubContractPayload(array $validated): array
    {
        return [
            'type_contrat' => (int) $validated['type_contrat'],
            'montant_total' => (string) $validated['montant_total'],
            'montant_paye' => (string) ($validated['montant_paye'] ?? 0),
            'montant_par_paiement' => (string) $validated['montant_par_paiement'],
            'frequence' => mb_strtoupper((string) $validated['frequence'], 'UTF-8'),
            'date_debut' => (string) $validated['date_debut'],
            'date_fin' => (string) $validated['date_fin'],
            'prochaine_echeance' => (string) $validated['prochaine_echeance'],
            'specificites' => $this->normalizeSpecificites($validated['specificites'] ?? null),
        ];
    }

    private function applyCutoffRuleAfterContractCreation(array $validated, array $apiResponse): void
    {
        if (empty($validated['apply_default_cutoff_rule']) && empty($validated['customize_cutoff_rule'])) {
            return;
        }

        $sourceContractId = (int) (
            data_get($apiResponse, 'id')
            ?? data_get($apiResponse, 'data.id')
            ?? data_get($apiResponse, 'contrat.id')
            ?? 0
        );

        if ($sourceContractId <= 0) {
            Log::warning('[LEASE_CUTOFF_RULE_APPLY_SKIPPED_NO_CONTRACT_ID]', [
                'api_response_keys' => array_keys($apiResponse),
            ]);

            return;
        }

        $actor = auth()->user();
        $partnerId = (int) ($actor->partner_id ?: $actor->id);

        $contractLink = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('source_contract_id', $sourceContractId)
            ->first();

        if (! $contractLink) {
            Log::warning('[LEASE_CUTOFF_RULE_APPLY_SKIPPED_LINK_NOT_FOUND]', [
                'partner_id' => $partnerId,
                'source_contract_id' => $sourceContractId,
            ]);

            return;
        }

        if (! empty($validated['customize_cutoff_rule'])) {
            $this->ruleApplicationService->applyCustomRule($actor, $contractLink, $validated);

            return;
        }

        if (! empty($validated['apply_default_cutoff_rule'])) {
            $this->ruleApplicationService->applyDefaultRule($actor, $contractLink);
        }
    }

    private function syncContractLinkAfterWrite(array $validated, array $apiPayload, array $apiResponse): void
    {
        /**
         * Priorité au parent : si parent est présent, c'est un sous-contrat.
         * Même si vehicle_id est aussi envoyé par la vue pour aider Tracking,
         * on ne doit pas synchroniser ce sous-contrat comme un contrat principal.
         */
        if (! empty($validated['parent'])) {
            $this->contractLinkService->syncSubContractFromParent(
                actor: auth()->user(),
                parentContractId: (int) $validated['parent'],
                payload: $apiPayload,
                apiResponse: $apiResponse
            );

            return;
        }

        $vehicleId = (int) ($validated['vehicle_id'] ?? 0);

        if ($vehicleId > 0) {
            $this->contractLinkService->syncAfterContractWrite(
                actor: auth()->user(),
                vehicleId: $vehicleId,
                payload: $apiPayload,
                apiResponse: $apiResponse
            );
        }
    }

    private function normalizeSpecificites(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null) {
            return new stdClass();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return new stdClass();
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function mapUiContractStatusToApi(string $status): string
    {
        return match (mb_strtoupper($status, 'UTF-8')) {
            'ACTIF' => 'ACTIF',
            'SUSPENDU' => 'SUSPENDU',
            'SOLDE', 'SOLDÉ', 'TERMINE', 'TERMINÉ' => 'SOLDE',
            'CONTENTIEUX', 'RETARD' => 'CONTENTIEUX',
            default => 'ACTIF',
        };
    }

    private function extractCutoffPayload(Request $request): array
    {
        $cutoff = $request->input('cutoff', []);

        return [
            'contract_id' => $request->input('contract_id'),
            'vehicle_id' => $request->input('vehicle_id'),
            'is_enabled' => Arr::get($cutoff, 'is_enabled', $request->input('is_enabled', 0)),
            'cutoff_time' => Arr::get($cutoff, 'cutoff_time', $request->input('cutoff_time', '12:00')),
            'timezone' => Arr::get($cutoff, 'timezone', $request->input('timezone', 'Africa/Douala')),
            'grace_days' => Arr::get($cutoff, 'grace_days', $request->input('grace_days', 0)),
            'only_when_stopped' => Arr::get($cutoff, 'only_when_stopped', $request->input('only_when_stopped', 1)),
            'notify_before_cutoff' => Arr::get($cutoff, 'notify_before_cutoff', $request->input('notify_before_cutoff', 0)),
            'contract_types' => Arr::get($cutoff, 'contract_types', $request->input('contract_types', [])),
        ];
    }

    private function cutoffValidationRules(): array
    {
        return [
            'contract_id' => ['nullable', 'integer'],
            'vehicle_id' => ['required', 'integer', 'exists:voitures,id'],
            'is_enabled' => ['nullable', 'boolean'],
            'cutoff_time' => ['nullable', 'date_format:H:i'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:60'],
            'only_when_stopped' => ['nullable', 'boolean'],
            'notify_before_cutoff' => ['nullable', 'boolean'],
            'contract_types' => ['nullable', 'array'],
            'contract_types.*.type_contrat_id' => ['required_with:contract_types', 'integer'],
            'contract_types.*.type_contrat_label' => ['nullable', 'string', 'max:150'],
            'contract_types.*.is_enabled' => ['nullable', 'boolean'],
            'contract_types.*.grace_days' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }

    private function cutoffValidationMessages(): array
    {
        return [
            'vehicle_id.required' => 'Aucun véhicule Tracking n’est associé à ce contrat. Impossible d’enregistrer la règle de coupure.',
            'vehicle_id.exists' => 'Le véhicule Tracking associé est introuvable.',
            'cutoff_time.date_format' => 'L’heure de coupure doit être au format HH:MM.',
            'contract_types.*.type_contrat_id.required_with' => 'Chaque règle de type de contrat doit contenir son identifiant.',
        ];
    }

    /**
     * Reconstruit la hiérarchie métier attendue par la vue.
     *
     * Recouvrement retourne souvent les contrats à plat :
     * - contrat principal : parent = null ;
     * - sous-contrat : parent = id du contrat principal.
     *
     * La vue, elle, doit afficher une seule ligne par contrat principal,
     * avec le nombre de sous-contrats associés. Cette méthode enlève donc les
     * sous-contrats de la liste principale et les rattache à leur parent.
     */
    private function groupContractsForView(array $contracts): array
    {
        $rows = collect($contracts)
            ->filter(fn ($contract) => is_array($contract))
            ->values();

        $byId = $rows->keyBy(fn (array $contract) => $this->extractContractId($contract));

        $childrenByParent = $rows
            ->filter(fn (array $contract) => $this->extractParentContractId($contract) > 0)
            ->groupBy(fn (array $contract) => $this->extractParentContractId($contract));

        $parents = $rows
            ->filter(fn (array $contract) => $this->extractParentContractId($contract) <= 0)
            ->map(function (array $parent) use ($childrenByParent) {
                $parentId = $this->extractContractId($parent);

                $existingChildren = collect($parent['sub_contracts'] ?? $parent['sous_contrats'] ?? [])
                    ->filter(fn ($row) => is_array($row));

                $flatChildren = collect($childrenByParent->get($parentId, []))
                    ->map(fn (array $child) => $this->normalizeFlatSubContractForView($child, $parent));

                $mergedChildren = $existingChildren
                    ->merge($flatChildren)
                    ->filter(fn ($child) => is_array($child) && $this->extractContractId($child) > 0)
                    ->unique(fn (array $child) => $this->extractContractId($child))
                    ->values()
                    ->all();

                $parent['sub_contracts'] = $mergedChildren;
                $parent['sous_contrats'] = $mergedChildren;

                return $parent;
            })
            ->values();

        /**
         * Si recouvrement renvoie un sous-contrat dont le parent n'est pas dans
         * la page courante, on ne le transforme pas en contrat principal normal.
         * On l’affiche en dernier comme ligne orpheline, avec un marqueur clair,
         * afin de ne pas masquer une incohérence de pagination ou de filtre API.
         */
        $orphans = $rows
            ->filter(function (array $contract) use ($byId) {
                $parentId = $this->extractParentContractId($contract);

                return $parentId > 0 && ! $byId->has($parentId);
            })
            ->map(function (array $contract) {
                $contract['is_orphan_sub_contract'] = true;
                $contract['sub_contracts'] = [];
                $contract['sous_contrats'] = [];

                return $contract;
            })
            ->values();

        return $parents->merge($orphans)->values()->all();
    }

    /**
     * Ajoute les règles de coupure Tracking existantes aux contrats récupérés.
     * Les règles sont volontairement portées par le véhicule, pas par le contrat
     * recouvrement : un même véhicule peut dire que Téléphone coupe, Parapluie ne
     * coupe pas, Moto coupe, etc.
     */
    private function attachCutoffPoliciesToContracts(array $contracts, array $contractTypes): array
    {
        if (! auth()->check()) {
            return $contracts;
        }

        $vehicleIds = collect($contracts)
            ->pluck('vehicle_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            return $contracts;
        }

        $policiesByVehicle = $this->cutoffRuleService->getPolicyMapForVehicles(
            user: auth()->user(),
            vehicleIds: $vehicleIds,
            contractTypes: $contractTypes
        );

        return collect($contracts)
            ->map(function (array $contract) use ($policiesByVehicle) {
                $vehicleId = (int) ($contract['vehicle_id'] ?? 0);

                if ($vehicleId > 0 && isset($policiesByVehicle[$vehicleId])) {
                    $contract['cutoff'] = $policiesByVehicle[$vehicleId];
                }

                return $contract;
            })
            ->values()
            ->all();
    }

    private function normalizeFlatSubContractForView(array $child, array $parent): array
    {
        $raw = $child['raw'] ?? $child;

        return [
            'id' => $this->extractContractId($child),
            'parent' => $this->extractParentContractId($child) ?: $this->extractContractId($parent),
            'chauffeur_id' => $child['chauffeur_id'] ?? $raw['chauffeur'] ?? $parent['chauffeur_id'] ?? null,
            'vehicle_id' => $child['vehicle_id'] ?? $parent['vehicle_id'] ?? null,
            'vehicule' => $child['vehicule'] ?? $raw['immatriculation'] ?? $parent['vehicule'] ?? null,
            'vin' => $child['vin'] ?? $raw['vin'] ?? $parent['vin'] ?? '',
            'type_contrat' => (int) ($child['type_contrat'] ?? $raw['type_contrat'] ?? 0),
            'type_label' => $child['type_label'] ?? $child['type_contrat_label'] ?? $raw['type_contrat_label'] ?? 'Sous-contrat',
            'type_contrat_label' => $child['type_contrat_label'] ?? $child['type_label'] ?? $raw['type_contrat_label'] ?? 'Sous-contrat',
            'montant_total' => (float) ($child['montant_total'] ?? $raw['montant_total'] ?? 0),
            'montant_restant' => (float) ($child['montant_restant'] ?? $raw['montant_restant'] ?? 0),
            'total_paye' => (float) ($child['total_paye'] ?? $raw['montant_verse'] ?? 0),
            'montant_par_paiement' => (float) ($child['montant_par_paiement'] ?? $raw['montant_par_paiement'] ?? 0),
            'frequence' => $child['frequence'] ?? $raw['frequence'] ?? '',
            'date_debut' => $child['date_debut'] ?? $raw['date_debut'] ?? null,
            'date_fin' => $child['date_fin'] ?? $raw['date_fin'] ?? null,
            'prochaine_echeance' => $child['prochaine_echeance'] ?? $raw['prochaine_echeance'] ?? null,
            'statut' => $child['statut'] ?? mb_strtolower((string) ($raw['statut'] ?? 'ACTIF'), 'UTF-8'),
            'specificites' => $child['specificites'] ?? $raw['specificites'] ?? null,
            'raw' => $raw,
        ];
    }

    private function extractContractId(array $contract): int
    {
        return (int) (
            $contract['id']
            ?? $contract['source_contract_id']
            ?? $contract['source_contrat_id']
            ?? data_get($contract, 'raw.id')
            ?? 0
        );
    }

    private function extractParentContractId(array $contract): int
    {
        return (int) (
            $contract['parent']
            ?? $contract['source_parent_contract_id']
            ?? data_get($contract, 'raw.parent')
            ?? data_get($contract, 'raw.parent.id')
            ?? 0
        );
    }

    private function normalizeChauffeursForView(array $chauffeurs): array
    {
        return collect($chauffeurs)
            ->map(function (array $chauffeur) {
                $id = $chauffeur['id'] ?? null;
                $label = $chauffeur['label']
                    ?? $chauffeur['nom_complet']
                    ?? trim(($chauffeur['firstName'] ?? '') . ' ' . ($chauffeur['lastName'] ?? ''))
                    ?: ('Chauffeur #' . $id);

                return [
                    'id' => $id,
                    'label' => $label,
                    'email' => $chauffeur['email'] ?? null,
                    'phone' => $chauffeur['phone'] ?? $chauffeur['telephone'] ?? null,
                ];
            })
            ->filter(fn ($row) => ! empty($row['id']))
            ->values()
            ->all();
    }

    private function attachTrackingVehiclesToContracts(array $contracts, array $vehicles): array
    {
        $vehiclesByImmatriculation = collect($vehicles)
            ->filter(fn (array $vehicle) => ! empty($vehicle['immatriculation']))
            ->keyBy(fn (array $vehicle) => $this->normalizeImmatriculation($vehicle['immatriculation']));

        return collect($contracts)
            ->map(function (array $contract) use ($vehiclesByImmatriculation) {
                $contractImmatriculation = $contract['vehicule']
                    ?? $contract['immatriculation']
                    ?? data_get($contract, 'raw.immatriculation')
                    ?? null;

                $trackingVehicle = $vehiclesByImmatriculation->get($this->normalizeImmatriculation($contractImmatriculation));

                if (! $trackingVehicle) {
                    return $contract;
                }

                $contract['vehicle_id'] = $trackingVehicle['vehicle_id'] ?? $trackingVehicle['id'] ?? $contract['vehicle_id'] ?? null;
                $contract['vehicule_id'] = $contract['vehicle_id'];
                $contract['vehicule'] = $contract['vehicule'] ?? $trackingVehicle['immatriculation'] ?? null;
                $contract['vin'] = $contract['vin'] ?: ($trackingVehicle['vin'] ?? '');
                $contract['tracking_vehicle'] = $trackingVehicle;

                return $contract;
            })
            ->values()
            ->all();
    }

    private function normalizeImmatriculation(?string $value): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', trim((string) $value)), 'UTF-8');
    }

    private function reportLeaseError(string $event, Throwable $e, array $context = []): void
    {
        report($e);

        Log::error("[{$event}]", [
            ...$context,
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
            'lease_request_id' => $e instanceof LeaseApiException ? $e->requestId : null,
            'status' => $e instanceof LeaseApiException ? $e->status : null,
            'api_message' => $e instanceof LeaseApiException ? $e->apiMessage : null,
        ]);
    }

    private function clientErrorMessage(Throwable $e, string $fallback): string
    {
        if ($e instanceof LeaseApiException) {
            $message = $e->userMessage();

            return app()->environment('local')
                ? $message . ' Code debug : ' . $e->requestId
                : $message;
        }

        return app()->environment('local')
            ? $fallback . ' Détail développeur : ' . $e->getMessage()
            : $fallback;
    }
}
