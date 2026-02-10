<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Users\TrackingUserController;
use App\Http\Controllers\AgenceAuthController;
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



//Route::get('/', function () {
//    return view('welcome');
//});








//Route::get('login', function () {
//    return view('auth.login');  // Vue de la page de connexion
//})->name('login');

Route::post('login', [AgenceAuthController::class, 'authenticate'])->name('login');

Route::middleware(['auth:web'])->group(function () {
    // Route pour la déconnexion
    Route::post('logout', [AgenceAuthController::class, 'logout'])->name('logout');

    // Routes protégées par authentification

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/vehicles/positions', [DashboardController::class, 'vehiclesPositions'])
    ->name('dashboard.vehicles.positions');



// Liste des véhicules
Route::prefix('tracking')->name('tracking.')->group(function() {
    Route::get('vehicles', [VoitureController::class, 'index'])->name('vehicles');
 
});
Route::get('/profile/vehicles/positions', [ProfileController::class, 'vehiclePositions'])
        ->name('profile.vehicles.positions');










//alerts
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
Route::post('/alerts/turnoff/{voiture}', [AlertController::class, 'turnOff'])->name('alerts.turnoff');



// 1. Route to show the page
Route::get('/add-vehicle', function () {
    return view('vehicles.create');
})->name('vehicles.add');

// 2. Route to save the vehicle (form POST)
Route::post('/save-vehicle', [\App\Http\Controllers\VehicleController::class, 'store'])->name('vehicles.save');




Route::get('/users/{id}/profile', [ProfileController::class, 'show'])
    ->name('users.profile');

    // Liste de toutes les alertes (JSON)
Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');


// Vue HTML des alertes
Route::get('/alerts/view', function () {
    return view('alerts.index'); // le nom du blade fourni
})->name('alerts.view');


//trajets
Route::get('/trajets', [TrajetController::class, 'index'])->name('trajets.index');
Route::get('/voitures/{id}/trajets', [TrajetController::class, 'byVoiture'])->name('voitures.trajets');

Route::get('/trajets/{vehicle_id}/detail/{trajet_id}', 
    [TrajetController::class, 'showTrajet'])
    ->name('voitures.trajet.detail');













});




Route::middleware('guest')->prefix('partner')->group(function () {

    // Send OTP
    Route::post('forgot-password/send', [VerifyLoginController::class, 'sendForgotOtp'])
        ->name('partner.password.otp.send');

    // Resend OTP
    Route::post('forgot-password/resend', [VerifyLoginController::class, 'resendForgotOtp'])
        ->name('partner.password.otp.resend');

    // Verify OTP
    Route::post('forgot-password/verify', [VerifyLoginController::class, 'verifyForgotOtp'])
        ->name('partner.password.otp.verify');

    // Reset form (GET)
    Route::get('reset-password/{token}', [VerifyLoginController::class, 'showResetForm'])
        ->name('partner.otp.password.reset');

    // Reset perform (POST)
    Route::post('reset-password', [VerifyLoginController::class, 'resetPassword'])
        ->name('partner.otp.password.reset.perform');
});



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';




Route::prefix('tests')->name('test.')->group(function () {
    Route::get('/profile', [TestController::class, 'profile'])->name('profile'); 
    Route::get('/dashboard', [TestController::class, 'dashboard'])->name('dashboard');        
    Route::get('/alert', [TestController::class, 'alert'])->name('alert');       
    Route::get('/alertcentre', [TestController::class, 'alertcentre'])->name('alert.centre');  
   
});












