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

  /* base (ajoute ça) */
.input-style{
  transition: border-color .2s ease, box-shadow .25s ease, transform .12s ease;
}

/* focus premium orange */
.input-style:focus{
  outline: none; /* important */
  border-color: var(--color-primary) !important;

  /* halo + ombre */
  box-shadow:
    0 0 0 4px rgba(245,130,32,0.35),  /* glow */
    0 10px 22px rgba(245,130,32,0.18); /* profondeur */

  transform: translateY(-1px);
}
    .btn-primary{ background-color:var(--color-primary); transition:0.2s, transform 0.1s; color:#fff; padding:0.5rem 1.5rem; border-radius:0.5rem; font-weight:bold; }
    .btn-primary:hover{ background-color:#e06d12; transform: translateY(-1px); }

    .toggle-switch{ width:48px;height:24px;background:#4b5563;border-radius:9999px;position:relative;cursor:pointer;transition:0.4s; }
    .toggle-switch.toggled{ background: var(--color-primary); }
    .toggle-switch::after{ content:''; position:absolute; top:2px; left:2px; width:20px; height:20px; background:#fff; border-radius:9999px; transition:0.4s; }
    .toggle-switch.toggled::after{ transform: translateX(24px); }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen p-4 light-mode bg-image" id="theme-container">

<div class="w-full max-w-md mx-auto z-content">

    <div class="flex justify-end mb-4">
        <span class="text-sm mr-2 pt-0.5 text-secondary font-orbitron hidden md:block" id="mode-label">Mode Clair</span>
        <div id="theme-toggle" class="toggle-switch"></div>
    </div>

    <div class="card-shadow p-8 md:p-10 rounded-xl border">

       <header class="text-center mb-8">
    <!-- Logo -->
    <div class="mx-auto mb-4 w-20 h-20 md:w-24 md:h-24 rounded-2xl flex items-center justify-center"
         style="background: rgba(245,130,32,0.12); border:1px solid rgba(245,130,32,0.28); box-shadow: 0 12px 30px rgba(245,130,32,0.18);">
        <img src="{{ asset('assets/images/logo_tracking.png') }}"
             alt="Fleetra"
             class="w-14 h-14 md:w-16 md:h-16 object-contain">
    </div>

    <!-- Brand -->
    <h1 class="font-orbitron font-extrabold tracking-wide leading-none">
        <span class="text-primary text-3xl md:text-5xl">Fleetra</span>
    </h1>

    <!-- Tagline / Signature -->
    <div class="mt-3 flex items-center justify-center gap-2">
        <span class="text-xs md:text-sm px-3 py-1 rounded-full"
              style="background: rgba(255,255,255,0.06); border: 1px solid var(--color-border-subtle); color: var(--color-secondary-text);">
            By <span class="font-semibold">Proxym Group</span>
        </span>

        <span class="text-xs md:text-sm px-3 py-1 rounded-full"
              style="background: rgba(245,130,32,0.12); color: var(--color-primary); border: 1px solid rgba(245,130,32,0.25);">
            Espace Partenaire
        </span>
    </div>

    <!-- Message -->
    <p class="mt-4 text-sm md:text-base text-secondary">
        Connectez-vous pour accéder à votre tableau de bord et suivre votre activité en temps réel.
    </p>

    <!-- Divider -->
    <div class="mt-6 mx-auto h-[2px] w-24 rounded-full"
         style="background: linear-gradient(90deg, rgba(245,130,32,0), rgba(245,130,32,0.9), rgba(245,130,32,0));">
    </div>
</header>

        @if(session('status') && !session('partner_pwd_reset_modal'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">
                {{ session('status') }}
            </div>
        @endif

        {{-- Erreurs login “classiques” (hors modales OTP) --}}
        @if($errors->any() && !session('partner_pwd_reset_modal') && !session('show_forgot'))
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ⚠️ adapte la route du login partenaire si besoin --}}
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-4">
                <label for="login" class="block text-sm font-medium font-orbitron">Email ou téléphone</label>
                <input type="text" id="login" name="login" value="{{ old('login') }}" required autofocus
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="votre.email@agence.com ou 690000000">
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-medium font-orbitron">Mot de passe</label>
                <input type="password" id="password" name="password" required
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="••••••••">
            </div>



            <div class="flex items-center justify-end mt-6 pt-2">
                <button type="button" id="forgot-open"
                        class="underline text-sm text-secondary hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    Mot de passe oublié ?
                </button>

                <button type="submit"
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

        @if($errors->has('login') && session('show_forgot'))
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
                {{ $errors->first('login') }}
            </div>
        @endif

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
/* THEME */
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

/* MODALS */
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
