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




<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\AgenceAuthController; // Votre contrôleur d'authentification personnalisé
use App\Http\Controllers\Voitures\VoitureController;
use App\Http\Controllers\Associations\AssociationController;
use App\Http\Controllers\Alert\AlertController;
use App\Http\Controllers\VehiclesController;
use App\Http\Controllers\VehicleController; // Assurez-vous que c'est le bon contrôleur pour les véhicules
use App\Http\Controllers\ReportController; // Ajouté pour corriger l'erreur reports.index


// ----------------------------------------------------------------------
// 1. ROUTES PUBLIQUES (NON AUTHENTIFIÉES)
// ----------------------------------------------------------------------

// Page d'accueil (public si l'utilisateur n'est pas connecté)
Route::get('/', function () {
    return view('welcome');
});

// Route de connexion personnalisée
// Note: Laravel Breeze ajoute déjà les routes GET/POST login, mais si vous utilisez AgenceAuthController,
// assurez-vous de commenter ou supprimer le require __DIR__.'/auth.php'; si vous gérez TOUT manuellement.
//Route::post('login', [AgenceAuthController::class, 'authenticate'])->name('login.attempt');


// ----------------------------------------------------------------------
// 2. ROUTES PROTÉGÉES (ACCESSIBLE APRÈS CONNEXION)
// ----------------------------------------------------------------------

