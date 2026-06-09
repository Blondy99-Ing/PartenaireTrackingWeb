<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\Voitures\VoitureController;
use App\Http\Controllers\Associations\AssociationController;
use App\Http\Controllers\Employes\EmployeController;
use App\Http\Controllers\Villes\VilleController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\Users\ProfileController;
use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\Trajets\TrajetController;
use App\Http\Controllers\Auth\PasswordOtpController;
use App\Http\Controllers\Auth\VerifyLoginController;

use App\Http\Controllers\Partner\AffectationChauffeurVoitureController;
use App\Http\Controllers\Gps\ControlGpsController;
use App\Http\Controllers\Leases\LeaseController;
use App\Http\Controllers\Leases\ContratLeaseController;
use App\Http\Controllers\Leases\LeaseCutoffRuleController;
use App\Http\Controllers\Leases\LeaseCutoffHistoryController;
use App\Http\Controllers\Leases\DashbaordLeaseController;
use App\Http\Controllers\Partner\PartnerDriverController;use App\Http\Controllers\Settings\LeaseSettingsController;
use App\Http\Controllers\Api\Internal\Lease\SubContractTypeController;
use App\Http\Controllers\Settings\GeofenceSettingsController;
use App\Http\Controllers\Settings\VehicleTimeZoneSettingsController;



