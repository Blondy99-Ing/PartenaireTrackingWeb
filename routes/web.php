<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\AgenceAuthController;
use App\Http\Controllers\Voitures\VoitureController;
use App\Http\Controllers\Associations\AssociationController;
use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\VehiclesController;



Route::get('/', function () {
    return view('welcome');
});

//Route::get('login', function () {
//    return view('auth.login');  // Vue de la page de connexion
//})->name('login');

Route::post('login', [AgenceAuthController::class, 'authenticate']);

//Route::middleware(['auth.agence'])->group(function () {
    // Route pour la déconnexion
    Route::post('logout', [AgenceAuthController::class, 'logout'])->name('logout');

    // Routes protégées par authentification

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Afficher les utilisateurs de l'agence
   Route::get('/tracking_users', [TrackingUserController::class, 'index'])->name('tracking.users');


//});



// Enregistrer un nouvel agent swap
Route::post('/tracking_users', [TrackingUserController::class, 'store'])->name('tracking.users.store');

// Modifier un utilisateur (Formulaire)
Route::get('/tracking_users/{trackingUser}/edit', [TrackingUserController::class, 'edit'])->name('tracking.users.edit');

// Mettre à jour un utilisateur
Route::put('/tracking_users/{trackingUser}', [TrackingUserController::class, 'update'])->name('tracking.users.update');


// Supprimer un agent swap
Route::delete('/tracking_users/{trackingUser}', [TrackingUserController::class, 'destroy'])->name('tracking.users.destroy');






// Liste des véhicules
Route::get('/tracking_vehicles', [VoitureController::class, 'index'])->name('tracking.vehicles');

// Enregistrer ou mettre à jour un véhicule
Route::post('/tracking_vehicles', [VoitureController::class, 'store'])->name('tracking.vehicles.store');

// Supprimer un véhicule
Route::delete('/tracking_vehicles/{vehicles}', [VoitureController::class, 'destroy'])->name('tracking.vehicles.destroy');

// Modifier un véhicule
Route::get('/tracking_vehicles/{vehicles}/edit', [VoitureController::class, 'edit'])->name('tracking.vehicles.edit');






// Formulaire pour associer
Route::get('/association', [AssociationController::class, 'index'])->name('association.index');

// Enregistrer l'association
Route::post('/association', [AssociationController::class, 'associerVoitureAUtilisateur'])->name('association.store');

Route::delete('/associations/{id}', [AssociationController::class, 'destroy'])->name('association.destroy');


//alerts
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::post('/alerts/turnoff/{voiture}', [AlertController::class, 'turnOff'])->name('alerts.turnoff');
Route::post('/alerts/{alert}/mark-as-read', [AlertController::class, 'markAsRead'])->name('alerts.markAsRead');
Route::post('/alerts/{alert}/mark-as-unread', [AlertController::class, 'markAsUnread'])->name('alerts.markAsUnread');

// Vehicles Routes
Route::get('/vehicles/create', [VehiclesController::class, 'create'])->name('vehicles.create');
Route::post('/vehicles/store', [VehiclesController::class, 'store'])->name('vehicles.store');


// 1. Route to show the page
Route::get('/add-vehicle', function () {
    return view('vehicles.create');
})->name('vehicles.add');

// 2. Route to save the vehicle (form POST)
Route::post('/save-vehicle', [\App\Http\Controllers\VehicleController::class, 'store'])->name('vehicles.save');