// Le middleware 'auth' garantit que seul un utilisateur connecté peut accéder à ces routes.
// Utiliser 'auth:web' est l'approche la plus sûre si vous avez défini le guard 'web' pour les employés.
Route::middleware(['auth:web'])->group(function () {

    // Route pour la déconnexion
    Route::post('logout', [AgenceAuthController::class, 'logout'])->name('logout');

    // DASHBOARD (Route de base après connexion)
    // J'ai utilisé 'index' de DashboardController pour le point d'entrée
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');


    // ------------------- GESTION DES EMPLOYES (TRACKING USERS) -------------------

    // Afficher tous les employés
    Route::get('/tracking_users', [TrackingUserController::class, 'index'])->name('tracking.users');
    // Enregistrer un nouvel employé (POST)
    Route::post('/tracking_users', [TrackingUserController::class, 'store'])->name('tracking.users.store');
    // Modifier un employé (Formulaire GET)
    Route::get('/tracking_users/{trackingUser}/edit', [TrackingUserController::class, 'edit'])->name('tracking.users.edit');
    // Mettre à jour un employé (PUT/PATCH)
    Route::put('/tracking_users/{trackingUser}', [TrackingUserController::class, 'update'])->name('tracking.users.update');
    // Supprimer un employé
    Route::delete('/tracking_users/{trackingUser}', [TrackingUserController::class, 'destroy'])->name('tracking.users.destroy');


    // ------------------- GESTION DES VÉHICULES -------------------

    // Liste des véhicules (Note: J'utilise 'tracking.vehicles' pour la liste)
    Route::get('/tracking_vehicles', [VoitureController::class, 'index'])->name('tracking.vehicles');
    // Formulaire d'ajout (Si vous utilisez un formulaire séparé)
    Route::get('/vehicles/create', [VehiclesController::class, 'create'])->name('vehicles.create');
    // Enregistrer ou mettre à jour un véhicule (J'ai regroupé le store)
    Route::post('/tracking_vehicles', [VoitureController::class, 'store'])->name('tracking.vehicles.store');
    // Route de sauvegarde (Nettoyage de la duplication)
    Route::post('/save-vehicle', [VehicleController::class, 'store'])->name('vehicles.save'); // Utilisez soit VoitureController, soit VehicleController, pas les deux !
    // Modifier un véhicule (Formulaire GET)
    Route::get('/tracking_vehicles/{vehicles}/edit', [VoitureController::class, 'edit'])->name('tracking.vehicles.edit');
    // Supprimer un véhicule
    Route::delete('/tracking_vehicles/{vehicles}', [VoitureController::class, 'destroy'])->name('tracking.vehicles.destroy');


    // ------------------- GESTION DES ASSOCIATIONS -------------------

    Route::get('/association', [AssociationController::class, 'index'])->name('association.index');
    Route::post('/association', [AssociationController::class, 'associerVoitureAUtilisateur'])->name('association.store');
    Route::delete('/associations/{id}', [AssociationController::class, 'destroy'])->name('association.destroy');


    // ------------------- GESTION DES ALERTES -------------------

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/turnoff/{voiture}', [AlertController::class, 'turnOff'])->name('alerts.turnoff');
    Route::post('/alerts/{alert}/mark-as-read', [AlertController::class, 'markAsRead'])->name('alerts.markAsRead');
    Route::post('/alerts/{alert}/mark-as-unread', [AlertController::class, 'markAsUnread'])->name('alerts.markAsUnread');
    
    // ------------------- REPORTS (CORRECTION DE L'ERREUR) -------------------
    // Ajoutez cette route pour corriger l'erreur Route [reports.index] not defined.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // ------------------- SETTINGS (PARAMÈTRES) -------------------
    Route::get('/settings', function () {
        return view('settings.index'); // Assurez-vous que cette vue existe
    })->name('settings.index');


    // ------------------- ROUTES BREEZE PAR DÉFAUT (Profile) -------------------
    // Ces routes sont ajoutées par Breeze pour la gestion du profil utilisateur
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// ----------------------------------------------------------------------
// 3. AUTHENTIFICATION BREEZE PAR DÉFAUT
// ----------------------------------------------------------------------

// Sauf si vous avez entièrement réécrit les formulaires de connexion/inscription de Breeze,
// il est préférable de conserver ce require qui charge les routes auth/register/password-reset
// de Laravel Breeze, en s'assurant qu'il est cohérent avec AgenceAuthController.
require __DIR__.'/auth.php';

// Route:get('/dashboard') de Breeze (supprimée car remplacée par la vôtre dans le groupe 'auth')
















<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>Connexion Employé - ProxyM Tracking</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
:root { --color-primary: #F58220; --font-family: 'Orbitron', sans-serif; }
.font-orbitron { font-family: var(--font-family); }
.light-mode { --color-bg:#f3f4f6; --color-card:#fff; --color-text:#111827; --color-input-bg:#fff; --color-input-border:#d1d5db; --color-secondary-text:#6b7280; color: var(--color-text);}
.light-mode .card-shadow { box-shadow:0 10px 30px rgba(0,0,0,0.1); border-color:#e5e7eb; background-color:var(--color-card);}
.light-mode .input-style { background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text);}
.light-mode .text-primary{color:var(--color-primary);}
.light-mode .text-secondary{color:var(--color-secondary-text);}
.dark-mode { --color-bg:#121212; --color-card:#1f2937; --color-text:#f3f4f6; --color-input-bg:#374151; --color-input-border:#4b5563; --color-secondary-text:#9ca3af; color: var(--color-text);}
.dark-mode .card-shadow { box-shadow:0 15px 40px rgba(0,0,0,0.5); border-color:#374151; background-color:var(--color-card);}
.dark-mode .input-style { background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text);}
.dark-mode .text-primary { color: var(--color-primary); }
.dark-mode .text-secondary { color: var(--color-secondary-text); }
.input-style:focus { border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px rgba(245,130,32,0.4);}
.btn-primary { background-color:var(--color-primary); transition:0.2s;}
.btn-primary:hover { background-color:#e06d12; transform:translateY(-1px);}
.toggle-switch { width:48px;height:24px;background:#4b5563;border-radius:9999px;position:relative;cursor:pointer;transition:0.4s;}
.toggle-switch.toggled { background: var(--color-primary);}
.toggle-switch::after { content:''; position:absolute; top:2px; left:2px;width:20px;height:20px;background:#fff;border-radius:9999px;transition:0.4s;}
.toggle-switch.toggled::after { transform: translateX(24px);}
</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 light-mode bg-image" id="theme-container">

<div class="w-full max-w-md mx-auto z-content">
    <!-- Toggle Mode -->
    <div class="flex justify-end mb-4">
        <span id="mode-label" class="text-sm mr-2 pt-0.5 text-secondary font-orbitron hidden md:block">Mode Clair</span>
        <div id="theme-toggle" class="toggle-switch"></div>
    </div>

    <!-- Carte Connexion -->
    <div class="card-shadow p-8 md:p-10 rounded-xl border">
        <header class="text-center mb-8">
            <div class="font-orbitron text-xl md:text-2xl font-extrabold">
                PROXYM <span class="text-primary">TRACKING</span>
            </div>
            <h1 class="font-orbitron text-2xl md:text-3xl font-bold mt-4">Connexion Employé</h1>
            <p class="text-sm text-secondary mt-1">Connectez-vous pour accéder à votre espace.</p>
        </header>

        <!-- Formulaire Dynamique -->
        <form id="login-form" class="space-y-6">
            <div>
                <label for="email" class="block text-sm font-medium font-orbitron">Email</label>
                <input type="email" id="email" name="email" required autofocus autocomplete="username"
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="votre.email@agence.com">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium font-orbitron">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="••••••••">
            </div>
            <div class="block">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" name="remember" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                    <span class="ms-2 text-sm text-secondary">Se souvenir de moi</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-6 pt-2">
                <a class="underline text-sm text-secondary hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" href="/forgot-password">
                    Mot de passe oublié ?
                </a>
                <button type="submit" class="ms-3 btn-primary text-white px-5 py-2 rounded-lg font-orbitron font-bold text-sm shadow-md shadow-orange-500/50">
                    Connexion
                </button>
            </div>
        </form>

        <div id="login-message" class="mt-6 p-4 rounded-lg hidden text-sm font-semibold font-orbitron" role="alert"></div>
    </div>
</div>

<script>
const themeContainer = document.getElementById('theme-container');
const themeToggle = document.getElementById('theme-toggle');
const modeLabel = document.getElementById('mode-label');
const loginForm = document.getElementById('login-form');
const loginMessage = document.getElementById('login-message');

// --- Thème ---
function setTheme(theme){
    if(theme==='dark'){
        themeContainer.classList.replace('light-mode','dark-mode');
        themeToggle.classList.add('toggled'); modeLabel.textContent='Mode Sombre';
    } else{
        themeContainer.classList.replace('dark-mode','light-mode');
        themeToggle.classList.remove('toggled'); modeLabel.textContent='Mode Clair';
    }
    localStorage.setItem('theme',theme);
}
themeToggle.addEventListener('click',()=>{setTheme(themeContainer.classList.contains('dark-mode')?'light':'dark');});
document.addEventListener('DOMContentLoaded',()=>setTheme(localStorage.getItem('theme')||'light'));

// --- Messages ---
function displayLoginMessage(msg,type='info'){
    const theme = themeContainer.classList.contains('dark-mode')?'dark':'light';
    loginMessage.textContent = msg; loginMessage.style.display='block';
    loginMessage.className='mt-6 p-4 rounded-lg text-sm font-semibold font-orbitron';
    if(type==='success') loginMessage.classList.add(...(theme==='dark'?['bg-green-700','text-green-100']:['bg-green-100','text-green-800']));
    else if(type==='error') loginMessage.classList.add(...(theme==='dark'?['bg-red-700','text-red-100']:['bg-red-100','text-red-800']));
    else loginMessage.classList.add(...(theme==='dark'?['bg-blue-700','text-blue-100']:['bg-blue-100','text-blue-800']));
}

// --- Soumission Login ---
loginForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    loginMessage.style.display='none';
    const formData = new FormData(loginForm);

    try {
        const response = await fetch('/login', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
            body: formData
        });

        const data = await response.json();

        if(response.ok && data.success){
            displayLoginMessage("✅ Connexion réussie ! Redirection en cours...", 'success');
            loginForm.reset();
            setTimeout(()=>{ window.location.href = data.redirect || '/dashboard'; }, 1500);
        } else if(data.errors){
            const messages = Object.values(data.errors).flat().join(' ');
            displayLoginMessage("❌ " + messages, 'error');
        } else {
            displayLoginMessage("❌ Identifiants incorrects.", 'error');
        }
    } catch(err){
        displayLoginMessage("❌ Une erreur est survenue lors de la connexion.", 'error');
    }
});
</script>
</body>
</html>

















@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-8 p-4 md:p-8">

    {{-- Bande de navigation secondaire --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4" style="border-color: var(--color-border-subtle);">
        <h1 class="text-3xl font-bold font-orbitron" style="color: var(--color-text);">Gestion des Véhicules</h1>
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('tracking.vehicles') }}" class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                <i class="fas fa-car mr-2"></i> Véhicules
            </a>
        </div>
    </div>

    {{-- Bouton pour basculer le formulaire --}}
    <div class="flex justify-end">
        <button id="toggle-form" class="btn-primary">
            <i class="fas fa-plus mr-2"></i>
            @if(isset($voitureEdit))
                Modifier le véhicule
            @else
                Ajouter un véhicule
            @endif
        </button>
    </div>

    {{-- Formulaire conditionnel pour ajout / modification --}}
    <div id="vehicle-form" class="ui-card mt-4 max-h-0 overflow-hidden opacity-0 transition-all duration-500 ease-in-out @if($errors->any() || isset($voitureEdit)) is-error-state @endif">
        <h2 class="text-xl font-bold font-orbitron mb-6">
            @if(isset($voitureEdit))
                Modifier un Véhicule
            @else
                Ajouter un Véhicule
            @endif
        </h2>

        {{-- Affichage des erreurs --}}
        @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <strong class="font-bold">Erreurs de validation:</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Formulaire --}}
        <form action="@if(isset($voitureEdit)) {{ route('tracking.vehicles.update', $voitureEdit->id) }} @else {{ route('tracking.vehicles.store') }} @endif" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if(isset($voitureEdit))
                @method('PUT')
            @endif

            {{-- Champs de base --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="immatriculation" class="block text-sm font-medium text-secondary">Immatriculation</label>
                    <input type="text" class="ui-input-style mt-1" id="immatriculation" name="immatriculation" placeholder="ABC-123-XY"
                        value="{{ old('immatriculation', $voitureEdit->immatriculation ?? '') }}" required>
                </div>
                <div>
                    <label for="model" class="block text-sm font-medium text-secondary">Modèle</label>
                    <input type="text" class="ui-input-style mt-1" id="model" name="model" placeholder="SUV, Berline, etc."
                        value="{{ old('model', $voitureEdit->model ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="couleur" class="block text-sm font-medium text-secondary">Couleur</label>
                    <input type="text" class="ui-input-style mt-1" id="couleur" name="couleur" placeholder="Noir, Blanc, Rouge..."
                        value="{{ old('couleur', $voitureEdit->couleur ?? '') }}" required>
                </div>
                <div>
                    <label for="marque" class="block text-sm font-medium text-secondary">Marque</label>
                    <input type="text" class="ui-input-style mt-1" id="marque" name="marque" placeholder="Toyota, Renault..."
                        value="{{ old('marque', $voitureEdit->marque ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mac_id_gps" class="block text-sm font-medium text-secondary">Numéro GPS</label>
                    <input type="text" class="ui-input-style mt-1" id="mac_id_gps" name="mac_id_gps" placeholder="GPS-XXXX-XXXX"
                        value="{{ old('mac_id_gps', $voitureEdit->mac_id_gps ?? '') }}" required>
                </div>
                <div>
                    <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                    <input type="file" class="ui-input-style mt-1" id="photo" name="photo">
                    @if(isset($voitureEdit) && $voitureEdit->photo)
                        <img src="{{ asset('storage/' . $voitureEdit->photo) }}" class="h-10 w-10 object-cover rounded mt-2">
                    @endif
                </div>
            </div>

            {{-- Géofence --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                <div>
                    <label for="geofence_latitude" class="block text-sm font-medium text-secondary">Latitude</label>
                    <input type="number" step="0.000001" id="geofence_latitude" name="geofence_latitude"
                        value="{{ old('geofence_latitude', $voitureEdit->geofence_latitude ?? 4.0500) }}" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="geofence_longitude" class="block text-sm font-medium text-secondary">Longitude</label>
                    <input type="number" step="0.000001" id="geofence_longitude" name="geofence_longitude"
                        value="{{ old('geofence_longitude', $voitureEdit->geofence_longitude ?? 9.7000) }}" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="geofence_radius" class="block text-sm font-medium text-secondary">Rayon (mètres)</label>
                    <input type="range" id="geofence_radius_slider" min="100" max="10000" step="100" value="{{ old('geofence_radius', $voitureEdit->geofence_radius ?? 1000) }}" class="mt-1 w-full">
                    <input type="number" id="geofence_radius" name="geofence_radius" min="100" max="100000000" step="100" value="{{ old('geofence_radius', $voitureEdit->geofence_radius ?? 1000) }}" class="ui-input-style mt-1">
                    <div class="flex justify-between text-xs text-secondary">
                        <span>100m</span>
                        <span>100000m</span>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-secondary mb-2">Choisir l'emplacement du véhicule</label>
                <div id="map" class="h-96 w-full rounded border border-gray-300"></div>
            </div>

            <button type="submit" class="btn-primary w-full mt-4">
                <i class="fas fa-save mr-2"></i>
                @if(isset($voitureEdit))
                    Mettre à jour le véhicule
                @else
                    Enregistrer le véhicule
                @endif
            </button>
        </form>
    </div>

    {{-- Liste des véhicules --}}
    <div class="ui-card mt-6">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste des Véhicules</h2>
        <div class="ui-table-container shadow-md">
            <table id="example" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>GPS</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    <tr>
                        <td>{{ $voiture->immatriculation }}</td>
                        <td>{{ $voiture->model }}</td>
                        <td>{{ $voiture->marque }}</td>
                        <td>{{ $voiture->couleur }}</td>
                        <td>{{ $voiture->mac_id_gps }}</td>
                        <td>
                            @if($voiture->photo)
                            <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo" class="h-10 w-10 object-cover rounded">
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('tracking.vehicles', ['edit' => $voiture->id]) }}" class="btn-secondary btn-edit">
                                <i class="fas fa-edit mr-2"></i> 
                            </a>
                            <form action="{{ route('tracking.vehicles.destroy', $voiture->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-delete" onclick="return confirm('Voulez-vous vraiment supprimer ce véhicule ?');">
                                    <i class="fas fa-trash mr-2"></i> 
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Scripts --}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // FORMULAIRE
    const form = document.getElementById('vehicle-form');
    const toggleBtn = document.getElementById('toggle-form');

    const latInput   = document.getElementById('geofence_latitude');
    const lngInput   = document.getElementById('geofence_longitude');
    const radiusInput = document.getElementById('geofence_radius');
    const radiusSlider = document.getElementById('geofence_radius_slider');

    const initialLat = parseFloat(latInput.value);
    const initialLng = parseFloat(lngInput.value);
    const initialRadius = parseInt(radiusInput.value);

    // ========= CORRECTION : DÉLAY POUR ÉVITER LA MULTIPLICATION DE LA CARTE =========
    let mapInitialized = false;
    let map, circle, marker;

    function initMap() {
        if (mapInitialized) return; // Empêche la création multiple
        mapInitialized = true;

        map = L.map('map');
        map.setView([initialLat, initialLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        circle = L.circle([initialLat, initialLng], {
            radius: initialRadius,
            color: '#4361ee',
            fillColor: '#4895ef',
            fillOpacity: 0.3,
            weight: 2
        }).addTo(map);

        marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);

        marker.on('drag', e => updateFields(e.latlng.lat, e.latlng.lng, parseInt(radiusInput.value)));
        map.on('click', e => updateFields(e.latlng.lat, e.latlng.lng, parseInt(radiusInput.value)));

        // Correction d'affichage
        setTimeout(() => map.invalidateSize(), 200);
    }

    function updateFields(lat, lng, radius) {
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
        radiusInput.value = radius;
        radiusSlider.value = radius;

        circle.setLatLng([lat, lng]);
        circle.setRadius(radius);
        marker.setLatLng([lat, lng]);
    }

    radiusInput.addEventListener('input', () =>
        updateFields(parseFloat(latInput.value), parseFloat(lngInput.value), parseInt(radiusInput.value))
    );

    radiusSlider.addEventListener('input', () =>
        updateFields(parseFloat(latInput.value), parseFloat(lngInput.value), parseInt(radiusSlider.value))
    );

    // ========= OUVERTURE DU FORMULAIRE =========
    toggleBtn.addEventListener('click', () => {
        const isHidden = form.classList.contains('max-h-0');

        if (isHidden) {
            form.classList.remove('max-h-0', 'opacity-0');
            form.classList.add('max-h-[2000px]', 'opacity-100');

            // Initialise la map SEULEMENT APRÈS l'animation (sinon bugs)
            setTimeout(() => {
                initMap();
                map.invalidateSize();
            }, 500);

        } else {
            form.classList.remove('max-h-[2000px]', 'opacity-100');
            form.classList.add('max-h-0', 'opacity-0');
        }
    });

    // Si on est en MODE ÉDIT → ouvrir directement le formulaire et charger la carte
    @if(isset($voitureEdit))
        initMap();
        setTimeout(() => map.invalidateSize(), 400);
    @endif

    // ========= DATATABLES =========
    const table = document.getElementById('example');
    if (table && window.jQuery && typeof $.fn.DataTable !== 'undefined') {
        $(table).DataTable({
            language: {
                url: "/datatables/i18n/fr-FR.json"
            }
        });
    }
});
</script>

@endsection














<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ProxymTracking Dashboard')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Chargement de Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Chargement de la police Orbitron depuis Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">


    @stack('head')

    <style>
    /* --- DESIGN SYSTEM : COULEURS ET POLICES --- */
    :root {
        --color-primary: #F58220;
        /* Orange vibrant */
        --color-primary-light: #FF9800;
        --color-primary-dark: #E65100;
        --font-family: 'Orbitron', sans-serif;
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 80px;
    }

    .font-orbitron {
        font-family: var(--font-family);
    }

    /* --- LIGHT MODE VARIABLES (Par Défaut) --- */
    .light-mode {
        --color-bg: #f3f4f6;
        --color-card: #ffffff;
        --color-text: #111827;
        --color-input-bg: #ffffff;
        --color-input-border: #d1d5db;
        --color-secondary-text: #6b7280;
        --color-sidebar-bg: #ffffff;
        --color-sidebar-text: #1f2937;
        --color-sidebar-active-bg: rgba(245, 130, 32, 0.1);
        --color-border-subtle: #e5e7eb;
        --color-navbar-bg: #ffffff;
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    /* --- DARK MODE VARIABLES --- */
    .dark-mode {
        --color-bg: #121212;
        /* Fond très sombre */
        --color-card: #1f2937;
        /* Cartes et boîtes de dialogue */
        --color-text: #f3f4f6;
        /* Texte clair */
        --color-input-bg: #374151;
        /* Fond des champs sombres */
        --color-input-border: #4b5563;
        /* Bordure des champs sombres */
        --color-secondary-text: #9ca3af;
        /* Texte secondaire */
        --color-sidebar-bg: #1f2937;
        /* Sidebar sombre */
        --color-sidebar-text: #f3f4f6;
        --color-sidebar-active-bg: rgba(245, 130, 32, 0.25);
        --color-border-subtle: #374151;
        /* Bordures sombres */
        --color-navbar-bg: #1f2937;
        /* Navbar sombre */
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    /* --- LAYOUT GÉNÉRAL --- */
    body {
        min-height: 100vh;
    }

    /* --- SIDEBAR STYLES --- */
    .sidebar {
        width: var(--sidebar-width);
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 20;
        transition: width 0.3s ease, transform 0.3s ease, background-color 0.3s;
        overflow-y: auto;
        border-right: 1px solid var(--color-border-subtle);
        padding-bottom: 5rem;
        background-color: var(--color-sidebar-bg);
    }

    /* Sidebar en mode rétracté (collapsed) */
    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    .sidebar.collapsed .logo-text,
    .sidebar.collapsed .title,
    .sidebar.collapsed .nav-dropdown,
    .sidebar.collapsed .profile-text {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.1s;
    }

    .sidebar.collapsed .dropdown-toggle .fa-chevron-right {
        display: none;
    }

    /* Logo et Texte du Branding */
    .brand {
        display: flex;
        align-items: center;
        padding: 1.5rem 1.5rem 2rem;
        white-space: nowrap;
        overflow: hidden;
        border-bottom: 1px solid var(--color-border-subtle);
    }

    .sidebar.collapsed .brand {
        padding: 1.5rem 0.5rem 2rem;
        justify-content: center;
        /* La ligne de séparation reste en mode rétracté sur desktop pour la cohérence */
    }

    .brand .icon {
        min-width: 48px;
    }

    .brand .logo-text {
        font-family: var(--font-family);
        font-weight: 800;
        font-size: 1.25rem;
        color: var(--color-primary);
        /* Le logo (texte) utilise la couleur primaire */
    }

    /* Liens de Navigation */
    .sidebar-nav a {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.5rem;
        margin: 0.25rem 0.5rem;
        color: var(--color-sidebar-text);
        transition: background-color 0.2s, color 0.2s;
        border-radius: 0.5rem;
        position: relative;
    }

    .sidebar.collapsed .sidebar-nav a {
        justify-content: center;
        padding: 0.75rem 0;
        margin: 0.25rem 0.5rem;
    }

    .sidebar-nav a:hover,
    .sidebar-nav a.active {
        background-color: var(--color-sidebar-active-bg);
        color: var(--color-primary);
    }

    .sidebar-nav a .icon {
        min-width: 48px;
        font-size: 1.1rem;
        text-align: center;
        color: var(--color-secondary-text);
    }

    .sidebar-nav a:hover .icon,
    .sidebar-nav a.active .icon {
        color: var(--color-primary);
    }

    /* Sous-menus (Dropdowns) */
    .nav-dropdown {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        padding-left: 2rem;
        background-color: var(--color-sidebar-bg);
        /* S'assure que le fond reste cohérent */
    }

    .nav-dropdown.open {
        max-height: 500px;
    }

    .nav-dropdown a {
        padding-left: 1.5rem;
        margin: 0.1rem 0.5rem;
        font-size: 0.9rem;
    }

    .sidebar.collapsed .nav-dropdown {
        display: none;
    }

    .dropdown-toggle .fa-chevron-right {
        position: absolute;
        right: 1.5rem;
        transition: transform 0.3s ease;
    }

    .dropdown-toggle.open .fa-chevron-right {
        transform: rotate(90deg);
    }


    /* --- MAIN CONTENT & NAVBAR ADAPTATION --- */
    .main-content {
        margin-left: var(--sidebar-width);
        transition: margin-left 0.3s ease;
        min-height: 100vh;
        padding-top: 5rem;
    }

    .main-content.expanded {
        margin-left: var(--sidebar-collapsed-width);
    }

    .navbar {
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        height: 5rem;
        z-index: 10;
        background-color: var(--color-navbar-bg);
        border-bottom: 1px solid var(--color-border-subtle);
        transition: left 0.3s ease, background-color 0.3s;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 2rem;
    }

    /* Adaptation de la navbar lorsque la sidebar est rétractée */
    .navbar.expanded {
        left: var(--sidebar-collapsed-width);
    }

    /* Dropdown Navbar (User Menu) */
    .user-dropdown-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 10px);
        z-index: 30;
        width: 200px;
        background-color: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        border-radius: 0.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: opacity 0.2s, transform 0.2s, visibility 0s 0.2s;
    }

    .user-dropdown-menu.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        transition: opacity 0.2s, transform 0.2s, visibility 0s;
    }

    .user-dropdown-menu a {
        display: block;
        padding: 0.75rem 1rem;
        color: var(--color-text);
        transition: background-color 0.2s;
    }

    .user-dropdown-menu a:hover {
        background-color: var(--color-sidebar-active-bg);
        color: var(--color-primary);
    }

    /* --- DARK MODE TOGGLE SWITCH STYLING (AJOUTÉ) --- */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 25px;
        cursor: pointer;
        border-radius: 12.5px;
        background-color: var(--color-input-border);
        transition: background-color 0.3s;
        flex-shrink: 0;
    }

    .toggle-switch::after {
        content: '';
        position: absolute;
        top: 3px;
        left: 3px;
        width: 19px;
        height: 19px;
        border-radius: 50%;
        background-color: var(--color-card);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        transition: transform 0.3s ease, background 0.3s;
    }

    .toggle-switch.toggled {
        background-color: var(--color-primary);
    }

    .toggle-switch.toggled::after {
        transform: translateX(25px);
        background-color: #ffffff;
    }

    /* Fin du style pour le Toggle Switch */


    /* --- STYLES DES COMPOSANTS UI (Tableaux, Formulaires, Cartes) --- */

    /* Carte/Conteneur */
    .ui-card {
        background-color: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        color: var(--color-text);
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    /* Champs de Formulaire / Input */
    .ui-input-style,
    .ui-textarea-style,
    .ui-select-style {
        background-color: var(--color-input-bg);
        border: 1px solid var(--color-input-border);
        color: var(--color-text);
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%;
    }

    .ui-input-style:focus,
    .ui-textarea-style:focus,
    .ui-select-style:focus {
        outline: none;
        border-color: var(--color-primary) !important;
        box-shadow: 0 0 0 3px rgba(245, 130, 32, 0.4);
    }

    /* Tableau */
    .ui-table-container {
        overflow-x: auto;
        border-radius: 0.5rem;
        border: 1px solid var(--color-border-subtle);
    }

    .ui-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ui-table th {
        font-family: var(--font-family);
        background-color: var(--color-border-subtle);
        /* Utilisation de la couleur de bordure/fond secondaire */
        color: var(--color-text);
        padding: 0.75rem 1rem;
        text-align: left;
        border-bottom: 2px solid var(--color-primary);
    }

    .ui-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--color-border-subtle);
    }

    .ui-table tr:hover {
        background-color: var(--color-sidebar-active-bg);
    }

    /* Styles des boutons (conservés) */
    .btn-primary {
        background-color: var(--color-primary);
        color: white;
        padding: 0.5rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.2s, transform 0.1s;
        font-family: var(--font-family);
    }

    .btn-primary:hover {
        background-color: var(--color-primary-dark);
        transform: translateY(-1px);
    }

    .btn-secondary {
        color: var(--color-primary);
        border: 1px solid var(--color-primary);
        padding: 0.5rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.2s;
        background-color: transparent;
        font-family: var(--font-family);
    }

    .btn-secondary:hover {
        background-color: rgba(245, 130, 32, 0.1);
    }

    /* Styles divers (conservés) */
    .navbar-icon-btn {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 1.1rem;
        color: var(--color-text);
        transition: background-color 0.2s, color 0.2s;
        cursor: pointer;
    }

    .navbar-icon-btn:hover {
        background-color: var(--color-sidebar-active-bg);
        color: var(--color-primary);
    }

    .text-primary {
        color: var(--color-primary);
    }

    .text-secondary {
        color: var(--color-secondary-text);
    }


    map {
        width: 100%;
        height: 400px;
        /* Hauteur fixe pour que Leaflet sache où dessiner */
        min-height: 300px;
    }

    /* Toggle formulaire */
    #vehicle-form.hidden {
        display: none;
    }




    /* --- MOBILE STYLES (md: 768px) --- */
    @media (max-width: 767px) {
        .sidebar {
            transform: translateX(-100%);
            width: var(--sidebar-width);
        }

        .sidebar.active {
            transform: translateX(0);
            box-shadow: 5px 0 10px rgba(0, 0, 0, 0.2);
        }

        .main-content {
            margin-left: 0;
        }

        .navbar {
            left: 0;
            padding-left: 5rem;
        }

        .toggle-sidebar {
            display: flex !important;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 19;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        /* Désactiver les classes 'expanded' sur mobile */
        .main-content.expanded,
        .navbar.expanded {
            margin-left: 0 !important;
            left: 0 !important;
        }
    }

    @media (min-width: 768px) {
        .toggle-sidebar {
            display: none !important;
        }

        .sidebar.collapsed .sidebar-nav a .icon {
            margin-left: -0.25rem;
        }
    }

    .toggle-sidebar {
        position: fixed;
        top: 1rem;
        left: 1rem;
        width: 40px;
        height: 40px;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        border-radius: 50%;
        cursor: pointer;
        z-index: 25;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        background-color: var(--color-card);
        color: var(--color-primary);
        border: 1px solid var(--color-primary);
        transition: background-color 0.2s, color 0.2s;
    }

    .toggle-sidebar:hover {
        background-color: var(--color-primary);
        color: var(--color-card);
    }
    </style>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

</head>

<body class="light-mode" id="theme-container">

    <!-- Sidebar Section -->
    <div class="sidebar" id="sidebar">
        <!-- Logo et Titre -->
        <div class="brand">
            <div class="icon">
                <!-- L'icône de logo utilise la couleur primaire, visible en mode sombre/clair -->
                <i class="fas fa-map-marker-alt" style="color: var(--color-primary); font-size: 24px;"></i>
            </div>
            <div class="logo-text">
                <img src="assets/images/logo_tracking.png" alt="">
            </div>
        </div>

        <!-- Bouton pour Rétracter la sidebar sur Desktop -->
        <div class="hidden md:flex justify-end px-4 mb-4">
            <button id="toggle-sidebar-desktop" class="navbar-icon-btn">
                <i class="fas fa-chevron-left transition-transform duration-300" id="toggle-icon-desktop"></i>
            </button>
        </div>

        <!-- Liens de Navigation -->
        <ul class="sidebar-nav">
            <li>
                <a href="{{ route('dashboard') ?? '#' }}" class="{{ request()->is('dashboard') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span class="title">Dashboard</span>
                </a>
            </li>

            <!-- Lien avec Sous-Menu (Module de Suivi) -->
            <li class="nav-item">
                <a href="#" class="dropdown-toggle" data-dropdown="tracking-menu">
                    <span class="icon"><i class="fas fa-satellite-dish"></i></span>
                    <span class="title">Suivi & Localisation</span>
                    <i class="fas fa-chevron-right text-xs ml-auto"></i>
                </a>
                <ul class="nav-dropdown" id="tracking-menu">
                    <li><a href="{{ route('tracking.users') ?? '#' }}"
                            class="{{ request()->is('tracking_users') ? 'active' : '' }}">Utilisateurs</a></li>
                    <li><a href="{{ route('tracking.vehicles') ?? '#' }}"
                            class="{{ request()->is('tracking.vehicles') ? 'active' : '' }}">Véhicules</a></li>
                    <li><a href="#" class="{{ request()->is('tracking.zones') ? 'active' : '' }}">Zones</a></li>
                </ul>
            </li>

            <li>
                <a href="{{ route('association.index') ?? '#' }}"
                    class="{{ request()->is('association*') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-link"></i></span>
                    <span class="title">Associations</span>
                </a>
            </li>
            <li>
                <a href="{{ route('alerts.view') ?? '#' }}"
                    class="{{ request()->routeIs('alerts.index') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span class="title">Alertes</span>
                </a>
            </li>
        </ul>

        <!-- Section Pied de page de la Sidebar (Déconnexion) -->
        <div class="absolute bottom-0 left-0 w-full p-2 border-t border-solid border-border-subtle"
            style="background-color: var(--color-sidebar-bg);">
            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                class="flex items-center p-2 rounded-lg text-secondary hover:text-red-500 transition-colors sidebar-logout-link"
                title="Déconnexion">
                <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
                <span class="title font-bold profile-text">Déconnexion</span>
            </a>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>

        </div>
    </div>

    <!-- Toggle Button for Mobile -->
    <div class="toggle-sidebar" id="toggle-btn">
        <i class="fas fa-bars"></i>
    </div>

    <!-- NAVBAR (Barre de Navigation Supérieure) -->
    <div class="navbar" id="navbar">

        <div class="flex-grow">
            <!-- Exemple de titre de page -->
            <h1 class="text-xl font-bold font-orbitron hidden sm:block" style="color: var(--color-text); font-size: 2rem;">@yield('title',
                'Dashboard')</h1>
        </div>

        <div class="flex items-center space-x-4">

            <!-- 1. Toggle Mode Sombre/Clair (TOUJOURS PRÉSENT) -->
            <div class="flex items-center">
                <span class="text-sm mr-2 pt-0.5 font-orbitron hidden lg:block"
                    style="color: var(--color-secondary-text);" id="mode-label">Mode Clair</span>
                <div id="theme-toggle" class="toggle-switch"></div>
            </div>

            <!-- 2. Notifications -->
            <div class="relative">
                <button class="navbar-icon-btn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="absolute top-1 right-1 h-2 w-2 bg-red-500 rounded-full border"
                        style="border-color: var(--color-card);"></span>
                </button>
                <!-- Menu de notifications (caché) -->
            </div>

            <!-- 3. Menu Utilisateur -->
            <div class="relative" id="user-menu-container">
                <button
                    class="flex items-center space-x-2 p-1 rounded-full hover:bg-sidebar-active-bg transition-colors"
                    id="user-menu-toggle">
                    <img src="https://placehold.co/36x36/F58220/ffffff?text=U" alt="Profile"
                        class="h-9 w-9 rounded-full object-cover border-2 border-primary">
                    <span class="font-semibold hidden lg:block profile-text" style="color: var(--color-text);">John
                        Doe</span>
                </button>

                <!-- Dropdown Utilisateur -->
                <div class="user-dropdown-menu" id="user-menu">
                    <div class="p-3 border-b" style="border-color: var(--color-border-subtle);">
                        <p class="font-semibold">John Doe</p>
                        <p class="text-xs text-secondary">john.doe@email.com</p>
                    </div>
                    <a href="{{ route('profile.edit') ?? '#' }}"><i class="fas fa-user-circle mr-2"></i> Mon Profil</a>
                    <a href="#"><i class="fas fa-cog mr-2"></i> Paramètres</a>
                    <a href="#" class="text-red-500"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt mr-2"></i> Déconnexion
                    </a>

                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>

                </div>
            </div>

        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="main-content" id="main-content">
        <!-- Contenu de la page -->
        <div class="p-8">
            <div class="page-content">
                @yield('content')
            </div>





        </div>

        <!-- Pour s'assurer que le bas de la page n'est pas caché par la barre de déconnexion de la sidebar -->
        <div class="h-10"></div>
    </div>

    <!-- JavaScript for Sidebar Toggle, Theme, and Dropdowns -->


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async></script>

    <script>
    $(function() { // équivalent de $(document).ready()
        if ($.fn.DataTable) { // Vérifie que DataTables est chargé
            $('#myTable').DataTable({
                responsive: true,
                processing: true,
                serverSide: false,
                language: {
                    url: "/datatables/i18n/fr-FR.json"
                }
            });
        } else {
            console.error("DataTables non chargé !");
        }
    });
    </script>

    <script>
    const themeContainer = document.getElementById('theme-container');
    const themeToggle = document.getElementById('theme-toggle');
    const modeLabel = document.getElementById('mode-label');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const toggleBtn = document.getElementById('toggle-btn');
    const navbar = document.getElementById('navbar');
    const desktopToggle = document.getElementById('toggle-sidebar-desktop');
    const desktopToggleIcon = document.getElementById('toggle-icon-desktop');

    // --- THÈME ---
    function setTheme(theme) {
        if (theme === 'dark') {
            themeContainer.classList.remove('light-mode');
            themeContainer.classList.add('dark-mode');
            themeToggle.classList.add('toggled');
            modeLabel.textContent = 'Mode Sombre';
        } else {
            themeContainer.classList.remove('dark-mode');
            themeContainer.classList.add('light-mode');
            themeToggle.classList.remove('toggled');
            modeLabel.textContent = 'Mode Clair';
        }
        localStorage.setItem('theme', theme);
    }

    function initTheme() {
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        const savedTheme = localStorage.getItem('theme') || (prefersDark ? 'dark' : 'light');
        setTheme(savedTheme);
    }

    themeToggle.addEventListener('click', () => {
        const currentTheme = themeContainer.classList.contains('dark-mode') ? 'dark' : 'light';
        setTheme(currentTheme === 'light' ? 'dark' : 'light');
    });

    // --- SIDEBAR : LOGIQUE D'ÉTAT PRINCIPAL (Desktop) ---

    function toggleSidebarDesktop() {
        // Applique l'état 'collapsed' à la sidebar et l'état 'expanded' au contenu/navbar
        const isCollapsed = sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded', isCollapsed);
        navbar.classList.toggle('expanded', isCollapsed);
        desktopToggleIcon.classList.toggle('rotate-180', !isCollapsed);
        localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
    }

    // --- SIDEBAR : LOGIQUE Mobile ---
    function toggleSidebarMobile() {
        const isActive = sidebar.classList.toggle('active');
        handleOverlay(isActive);
    }

    desktopToggle.addEventListener('click', toggleSidebarDesktop);
    toggleBtn.addEventListener('click', toggleSidebarMobile);

    // Gérer l'overlay (uniquement sur mobile)
    function handleOverlay(isActive) {
        let overlay = document.querySelector('.overlay');

        if (isActive && window.innerWidth <= 767) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'overlay';
                document.body.appendChild(overlay);

                overlay.addEventListener('click', () => {
                    toggleSidebarMobile();
                });
            }
            // Utiliser setTimeout pour s'assurer que l'animation est appliquée après l'ajout à la page
            setTimeout(() => {
                if (isActive) {
                    overlay.classList.add('active');
                } else {
                    overlay.classList.remove('active');
                    // Retirer l'overlay après la transition si besoin, mais le garder pour la performance
                }
            }, 10);
        } else if (overlay) {
            overlay.classList.remove('active');
        }
    }

    // --- SIDEBAR : LOGIQUE DE REDIMENSIONNEMENT ET INITIALISATION ---
    function handleResize() {
        const isDesktop = window.innerWidth >= 768; // Utiliser le breakpoint de Tailwind (md)
        const isMobile = window.innerWidth < 768;

        if (isMobile) {
            // Sur mobile, toujours masqué et désactiver l'état desktop
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
            navbar.classList.remove('expanded');
        } else {
            // Sur desktop/tablette, restaurer l'état mémorisé
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                navbar.classList.add('expanded');
                desktopToggleIcon.classList.add('rotate-180');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                navbar.classList.remove('expanded');
                desktopToggleIcon.classList.remove('rotate-180');
            }
            sidebar.classList.remove('active');
            handleOverlay(false); // S'assurer que l'overlay est retiré si on passe du mode mobile au mode desktop
        }
    }

    // --- GESTION DES SOUS-MENUS ---
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            const dropdownId = toggle.getAttribute('data-dropdown');
            const dropdown = document.getElementById(dropdownId);

            // Les sous-menus ne fonctionnent pas en mode rétracté
            if (dropdown && !sidebar.classList.contains('collapsed')) {
                // Fermer les autres menus ouverts
                document.querySelectorAll('.nav-dropdown.open').forEach(openDropdown => {
                    if (openDropdown.id !== dropdownId) {
                        openDropdown.classList.remove('open');
                        openDropdown.previousElementSibling.classList.remove('open');
                    }
                });

                // Ouvrir/Fermer le menu actuel
                dropdown.classList.toggle('open');
                toggle.classList.toggle('open');
            }
        });
    });

    // --- GESTION DU MENU UTILISATEUR (NAVBAR) ---
    const userMenuToggle = document.getElementById('user-menu-toggle');
    const userMenu = document.getElementById('user-menu');

    userMenuToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('open');
    });

    // Fermer le menu utilisateur lorsque l'on clique n'importe où
    document.addEventListener('click', (e) => {
        if (userMenu.classList.contains('open') && !userMenu.contains(e.target) && !userMenuToggle.contains(e
                .target)) {
            userMenu.classList.remove('open');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        initTheme();
        handleResize();
    });

    window.addEventListener('resize', handleResize);
    </script>

    @stack('scripts')

    @push('scripts')
    {{-- Si vous avez des scripts spécifiques à cette page, placez-les ici --}}
    @endpush

</body>

</html>














@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@section('content')
<div class="space-y-8">

    {{-- Statistiques --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Utilisateurs Actifs</p>
                <p class="text-3xl font-bold mt-1 text-primary">{{ $usersCount }}</p>
            </div>
            <div class="text-3xl text-primary opacity-70"><i class="fas fa-users"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Véhicules de la Flotte</p>
                <p class="text-3xl font-bold mt-1">{{ $vehiclesCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-car-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Associations Actives</p>
                <p class="text-3xl font-bold mt-1">{{ $associationsCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-link"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider">Alertes Non-résolues</p>
                <p class="text-3xl font-bold mt-1 text-red-500">{{ $alertsCount }}</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>

    {{-- Carte --}}
    <div class="ui-card">
        <h2 class="text-xl font-bold mb-4">Localisation de la Flotte Globale</h2>
        <div id="fleetMap" class="rounded-lg shadow-inner" style="height: 700px;"></div>
    </div>

    {{-- Tableau alertes --}}
    <div class="ui-card">
        <h2 class="text-xl font-bold mb-4">Historique des Dernières Alertes</h2>
        <table class="ui-table w-full">
            <thead>
                <tr>
                    <th>Véhicule</th>
                    <th>Type</th>
                    <th>Utilisateur</th>
                    <th>Heure</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alerts as $alert)
                    <tr>
                        <td>{{ $alert['vehicle'] }}</td>
                        <td>{{ $alert['type'] }}</td>
                        <td>{{ $alert['users'] ?? '-' }}</td>
                        <td>{{ $alert['time'] }}</td>
                        <td>
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $alert['status_color'] }}" style="color:white;">
                                {{ $alert['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

{{-- Google Maps --}}
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async></script>

<script>
let map;

function initMap() {
    const vehicles = @json($vehicles);

    map = new google.maps.Map(document.getElementById('fleetMap'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 7
    });

    const bounds = new google.maps.LatLngBounds();

    vehicles.forEach(v => {
        if(v.lat == null || v.lon == null) return; // ignore seulement si pas de coords

        const lat = parseFloat(v.lat);
        const lon = parseFloat(v.lon);

        // Icône par défaut bleu
        let iconUrl = 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png';

        const marker = new google.maps.Marker({
            position: { lat, lng: lon },
            map: map,
            title: v.immatriculation,
            icon: iconUrl
        });

        bounds.extend(marker.getPosition());

        // InfoWindow avec boutons GPS ON/OFF
        const infoWindow = new google.maps.InfoWindow({
            content: `
                <b>${v.immatriculation}</b><br>
                Status: ${v.status}<br>
                <button onclick="toggleGPS(${v.id}, true)" style="background:green;color:white;padding:2px 5px;margin:2px;border:none;border-radius:4px;">ON</button>
                <button onclick="toggleGPS(${v.id}, false)" style="background:red;color:white;padding:2px 5px;margin:2px;border:none;border-radius:4px;">OFF</button>
            `
        });

        marker.addListener('click', () => infoWindow.open(map, marker));
    });

    if(vehicles.length > 0) {
        map.fitBounds(bounds);

        const listener = google.maps.event.addListener(map, "idle", function() {
            if(map.getZoom() > 14) map.setZoom(14);
            google.maps.event.removeListener(listener);
        });
    }
}

// Fonction toggle GPS (à compléter côté backend)
function toggleGPS(vehicleId, turnOn) {
    fetch(`/vehicles/${vehicleId}/toggle-gps`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
        body: JSON.stringify({ on: turnOn })
    }).then(res => {
        if(res.ok) alert(`Véhicule ${turnOn ? 'activé' : 'désactivé'}`);
        else alert('Erreur lors du changement du GPS');
    });
}
</script>
@endsection






@extends('layouts.app')

@section('title', 'Profile')

@push('head')
    {{-- Google Maps API avec callback --}}
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async defer></script>
@endpush

@section('content')
<div class="space-y-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne 1 : Informations personnelles --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="ui-card p-6 flex flex-col items-center">
                <div class="relative group">
                    <img src="{{ $user->photo ?? 'https://placehold.co/120x120/F58220/ffffff?text=JD' }}" 
                         alt="Profile"
                         id="user-profile-img"
                         class="h-32 w-32 rounded-full object-cover border-4 border-primary shadow-lg mb-4 cursor-pointer transition-transform duration-200 hover:scale-105"
                         onclick="openImageModal(this.src)">
                </div>
                <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">{{ $user->prenom }} {{ $user->nom }}</h2>
                <p class="text-secondary">Propriétaire de Flotte</p>
                <button class="btn-secondary mt-4 py-2 px-4 text-sm font-normal">
                    <i class="fas fa-edit mr-2"></i> Mettre à jour les informations
                </button>
            </div>

            {{-- Détails du compte --}}
            <div class="ui-card p-6 space-y-4">
                <h3 class="text-lg font-semibold border-b pb-2" style="border-color: var(--color-border-subtle);">Détails du Compte</h3>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Prénom:</span><span class="text-primary font-semibold">{{ $user->prenom }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Nom:</span><span class="text-semibold">{{ $user->nom }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Téléphone:</span><span class="text-semibold">{{ $user->phone }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Email:</span><span class="text-semibold">{{ $user->email }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Identifiant:</span><span class="text-semibold">{{ $user->user_unique_id }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Ville:</span><span class="text-semibold">{{ $user->ville }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Quartier:</span><span class="text-semibold">{{ $user->quartier }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">CIN / Passport:</span><span class="text-semibold">{{ $user->cin ?? '-' }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Statut:</span><span class="font-semibold text-green-500">Actif</span></div>
            </div>
        </div>

        {{-- Colonne 2/3 : Carte et tableau --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Carte --}}
            <div class="ui-card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);" id="map-title">
                        Carte de Suivi : Tous Mes Véhicules
                    </h2>
                    <button id="showAllVehiclesBtn" class="btn-secondary py-1 px-3 text-sm font-normal hidden">
                        <i class="fas fa-list-ul mr-1"></i> Afficher Tous
                    </button>
                </div>
                <div id="userMap" class="rounded-lg shadow-inner" style="height:450px;"></div>
            </div>

            {{-- Tableau des véhicules --}}
            <div class="ui-card p-6">
                <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
                    Véhicules Associés ({{ $vehiclesCount }})
                </h2>
                <div class="ui-table-container shadow-md">
                    <table id="vehiclesTable" class="ui-table w-full">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Immatriculation</th>
                                <th>Marque/Modèle</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->voitures as $vehicle)
                            <tr id="row-{{ $vehicle->id }}">
                                <td>
                                    <div class="relative group w-10 h-10">
                                        <img src="{{ $vehicle->photo ?? 'https://placehold.co/40x40' }}" 
                                             class="w-10 h-10 object-cover rounded-md cursor-pointer border border-border-subtle transition-transform duration-200 hover:scale-105"
                                             onclick="openImageModal('{{ $vehicle->photo ?? '' }}')">
                                    </div>
                                </td>
                                <td>{{ $vehicle->immatriculation }}</td>
                                <td>{{ $vehicle->marque }} / {{ $vehicle->model }}</td>
                                <td>
                                   {{ $vehicle->couleur }}
                                </td>
                                <td>
                                    <button onclick="zoomToVehicle({{ $vehicle->id }})" class="text-primary hover:text-primary-dark transition-colors p-1">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </button>
                                    <button class="ml-3 text-secondary hover:text-red-500 transition-colors p-1">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Modal image --}}
<div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-75">
    <div class="relative bg-white rounded-lg shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden">
        <button id="closeModalBtn" class="absolute top-4 right-4 text-3xl font-bold text-white hover:text-primary transition-colors bg-gray-900 rounded-full h-10 w-10 flex items-center justify-center">&times;</button>
        <img id="modalImage" src="" alt="Image en grand plan" class="w-full h-auto object-contain max-h-[85vh] p-2">
    </div>
</div>
@endsection

@push('scripts')
<script>
let map;
let markers = [];
const vehiclesData = [
    @foreach($user->voitures as $vehicle)
    {
        id: {{ $vehicle->id }},
        immat: '{{ $vehicle->immatriculation }}',
        model: '{{ $vehicle->marque }} {{ $vehicle->model }}',
        lat: {{ $vehicle->latestLocation->latitude ?? 0 }},
        lng: {{ $vehicle->latestLocation->longitude ?? 0 }},
        status: '{{ $vehicle->latestLocation->status ?? "Arrêté" }}',
        photo_url: '{{ $vehicle->photo ?? "https://placehold.co/600x400" }}'
    },
    @endforeach
];

function initMap() {
    map = new google.maps.Map(document.getElementById('userMap'), {
        center: {lat: 36.82, lng: 10.20},
        zoom: 11
    });
    displayVehiclesOnMap(vehiclesData, true);
}

function getCarIcon(status){
    const color = status==='Alerte'?'red':(status==='En Mouvement'?'green':'yellow');
    return {
        url: '/assets/icons/car_icon_'+color+'.png', // Vérifie que les icônes existent
        scaledSize: new google.maps.Size(50, 50)
    };
}

function displayVehiclesOnMap(data, zoomToFit=false){
    markers.forEach(m=>m.setMap(null));
    markers=[];

    data.forEach(v=>{
        const marker = new google.maps.Marker({
            position:{lat:v.lat,lng:v.lng},
            map: map,
            title: `${v.model} (${v.immat})`,
            icon: getCarIcon(v.status)
        });

        const infowindow = new google.maps.InfoWindow({
            content: `
                <div style="font-size:14px;">
                    <b>${v.model} (${v.immat})</b><br>
                    Statut: ${v.status}<br>
                    <a href="#" onclick="zoomToVehicle(${v.id});return false;" style="color:blue; text-decoration:underline;">Localiser</a>
                </div>`
        });

        marker.addListener('click', ()=>infowindow.open(map, marker));
        markers.push(marker);
    });

    if(zoomToFit && data.length>0){
        const bounds = new google.maps.LatLngBounds();
        data.forEach(v=>bounds.extend({lat:v.lat,lng:v.lng}));
        map.fitBounds(bounds);
        const listener = google.maps.event.addListener(map, "idle", function() {
            if(map.getZoom() > 16) map.setZoom(16);
            google.maps.event.removeListener(listener);
        });
    }
}

function zoomToVehicle(vehicleId){
    const v = vehiclesData.find(x=>x.id===vehicleId);
    if(v){
        displayVehiclesOnMap([v], true);
        document.getElementById('map-title').textContent = `Suivi en Direct : ${v.model} (${v.immat})`;
        document.getElementById('showAllVehiclesBtn').classList.remove('hidden');
    }
}

document.getElementById('showAllVehiclesBtn').addEventListener('click', function(){
    displayVehiclesOnMap(vehiclesData,true);
    document.getElementById('map-title').textContent='Carte de Suivi : Tous Mes Véhicules';
    this.classList.add('hidden');
});

// --- Modal image ---
const imageModal = document.getElementById('imageModal');
const modalImage = document.getElementById('modalImage');
const closeModalBtn = document.getElementById('closeModalBtn');
window.openImageModal = function(url){
    modalImage.src = url;
    imageModal.classList.remove('hidden');
    imageModal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
closeModalBtn.addEventListener('click', function(){
    imageModal.classList.add('hidden');
    imageModal.classList.remove('flex');
    document.body.style.overflow = '';
});
imageModal.addEventListener('click', (e)=>{if(e.target.id==='imageModal'){closeModalBtn.click();}});
</script>
@endpush
