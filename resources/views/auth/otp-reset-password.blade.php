<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Réinitialiser le mot de passe — Fleetra</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Rajdhani:wght@400;500;600;700&family=Lato:ital,wght@0,300;0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
    :root {
        --font-logo:    'Orbitron',  sans-serif;
        --font-display: 'Rajdhani', system-ui, sans-serif;
        --font-body:    'Lato', ui-sans-serif, system-ui, -apple-system, sans-serif;
        --font-mono:    ui-monospace, 'SFMono-Regular', Consolas, monospace;

        --color-primary:        #F58220;
        --color-primary-hover:  #E07318;
        --color-primary-dark:   #C45E00;
        --color-primary-light:  rgba(245,130,32,0.12);
        --color-primary-border: rgba(245,130,32,0.30);

        --color-success:    #16a34a;
        --color-success-bg: rgba(22,163,74,0.10);
        --color-error:      #dc2626;
        --color-error-bg:   rgba(220,38,38,0.10);

        --r-sm:   4px;
        --r-md:   6px;
        --r-lg:   8px;
        --r-xl:   12px;
        --r-2xl:  16px;
        --r-pill: 9999px;

        --shadow-sm: 0 2px 6px  rgba(0,0,0,0.08);
        --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
        --shadow-lg: 0 8px 32px rgba(0,0,0,0.14);
        --shadow-xl: 0 20px 60px rgba(0,0,0,0.20);

        --focus-ring: 0 0 0 3px rgba(245,130,32,0.40);
    }

    .light-mode {
        --color-bg:             #f0f2f5;
        --color-bg-subtle:      #e8eaed;
        --color-card:           #ffffff;
        --color-text:           #0f172a;
        --color-text-muted:     #64748b;
        --color-secondary-text: #64748b;
        --color-border-subtle:  #e2e8f0;
        --color-border:         #cbd5e1;
        --color-input-bg:       #ffffff;
        --color-input-border:   #cbd5e1;
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    .dark-mode {
        --color-bg:             #0d1117;
        --color-bg-subtle:      #161b22;
        --color-card:           #1c2333;
        --color-text:           #e6edf3;
        --color-text-muted:     #8b949e;
        --color-secondary-text: #b0bec5;
        --color-border-subtle:  #30363d;
        --color-border:         #484f58;
        --color-input-bg:       #21262d;
        --color-input-border:   #30363d;
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: var(--font-body);
        font-size: 0.875rem;
        line-height: 1.5;
        min-height: 100vh;
        margin: 0;
        -webkit-font-smoothing: antialiased;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        position: relative;
        overflow-x: hidden;
        transition: background-color 0.25s, color 0.25s;
    }

    .light-mode body,
    body.light-mode {
        background-image: url('{{ asset("assets/images/bgloginlight.png") }}');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
    }

    .dark-mode body,
    body.dark-mode {
        background-image: url('{{ asset("assets/images/bglogindarck.png") }}');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        transition: background-color 0.3s;
    }

    .light-mode::before { background-color: rgba(240,242,245,0.55); }
    .dark-mode::before  { background-color: rgba(13,17,23,0.65); }

    .login-wrapper {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 460px;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .topbar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .mode-label {
        font-family: var(--font-display);
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: var(--color-secondary-text);
        white-space: nowrap;
    }

    .toggle-switch {
        position: relative;
        width: 40px; height: 20px;
        cursor: pointer;
        border-radius: 10px;
        background: var(--color-input-border);
        transition: background 0.3s;
        flex-shrink: 0;
    }

    .toggle-switch::after {
        content: '';
        position: absolute;
        top: 2px; left: 2px;
        width: 16px; height: 16px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        transition: transform 0.3s ease;
    }

    .toggle-switch.on        { background: var(--color-primary); }
    .toggle-switch.on::after { transform: translateX(20px); }
    .toggle-switch:focus-visible  { box-shadow: var(--focus-ring); outline: none; }

    .login-card {
        background: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--r-2xl);
        padding: 2.5rem 2rem;
        box-shadow: var(--shadow-xl);
        transition: background 0.25s, border-color 0.25s, box-shadow 0.25s;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }

    .light-mode .login-card {
        background: rgba(255,255,255,0.88);
        box-shadow: 0 20px 60px rgba(0,0,0,0.12), 0 1px 0 rgba(255,255,255,0.9) inset;
    }

    .dark-mode .login-card {
        background: rgba(28,35,51,0.90);
        box-shadow: 0 20px 60px rgba(0,0,0,0.50), 0 1px 0 rgba(255,255,255,0.04) inset;
    }

    .card-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .logo-wrap {
        width: 80px; height: 80px;
        margin: 0 auto 1rem;
        border-radius: var(--r-xl);
        display: flex; align-items: center; justify-content: center;
        background: var(--color-primary-light);
        border: 1px solid var(--color-primary-border);
        box-shadow: 0 12px 30px rgba(245,130,32,0.20);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .logo-wrap:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 40px rgba(245,130,32,0.28);
    }

    .logo-wrap img {
        width: 52px; height: 52px;
        object-fit: contain;
        display: block;
    }

    .brand-title {
        font-family: var(--font-logo);
        font-weight: 800;
        font-size: 2.2rem;
        letter-spacing: 0.08em;
        line-height: 1;
        color: var(--color-primary);
        margin: 0 0 0.75rem;
    }

    .badge-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .badge {
        font-family: var(--font-display);
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        padding: 0.25rem 0.75rem;
        border-radius: var(--r-pill);
        white-space: nowrap;
    }

    .badge-neutral {
        background: rgba(255,255,255,0.06);
        border: 1px solid var(--color-border-subtle);
        color: var(--color-secondary-text);
    }

    .dark-mode .badge-neutral {
        background: rgba(255,255,255,0.05);
    }

    .badge-primary {
        background: var(--color-primary-light);
        border: 1px solid var(--color-primary-border);
        color: var(--color-primary);
    }

    .tagline {
        font-family: var(--font-body);
        font-size: 0.82rem;
        color: var(--color-secondary-text);
        margin: 0 0 1.25rem;
        line-height: 1.55;
    }

    .divider-gradient {
        height: 2px;
        width: 80px;
        margin: 0 auto;
        border-radius: var(--r-pill);
        background: linear-gradient(
            90deg,
            rgba(245,130,32,0),
            rgba(245,130,32,0.9),
            rgba(245,130,32,0)
        );
    }

    .alert {
        display: flex;
        align-items: flex-start;
        gap: 0.625rem;
        padding: 0.75rem 0.875rem;
        border-radius: var(--r-lg);
        margin-bottom: 1rem;
        font-family: var(--font-body);
        font-size: 0.78rem;
        line-height: 1.45;
        border-left-width: 3px;
        border-left-style: solid;
    }

    .alert i {
        font-size: 0.85rem;
        margin-top: 1px;
        flex-shrink: 0;
    }

    .alert-success {
        background: var(--color-success-bg);
        border-color: var(--color-success);
        color: var(--color-success);
    }

    .alert-error {
        background: var(--color-error-bg);
        border-color: var(--color-error);
        color: var(--color-error);
    }

    .alert ul {
        margin: 0;
        padding-left: 1rem;
    }

    .alert ul li {
        list-style: disc;
    }

    .form-group {
        margin-bottom: 1.125rem;
    }

    .form-label {
        display: block;
        font-family: var(--font-display);
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--color-secondary-text);
        margin-bottom: 0.375rem;
    }

    .input-wrap {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-secondary-text);
        font-size: 0.75rem;
        pointer-events: none;
        transition: color 0.15s;
    }

    .form-input {
        width: 100%;
        background: var(--color-input-bg);
        border: 1px solid var(--color-input-border);
        color: var(--color-text);
        border-radius: var(--r-md);
        padding: 0.55rem 0.875rem 0.55rem 2.25rem;
        font-family: var(--font-body);
        font-size: 0.82rem;
        transition: border-color 0.15s, box-shadow 0.2s, transform 0.12s, opacity 0.2s;
        appearance: none;
        outline: none;
        min-height: 40px;
    }

    .form-input::placeholder {
        color: var(--color-secondary-text);
        opacity: 0.65;
    }

    .form-input:focus {
        border-color: var(--color-primary);
        box-shadow: var(--focus-ring), 0 8px 18px rgba(245,130,32,0.12);
        transform: translateY(-1px);
    }

    .form-input:focus + .input-icon,
    .input-wrap:focus-within .input-icon {
        color: var(--color-primary);
    }

    .input-hint {
        font-family: var(--font-body);
        font-size: 0.65rem;
        color: var(--color-secondary-text);
        margin-top: 0.3rem;
        opacity: 0.8;
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        background: var(--color-primary);
        color: #fff;
        padding: 0.55rem 1.25rem;
        border-radius: var(--r-md);
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 0.82rem;
        letter-spacing: 0.04em;
        border: none;
        cursor: pointer;
        min-height: 40px;
        white-space: nowrap;
        text-decoration: none;
        transition: background 0.15s, transform 0.1s, box-shadow 0.15s, opacity 0.2s;
        box-shadow: 0 4px 14px rgba(245,130,32,0.30);
    }

    .btn-primary:hover {
        background: var(--color-primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(245,130,32,0.40);
    }

    .btn-primary:active {
        transform: none;
        box-shadow: none;
    }

    .btn-primary:focus-visible {
        outline: none;
        box-shadow: var(--focus-ring);
    }

    .btn-primary-full {
        width: 100%;
        padding: 0.65rem 1.25rem;
        min-height: 44px;
        font-size: 0.88rem;
    }

    .btn-ghost {
        background: transparent;
        border: none;
        padding: 0;
        color: var(--color-secondary-text);
        font-family: var(--font-display);
        font-size: 0.72rem;
        font-weight: 600;
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 3px;
        transition: color 0.15s;
    }

    .btn-ghost:hover {
        color: var(--color-primary);
    }

    .form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 1.5rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--color-border-subtle);
    }

    :focus { outline: none; }

    :focus-visible {
        outline: 2px solid var(--color-primary);
        outline-offset: 2px;
        border-radius: var(--r-sm);
    }

    @keyframes cardIn {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: none; }
    }

    .login-card { animation: cardIn 0.35s ease both; }
    .topbar     { animation: cardIn 0.25s ease both; }

    @media (max-width: 560px) {
        .login-card {
            padding: 2rem 1.25rem;
        }

        .brand-title {
            font-size: 1.9rem;
        }

        .form-footer {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .btn-primary,
        .btn-primary-full {
            width: 100%;
        }

        .btn-ghost {
            text-align: center;
        }
    }
    </style>
</head>

<body class="light-mode" id="app-root">

<div class="login-wrapper">
    <div class="topbar">
        <span class="mode-label" id="mode-label" aria-hidden="true">Mode Clair</span>
        <div id="theme-toggle"
             class="toggle-switch"
             role="switch"
             aria-checked="false"
             aria-label="Basculer entre mode clair et sombre"
             tabindex="0"
             title="Changer de thème"></div>
    </div>

    <div class="login-card">
        <div class="card-header">
            <div class="logo-wrap">
                <img src="{{ asset('assets/images/logo_tracking.png') }}" alt="Logo Fleetra">
            </div>

            <h1 class="brand-title">Fleetra</h1>

            <div class="badge-row">
                <span class="badge badge-neutral">
                    By <strong>Proxym Group</strong>
                </span>
                <span class="badge badge-primary">
                    <i class="fas fa-shield-halved" style="font-size:.6rem;margin-right:.25rem"></i>
                    Espace Partenaire
                </span>
            </div>

            <p class="tagline">
                Définissez un nouveau mot de passe pour sécuriser votre accès<br>
                à votre espace partenaire Fleetra.
            </p>

            <div class="divider-gradient"></div>
        </div>

        @if(session('status'))
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i>
            <span>{{ session('status') }}</span>
        </div>
        @endif

        @if($errors->any())
        <div class="alert alert-error" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <ul>
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('partner.otp.password.reset.perform') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="password" class="form-label">Nouveau mot de passe</label>
                <div class="input-wrap">
                    <input type="password"
                           id="password"
                           name="password"
                           required
                           autocomplete="new-password"
                           class="form-input"
                           placeholder="••••••••">
                    <i class="fas fa-lock input-icon"></i>
                </div>
                <div class="input-hint">
                    Minimum 8 caractères.
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirmer le mot de passe</label>
                <div class="input-wrap">
                    <input type="password"
                           id="password_confirmation"
                           name="password_confirmation"
                           required
                           autocomplete="new-password"
                           class="form-input"
                           placeholder="••••••••">
                    <i class="fas fa-shield-keyhole input-icon"></i>
                </div>
            </div>

            <div class="form-footer">
                <a href="{{ route('login') }}" class="btn-ghost">
                    <i class="fas fa-arrow-left" style="margin-right:.3rem;font-size:.65rem"></i>
                    Retour à la connexion
                </a>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-floppy-disk"></i>
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var ROOT   = document.getElementById('app-root');
    var TOGGLE = document.getElementById('theme-toggle');
    var LABEL  = document.getElementById('mode-label');

    function applyTheme(theme) {
        var dark = (theme === 'dark');

        ROOT.classList.toggle('dark-mode', dark);
        ROOT.classList.toggle('light-mode', !dark);

        if (TOGGLE) {
            TOGGLE.classList.toggle('on', dark);
            TOGGLE.setAttribute('aria-checked', String(dark));
        }

        if (LABEL) {
            LABEL.textContent = dark ? 'Mode Sombre' : 'Mode Clair';
        }

        localStorage.setItem('fleetra-theme', theme);
    }

    function initTheme() {
        var saved = localStorage.getItem('fleetra-theme');

        if (!saved) {
            saved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        applyTheme(saved);
    }

    if (TOGGLE) {
        TOGGLE.addEventListener('click', function () {
            applyTheme(ROOT.classList.contains('dark-mode') ? 'light' : 'dark');
        });

        TOGGLE.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                TOGGLE.click();
            }
        });
    }

    if (window.matchMedia) {
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', function (e) {
                if (!localStorage.getItem('fleetra-theme')) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(function (e) {
                if (!localStorage.getItem('fleetra-theme')) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
    });
})();
</script>
</body>
</html>