<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connexion Partner - ProxyM Tracking</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">

    <style>
    :root { --color-primary: #F58220; --font-family: 'Orbitron', sans-serif; }
    .font-orbitron { font-family: var(--font-family); }

    .bg-image{ background-size:cover; background-position:center; background-repeat:no-repeat; position:relative; background-attachment:fixed; z-index:0; }
    .bg-image::before{ content:''; position:absolute; inset:0; z-index:1; pointer-events:none; }
    .z-content { z-index:2; position:relative; }

    .light-mode{
        --color-bg:#f3f4f6; --color-card:#fff; --color-text:#111827;
        --color-input-bg:#fff; --color-input-border:#d1d5db; --color-secondary-text:#6b7280;
        color: var(--color-text);
    }
    .light-mode.bg-image{ background-image:url('{{ asset('assets/images/bgloginlight.png') }}'); background-color:#e5e7eb; }
    .light-mode.bg-image::before{ background-color: rgba(243,244,246,0.10); }
    .light-mode .card-shadow{ box-shadow:0 10px 30px rgba(0,0,0,0.10); border-color:#e5e7eb; background-color:var(--color-card); }
    .light-mode .input-style{ background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text); }
    .light-mode .text-primary{ color:var(--color-primary); }
    .light-mode .text-secondary{ color:var(--color-secondary-text); }

    .dark-mode{
        --color-bg:#121212; --color-card:#1f2937; --color-text:#f3f4f6;
        --color-input-bg:#374151; --color-input-border:#4b5563; --color-secondary-text:#9ca3af;
        color: var(--color-text);
    }
    .dark-mode.bg-image{ background-image:url('{{ asset('assets/images/bglogindarck.png') }}'); background-color:#121212; }
    .dark-mode.bg-image::before{ background-color: rgba(18,18,18,0.10); }
    .dark-mode .card-shadow{ box-shadow:0 15px 40px rgba(0,0,0,0.50); border-color:#374151; background-color:var(--color-card); }
    .dark-mode .input-style{ background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text); }
    .dark-mode .text-primary{ color:var(--color-primary); }
    .dark-mode .text-secondary{ color:var(--color-secondary-text); }

    .input-style:focus{ border-color: var(--color-primary) !important; box-shadow: 0 0 0 3px rgba(245,130,32,0.40); }
    .btn-primary{ background-color:var(--color-primary); transition:0.2s, transform 0.1s; color:#fff; padding:0.5rem 1.5rem; border-radius:0.5rem; font-weight:bold; }
    .btn-primary:hover{ background-color:#e06d12; transform: translateY(-1px); }

    .toggle-switch{ width:48px;height:24px;background:#4b5563;border-radius:9999px;position:relative;cursor:pointer;transition:0.4s; }
    .toggle-switch.toggled{ background: var(--color-primary); }
    .toggle-switch::after{ content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; background:#fff; border-radius:9999px; transition:0.4s; }
    .toggle-switch.toggled::after{ transform: translateX(24px); }

    .hidden-soft { display:none; }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-4 light-mode bg-image" id="theme-container">

@php
    // ✅ change ça si besoin (dashboard partner)
    $redirectAfterLogin = '/'; // ex: '/partner/dashboard'
@endphp

<div class="w-full max-w-md mx-auto z-content">

    <div class="flex justify-end mb-4">
        <span class="text-sm mr-2 pt-0.5 text-secondary font-orbitron hidden md:block" id="mode-label">Mode Clair</span>
        <div id="theme-toggle" class="toggle-switch"></div>
    </div>

    <div class="card-shadow p-8 md:p-10 rounded-xl border">

        <header class="text-center mb-8">
            <div class="font-orbitron text-xl md:text-2xl font-extrabold">
                PROXYM <span class="text-primary">TRACKING</span>
            </div>
            <h1 class="font-orbitron text-2xl md:text-3xl font-bold mt-4">Connexion Partner</h1>
            <p class="text-sm text-secondary mt-1">Connectez-vous pour accéder à votre espace.</p>
        </header>

        {{-- ✅ zone erreur/succès pilotée en JS --}}
        <div id="alertBox" class="hidden-soft mb-4 p-3 rounded text-sm"></div>

        {{-- ✅ FORM BRANCHÉ SUR API (pas route('login')) --}}
        <form id="loginForm">
            @csrf

            <div class="mb-4">
                <label for="login" class="block text-sm font-medium font-orbitron">Email ou téléphone</label>
                <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="votre.email@agence.com ou 690000000">
                <p class="text-xs text-secondary mt-1">Formats: 696..., 0696..., +237..., 237..., 00237...</p>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium font-orbitron">Mot de passe</label>
                <input type="password" id="password" name="password" required
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="••••••••">
            </div>

            <div class="block mb-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" name="remember"
                           class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary h-4 w-4">
                    <span class="ms-2 text-sm text-secondary">Se souvenir de moi</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-6 pt-2">
                <button type="button" id="forgot-open"
                        class="underline text-sm text-secondary hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    Mot de passe oublié ?
                </button>

                <button type="submit" id="loginBtn"
                        class="ms-3 btn-primary text-white px-5 py-2 rounded-lg font-orbitron font-bold text-sm shadow-md shadow-orange-500/50">
                    Connexion
                </button>
            </div>
        </form>

    </div>
</div>

{{-- ===================== MODALE 1: SEND OTP ===================== --}}
<div id="forgotModal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black/60" data-close="forgotModal"></div>

    <div class="relative z-10 w-full max-w-md card-shadow p-6 md:p-8 rounded-xl border">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="font-orbitron text-xl font-bold">Mot de passe oublié</h2>
                <p class="text-sm text-secondary mt-1">Saisissez votre email ou téléphone.</p>
            </div>
            <button type="button" class="text-secondary hover:text-primary text-2xl leading-none" data-close="forgotModal">×</button>
        </div>

        {{-- Ici tu peux garder tes routes web OTP si tu veux.
           Si tu as un endpoint API OTP, on le branche pareil en JS (je peux te le faire).
        --}}
        <form method="POST" action="{{ route('partner.password.otp.send') }}" class="space-y-4">
            @csrf
            <div>
                <label for="forgot_login" class="block text-sm font-medium font-orbitron">Email ou téléphone</label>
                <input type="text" id="forgot_login" name="login" value="{{ old('login') }}"
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="votre.email@agence.com ou 690000000" required>
                <p class="text-xs text-secondary mt-1">Formats acceptés : 696..., 0696..., +237..., 237...</p>
            </div>

            <button type="submit" class="w-full btn-primary font-orbitron">
                Envoyer le code
            </button>
        </form>
    </div>
</div>

{{-- ===================== MODALE 2: VERIFY + RESEND ===================== --}}
<div id="otpModal" class="fixed inset-0 hidden items-center justify-center z-50">
    <div class="absolute inset-0 bg-black/60" data-close="otpModal"></div>

    <div class="relative z-10 w-full max-w-md card-shadow p-6 md:p-8 rounded-xl border">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="font-orbitron text-xl font-bold">Code de vérification</h2>
                <p class="text-sm text-secondary mt-1">
                    @php
                        $pwdReset = session('partner_pwd_reset');
                        $maskedTo = is_array($pwdReset) ? ($pwdReset['masked_to'] ?? null) : null;
                    @endphp
                    @if($maskedTo)
                        Code envoyé à : <span class="font-semibold">{{ $maskedTo }}</span>
                    @else
                        Entrez le code reçu.
                    @endif
                </p>
            </div>
            <button type="button" class="text-secondary hover:text-primary text-2xl leading-none" data-close="otpModal">×</button>
        </div>

        @if(session('status') && session('partner_pwd_reset_modal'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->has('otp_code') && session('partner_pwd_reset_modal'))
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
                {{ $errors->first('otp_code') }}
            </div>
        @endif

        <form method="POST" action="{{ route('partner.password.otp.verify') }}" class="space-y-4">
            @csrf
            <label class="block text-sm font-medium font-orbitron">Code (6 chiffres)</label>
            <input type="text" inputmode="numeric" maxlength="6" name="otp_code"
                   class="mt-1 block w-full px-4 py-2 border rounded-lg input-style tracking-widest text-center text-lg"
                   placeholder="••••••" required>

            <button type="submit" class="w-full btn-primary font-orbitron">
                Vérifier le code
            </button>
        </form>

        <form method="POST" action="{{ route('partner.password.otp.resend') }}" class="mt-4">
            @csrf
            <button type="submit" class="underline text-sm text-secondary hover:text-primary">
                Renvoyer le code
            </button>
        </form>

        <p class="text-xs text-secondary mt-3">
            Si vous ne recevez rien, vérifiez le format du numéro (237...) et réessayez.
        </p>
    </div>
</div>

<script>
/* =========================
   THEME
========================= */
const themeContainer = document.getElementById('theme-container');
const themeToggle = document.getElementById('theme-toggle');
const modeLabel = document.getElementById('mode-label');

function setTheme(theme){
    if(theme === 'dark'){
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
themeToggle.addEventListener('click', () => {
    setTheme(themeContainer.classList.contains('dark-mode') ? 'light' : 'dark');
});
document.addEventListener('DOMContentLoaded', () => setTheme(localStorage.getItem('theme') || 'light'));

/* =========================
   MODALS
========================= */
function openModal(id){
    const el = document.getElementById(id);
    el.classList.remove('hidden');
    el.classList.add('flex');
}
function closeModal(id){
    const el = document.getElementById(id);
    el.classList.add('hidden');
    el.classList.remove('flex');
}
document.addEventListener('click', (e)=>{
    const closeId = e.target.getAttribute('data-close');
    if(closeId) closeModal(closeId);
});
document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape'){
        closeModal('forgotModal');
        closeModal('otpModal');
    }
});
document.getElementById('forgot-open')?.addEventListener('click', ()=> openModal('forgotModal'));

/* =========================
   HELPERS: Alert UI
========================= */
const alertBox = document.getElementById('alertBox');

function showAlert(type, msg) {
    alertBox.classList.remove('hidden-soft');
    alertBox.classList.remove('bg-red-100','text-red-800','bg-green-100','text-green-800','bg-yellow-100','text-yellow-800');
    if (type === 'success') alertBox.classList.add('bg-green-100','text-green-800');
    else if (type === 'warning') alertBox.classList.add('bg-yellow-100','text-yellow-800');
    else alertBox.classList.add('bg-red-100','text-red-800');
    alertBox.innerHTML = msg;
}

function hideAlert() {
    alertBox.classList.add('hidden-soft');
    alertBox.innerHTML = '';
}

/* =========================
   PHONE NORMALIZATION (CM)
   Objectif:
   - accepter +237, 237, 00237, 6XXXXXXXX, 06XXXXXXXX, 096XXXXXXXX etc
   - retourner:
      - E164: +2376XXXXXXXX
      - national: 6XXXXXXXX
========================= */
function normalizeCameroonPhone(raw) {
    if (!raw) return null;

    // enlever espaces, tirets, parenthèses, etc.
    let s = String(raw).trim().replace(/[^\d+]/g, '');

    // si c'est clairement un email => pas un téléphone
    if (s.includes('@')) return null;

    // enlever "00" international
    if (s.startsWith('00')) s = '+' + s.slice(2);

    // si commence par +, garder
    if (s.startsWith('+')) {
        // +237...
        if (s.startsWith('+237')) {
            let rest = s.slice(4);
            // enlever zéros en trop au début ex: +2370696...
            rest = rest.replace(/^0+/, '');
            // souvent on veut 9 chiffres au Cameroun, commençant par 6
            // on prend les 9 derniers si ça dépasse (cas données sales)
            if (rest.length > 9) rest = rest.slice(-9);
            if (rest.length === 9) return { e164: '+237' + rest, national: rest };
            return null;
        }
        // autre indicatif: pas géré
        return null;
    }

    // si commence par 237...
    if (s.startsWith('237')) {
        let rest = s.slice(3).replace(/^0+/, '');
        if (rest.length > 9) rest = rest.slice(-9);
        if (rest.length === 9) return { e164: '+237' + rest, national: rest };
        return null;
    }

    // si commence par 0 (ex: 0696..., 096...)
    if (s.startsWith('0')) {
        s = s.replace(/^0+/, ''); // enlever tous les 0 en tête
    }

    // à ce stade, si on a 9 chiffres, ok
    if (/^\d{9}$/.test(s)) {
        return { e164: '+237' + s, national: s };
    }

    // si l'utilisateur a tapé seulement 8-10+ chiffres, on tente d'extraire les 9 derniers
    const digitsOnly = s.replace(/\D/g,'');
    if (digitsOnly.length >= 9) {
        const last9 = digitsOnly.slice(-9);
        return { e164: '+237' + last9, national: last9 };
    }

    return null;
}

/* =========================
   API LOGIN BRANCH
========================= */
const API_LOGIN_URL = "/api/v1/auth/login";
const REDIRECT_AFTER_LOGIN = @json($redirectAfterLogin);

const loginForm = document.getElementById('loginForm');
const loginBtn = document.getElementById('loginBtn');

loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    hideAlert();

    loginBtn.disabled = true;
    const oldText = loginBtn.textContent;
    loginBtn.textContent = "Connexion...";

    try {
        const loginInput = document.getElementById('login').value.trim();
        const password = document.getElementById('password').value;

        if (!loginInput || !password) {
            showAlert('error', "Veuillez remplir tous les champs.");
            return;
        }

        // Si email => envoyer tel quel
        // Si téléphone => normaliser (E164 et national)
        let payload = { password };

        if (loginInput.includes('@')) {
            payload.login = loginInput;
        } else {
            const n = normalizeCameroonPhone(loginInput);
            if (!n) {
                showAlert('error', "Numéro invalide. Ex: 696..., 0696..., +237..., 237..., 00237...");
                return;
            }

            // On envoie plusieurs variantes pour matcher ta BD sale sans créer de colonne
            // Ton AuthController doit essayer dans cet ordre.
            payload.login = loginInput;           // brut (au cas où)
            payload.phone_e164 = n.e164;          // +2376XXXXXXXX
            payload.phone_national = n.national;  // 6XXXXXXXX
        }

        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const res = await fetch(API_LOGIN_URL, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',              // ✅ évite redirects HTML
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,                      // ok même si API stateless
            },
            body: JSON.stringify(payload),
        });

        // Essayer JSON, sinon texte
        let data = null;
        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) data = await res.json();
        else data = { message: await res.text() };

        if (!res.ok) {
            // Format robuste: message + errors
            const msg = data?.message || "Erreur de connexion.";
            const errors = data?.errors
                ? "<ul class='list-disc list-inside mt-2'>" + Object.values(data.errors).flat().map(e => `<li>${e}</li>`).join('') + "</ul>"
                : "";
            showAlert('error', msg + errors);
            return;
        }

        // ✅ On suppose que ton API renvoie: { token: "...", user: {...}, message?: "..." }
        if (!data?.token) {
            showAlert('error', "Connexion OK mais token absent. Vérifie la réponse de /api/v1/auth/login.");
            return;
        }

        // stock token
        localStorage.setItem('partner_token', data.token);
        if (data.user) localStorage.setItem('partner_user', JSON.stringify(data.user));

        showAlert('success', data?.message || "Connexion réussie. Redirection...");

        window.location.href = REDIRECT_AFTER_LOGIN;

    } catch (err) {
        showAlert('error', "Erreur réseau ou serveur. Vérifie que l’API répond en JSON.");
    } finally {
        loginBtn.disabled = false;
        loginBtn.textContent = oldText;
    }
});

/* AUTO OPEN (sessions) */
document.addEventListener('DOMContentLoaded', ()=>{
    @if(session('show_forgot'))
        openModal('forgotModal');
    @endif

    @if(session('partner_pwd_reset_modal'))
        openModal('otpModal');
    @endif
});
</script>

</body>
</html>
