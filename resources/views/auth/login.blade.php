<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion Employé - ProxyM Tracking</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
<style>
:root { --color-primary: #F58220; --font-family: 'Orbitron', sans-serif; }
.font-orbitron { font-family: var(--font-family); }

/* Fond dynamique */
.bg-image { background-size: cover; background-position: center; background-repeat: no-repeat; position: relative; background-attachment: fixed; z-index: 0; }
.bg-image::before { content: ''; position: absolute; top:0; left:0; right:0; bottom:0; z-index:1; pointer-events:none; }
.z-content { z-index: 2; position: relative; }

/* Mode clair */
.light-mode { --color-bg:#f3f4f6; --color-card:#fff; --color-text:#111827; --color-input-bg:#fff; --color-input-border:#d1d5db; --color-secondary-text:#6b7280; color: var(--color-text);}
.light-mode.bg-image { background-image: url('assets/images/bgloginlight.png'); background-color: #e5e7eb; }
.light-mode.bg-image::before { background-color: rgba(243,244,246,0.1); }
.light-mode .card-shadow { box-shadow:0 10px 30px rgba(0,0,0,0.1); border-color:#e5e7eb; background-color:var(--color-card); }
.light-mode .input-style { background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text); }
.light-mode .text-primary{color:var(--color-primary);} .light-mode .text-secondary{color:var(--color-secondary-text);}

/* Mode sombre */
.dark-mode { --color-bg:#121212; --color-card:#1f2937; --color-text:#f3f4f6; --color-input-bg:#374151; --color-input-border:#4b5563; --color-secondary-text:#9ca3af; color: var(--color-text);}
.dark-mode.bg-image { background-image: url('assets/images/bglogindarck.png'); background-color: #121212; }
.dark-mode.bg-image::before { background-color: rgba(18,18,18,0.1); }
.dark-mode .card-shadow { box-shadow:0 15px 40px rgba(0,0,0,0.5); border-color:#374151; background-color:var(--color-card); }
.dark-mode .input-style { background-color:var(--color-input-bg); border-color:var(--color-input-border); color:var(--color-text); }
.dark-mode .text-primary { color: var(--color-primary); } .dark-mode .text-secondary { color: var(--color-secondary-text); }

/* Commun */
.input-style:focus { border-color: var(--color-primary) !important; box-shadow:0 0 0 3px rgba(245,130,32,0.4); }
.btn-primary { background-color:var(--color-primary); transition:0.2s, transform 0.1s; color:#fff; padding:0.5rem 1.5rem; border-radius:0.5rem; font-weight:bold;}
.btn-primary:hover { background-color:#e06d12; transform:translateY(-1px);}
.toggle-switch { width:48px;height:24px;background:#4b5563;border-radius:9999px;position:relative;cursor:pointer;transition:0.4s;}
.toggle-switch.toggled { background: var(--color-primary);}
.toggle-switch::after { content:''; position:absolute; top:2px; left:2px;width:20px;height:20px;background:#fff;border-radius:9999px;transition:0.4s;}
.toggle-switch.toggled::after { transform: translateX(24px);}
</style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 light-mode bg-image" id="theme-container">

<div class="w-full max-w-md mx-auto z-content">

    <!-- Toggle Switch -->
    <div class="flex justify-end mb-4">
         <span class="text-sm mr-2 pt-0.5 text-secondary font-orbitron hidden md:block" id="mode-label">Mode Clair</span>
        <div id="theme-toggle" class="toggle-switch"></div>
    </div>

    <!-- Carte Connexion -->
    <div class="card-shadow p-8 md:p-10 rounded-xl border">

        <!-- En-tête -->
        <header class="text-center mb-8">
            <div class="font-orbitron text-xl md:text-2xl font-extrabold">PROXYM <span class="text-primary">TRACKING</span></div>
            <h1 class="font-orbitron text-2xl md:text-3xl font-bold mt-4">Connexion Employé</h1>
            <p class="text-sm text-secondary mt-1">Connectez-vous pour accéder à votre espace.</p>
        </header>

        <!-- Messages Laravel -->
        @if(session('status'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Formulaire -->
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium font-orbitron">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 block w-full px-4 py-2 border rounded-lg input-style"
                       placeholder="votre.email@agence.com">
            </div>
            <div>
                <label for="password" class="block text-sm font-medium font-orbitron">Mot de passe</label>
                <input type="password" id="password" name="password" required
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
                <a class="underline text-sm text-secondary hover:text-primary rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary" href="{{ route('password.request') }}">
                    Mot de passe oublié ?
                </a>
                <button type="submit" class="ms-3 btn-primary text-white px-5 py-2 rounded-lg font-orbitron font-bold text-sm shadow-md shadow-orange-500/50">
                    Connexion
                </button>
            </div>
        </form>

    </div>
</div>

<script>
const themeContainer = document.getElementById('theme-container');
const themeToggle = document.getElementById('theme-toggle');
const modeLabel = document.getElementById('mode-label');
function setTheme(theme){
    if(theme==='dark'){themeContainer.classList.remove('light-mode');themeContainer.classList.add('dark-mode');themeToggle.classList.add('toggled');modeLabel.textContent='Mode Sombre';}
    else{themeContainer.classList.remove('dark-mode');themeContainer.classList.add('light-mode');themeToggle.classList.remove('toggled');modeLabel.textContent='Mode Clair';}
    localStorage.setItem('theme',theme);
}
themeToggle.addEventListener('click',()=>{setTheme(themeContainer.classList.contains('dark-mode')?'light':'dark');});
document.addEventListener('DOMContentLoaded',()=>setTheme(localStorage.getItem('theme')||'light'));
</script>
</body>
</html>
