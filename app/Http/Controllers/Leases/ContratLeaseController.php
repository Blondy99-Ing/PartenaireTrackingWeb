<?php

namespace App\Http\Controllers\Leases;

use App\Http\Controllers\Controller;
use App\Services\Leases\LeaseCutoffRuleService;
use App\Services\Leases\PartnerLeaseApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ContratLeaseController extends Controller
{
    public function __construct(
        private readonly PartnerLeaseApiService $leaseApiService,
        private readonly LeaseCutoffRuleService $cutoffRuleService
    ) {
    }

    public function index(Request $request): View
    {
        try {
            $contracts = $this->leaseApiService->fetchContracts();

            /**
             * Chauffeurs venant de recouvrement :
             * GET /accounts/chauffeurs/
             */
            $chauffeurs_list = collect($this->leaseApiService->fetchChauffeurs())
                ->map(function (array $chauffeur) {
                    return [
                        'id' => $chauffeur['id'],
                        'label' => trim(
                            ($chauffeur['nom_complet'] ?: 'Chauffeur #' . $chauffeur['id'])
                            . (! empty($chauffeur['email']) ? ' — ' . $chauffeur['email'] : '')
                        ),
                    ];
                })
                ->values()
                ->all();

            /**
             * Véhicules venant de la base locale Tracking.
             */
            $vehicules_list = $this->leaseApiService->fetchPartnerVehiclesForContracts();

            $pageError = null;
        } catch (Throwable $e) {
            report($e);

            $contracts = [];
            $chauffeurs_list = [];
            $vehicules_list = [];
            $pageError = app()->environment('local')
                ? $e->getMessage()
                : 'Impossible de charger les contrats pour le moment.';
        }

        return view('leases.contrat', [
            'contracts' => $contracts,
            'chauffeurs_list' => $chauffeurs_list,
            'vehicules_list' => $vehicules_list,
            'pageError' => $pageError,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'chauffeur' => ['required', 'integer'],
            'montant_total' => ['required', 'numeric', 'min:1'],
            'immatriculation' => ['required', 'string', 'max:190'],
            'vehicle_id' => ['required', 'integer', 'exists:voitures,id'],
            'montant_par_paiement' => ['required', 'numeric', 'min:1'],
            'frequence' => ['required', 'string', 'in:JOURNALIER,HEBDOMADAIRE,MENSUEL'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'prochaine_echeance' => ['required', 'date'],

            /**
             * Option locale : règle de coupure.
             * Le contrat est créé côté recouvrement.
             * La règle de coupure reste locale dans Tracking.
             */
            'coupure_auto' => ['nullable', 'boolean'],
            'cutoff_time' => ['nullable', 'date_format:H:i'],
        ], [
            'chauffeur.required' => 'Veuillez sélectionner un chauffeur.',
            'immatriculation.required' => 'Veuillez sélectionner un véhicule.',
            'vehicle_id.required' => 'Le véhicule local est introuvable.',
            'montant_total.required' => 'Le montant total est obligatoire.',
            'montant_par_paiement.required' => 'Le montant par paiement est obligatoire.',
            'frequence.in' => 'La fréquence doit être JOURNALIER, HEBDOMADAIRE ou MENSUEL.',
            'date_fin.after_or_equal' => 'La date de fin doit être supérieure ou égale à la date de début.',
            'cutoff_time.date_format' => 'L’heure de coupure doit être au format HH:MM.',
        ]);

        try {
            /**
             * 1. Création du contrat côté recouvrement.
             * Le statut n’est pas envoyé : ACTIF par défaut côté recouvrement.
             */
            $this->leaseApiService->createContract([
                'chauffeur' => $validated['chauffeur'],
                'montant_total' => $validated['montant_total'],
                'immatriculation' => $validated['immatriculation'],
                'montant_par_paiement' => $validated['montant_par_paiement'],
                'frequence' => $validated['frequence'],
                'date_debut' => $validated['date_debut'],
                'date_fin' => $validated['date_fin'],
                'prochaine_echeance' => $validated['prochaine_echeance'],
            ]);

            /**
             * 2. Si demandé, activation/mise à jour de la règle de coupure locale
             * pour le véhicule sélectionné.
             */
            $cutoffEnabled = $request->boolean('coupure_auto');

            if ($cutoffEnabled) {
                $this->cutoffRuleService->saveRules(auth()->user(), [
                    [
                        'vehicle_id' => (int) $validated['vehicle_id'],
                        'is_enabled' => true,
                        'cutoff_time' => $validated['cutoff_time'] ?: '12:00',
                        'timezone' => 'Africa/Douala',
                    ],
                ]);
            }

            return redirect()
                ->route('lease.contrat')
                ->with('success', 'Contrat créé avec succès.');
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', app()->environment('local')
                    ? $e->getMessage()
                    : 'Impossible de créer le contrat pour le moment.'
                );
        }
    }
}