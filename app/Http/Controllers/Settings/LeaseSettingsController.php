<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Leases\LeaseContractTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;
use App\Services\Keycloak\KeycloakAdminService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Services\Leases\LeaseCutoffDefaultRuleService;
use App\Services\GeofenceZoneService;
use App\Services\VehicleTimeZoneService;
use App\Models\User;



class LeaseSettingsController extends Controller
{
    


public function __construct(
    private readonly LeaseContractTypeService $contractTypeService,
    private readonly LeaseCutoffDefaultRuleService $defaultRuleService,
    private readonly GeofenceZoneService $geofenceZoneService,
    private readonly VehicleTimeZoneService $vehicleTimeZoneService,
) {}

public function index(): View
{
    $user = auth()->user();

    abort_if(! $user, 403);

    $partner = $user;

    if (! empty($user->partner_id)) {
        $partner = User::query()->find($user->partner_id) ?? $user;
    }

    return view('settings.lease', [
        'partner' => $partner,

        'contractTypes' => $this->contractTypeService->getAllContractTypes(),
        'mainContractTypes' => $this->contractTypeService->getMainContractTypes(),
        'subContractTypes' => $this->contractTypeService->getSubContractTypes(),

        'defaultRules' => $this->defaultRuleService->getRulesForPartner($partner),
        'defaultActiveDays' => $this->defaultRuleService->defaultActiveDays(),

        'geofences' => $this->geofenceZoneService->geofencesForUser($partner),
        'vehicles' => $this->geofenceZoneService->vehiclesForUser($partner),

        'timeZoneVehicles' => $this->vehicleTimeZoneService->vehiclesForUser($partner),
    ]);
}

 public function storeContractType(Request $request): RedirectResponse
{
    $request->merge([
        'est_principal' => $request->boolean('est_principal'),
    ]);

    $validated = $request->validate([
        'libelle' => ['required', 'string', 'max:150'],
        'code' => ['nullable', 'string', 'max:40'],
        'est_principal' => ['required', 'boolean'],
    ]);

    try {
        $this->contractTypeService->createContractType($validated);

        return back()->with('success', 'Type de contrat créé avec succès.');
    } catch (Throwable $e) {
        report($e);

        return back()
            ->withInput()
            ->with('error', 'Impossible de créer le type de contrat : ' . $e->getMessage());
    }
}

    //Modification de mot de passe
    public function updatePassword(Request $request, KeycloakAdminService $keycloak): RedirectResponse
{
    $validated = $request->validateWithBag('updatePassword', [
        'current_password' => ['required', 'current_password'],
        'password' => ['required', Password::defaults(), 'confirmed'],
    ]);

    $user = $request->user();
    $plainPassword = $validated['password'];

    try {
        if (! empty($user->keycloak_id)) {
            $keycloak->resetUserPassword(
                $user->keycloak_id,
                $plainPassword,
                false
            );
        } else {
            $result = $keycloak->createOrFindUserWithPassword(
                $user,
                $plainPassword,
                false
            );

            $user->forceFill([
                'keycloak_id' => $result['id'] ?? null,
                'keycloak_username' => $result['username'] ?? $user->keycloak_username,
                'keycloak_sync_status' => 'SYNCED',
            ])->save();
        }

        $user->forceFill([
            'password' => Hash::make($plainPassword),
        ])->save();

        return back()->with('success', 'Mot de passe modifié localement et sur Keycloak.');
    } catch (\Throwable $e) {
        report($e);

        return back()
            ->withInput()
            ->with('error', 'Impossible de modifier le mot de passe : ' . $e->getMessage());
    }
}



//regle de coupure par defaut par type de contrat
public function updateCutoffDefaultRules(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'rules' => ['nullable', 'array'],
        'rules.*.type_contrat_id' => ['required', 'integer'],
        'rules.*.type_contrat_label' => ['required', 'string', 'max:150'],
        'rules.*.type_contrat_code' => ['nullable', 'string', 'max:80'],
        'rules.*.is_enabled' => ['nullable', 'boolean'],
        'rules.*.cutoff_time' => ['nullable', 'date_format:H:i'],
        'rules.*.grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        'rules.*.active_days' => ['nullable', 'array'],
        'rules.*.active_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
        'rules.*.only_when_stopped' => ['nullable', 'boolean'],
        'rules.*.notify_before_cutoff' => ['nullable', 'boolean'],
    ]);

    $this->defaultRuleService->upsertRules(
        $request->user(),
        $validated['rules'] ?? [],
        $request->user()
    );

    return back()->with('success', 'Règles de coupure par défaut mises à jour.');
}

}