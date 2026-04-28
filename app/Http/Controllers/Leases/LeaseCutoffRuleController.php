<?php

namespace App\Http\Controllers\Leases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leases\SaveLeaseCutoffRulesRequest;
use App\Services\Leases\LeaseCutoffRuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;

class LeaseCutoffRuleController extends Controller
{
    public function __construct(
        private readonly LeaseCutoffRuleService $service
    ) {
    }

    public function index(): View
    {
        $vehicles = $this->service->getPartnerVehiclesWithRules(auth()->user());

        return view('leases.cutoff-rules', [
            'vehicles' => $vehicles,
        ]);
    }

    public function store(SaveLeaseCutoffRulesRequest $request): RedirectResponse
    {
        try {
            $this->service->saveRules(
                auth()->user(),
                $request->validated('rules')
            );

            return redirect()
                ->route('lease.cutoff-rules.index')
                ->with('success', 'Les règles de coupure ont été enregistrées avec succès.');
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', "Impossible d'enregistrer les règles de coupure pour le moment.");
        }
    }
}