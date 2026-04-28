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
use App\Http\Controllers\Partner\PartnerDriverController;


Route::middleware(['auth:web'])->group(function () {

    // ── Dashboard ──────────────────────────────────────────────────────
    // Le middleware rebuild.dashboard garantit que Redis est rempli
    // à chaque chargement / actualisation de la page
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('rebuild.dashboard')
        ->name('dashboard');

    Route::get('/dashboard/vehicles/positions', [DashboardController::class, 'vehiclesPositions']);
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
    Route::get('/alerts/day', [AlertController::class, 'day'])->name('alerts.day');

    // Marquer une alerte comme lue / traitée (appelé depuis le front JS)
    Route::patch('/alerts/{alert}/read', [AlertController::class, 'markRead'])->name('alerts.read');
    Route::patch('/alerts/{alert}/process', [AlertController::class, 'markProcessed'])->name('alerts.process');

    // ── Trajets ────────────────────────────────────────────────────────
    Route::get('/trajets', [TrajetController::class, 'index'])->name('trajets.index');
    Route::get('/trajets/{vehicle_id}/detail/{trajet_id}', [TrajetController::class, 'showTrajet'])
        ->name('trajets.detail.api');
    Route::get('/voitures/{id}/trajets', [TrajetController::class, 'byVoiture'])->name('voitures.trajets');
    Route::get('/trajets/show/{voiture_id}/{trajet_id}', [TrajetController::class, 'showTrajet'])
        ->name('trajets.show');

    // ── Vehicles ───────────────────────────────────────────────────────
    Route::get('/add-vehicle', fn() => view('vehicles.create'))->name('vehicles.add');
    Route::post('/save-vehicle', [\App\Http\Controllers\VehicleController::class, 'store'])->name('vehicles.save');


    //gestion des lease
    Route::get('lease', [LeaseController::class, 'index'])->name('lease.index');
    Route::post('/leases/payments/cash', [\App\Http\Controllers\Leases\LeaseController::class, 'payCash'])
    ->name('leases.payments.cash');
    //gestion contrat de lease
    Route::get('contrats', [ContratLeaseController::class, 'index'])->name('lease.contrat');
    Route::post('contrats', [ContratLeaseController::class, 'store'])
    ->name('lease.contrat.store');

// regle de coupure automatique de vehicule en leases 
Route::get('lease/cutoff-rules', [LeaseCutoffRuleController::class, 'index'])
    ->name('lease.cutoff-rules.index');

// mise à jour de coupure globale
Route::post('/leases/global-cutoff', [\App\Http\Controllers\Leases\LeaseController::class, 'updateGlobalCutoff'])
    ->name('leases.global-cutoff.update');

Route::post('lease/cutoff-rules', [LeaseCutoffRuleController::class, 'store'])
    ->name('lease.cutoff-rules.store');

    //pardonner un lease non payé  en rallumant le vehicuel
    Route::post('/leases/{leaseId}/forgive', [\App\Http\Controllers\Leases\LeaseController::class, 'forgive'])
    ->name('leases.forgive');

// histirique de coupure automatique
Route::get('lease/cutoff-history', [LeaseCutoffHistoryController::class, 'index'])
    ->name('lease.cutoff-history.index');






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