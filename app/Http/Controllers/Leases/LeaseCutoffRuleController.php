<?php

namespace App\Http\Controllers\Leases;

use App\Exceptions\LeaseApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leases\SaveLeaseCutoffRulesRequest;
use App\Services\Leases\LeaseCutoffRuleService;
use App\Services\Leases\PartnerLeaseApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class LeaseCutoffRuleController extends Controller
{
    public function __construct(
        private readonly LeaseCutoffRuleService $service,
        private readonly PartnerLeaseApiService $leaseApiService,
    ) {
    }

    /**
     * Page de paramétrage dynamique : véhicules Tracking + types recouvrement.
     */
    public function index(): View
    {
        $warnings = [];
        $contractTypes = [];

        try {
            $contractTypes = $this->leaseApiService->fetchContractTypes();

            if (empty($contractTypes)) {
                $warnings[] = 'Aucun type de contrat n’est disponible côté recouvrement. Créez au moins un type avant de configurer les règles par type.';
            }
        } catch (Throwable $e) {
            report($e);

            $warnings[] = $this->clientErrorMessage(
                $e,
                'Impossible de charger les types de contrats depuis recouvrement. La matrice de coupure par type est indisponible.'
            );

            Log::error('[LEASE_CUTOFF_RULES_FETCH_TYPES_FAILED]', [
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'request_id' => $e instanceof LeaseApiException ? $e->requestId() : null,
                'context' => $e instanceof LeaseApiException ? $e->context() : [],
            ]);
        }

        $vehicles = $this->service->getPartnerVehiclesWithRules(auth()->user(), $contractTypes);

        return view('leases.cutoff-rules', [
            'vehicles' => $vehicles,
            'contractTypes' => $contractTypes,
            'pageWarnings' => $warnings,
        ]);
    }

    /**
     * Sauvegarde la matrice véhicule + types de contrats.
     */
    public function store(SaveLeaseCutoffRulesRequest $request): RedirectResponse
    {
        try {
            $contractTypes = $this->leaseApiService->fetchContractTypes();

            $this->service->saveRules(
                auth()->user(),
                $request->validated('rules'),
                $contractTypes
            );

            return redirect()
                ->route('lease.cutoff-rules.index')
                ->with('success', 'Les règles de coupure ont été enregistrées avec succès.');
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_CUTOFF_RULES_SAVE_FAILED]', [
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'request_id' => $e instanceof LeaseApiException ? $e->requestId() : null,
                'payload_preview' => array_slice($request->all(), 0, 5),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $this->clientErrorMessage($e, "Impossible d'enregistrer les règles de coupure pour le moment."));
        }
    }

    /**
     * Crée un nouveau type de contrat/sous-contrat côté recouvrement.
     *
     * Ensuite, Tracking initialise ce type en OFF pour les véhicules du partenaire,
     * afin d’éviter toute coupure accidentelle.
     */
    public function storeContractType(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'libelle' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:40'],
            'est_principal' => ['nullable', 'boolean'],
            'enable_by_default' => ['nullable', 'boolean'],
        ], [
            'libelle.required' => 'Le libellé du type de contrat est obligatoire.',
            'libelle.max' => 'Le libellé est trop long.',
            'code.max' => 'Le code est trop long.',
        ])->validate();

        try {
            $created = $this->leaseApiService->createContractType([
                'libelle' => $validated['libelle'],
                'code' => $validated['code'] ?? null,
                'est_principal' => filter_var($validated['est_principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);

            $createdType = [
                'id' => $created['id'] ?? null,
                'label' => $created['libelle'] ?? $created['label'] ?? $validated['libelle'],
                'code' => $created['code'] ?? $validated['code'] ?? null,
                'is_main' => $created['est_principal'] ?? $validated['est_principal'] ?? false,
            ];

            $this->service->initializeContractTypeForPartner(
                user: auth()->user(),
                contractType: $createdType,
                enabledByDefault: filter_var($validated['enable_by_default'] ?? false, FILTER_VALIDATE_BOOLEAN)
            );

            return redirect()
                ->route('lease.cutoff-rules.index')
                ->with('success', 'Type de contrat créé et ajouté au paramétrage de coupure.');
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_CONTRACT_TYPE_CREATE_FAILED]', [
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'request_id' => $e instanceof LeaseApiException ? $e->requestId() : null,
                'payload' => $validated,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', $this->clientErrorMessage($e, 'Impossible de créer ce type de contrat côté recouvrement.'));
        }
    }

    private function clientErrorMessage(Throwable $e, string $fallback): string
    {
        if ($e instanceof LeaseApiException) {
            return $e->clientMessage();
        }

        return $fallback;
    }
}
