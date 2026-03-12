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
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Partner\AffectationChauffeurVoitureController;
use App\Http\Controllers\Gps\ControlGpsController;



Route::middleware(['auth:web'])->group(function () {



    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/vehicles/positions', [DashboardController::class, 'vehiclesPositions']);
    Route::get('/dashboard/stream', [DashboardController::class, 'dashboardStream'])->name('dashboard.stream');
    Route::post('/dashboard/rebuild', [DashboardController::class, 'rebuildCache'])->name('dashboard.rebuild');

    Route::prefix('tracking')->name('tracking.')->group(function () {
        Route::get('vehicles', [VoitureController::class, 'index'])->name('vehicles');
    });

    Route::get('/profile/vehicles/positions', [ProfileController::class, 'vehiclePositions'])
        ->name('profile.vehicles.positions');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{id}/profile', [ProfileController::class, 'show'])->name('users.profile');
 
    // Partner Affectations
    Route::get('partner/affectations/vehicles', [AffectationChauffeurVoitureController::class, 'vehicles'])
        ->name('partner.affectations.vehicles');
    Route::get('partner/affectations/drivers', [AffectationChauffeurVoitureController::class, 'drivers'])
        ->name('partner.affectations.drivers');
    Route::post('partner/affectations/assign', [AffectationChauffeurVoitureController::class, 'assign'])
        ->name('partner.affectations.assign');
    Route::post('partner/affectations/unassign', [AffectationChauffeurVoitureController::class, 'unassign'])
        ->name('partner.affectations.unassign');
    Route::get('partner/affectations/history', [AffectationChauffeurVoitureController::class, 'history'])
        ->name('partner.affectations.history');
    Route::get('partner/affectations', [AffectationChauffeurVoitureController::class, 'index'])
        ->name('partner.affectations.index');


    // Engine
// Engine
Route::get('/engine/actions', [ControlGpsController::class, 'index'])->name('engine.action.index');
Route::get('/engine/history', [ControlGpsController::class, 'history'])->name('engine.action.history');

Route::get('/voitures/engine-status/batch', [ControlGpsController::class, 'engineStatusBatch'])
    ->name('voitures.engineStatusBatch');

Route::get('/voitures/{voiture}/engine-status', [ControlGpsController::class, 'engineStatus'])
    ->name('voitures.engineStatus');

Route::post('/voitures/{voiture}/toggle-engine', [ControlGpsController::class, 'toggleEngine'])
    ->name('voitures.toggleEngine');


// ✅ Alerts API GET only
    Route::get('/alerts',     [AlertController::class, 'index'])->name('alerts.index');
    Route::get('/alerts/day', [AlertController::class, 'day'])->name('alerts.day');

    // ✅ Trips API GET only (liste + détail carte)
    Route::get('/trajets', [TrajetController::class, 'index'])->name('trajets.index');
    Route::get('/trajets/{vehicle_id}/detail/{trajet_id}', [TrajetController::class, 'showTrajet'])
        ->name('trajets.detail.api');

    // (Optionnel si tu as une page dédiée par voiture)
    Route::get('/voitures/{id}/trajets', [TrajetController::class, 'byVoiture'])->name('voitures.trajets');
    Route::get('/trajets/show/{voiture_id}/{trajet_id}', [TrajetController::class, 'showTrajet'])->name('trajets.show');




// 1. Route to show the page
Route::get('/add-vehicle', function () {
    return view('vehicles.create');
})->name('vehicles.add');

// 2. Route to save the vehicle (form POST)
Route::post('/save-vehicle', [\App\Http\Controllers\VehicleController::class, 'store'])->name('vehicles.save');




Route::get('/users/{id}/profile', [ProfileController::class, 'show'])
    ->name('users.profile');






 

    // Vehicles create (custom)
    Route::get('/add-vehicle', function () {
        return view('vehicles.create');
    })->name('vehicles.add');
    Route::post('/save-vehicle', [\App\Http\Controllers\VehicleController::class, 'store'])
        ->name('vehicles.save');

 
});

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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


require __DIR__ . '/auth.php';