Route::middleware(['auth:web', 'partner.only'])->group(function () {

    // ── Dashboard ──────────────────────────────────────────────────────
    // Le middleware rebuild.dashboard garantit que Redis est rempli
    // à chaque chargement / actualisation de la page
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('rebuild.dashboard')
        ->name('dashboard');


    Route::get('/dashboard/stream', [DashboardController::class, 'dashboardStream'])->name('dashboard.stream');
    Route::post('/dashboard/rebuild', [DashboardController::class, 'rebuildCache'])->name('dashboard.rebuild');

    // ── Tracking ───────────────────────────────────────────────────────
    Route::prefix('tracking')->name('tracking.')->group(function () {
        Route::get('vehicles', [VoitureController::class, 'index'])->name('vehicles');
    });

    // ── Profile ────────────────────────────────────────────────────────
    Route::get('/profile/vehicles/positions', [ProfileController::class, 'vehiclePositions'])
        ->name('profile.vehicles.positions');

   


    // ── Partner Affectations ───────────────────────────────────────────
    Route::prefix('partner/affectations')->name('partner.affectations.')->group(function () {
        Route::get('vehicles', [AffectationChauffeurVoitureController::class, 'vehicles'])->name('vehicles');
        Route::get('drivers', [AffectationChauffeurVoitureController::class, 'drivers'])->name('drivers');
        Route::post('assign', [AffectationChauffeurVoitureController::class, 'assign'])->name('assign');
        Route::post('unassign', [AffectationChauffeurVoitureController::class, 'unassign'])->name('unassign');
        Route::get('history', [AffectationChauffeurVoitureController::class, 'history'])->name('history');
        Route::get('/', [AffectationChauffeurVoitureController::class, 'index'])->name('index');
    });

    // ── Engine / GPS ───────────────────────────────────────────────────
    Route::get('/engine/actions', [ControlGpsController::class, 'index'])->name('engine.action.index');
    Route::get('/engine/history', [ControlGpsController::class, 'history'])->name('engine.action.history');

    Route::get('/voitures/engine-status/batch', [ControlGpsController::class, 'engineStatusBatch'])
        ->name('voitures.engineStatusBatch');
    Route::get('/voitures/{voiture}/engine-status', [ControlGpsController::class, 'engineStatus'])
        ->name('voitures.engineStatus');
    Route::post('/voitures/{voiture}/toggle-engine', [ControlGpsController::class, 'toggleEngine'])
        ->name('voitures.toggleEngine');

    // ── Alerts ─────────────────────────────────────────────────────────
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');


 


   
   


    // ── Trajets ────────────────────────────────────────────────────────
    Route::get('/trajets', [TrajetController::class, 'index'])->name('trajets.index');
    Route::get('/trajets/{vehicle_id}/detail/{trajet_id}', [TrajetController::class, 'showTrajet'])
        ->name('trajets.detail.api');
    //Route::get('/voitures/{id}/trajets', [TrajetController::class, 'byVoiture'])->name('voitures.trajets');
    Route::get('/trajets/show/{voiture_id}/{trajet_id}', [TrajetController::class, 'showTrajet'])
        ->name('trajets.show');

    // ── Vehicles ───────────────────────────────────────────────────────
    Route::get('/add-vehicle', fn() => view('vehicles.create'))->name('vehicles.add');
 


    //gestion des lease
    //gestion des lease
    Route::get('lease', [LeaseController::class, 'index'])->name('lease.index');
    Route::post('/leases/payments/cash', [\App\Http\Controllers\Leases\LeaseController::class, 'payCash'])
    ->name('leases.payments.cash');

Route::post('/leases/payments/mobile', [\App\Http\Controllers\Leases\LeaseController::class, 'payMobile'])
    ->name('leases.payments.mobile');


    //gestion contrat de lease
Route::get('contrats', [ContratLeaseController::class, 'index'])
    ->name('lease.contrat');

Route::post('contrats', [ContratLeaseController::class, 'store'])
    ->name('lease.contrat.store');

Route::put('contrats/{id}', [ContratLeaseController::class, 'update'])
    ->whereNumber('id')
    ->name('lease.contrat.update');

Route::patch('contrats/{id}', [ContratLeaseController::class, 'update'])
    ->whereNumber('id')
    ->name('lease.contrat.patch');

Route::delete('contrats/{id}', [ContratLeaseController::class, 'destroy'])
    ->whereNumber('id')
    ->name('lease.contrat.destroy');

Route::post('contrats/cutoff-policy', [ContratLeaseController::class, 'updateCutoffPolicy'])
    ->name('lease.contrat.cutoff-policy');

Route::post('contrats/bulk-cutoff-policy', [ContratLeaseController::class, 'bulkUpdateCutoffPolicies'])
    ->name('lease.contrat.bulk-cutoff-policy');


//parametre 
Route::get('/settings/lease', [LeaseSettingsController::class, 'index'])
    ->name('settings.lease.index');


// Paramètres Lease
Route::get('/settings/lease', [LeaseSettingsController::class, 'index'])
    ->name('settings.lease.index');

Route::post('/settings/lease/contract-types', [LeaseSettingsController::class, 'storeContractType'])
    ->name('settings.lease.contract-types.store');

//type de contrats
Route::prefix('internal-api/lease')
    ->name('internal.lease.')
    ->group(function () {
        Route::get('/sub-contract-types', [SubContractTypeController::class, 'index'])
            ->name('sub-contract-types.index');

        Route::post('/sub-contract-types', [SubContractTypeController::class, 'store'])
            ->name('sub-contract-types.store');
    });

//modification de mot de passe
Route::put('/settings/security/password', [LeaseSettingsController::class, 'updatePassword'])
    ->name('settings.security.password.update');




// regle de coupure automatique de vehicule en leases 
Route::get('lease/cutoff-rules', [LeaseCutoffRuleController::class, 'index'])
    ->name('lease.cutoff-rules.index');
    Route::put('/settings/lease/cutoff-default-rules', [LeaseSettingsController::class, 'updateCutoffDefaultRules'])
    ->name('settings.lease.cutoff-default-rules.update');

// mise à jour de coupure globale
Route::post('/leases/global-cutoff', [\App\Http\Controllers\Leases\LeaseController::class, 'updateGlobalCutoff'])
    ->name('leases.global-cutoff.update');

Route::post('lease/cutoff-rules', [LeaseCutoffRuleController::class, 'store'])
    ->name('lease.cutoff-rules.store');

Route::post('lease/cutoff-rules/type-contrats', [LeaseCutoffRuleController::class, 'storeContractType'])
    ->name('lease.cutoff-rules.type-contrats.store');

    //pardonner un lease non payé  en rallumant le vehicuel
    Route::post('/leases/{leaseId}/forgive', [\App\Http\Controllers\Leases\LeaseController::class, 'forgive'])
    ->name('leases.forgive');

// histirique de coupure automatique
Route::get('lease/cutoff-history', [LeaseCutoffHistoryController::class, 'index'])
    ->name('lease.cutoff-history.index');
//dashbaord lease
Route::get('/leases/dashboard', [DashbaordLeaseController::class, 'index'])
        ->name('leases.dashboard');





// ── Settings Geofences ───────────────────────────────────────────────
Route::prefix('settings/geofences')
    ->name('settings.geofences.')
    ->group(function () {
        Route::get('/', [GeofenceSettingsController::class, 'index'])
            ->name('index');

        Route::post('/', [GeofenceSettingsController::class, 'store'])
            ->name('store');

        Route::put('/{geofence}', [GeofenceSettingsController::class, 'update'])
            ->name('update');

        Route::delete('/{geofence}', [GeofenceSettingsController::class, 'destroy'])
            ->name('destroy');

        Route::post('/{geofence}/assign', [GeofenceSettingsController::class, 'assign'])
            ->name('assign');
    });


    //time_zone
    Route::put('/settings/time-zone', [VehicleTimeZoneSettingsController::class, 'update'])
    ->name('settings.timezone.update');





// ── Partner Drivers / Chauffeurs ───────────────────────────────────
Route::prefix('partner')
    ->name('partner.')
    ->group(function () {
        Route::get('drivers', [PartnerDriverController::class, 'index'])
            ->name('drivers.index');

        Route::post('drivers', [PartnerDriverController::class, 'store'])
            ->name('drivers.store');

        Route::get('drivers/{id}', [PartnerDriverController::class, 'show'])
            ->name('drivers.show');

        Route::put('drivers/{id}', [PartnerDriverController::class, 'update'])
            ->name('drivers.update');

        Route::patch('drivers/{id}', [PartnerDriverController::class, 'update'])
            ->name('drivers.patch');

        Route::delete('drivers/{id}', [PartnerDriverController::class, 'destroy'])
            ->name('drivers.destroy');
    });


});

// ── Auth invité (reset password OTP) ──────────────────────────────────
Route::middleware('guest')->prefix('partner')->group(function () {
    Route::post('forgot-password/send', [VerifyLoginController::class, 'sendForgotOtp'])
        ->name('partner.password.otp.send');
    Route::post('forgot-password/resend', [VerifyLoginController::class, 'resendForgotOtp'])
        ->name('partner.password.otp.resend');
    Route::post('forgot-password/verify', [VerifyLoginController::class, 'verifyForgotOtp'])
        ->name('partner.password.otp.verify');
    Route::get('reset-password/{token}', [VerifyLoginController::class, 'showResetForm'])
        ->name('partner.otp.password.reset');
    Route::post('reset-password', [VerifyLoginController::class, 'resetPassword'])
        ->name('partner.otp.password.reset.perform');
});

// ── Profile auth ───────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';