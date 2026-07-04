<?php

namespace App\Http\Controllers\Leases;

use App\Exceptions\LeaseApiException;
use App\Http\Controllers\Controller;
use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffDefaultRule;
use App\Services\Leases\LeaseContractCutoffRuleApplicationService;
use App\Services\Leases\LeaseContractLinkService;
use App\Services\Leases\LeaseCutoffRuleService;
use App\Services\Leases\PartnerLeaseApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
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
        private readonly LeaseContractLinkService $contractLinkService,
        private readonly LeaseContractCutoffRuleApplicationService $ruleApplicationService,
        private readonly LeaseCutoffRuleService $cutoffRuleService
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
        $defaultCutoffRulesForView = [];

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
            $contracts = $this->inheritParentVehicleOnSubContracts($contracts);
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
            if (auth()->check()) {
                $defaultCutoffRulesForView = LeaseCutoffDefaultRule::query()
                    ->where('partner_id', (int) (auth()->user()->partner_id ?: auth()->id()))
                    ->get()
                    ->mapWithKeys(fn (LeaseCutoffDefaultRule $rule) => [
                        (int) $rule->type_contrat_id => [
                            'type_contrat_id' => (int) $rule->type_contrat_id,
                            'is_enabled' => (bool) $rule->is_enabled,
                            'cutoff_time' => $rule->cutoff_time,
                            'grace_days' => (int) $rule->grace_days,
                            'active_days' => $this->normalizeActiveDays($rule->active_days ?? []),
                        ],
                    ])
                    ->all();
            }
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_INDEX_FETCH_DEFAULT_RULES_FAILED', $e);
            $pageWarnings[] = 'Les règles par défaut sont indisponibles : le calcul visuel des dates utilisera lundi à samedi.';
        }

        try {
            if (! empty($contracts) && auth()->check()) {
                /**
                 * Synchroniser avant d'attacher les règles : sinon les contrats ou sous-contrats
                 * fraîchement remontés par Recouvrement n'ont pas encore leur LeaseContractLink,
                 * donc l'interface affiche une coupure absente alors que la ligne locale vient
                 * juste d'être créée.
                 */
                $this->contractLinkService->syncFetchedContracts(auth()->user(), $contracts);
            }
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_LINK_SYNC_FETCHED_FAILED', $e);
            $pageWarnings[] = 'Les contrats sont affichés, mais la synchronisation locale Tracking n’a pas pu être finalisée.';
        }

        try {
            if (! empty($contracts) && ! empty($contractTypes) && auth()->check()) {
                $contracts = $this->attachCutoffPoliciesToContracts($contracts, $contractTypes);
            }
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CONTRACT_ATTACH_CUTOFF_POLICIES_FAILED', $e);
            $pageWarnings[] = 'Les contrats sont affichés, mais les règles de coupure existantes n’ont pas pu être chargées.';
        }

        $contractsForView = $this->groupContractsForView($contracts);

        return view('leases.contrat', [
            'contracts' => $contractsForView,
            'chauffeurs_list' => $chauffeursList,
            'vehicules_list' => $vehiculesList,
            'contractTypesFromApi' => $contractTypes,
            'pageError' => null,
            'pageWarnings' => $pageWarnings,
            'defaultCutoffRulesForView' => $defaultCutoffRulesForView,
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

        $validated = $this->prepareContractPayloadForPersistence($this->validateContractPayload($request, creating: true));
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

            $created = $this->refreshCreatedContractTreeForRuleApplication($validated, $created);

            $ruleWarning = $this->tryApplyCutoffRuleAfterContractCreation($validated, $created);

            $redirect = redirect()
                ->route('lease.contrat')
                ->with('success', $isSubContract
                    ? 'Sous-contrat enregistré avec succès.'
                    : 'Contrat enregistré avec succès.');

            return $ruleWarning ? $redirect->with('warning', $ruleWarning) : $redirect;
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

        $validated = $this->prepareContractPayloadForPersistence($this->validateContractPayload($request, creating: false));
        $apiPayload = $this->buildRecouvrementPayload($validated, updating: true);

        try {
            $response = $this->leaseApiService->updateContract($id, $apiPayload);
            $this->syncContractLinkAfterWrite($validated, $apiPayload, $response ?: ['id' => $id, ...$apiPayload]);
            $ruleWarning = $this->tryApplyCutoffRuleAfterContractCreation($validated, $response ?: ['id' => $id, ...$apiPayload]);

            $redirect = redirect()
                ->route('lease.contrat')
                ->with('success', 'Contrat modifié avec succès.');

            return $ruleWarning ? $redirect->with('warning', $ruleWarning) : $redirect;
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
     * Sauvegarde la règle de coupure du contrat/sous-contrat réel affiché.
     *
     * Important : on ne sauvegarde plus une règle abstraite "véhicule + type".
     * Le POST doit cibler le source_contract_id recouvrement, puis on retrouve
     * le LeaseContractLink exact et on enregistre lease_cutoff_contract_rules.
     */
    public function updateCutoffPolicy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contract_id' => ['required', 'integer'],
            'cutoff.is_enabled' => ['nullable', 'boolean'],
            'cutoff.cutoff_time' => ['nullable', 'date_format:H:i'],
            'cutoff.timezone' => ['nullable', 'string', 'max:64'],
            'cutoff.grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'cutoff.active_days' => ['nullable', 'array'],
            'cutoff.active_days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'cutoff.only_when_stopped' => ['nullable', 'boolean'],
            'cutoff.notify_before_cutoff' => ['nullable', 'boolean'],
        ], [
            'contract_id.required' => 'Aucun contrat réel n’est sélectionné pour la règle de coupure.',
            'cutoff.cutoff_time.date_format' => 'L’heure de coupure doit être au format HH:MM.',
        ]);

        $actor = auth()->user();
        $partnerId = (int) ($actor->partner_id ?: $actor->id);

        $contractLink = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('source_contract_id', (int) $validated['contract_id'])
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->first();

        if (! $contractLink) {
            return back()->with('error', 'Impossible d’enregistrer la règle : le contrat/sous-contrat n’est pas encore synchronisé localement. Rafraîchissez la page puis réessayez.');
        }

        $cutoff = $validated['cutoff'] ?? [];

        $this->cutoffRuleService->saveRules($actor, [[
            'contract_rules' => [[
                'contract_link_id' => $contractLink->id,
                'is_enabled' => (bool) ($cutoff['is_enabled'] ?? false),
                'cutoff_time' => $cutoff['cutoff_time'] ?? null,
                'timezone' => $cutoff['timezone'] ?? 'Africa/Douala',
                'grace_days' => (int) ($cutoff['grace_days'] ?? 0),
                'active_days' => $cutoff['active_days'] ?? ['monday','tuesday','wednesday','thursday','friday','saturday'],
                'only_when_stopped' => (bool) ($cutoff['only_when_stopped'] ?? true),
                'notify_before_cutoff' => (bool) ($cutoff['notify_before_cutoff'] ?? false),
            ]],
        ]]);

        return redirect()
            ->route('lease.contrat')
            ->with('success', 'Règle de coupure du contrat mise à jour.');
    }

    /**
     * Applique une règle en lot uniquement aux contrats réels sélectionnés.
     * Le formulaire doit fournir contract_ids[] ; on résout ensuite les links exacts.
     */
    public function bulkUpdateCutoffPolicies(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contract_ids' => ['required', 'array', 'min:1'],
            'contract_ids.*' => ['integer'],
            'cutoff.is_enabled' => ['nullable', 'boolean'],
            'cutoff.cutoff_time' => ['nullable', 'date_format:H:i'],
            'cutoff.timezone' => ['nullable', 'string', 'max:64'],
            'cutoff.grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'cutoff.active_days' => ['nullable', 'array'],
            'cutoff.active_days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'cutoff.only_when_stopped' => ['nullable', 'boolean'],
            'cutoff.notify_before_cutoff' => ['nullable', 'boolean'],
        ]);

        $actor = auth()->user();
        $partnerId = (int) ($actor->partner_id ?: $actor->id);
        $cutoff = $validated['cutoff'] ?? [];

        $links = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->whereIn('source_contract_id', collect($validated['contract_ids'])->map(fn ($id) => (int) $id)->all())
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->get();

        if ($links->isEmpty()) {
            return back()->with('error', 'Aucun contrat sélectionné n’est synchronisé localement. Rafraîchissez la page puis réessayez.');
        }

        $this->cutoffRuleService->saveRules($actor, [[
            'contract_rules' => $links->map(fn (LeaseContractLink $link) => [
                'contract_link_id' => $link->id,
                'is_enabled' => (bool) ($cutoff['is_enabled'] ?? false),
                'cutoff_time' => $cutoff['cutoff_time'] ?? null,
                'timezone' => $cutoff['timezone'] ?? 'Africa/Douala',
                'grace_days' => (int) ($cutoff['grace_days'] ?? 0),
                'active_days' => $cutoff['active_days'] ?? ['monday','tuesday','wednesday','thursday','friday','saturday'],
                'only_when_stopped' => (bool) ($cutoff['only_when_stopped'] ?? true),
                'notify_before_cutoff' => (bool) ($cutoff['notify_before_cutoff'] ?? false),
            ])->all(),
        ]]);

        return redirect()
            ->route('lease.contrat')
            ->with('success', $links->count() . ' règle(s) de coupure mise(s) à jour.');
    }

    private function validateContractPayload(Request $request, bool $creating = true): array
    {
        $isSubContract = $request->filled('parent');
        $specificitesRule = $this->specificitesJsonValidationRule();

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
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'prochaine_echeance' => ['required', 'date'],
            'statut' => ['nullable', Rule::in(['ACTIF', 'SUSPENDU', 'SOLDE', 'CONTENTIEUX', 'actif', 'suspendu', 'solde', 'contentieux'])],
            'specificites' => ['nullable', $specificitesRule],
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
            'sous_contrats.*.date_fin' => ['nullable', 'date'],
            'sous_contrats.*.prochaine_echeance' => ['required_with:sous_contrats', 'date'],
            'sous_contrats.*.specificites' => ['nullable', $specificitesRule],
            'sous_contrats.*.apply_default_cutoff_rule' => ['nullable', 'boolean'],
            'sous_contrats.*.customize_cutoff_rule' => ['nullable', 'boolean'],
            'sous_contrats.*.custom_rule_is_enabled' => ['nullable', 'boolean'],
            'sous_contrats.*.custom_rule_cutoff_time' => ['nullable', 'date_format:H:i'],
            'sous_contrats.*.custom_rule_timezone' => ['nullable', 'string', 'max:64'],
            'sous_contrats.*.custom_rule_grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'sous_contrats.*.custom_rule_active_days' => ['nullable', 'array'],
            'sous_contrats.*.custom_rule_active_days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'sous_contrats.*.custom_rule_only_when_stopped' => ['nullable', 'boolean'],
            'sous_contrats.*.custom_rule_notify_before_cutoff' => ['nullable', 'boolean'],
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

    /**
     * Normalise les montants puis calcule date_fin côté serveur.
     * La vue le fait aussi pour aider l'utilisateur, mais le backend reste
     * l'autorité afin d'éviter une date manuelle incohérente.
     */
    private function prepareContractPayloadForPersistence(array $validated): array
    {
        $validated['montant_paye'] = (string) ($validated['montant_paye'] ?? 0);
        $validated['montant_restant'] = (string) max(
            0,
            (float) ($validated['montant_total'] ?? 0) - (float) ($validated['montant_paye'] ?? 0)
        );

        $mainActiveDays = $this->resolveActiveDaysForDateCalculation(
            typeContratId: (int) $validated['type_contrat'],
            payload: $validated
        );

        $validated['date_fin'] = $this->calculateEndDate(
            dateDebut: (string) $validated['date_debut'],
            montantTotal: (float) $validated['montant_total'],
            montantPaye: (float) ($validated['montant_paye'] ?? 0),
            montantParPaiement: (float) $validated['montant_par_paiement'],
            frequence: (string) $validated['frequence'],
            activeDays: $mainActiveDays
        );

        $validated['sous_contrats'] = collect($validated['sous_contrats'] ?? [])
            ->filter(fn ($row) => is_array($row) && ! empty($row['type_contrat']))
            ->map(function (array $row) {
                $row['montant_paye'] = (string) ($row['montant_paye'] ?? 0);
                $row['date_fin'] = $this->calculateEndDate(
                    dateDebut: (string) ($row['date_debut'] ?? now()->toDateString()),
                    montantTotal: (float) ($row['montant_total'] ?? 0),
                    montantPaye: (float) ($row['montant_paye'] ?? 0),
                    montantParPaiement: (float) ($row['montant_par_paiement'] ?? 0),
                    frequence: (string) ($row['frequence'] ?? 'JOURNALIER'),
                    activeDays: $this->resolveActiveDaysForDateCalculation(
                        typeContratId: (int) ($row['type_contrat'] ?? 0),
                        payload: $row
                    )
                );

                return $row;
            })
            ->values()
            ->all();

        return $validated;
    }

    private function calculateEndDate(
        string $dateDebut,
        float $montantTotal,
        float $montantPaye,
        float $montantParPaiement,
        string $frequence,
        array $activeDays = []
    ): string {
        $start = Carbon::parse($dateDebut)->startOfDay();
        $remaining = max(0, $montantTotal - $montantPaye);
        $activeDays = $this->normalizeActiveDays($activeDays ?: $this->fallbackActiveDays());

        if ($remaining <= 0 || $montantParPaiement <= 0) {
            return $this->moveToNextActiveDay($start, $activeDays)->toDateString();
        }

        $numberOfPayments = (int) ceil($remaining / $montantParPaiement);
        $periodsToAdd = max(0, $numberOfPayments - 1);

        return match (mb_strtoupper($frequence, 'UTF-8')) {
            'JOURNALIER' => $this->addActivePaymentDays($start, $periodsToAdd, $activeDays)->toDateString(),
            'HEBDOMADAIRE' => $this->moveToNextActiveDay($start->copy()->addWeeks($periodsToAdd), $activeDays)->toDateString(),
            'MENSUEL' => $this->moveToNextActiveDay($start->copy()->addMonthsNoOverflow($periodsToAdd), $activeDays)->toDateString(),
            default => $this->addActivePaymentDays($start, $periodsToAdd, $activeDays)->toDateString(),
        };
    }

    private function addActivePaymentDays(Carbon $start, int $periodsToAdd, array $activeDays): Carbon
    {
        $date = $this->moveToNextActiveDay($start, $activeDays);
        $added = 0;

        while ($added < $periodsToAdd) {
            $date->addDay();

            if ($this->isActiveDay($date, $activeDays)) {
                $added++;
            }
        }

        return $date;
    }

    private function moveToNextActiveDay(Carbon $date, array $activeDays): Carbon
    {
        $date = $date->copy();
        $guard = 0;

        while (! $this->isActiveDay($date, $activeDays) && $guard < 14) {
            $date->addDay();
            $guard++;
        }

        return $date;
    }

    private function isActiveDay(Carbon $date, array $activeDays): bool
    {
        return in_array(strtolower($date->englishDayOfWeek), $activeDays, true);
    }

    private function resolveActiveDaysForDateCalculation(int $typeContratId, array $payload): array
    {
        if (! empty($payload['customize_cutoff_rule'])) {
            return $this->normalizeActiveDays($payload['custom_rule_active_days'] ?? []);
        }

        return $this->resolveDefaultActiveDaysForType($typeContratId);
    }

    private function resolveDefaultActiveDaysForType(int $typeContratId): array
    {
        if ($typeContratId <= 0 || ! auth()->check()) {
            return $this->fallbackActiveDays();
        }

        $partnerId = (int) (auth()->user()->partner_id ?: auth()->id());
        $rule = LeaseCutoffDefaultRule::query()
            ->where('partner_id', $partnerId)
            ->where('type_contrat_id', $typeContratId)
            ->first();

        return $this->normalizeActiveDays($rule?->active_days ?? []) ?: $this->fallbackActiveDays();
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

    private function fallbackActiveDays(): array
    {
        return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
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


    /**
     * Après une création avec sous-contrats, l'API Recouvrement peut répondre
     * uniquement avec le contrat principal. Dans ce cas, les sous-contrats existent
     * déjà côté Recouvrement, mais leurs identifiants ne sont pas encore dans la
     * réponse locale ; l'application automatique des règles par défaut les ignore
     * alors et l'interface affiche "Inactif".
     *
     * Cette méthode recharge immédiatement les contrats du partenaire, synchronise
     * les liens Tracking, puis rattache les sous-contrats fraîchement créés à la
     * réponse utilisée pour appliquer les règles de coupure.
     */
    private function refreshCreatedContractTreeForRuleApplication(array $validated, array $apiResponse): array
    {
        if (! auth()->check()) {
            return $apiResponse;
        }

        /**
         * Cas 1 : création d'un sous-contrat depuis le formulaire dédié.
         * Certaines réponses Recouvrement ne renvoient pas immédiatement l'id du
         * sous-contrat créé. Dans ce cas, on recharge les contrats du parent pour
         * retrouver le vrai source_contract_id avant d'appliquer la règle.
         */
        if (! empty($validated['parent']) && empty($validated['sous_contrats'])) {
            if ($this->extractContractId($apiResponse) > 0) {
                return $apiResponse;
            }

            try {
                $freshContracts = $this->leaseApiService->fetchContracts([]);
                $freshContracts = $this->inheritParentVehicleOnSubContracts($freshContracts);
                $this->contractLinkService->syncFetchedContracts(auth()->user(), $freshContracts);

                $createdSubContract = collect($freshContracts)
                    ->filter(fn ($contract) => is_array($contract))
                    ->filter(fn (array $contract) => $this->extractParentContractId($contract) === (int) $validated['parent'])
                    ->sortByDesc(fn (array $contract) => $this->extractContractId($contract))
                    ->first(function (array $contract) use ($validated) {
                        return (int) ($contract['type_contrat'] ?? data_get($contract, 'raw.type_contrat') ?? 0) === (int) $validated['type_contrat']
                            && (string) ($contract['date_debut'] ?? data_get($contract, 'raw.date_debut') ?? '') === (string) $validated['date_debut']
                            && (float) ($contract['montant_total'] ?? data_get($contract, 'raw.montant_total') ?? 0) === (float) $validated['montant_total'];
                    });

                if (is_array($createdSubContract) && $this->extractContractId($createdSubContract) > 0) {
                    return $createdSubContract;
                }
            } catch (Throwable $e) {
                $this->reportLeaseError('LEASE_CREATED_SUB_REFRESH_FAILED', $e, [
                    'parent_contract_id' => (int) $validated['parent'],
                ]);
            }

            return $apiResponse;
        }

        if (empty($validated['sous_contrats'])) {
            return $apiResponse;
        }

        $mainId = $this->extractContractId($apiResponse);

        if ($mainId <= 0) {
            Log::warning('[LEASE_CREATED_TREE_REFRESH_SKIPPED_NO_MAIN_ID]', [
                'api_response_keys' => array_keys($apiResponse),
            ]);

            return $apiResponse;
        }

        try {
            /**
             * Même quand Recouvrement renvoie déjà les sous-contrats dans la réponse
             * de création, syncAfterContractWrite() ne synchronise que le contrat
             * principal. On recharge donc systématiquement l'arbre complet afin de
             * créer les LeaseContractLink des sous-contrats avant d'appliquer leurs
             * règles de coupure.
             */
            $freshContracts = $this->leaseApiService->fetchContracts([]);
            $freshContracts = $this->inheritParentVehicleOnSubContracts($freshContracts);
            $this->contractLinkService->syncFetchedContracts(auth()->user(), $freshContracts);

            $children = collect($freshContracts)
                ->filter(fn ($contract) => is_array($contract))
                ->filter(fn (array $contract) => $this->extractParentContractId($contract) === $mainId)
                ->values()
                ->all();

            if (! empty($children)) {
                /*
                 * Point critique : syncFetchedContracts() peut ignorer ou retarder
                 * la création du LeaseContractLink des sous-contrats quand ils sont
                 * renvoyés à plat par Recouvrement ou sans immatriculation propre.
                 * Or applyDefaultRule() ne peut cibler qu'un LeaseContractLink local.
                 * On force donc ici la synchronisation de chaque sous-contrat avec
                 * son parent avant l'application automatique de la règle.
                 */
                $this->syncCreatedSubContractLinksForRuleApplication($mainId, $children);


                $existingChildren = collect($this->collectSubContractsFromApiResponse($apiResponse))
                    ->filter(fn ($row) => is_array($row));

                $mergedChildren = $existingChildren
                    ->merge($children)
                    ->unique(fn (array $child) => $this->extractContractId($child))
                    ->values()
                    ->all();

                $apiResponse['sous_contrats'] = $mergedChildren;
                $apiResponse['sub_contracts'] = $mergedChildren;

                Log::info('[LEASE_CREATED_TREE_REFRESHED_FOR_RULES]', [
                    'main_contract_id' => $mainId,
                    'sub_contract_ids' => collect($mergedChildren)
                        ->map(fn (array $child) => $this->extractContractId($child))
                        ->filter()
                        ->values()
                        ->all(),
                ]);
            }
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CREATED_TREE_REFRESH_FAILED', $e, [
                'main_contract_id' => $mainId,
            ]);
        }

        return $apiResponse;
    }


    /**
     * La création/modification du contrat ne doit jamais être bloquée par un
     * problème de règle de coupure. On logge l'incident et on laisse l'utilisateur
     * corriger depuis les détails du contrat ou les settings.
     */
    private function tryApplyCutoffRuleAfterContractCreation(array $validated, array $apiResponse): ?string
    {
        try {
            $this->applyCutoffRuleAfterContractCreation($validated, $apiResponse);

            return null;
        } catch (Throwable $e) {
            $this->reportLeaseError('LEASE_CUTOFF_RULE_APPLY_AFTER_WRITE_FAILED', $e, [
                'api_response' => $apiResponse,
                'validated_keys' => array_keys($validated),
            ]);

            return 'Le contrat a été enregistré, mais la règle de coupure n’a pas pu être appliquée automatiquement. Vous pouvez la vérifier depuis les détails du contrat.';
        }
    }

    private function normalizeCutoffPayloadForRuleApplication(array $payload): array
    {
        $payload['custom_rule_is_enabled'] = array_key_exists('custom_rule_is_enabled', $payload)
            ? (bool) $payload['custom_rule_is_enabled']
            : true;
        $payload['custom_rule_timezone'] = $payload['custom_rule_timezone'] ?? 'Africa/Douala';
        $payload['custom_rule_grace_days'] = (int) ($payload['custom_rule_grace_days'] ?? 0);
        $payload['custom_rule_active_days'] = $this->normalizeActiveDays($payload['custom_rule_active_days'] ?? []) ?: $this->fallbackActiveDays();
        $payload['custom_rule_only_when_stopped'] = array_key_exists('custom_rule_only_when_stopped', $payload)
            ? (bool) $payload['custom_rule_only_when_stopped']
            : true;
        $payload['custom_rule_notify_before_cutoff'] = (bool) ($payload['custom_rule_notify_before_cutoff'] ?? false);

        return $payload;
    }

    private function subCutoffPayloadsBySourceContractId(array $validated, array $apiResponse): array
    {
        $subRows = collect($validated['sous_contrats'] ?? [])
            ->filter(fn ($row) => is_array($row))
            ->values();

        if ($subRows->isEmpty()) {
            return [];
        }

        $apiChildren = collect($this->collectSubContractsFromApiResponse($apiResponse))
            ->filter(fn ($row) => is_array($row))
            ->values();

        $mapped = [];

        foreach ($apiChildren as $index => $child) {
            $sourceId = $this->extractContractId($child);
            $row = $subRows->get($index);

            if (! is_array($row)) {
                $row = $subRows->first(function (array $candidate) use ($child) {
                    return (int) ($candidate['type_contrat'] ?? 0) === (int) ($child['type_contrat'] ?? data_get($child, 'raw.type_contrat') ?? 0)
                        && (string) ($candidate['date_debut'] ?? '') === (string) ($child['date_debut'] ?? data_get($child, 'raw.date_debut') ?? '')
                        && (float) ($candidate['montant_total'] ?? 0) === (float) ($child['montant_total'] ?? data_get($child, 'raw.montant_total') ?? 0);
                });
            }

            if ($sourceId <= 0 || ! is_array($row)) {
                continue;
            }

            $mapped[$sourceId] = $row;
        }

        return $mapped;
    }

    private function collectSubContractsFromApiResponse(array $apiResponse): array
    {
        $children = [];

        foreach (['sous_contrats', 'sub_contracts', 'subContracts', 'children', 'data.sous_contrats', 'data.sub_contracts', 'data.subContracts', 'data.children', 'contrat.sous_contrats', 'contrat.sub_contracts', 'contrat.subContracts', 'contrat.children'] as $path) {
            $rows = data_get($apiResponse, $path, []);

            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (is_array($row)) {
                    $children[] = $row;
                }
            }
        }

        return collect($children)
            ->unique(fn (array $row) => $this->extractContractId($row) ?: spl_object_id((object) $row))
            ->values()
            ->all();
    }

    private function applyCutoffRuleAfterContractCreation(array $validated, array $apiResponse): void
    {
        $hasMainRuleChoice = ! empty($validated['apply_default_cutoff_rule']) || ! empty($validated['customize_cutoff_rule']);
        $hasSubRuleChoice = collect($validated['sous_contrats'] ?? [])->contains(function ($row) {
            return is_array($row)
                && (! empty($row['apply_default_cutoff_rule']) || ! empty($row['customize_cutoff_rule']));
        });

        if (! $hasMainRuleChoice && ! $hasSubRuleChoice) {
            return;
        }

        $sourceContractIds = $this->collectContractIdsFromApiResponse($apiResponse);

        if (empty($sourceContractIds)) {
            Log::warning('[LEASE_CUTOFF_RULE_APPLY_SKIPPED_NO_CONTRACT_ID]', [
                'api_response_keys' => array_keys($apiResponse),
            ]);

            return;
        }

        $actor = auth()->user();
        $partnerId = (int) ($actor->partner_id ?: $actor->id);

        /*
         * Sécurité supplémentaire : si les sous-contrats ont été créés simultanément
         * et que leur lien local n'existe pas encore, on le crée juste avant la
         * recherche des liens. Cela rend l'application de la règle idempotente.
         */
        $mainIdForSubSync = $this->extractContractId($apiResponse);
        $childrenForSubSync = $this->collectSubContractsFromApiResponse($apiResponse);
        if ($mainIdForSubSync > 0 && ! empty($childrenForSubSync)) {
            $this->syncCreatedSubContractLinksForRuleApplication($mainIdForSubSync, $childrenForSubSync);
        }

        $contractLinks = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->whereIn('source_contract_id', $sourceContractIds)
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', '!=', 'DELETED');
            })
            ->orderByRaw("CASE WHEN contract_kind = 'MAIN' THEN 0 ELSE 1 END")
            ->get()
            ->keyBy('source_contract_id');

        if ($contractLinks->isEmpty()) {
            Log::warning('[LEASE_CUTOFF_RULE_APPLY_SKIPPED_LINK_NOT_FOUND]', [
                'partner_id' => $partnerId,
                'source_contract_ids' => $sourceContractIds,
            ]);

            return;
        }

        $subPayloadsBySourceId = $this->subCutoffPayloadsBySourceContractId($validated, $apiResponse);
        $isDirectSubContractWrite = ! empty($validated['parent']) && empty($validated['sous_contrats']);

        foreach ($sourceContractIds as $sourceContractId) {
            /** @var LeaseContractLink|null $contractLink */
            $contractLink = $contractLinks->get((int) $sourceContractId);

            if (! $contractLink) {
                continue;
            }

            $isMainContractInThisWrite = ! $isDirectSubContractWrite
                && strtoupper((string) $contractLink->contract_kind) === 'MAIN';

            $rulePayload = ($isMainContractInThisWrite || $isDirectSubContractWrite)
                ? $validated
                : ($subPayloadsBySourceId[(int) $contractLink->source_contract_id] ?? []);

            if (! empty($rulePayload['customize_cutoff_rule'])) {
                $this->ruleApplicationService->applyCustomRule(
                    $actor,
                    $contractLink,
                    $this->normalizeCutoffPayloadForRuleApplication($rulePayload)
                );
                continue;
            }

            if (! empty($rulePayload['apply_default_cutoff_rule']) || (! $isMainContractInThisWrite && empty($rulePayload))) {
                $this->ruleApplicationService->applyDefaultRule($actor, $contractLink);
            }
        }
    }

    /**
     * Garantit l'existence des LeaseContractLink des sous-contrats créés en même temps
     * que le contrat principal.
     *
     * Sans cette étape, la règle du contrat principal s'applique correctement, mais la
     * règle du sous-contrat est ignorée parce qu'aucune ligne locale ne peut être ciblée.
     */
    private function syncCreatedSubContractLinksForRuleApplication(int $parentContractId, array $children): void
    {
        if (! auth()->check() || $parentContractId <= 0 || empty($children)) {
            return;
        }

        foreach ($children as $child) {
            if (! is_array($child) || $this->extractContractId($child) <= 0) {
                continue;
            }

            try {
                $this->contractLinkService->syncSubContractFromParent(
                    actor: auth()->user(),
                    parentContractId: $parentContractId,
                    payload: $child,
                    apiResponse: $child
                );
            } catch (Throwable $e) {
                $this->reportLeaseError('LEASE_CREATED_SUB_LINK_SYNC_FOR_RULE_FAILED', $e, [
                    'parent_contract_id' => $parentContractId,
                    'sub_contract_id' => $this->extractContractId($child),
                    'type_contrat' => $child['type_contrat'] ?? data_get($child, 'raw.type_contrat'),
                ]);
            }
        }
    }

    /**
     * Extrait l'identifiant du contrat principal et, quand l'API les retourne,
     * ceux des sous-contrats créés en même temps. Cela permet d'appliquer les
     * règles par défaut aux sous-contrats réels sans inventer de ligne.
     */
    private function collectContractIdsFromApiResponse(array $apiResponse): array
    {
        $ids = [];

        $mainId = data_get($apiResponse, 'id')
            ?? data_get($apiResponse, 'data.id')
            ?? data_get($apiResponse, 'contrat.id');

        if ((int) $mainId > 0) {
            $ids[] = (int) $mainId;
        }

        foreach (['sous_contrats', 'sub_contracts', 'subContracts', 'children', 'data.sous_contrats', 'data.sub_contracts', 'data.subContracts', 'data.children', 'contrat.sous_contrats', 'contrat.sub_contracts', 'contrat.subContracts', 'contrat.children'] as $path) {
            $rows = data_get($apiResponse, $path, []);

            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $id = data_get($row, 'id') ?? data_get($row, 'contrat.id') ?? data_get($row, 'source_contract_id');
                if ((int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }

        return collect($ids)->filter()->unique()->values()->all();
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

    private function specificitesJsonValidationRule(): callable
    {
        return function (string $attribute, mixed $value, callable $fail): void {
            if ($value === null || $value === '' || is_array($value)) {
                return;
            }

            json_decode((string) $value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('Le champ ' . str_replace('_', ' ', $attribute) . ' doit contenir un JSON valide. Exemple : {"marque":"Samsung","imei":"123456"}.');
            }
        };
    }

    private function normalizeSpecificites(mixed $value): mixed
    {
        if ($value === null) {
            return new stdClass();
        }

        if (is_array($value)) {
            return $this->formatSpecificites($value);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return new stdClass();
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $this->formatSpecificites($decoded);
        }

        return $this->titleCaseWords($value);
    }

    private function formatSpecificites(array $specificites): array|stdClass
    {
        $formatted = [];

        foreach ($specificites as $key => $value) {
            $label = $this->titleCaseWords((string) $key);

            if ($label === '') {
                continue;
            }

            $formatted[$label] = is_array($value)
                ? $this->formatSpecificites($value)
                : (is_string($value) ? $this->titleCaseWords($value) : $value);
        }

        return empty($formatted) ? new stdClass() : $formatted;
    }

    private function titleCaseWords(string $value): string
    {
        $value = trim(str_replace(['_', '-'], ' ', $value));
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '') {
            return '';
        }

        return collect(explode(' ', $value))
            ->map(function (string $word) {
                if ($word === mb_strtoupper($word, 'UTF-8') && mb_strlen($word, 'UTF-8') <= 8) {
                    return $word;
                }

                return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')
                    . mb_strtolower(mb_substr($word, 1, null, 'UTF-8'), 'UTF-8');
            })
            ->implode(' ');
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
     * Ajoute la règle de coupure effective connue localement à chaque contrat réel,
     * y compris les sous-contrats. L'interface doit raisonner par contrat/sous-contrat,
     * jamais par véhicule + matrice de types.
     */
    private function attachCutoffPoliciesToContracts(array $contracts, array $contractTypes): array
    {
        if (! auth()->check()) {
            return $contracts;
        }

        $actor = auth()->user();
        $partnerId = (int) ($actor->partner_id ?: $actor->id);
        $sourceContractIds = $this->collectSourceContractIdsRecursive($contracts);

        if (empty($sourceContractIds)) {
            return $contracts;
        }

        $linksBySourceId = LeaseContractLink::query()
            ->with('cutoffRule')
            ->where('partner_id', $partnerId)
            ->whereIn('source_contract_id', $sourceContractIds)
            ->get()
            ->keyBy('source_contract_id');

        return $this->attachCutoffPoliciesRecursive($contracts, $linksBySourceId);
    }

    private function collectSourceContractIdsRecursive(array $contracts): array
    {
        $ids = [];

        foreach ($contracts as $contract) {
            if (! is_array($contract)) {
                continue;
            }

            $id = $this->extractContractId($contract);
            if ($id > 0) {
                $ids[] = $id;
            }

            foreach (['sub_contracts', 'sous_contrats', 'sousContrats'] as $key) {
                if (! empty($contract[$key]) && is_array($contract[$key])) {
                    $ids = array_merge($ids, $this->collectSourceContractIdsRecursive($contract[$key]));
                }
            }

            if (! empty($contract['raw']) && is_array($contract['raw'])) {
                foreach (['sub_contracts', 'sous_contrats', 'sousContrats'] as $key) {
                    if (! empty($contract['raw'][$key]) && is_array($contract['raw'][$key])) {
                        $ids = array_merge($ids, $this->collectSourceContractIdsRecursive($contract['raw'][$key]));
                    }
                }
            }
        }

        return collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }

    private function attachCutoffPoliciesRecursive(array $contracts, $linksBySourceId): array
    {
        return collect($contracts)
            ->map(function (array $contract) use ($linksBySourceId) {
                $sourceId = $this->extractContractId($contract);
                $link = $linksBySourceId->get($sourceId);
                $rule = $link?->cutoffRule;

                $contract['contract_link_id'] = $link?->id;
                $contract['cutoff'] = $this->formatCutoffForView($rule);

                foreach (['sub_contracts', 'sous_contrats', 'sousContrats'] as $key) {
                    if (! empty($contract[$key]) && is_array($contract[$key])) {
                        $contract[$key] = $this->attachCutoffPoliciesRecursive($contract[$key], $linksBySourceId);
                    }
                }

                if (! empty($contract['raw']) && is_array($contract['raw'])) {
                    foreach (['sub_contracts', 'sous_contrats', 'sousContrats'] as $key) {
                        if (! empty($contract['raw'][$key]) && is_array($contract['raw'][$key])) {
                            $contract['raw'][$key] = $this->attachCutoffPoliciesRecursive($contract['raw'][$key], $linksBySourceId);
                        }
                    }
                }

                return $contract;
            })
            ->values()
            ->all();
    }

    private function formatCutoffForView(?\App\Models\LeaseCutoffContractRule $rule): array
    {
        return [
            'rule_id' => $rule?->id,
            'enabled' => (bool) ($rule?->is_enabled ?? false),
            'cutoff_time' => $rule?->effectiveCutoffTime(),
            'timezone' => $rule?->effectiveTimezone(),
            'grace_days' => (int) ($rule?->grace_days ?? 0),
            'active_days' => $rule?->active_days ?? [],
            'only_when_stopped' => (bool) ($rule?->only_when_stopped ?? true),
            'notify_before_cutoff' => (bool) ($rule?->notify_before_cutoff ?? false),
        ];
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
            'cutoff' => $child['cutoff'] ?? $raw['cutoff'] ?? [],
            'contract_link_id' => $child['contract_link_id'] ?? $raw['contract_link_id'] ?? null,
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

    /**
     * Les sous-contrats Recouvrement peuvent remonter avec immatriculation/vin à null.
     * Tracking a pourtant besoin du véhicule du contrat parent pour créer le
     * LeaseContractLink puis appliquer la règle de coupure du sous-contrat.
     *
     * On enrichit donc uniquement les sous-contrats qui n'ont pas de véhicule propre,
     * sans modifier les contrats qui possèdent déjà leurs informations.
     */
    private function inheritParentVehicleOnSubContracts(array $contracts): array
    {
        $parentsById = collect($contracts)
            ->filter(fn ($contract) => is_array($contract) && $this->extractParentContractId($contract) <= 0)
            ->keyBy(fn (array $contract) => $this->extractContractId($contract));

        return collect($contracts)
            ->map(function ($contract) use ($parentsById) {
                if (! is_array($contract)) {
                    return $contract;
                }

                $parentId = $this->extractParentContractId($contract);

                if ($parentId > 0) {
                    $parent = $parentsById->get($parentId);

                    if (is_array($parent)) {
                        $contract = $this->inheritVehicleFieldsFromParent($contract, $parent);
                    }
                }

                foreach (['sub_contracts', 'sous_contrats', 'sousContrats'] as $key) {
                    if (! empty($contract[$key]) && is_array($contract[$key])) {
                        $contract[$key] = collect($contract[$key])
                            ->map(fn ($child) => is_array($child)
                                ? $this->inheritVehicleFieldsFromParent($child, $contract)
                                : $child)
                            ->values()
                            ->all();
                    }

                    if (! empty($contract['raw'][$key]) && is_array($contract['raw'][$key])) {
                        $contract['raw'][$key] = collect($contract['raw'][$key])
                            ->map(fn ($child) => is_array($child)
                                ? $this->inheritVehicleFieldsFromParent($child, $contract)
                                : $child)
                            ->values()
                            ->all();
                    }
                }

                return $contract;
            })
            ->values()
            ->all();
    }

    private function inheritVehicleFieldsFromParent(array $child, array $parent): array
    {
        $parentImmatriculation = $parent['vehicule']
            ?? $parent['immatriculation']
            ?? data_get($parent, 'raw.immatriculation')
            ?? null;

        $parentVin = $parent['vin']
            ?? data_get($parent, 'raw.vin')
            ?? '';

        $parentVehicleId = $parent['vehicle_id']
            ?? $parent['vehicule_id']
            ?? data_get($parent, 'raw.vehicle_id')
            ?? data_get($parent, 'raw.vehicule_id')
            ?? null;

        $hasChildImmatriculation = trim((string) (
            $child['immatriculation']
            ?? $child['vehicule']
            ?? data_get($child, 'raw.immatriculation')
            ?? ''
        )) !== '';

        if (! $hasChildImmatriculation && ! empty($parentImmatriculation)) {
            $child['immatriculation'] = $parentImmatriculation;
            $child['vehicule'] = $child['vehicule'] ?? $parentImmatriculation;

            if (isset($child['raw']) && is_array($child['raw'])) {
                $child['raw']['immatriculation'] = $child['raw']['immatriculation'] ?? $parentImmatriculation;
            }
        }

        if (empty($child['vin']) && ! empty($parentVin)) {
            $child['vin'] = $parentVin;

            if (isset($child['raw']) && is_array($child['raw'])) {
                $child['raw']['vin'] = $child['raw']['vin'] ?? $parentVin;
            }
        }

        if (empty($child['vehicle_id']) && ! empty($parentVehicleId)) {
            $child['vehicle_id'] = $parentVehicleId;
            $child['vehicule_id'] = $child['vehicule_id'] ?? $parentVehicleId;
        }

        if (empty($child['tracking_vehicle']) && ! empty($parent['tracking_vehicle'])) {
            $child['tracking_vehicle'] = $parent['tracking_vehicle'];
        }

        return $child;
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
