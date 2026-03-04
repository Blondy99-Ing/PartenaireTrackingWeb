<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Fleetra') — Fleetra</title>

    {{--
    ╔══════════════════════════════════════════════════════════════╗
    ║  FONTS                                                       ║
    ║  Orbitron  → logo uniquement                                 ║
    ║  Rajdhani  → titres, menus, labels, KPI, badges              ║
    ║  Lato      → corps de texte, tableaux, descriptions          ║
    ║  display=swap → évite le FOUT bloquant le rendu              ║
    ╚══════════════════════════════════════════════════════════════╝
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&family=Rajdhani:wght@400;500;600;700&family=Lato:ital,wght@0,300;0,400;0,700;1,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

    @stack('head')

    <style>
      /* ════════════════════════════════════════════════════════════════
       SECTION 1 — DESIGN TOKENS
       Source de vérité pour toute l'interface
    ════════════════════════════════════════════════════════════════ */
    :root {
        font-size: 18px;

        /* ── Typographie ─────────────────────────────────────────── */
        --font-logo:    'Orbitron',  sans-serif;
        --font-display: 'Rajdhani', system-ui, sans-serif;
        --font-body:    'Lato',     ui-sans-serif, system-ui, -apple-system,
                        BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
        --font-mono:    ui-monospace, 'SFMono-Regular', Consolas, monospace;

        /* Échelle fluide 375px → 1440px */
        --text-xs:   clamp(0.625rem, 0.9vw,  0.688rem);
        --text-sm:   clamp(0.688rem, 1vw,    0.75rem);
        --text-base: clamp(0.75rem,  1.1vw,  0.875rem);
        --text-md:   clamp(0.875rem, 1.2vw,  1rem);
        --text-lg:   clamp(1rem,     1.5vw,  1.25rem);
        --text-xl:   clamp(1.25rem,  2vw,    1.75rem);
        --text-kpi:  clamp(1.5rem,   2.5vw,  2rem);

        --lh-tight:  1.1;
        --lh-snug:   1.3;
        --lh-normal: 1.5;

        --ls-tight:  -0.01em;
        --ls-normal:  0;
        --ls-wide:    0.04em;
        --ls-wider:   0.08em;
        --ls-widest:  0.12em;

        /* ── Couleurs Brand ──────────────────────────────────────── */
        --color-primary:         #F58220;
        --color-primary-hover:   #E07318;
        --color-primary-dark:    #C45E00;
        --color-primary-light:   rgba(245, 130, 32, 0.12);
        --color-primary-border:  rgba(245, 130, 32, 0.30);

        /* Sémantiques */
        --color-success:         #16a34a;
        --color-success-bg:      rgba(22, 163, 74, 0.10);
        --color-error:           #dc2626;
        --color-error-bg:        rgba(220, 38, 38, 0.10);
        --color-warning:         #d97706;
        --color-warning-bg:      rgba(217, 119, 6, 0.10);
        --color-info:            #2563eb;
        --color-info-bg:         rgba(37, 99, 235, 0.10);

        /* ── Radius system ───────────────────────────────────────── */
        --r-none: 0;
        --r-xs:   2px;
        --r-sm:   4px;
        --r-md:   6px;
        --r-lg:   8px;
        --r-xl:   12px;
        --r-pill: 9999px;

        /* ── Ombres ──────────────────────────────────────────────── */
        --shadow-xs: 0 1px 2px  rgba(0,0,0,0.06);
        --shadow-sm: 0 2px 6px  rgba(0,0,0,0.08);
        --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
        --shadow-lg: 0 8px 32px rgba(0,0,0,0.14);
        --shadow-xl: 0 20px 60px rgba(0,0,0,0.20);

        /* ── Focus ring (WCAG 2.4.7) ─────────────────────────────── */
        --focus-ring: 0 0 0 3px rgba(245, 130, 32, 0.40);

        /* ── Layout ──────────────────────────────────────────────── */
        --navbar-h:          64px;   /* override par JS après mesure réelle */
        --kpi-h:             0px;    /* override par JS */
        --page-pad:          28px;
        --dash-gap:          16px;

        /* ── Spacing ─────────────────────────────────────────────── */
        --sp-xs:  0.25rem;
        --sp-sm:  0.5rem;
        --sp-md:  0.875rem;
        --sp-lg:  1.25rem;
        --sp-xl:  1.75rem;
        --sp-2xl: 2.25rem;

        /* ── Z-index ─────────────────────────────────────────────── */
        --z-map:      1;
        --z-kpi:      9;
        --z-overlay:  19;
        --z-navbar:   30;
        --z-dropdown: 50;
        --z-modal:    100;
        --z-toast:    9999;
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 2 — LIGHT MODE
    ════════════════════════════════════════════════════════════════ */
    .light-mode {
        --color-bg:               #f0f2f5;
        --color-bg-subtle:        #e8eaed;
        --color-card:             #ffffff;
        --color-text:             #0f172a;
        --color-text-muted:       #64748b;
        --color-secondary-text:   #64748b;
        --color-sidebar-bg:       #ffffff;
        --color-sidebar-text:     #1e293b;
        --color-sidebar-active:   rgba(245, 130, 32, 0.08);
        --color-border-subtle:    #e2e8f0;
        --color-border:           #cbd5e1;
        --color-navbar-bg:        #ffffff;
        --color-input-bg:         #ffffff;
        --color-input-border:     #cbd5e1;
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 3 — DARK MODE
    ════════════════════════════════════════════════════════════════ */
    .dark-mode {
        --color-bg:               #0d1117;
        --color-bg-subtle:        #161b22;
        --color-card:             #1c2333;
        --color-text:             #e6edf3;
        --color-text-muted:       #8b949e;
        --color-secondary-text:   #b0bec5;
        --color-sidebar-bg:       #161b22;
        --color-sidebar-text:     #e6edf3;
        --color-sidebar-active:   rgba(245, 130, 32, 0.15);
        --color-border-subtle:    #30363d;
        --color-border:           #484f58;
        --color-navbar-bg:        #161b22;
        --color-input-bg:         #21262d;
        --color-input-border:     #30363d;
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    .dark-mode .kpi-label,
    .dark-mode .alert-type-label,
    .dark-mode .stat-label { color: #ffffffcb; }

    /* ════════════════════════════════════════════════════════════════
       SECTION 4 — RESET & BASE
    ════════════════════════════════════════════════════════════════ */
    *, *::before, *::after { box-sizing: border-box; }

    html { scroll-behavior: smooth; }

    body {
        font-family: var(--font-body);
        font-size: 1rem;
        line-height: var(--lh-normal);
        min-height: 100vh;
        margin: 0;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        overflow-x: hidden;
    }

    p, li, td, label,
    .toast-msg, .text-secondary,
    input, select, textarea {
        font-family: var(--font-body);
    }

    h1, h2, h3, h4, h5, h6,
    .font-orbitron,
    .navbar-title,
    .nav-label,
    .kpi-label,
    .stat-label,
    .alert-type-label,
    .form-label,
    .immat-badge,
    .role-badge,
    .alert-badge,
    .nav-tab,
    .btn-primary,
    .btn-secondary,
    .btn-partner-login,
    .toast-title,
    .kpi-alerts-header,
    .modal-title,
    .sidebar-section-title,
    .vehicles-count-badge,
    .users-count-badge,
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_info {
        font-family: var(--font-display);
    }

    .brand-logo h1,
    .brand-logo .logo-text {
        font-family: var(--font-logo);
    }

    .kpi-value, .stat-value, .alert-type-value {
        font-family: var(--font-display);
        font-weight: 700;
        font-size: var(--text-kpi);
        letter-spacing: var(--ls-tight);
        line-height: var(--lh-tight);
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 5 — FOCUS STATES ACCESSIBLES (WCAG 2.4.7)
    ════════════════════════════════════════════════════════════════ */
    :focus { outline: none; }

    :focus-visible {
        outline: 2px solid var(--color-primary);
        outline-offset: 2px;
        border-radius: var(--r-sm);
    }

    button:focus-visible,
    a:focus-visible,
    [role="button"]:focus-visible,
    [tabindex]:focus-visible {
        outline: none;
        box-shadow: var(--focus-ring);
        border-radius: var(--r-sm);
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--color-primary) !important;
        box-shadow: var(--focus-ring);
    }

    #theme-toggle:focus-visible { box-shadow: var(--focus-ring); }

    /* ════════════════════════════════════════════════════════════════
       SECTION 6 — NAVBAR HORIZONTALE (remplace la sidebar)
    ════════════════════════════════════════════════════════════════ */
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--navbar-h);
        z-index: var(--z-navbar);
        background-color: var(--color-navbar-bg);
        border-bottom: 1px solid var(--color-border-subtle);
        display: flex;
        align-items: center;
        padding: 0 var(--sp-xl);
        gap: 0;
        transition: background-color 0.2s;
        isolation: isolate;
    }

    /* ── Brand / Logo ─────────────────────────────────────────── */
    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        flex-shrink: 0;
        margin-right: var(--sp-xl);
    }

    .navbar-brand img {
        height: 36px;
        width: auto;
        display: block;
    }

    .navbar-brand .logo-text {
        font-family: var(--font-logo) !important;
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--color-primary);
        margin: 0;
        letter-spacing: 0.06em;
        line-height: 1;
        white-space: nowrap;
    }

    /* ── Navigation principale (desktop) ──────────────────────── */
    .navbar-nav {
        display: flex;
        align-items: center;
        gap: 0.125rem;
        flex: 1;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .navbar-nav > li {
        position: relative;
    }

    .navbar-nav > li > a,
    .navbar-nav > li > .nav-dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.75rem;
        color: var(--color-text);
        text-decoration: none;
        font-family: var(--font-display);
        font-weight: 600;
        font-size: 0.8rem;
        letter-spacing: 0.02em;
        white-space: nowrap;
        border-radius: var(--r-sm);
        border: none;
        background: transparent;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
        position: relative;
    }

    .navbar-nav > li > a:hover,
    .navbar-nav > li > .nav-dropdown-toggle:hover {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }

    .navbar-nav > li > a.active,
    .navbar-nav > li > .nav-dropdown-toggle.active {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }

    /* Underline actif */
    .navbar-nav > li > a.active::after,
    .navbar-nav > li > .nav-dropdown-toggle.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0.5rem;
        right: 0.5rem;
        height: 2px;
        background: var(--color-primary);
        border-radius: var(--r-pill);
    }

    .navbar-nav .nav-icon {
        color: var(--color-primary);
        font-size: 0.78rem;
        opacity: 0.85;
        flex-shrink: 0;
    }

    .navbar-nav > li > a:hover .nav-icon,
    .navbar-nav > li > a.active .nav-icon,
    .navbar-nav > li > .nav-dropdown-toggle:hover .nav-icon,
    .navbar-nav > li > .nav-dropdown-toggle.active .nav-icon {
        opacity: 1;
    }

    /* ── Séparateur vertical ───────────────────────────────────── */
    .navbar-sep {
        width: 1px;
        height: 20px;
        background: var(--color-border-subtle);
        margin: 0 0.5rem;
        flex-shrink: 0;
    }

    /* ── Flèche dropdown ───────────────────────────────────────── */
    .nav-dropdown-arrow {
        font-size: 0.55rem;
        color: var(--color-secondary-text);
        margin-left: 1px;
        transition: transform 0.25s ease;
    }

    .nav-dropdown-toggle.open .nav-dropdown-arrow {
        transform: rotate(180deg);
    }

    /* ── Dropdown menu ─────────────────────────────────────────── */
    .nav-dropdown-menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        min-width: 200px;
        background: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--r-lg);
        box-shadow: var(--shadow-lg);
        z-index: var(--z-dropdown);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-6px);
        transition: opacity 0.18s, transform 0.18s, visibility 0s 0.18s;
        list-style: none;
        margin: 0;
        padding: var(--sp-xs) 0;
        overflow: hidden;
    }

    .nav-dropdown-menu.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        transition: opacity 0.18s, transform 0.18s, visibility 0s;
    }

    .nav-dropdown-menu li a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem var(--sp-lg);
        color: var(--color-text);
        text-decoration: none;
        font-family: var(--font-display);
        font-weight: 600;
        font-size: 0.8rem;
        transition: background 0.12s, color 0.12s;
        white-space: nowrap;
    }

    .nav-dropdown-menu li a:hover,
    .nav-dropdown-menu li a.active {
        background: var(--color-sidebar-active);
        color: var(--color-primary);
    }

    .nav-dropdown-menu li a .nav-icon {
        color: var(--color-primary);
        font-size: 0.75rem;
        width: 1rem;
        text-align: center;
        flex-shrink: 0;
    }

    /* ── Actions navbar droite ─────────────────────────────────── */
    .navbar-actions {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        flex-shrink: 0;
        margin-left: auto;
    }

    /* ── Bouton icône navbar ────────────────────────────────────── */
    .navbar-icon-btn {
        width: 34px; height: 34px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        font-size: 0.95rem;
        color: var(--color-text);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
        position: relative;
        text-decoration: none;
        flex-shrink: 0;
    }

    .navbar-icon-btn:hover {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }

    /* ── Toggle dark/light ─────────────────────────────────────── */
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

    .toggle-switch.on { background: var(--color-primary); }
    .toggle-switch.on::after { transform: translateX(20px); }

    /* ── Dropdown utilisateur ───────────────────────────────────── */
    .user-menu-wrapper { position: relative; }

    .user-menu-trigger {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 4px;
        border-radius: var(--r-pill);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: background 0.15s;
    }

    .user-menu-trigger:hover { background: var(--color-primary-light); }

    .user-avatar {
        width: 32px; height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--color-primary);
        flex-shrink: 0;
        display: block;
    }

    .user-name {
        font-family: var(--font-body);
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--color-text);
        white-space: nowrap;
    }

    .user-chevron {
        font-size: 0.6rem;
        color: var(--color-secondary-text);
    }

    .user-dropdown {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        z-index: var(--z-dropdown);
        width: 220px;
        background: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--r-lg);
        box-shadow: var(--shadow-lg);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-6px);
        transition: opacity 0.18s, transform 0.18s, visibility 0s 0.18s;
    }

    .user-dropdown.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        transition: opacity 0.18s, transform 0.18s, visibility 0s;
    }

    .user-dropdown-header {
        padding: var(--sp-md) var(--sp-lg);
        border-bottom: 1px solid var(--color-border-subtle);
    }

    .user-dropdown-header .uname {
        font-family: var(--font-display);
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--color-text);
        margin: 0;
        line-height: 1.2;
    }

    .user-dropdown-header .uemail {
        font-family: var(--font-body);
        font-size: 0.72rem;
        color: var(--color-secondary-text);
        margin: 2px 0 0;
    }

    .user-dropdown a {
        display: flex;
        align-items: center;
        gap: var(--sp-sm);
        padding: var(--sp-md) var(--sp-lg);
        color: var(--color-text);
        text-decoration: none;
        font-family: var(--font-body);
        font-size: 0.82rem;
        transition: background 0.12s;
        border-radius: 0;
    }

    .user-dropdown a:last-of-type { border-radius: 0 0 var(--r-lg) var(--r-lg); }

    .user-dropdown a:hover {
        background: var(--color-sidebar-active);
        color: var(--color-primary);
    }

    .user-dropdown a.danger { color: var(--color-error) !important; }
    .user-dropdown a.danger:hover { background: var(--color-error-bg) !important; }

    .user-dropdown .menu-icon {
        width: 14px;
        color: var(--color-secondary-text);
        flex-shrink: 0;
    }

    /* ── Hamburger mobile ───────────────────────────────────────── */
    .btn-mobile-menu {
        display: none;
        width: 36px; height: 36px;
        align-items: center; justify-content: center;
        font-size: 1rem;
        border-radius: var(--r-sm);
        cursor: pointer;
        background: transparent;
        color: var(--color-text);
        border: 1px solid var(--color-border-subtle);
        transition: background 0.15s, color 0.15s, border-color 0.15s;
        flex-shrink: 0;
    }

    .btn-mobile-menu:hover {
        background: var(--color-primary-light);
        color: var(--color-primary);
        border-color: var(--color-primary-border);
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 7 — DRAWER MOBILE
    ════════════════════════════════════════════════════════════════ */
    .mobile-drawer {
        position: fixed;
        top: var(--navbar-h);
        left: 0;
        right: 0;
        bottom: 0;
        z-index: calc(var(--z-navbar) - 1);
        background: var(--color-card);
        border-top: 1px solid var(--color-border-subtle);
        transform: translateY(-100%);
        opacity: 0;
        visibility: hidden;
        transition: transform 0.3s ease, opacity 0.25s ease, visibility 0s 0.3s;
        overflow-y: auto;
        padding: var(--sp-lg);
        display: flex;
        flex-direction: column;
        gap: var(--sp-xs);
    }

    .mobile-drawer.open {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
        transition: transform 0.3s ease, opacity 0.25s ease, visibility 0s;
    }

    .mobile-drawer .drawer-section-title {
        font-family: var(--font-display);
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: var(--ls-widest);
        text-transform: uppercase;
        color: var(--color-secondary-text);
        opacity: 0.65;
        padding: 0.75rem 0.5rem 0.25rem;
    }

    .mobile-drawer a {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.6rem 0.75rem;
        color: var(--color-text);
        text-decoration: none;
        font-family: var(--font-display);
        font-weight: 600;
        font-size: 0.85rem;
        border-radius: var(--r-sm);
        transition: background 0.15s, color 0.15s;
    }

    .mobile-drawer a:hover,
    .mobile-drawer a.active {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }

    .mobile-drawer a .nav-icon {
        color: var(--color-primary);
        font-size: 0.85rem;
        width: 1.25rem;
        text-align: center;
        flex-shrink: 0;
    }

    .mobile-drawer .drawer-logout {
        margin-top: auto;
        padding-top: var(--sp-lg);
        border-top: 1px solid var(--color-border-subtle);
    }

    .mobile-drawer .drawer-logout a {
        color: var(--color-error);
    }

    .mobile-drawer .drawer-logout a:hover {
        background: var(--color-error-bg);
        color: var(--color-error);
    }

    .mobile-overlay {
        display: none;
        position: fixed;
        inset: 0;
        top: var(--navbar-h);
        background: rgba(0,0,0,0.45);
        z-index: calc(var(--z-navbar) - 2);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .mobile-overlay.visible { display: block; }
    .mobile-overlay.active  { opacity: 1; }

    /* ════════════════════════════════════════════════════════════════
       SECTION 8 — MAIN CONTENT WRAPPER
    ════════════════════════════════════════════════════════════════ */
    .main-content {
        margin-left: 0;
        padding-top: var(--navbar-h);
        min-height: 100vh;
    }

    .page-inner {
        padding: var(--sp-xl);
        padding-bottom: var(--sp-2xl);
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 9 — STICKY KPI BAR
    ════════════════════════════════════════════════════════════════ */
    .kpi-sticky-bar {
        position: sticky;
        top: var(--navbar-h);
        z-index: var(--z-kpi);
        background-color: var(--color-bg);
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        padding-block: var(--sp-sm);
    }

    .dark-mode .kpi-sticky-bar {
        box-shadow: 0 2px 10px rgba(0,0,0,0.40);
    }

    @media (max-width: 1023px) {
        .kpi-sticky-bar {
            position: static;
            background: transparent;
            box-shadow: none;
        }
    }

    .kpi-value {
        font-family: var(--font-display) !important;
        font-size: clamp(1.5rem, 2.5vw, 2rem) !important;
        font-weight: 700 !important;
        letter-spacing: -0.02em !important;
        line-height: 1 !important;
    }

    .kpi-label {
        font-family: var(--font-display) !important;
        font-size: 0.68rem !important;
        font-weight: 600 !important;
        letter-spacing: 0.07em !important;
        text-transform: uppercase;
    }

    @media (min-width: 768px) and (max-width: 1023px) {
        .kpi-sticky-bar .grid { grid-template-columns: repeat(2, 1fr) !important; }
        .kpi-value { font-size: 1.5rem !important; }
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 10 — FULL-HEIGHT LISTE + CARTE (dashboard desktop)
    ════════════════════════════════════════════════════════════════ */
    @media (min-width: 1024px) {
        .dashboard-content .list-map-grid {
            display: grid;
            grid-template-columns: minmax(350px, 1fr) 3fr;
            gap: 1rem;
            align-items: stretch;
        }

        .dashboard-content .list-map-grid > div.lg\:col-span-1 {
            grid-column: 1 / 2 !important;
            min-width: 0;
        }

        .dashboard-content .list-map-grid > div.lg\:col-span-3 {
            grid-column: 2 / 3 !important;
            min-width: 0;
        }

        .dashboard-content .list-map-grid > div.lg\:col-span-3 .panel-card {
            height: 100%;
            min-width: 0;
        }

        #fleetMap {
            width: 100%;
            height: 100%;
            min-width: 0;
        }
    }

    @media (min-width: 768px) and (max-width: 1023px) {
        #vehicleList { max-height: 50vh; overflow-y: auto; }
        #fleetMap    { height: 350px !important; }
    }

    @media (max-width: 767px) {
        #fleetMap { height: 280px !important; }
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 11 — UI CARDS
    ════════════════════════════════════════════════════════════════ */
    .ui-card {
        background: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--r-lg);
        padding: var(--sp-lg);
        box-shadow: var(--shadow-sm);
        color: var(--color-text);
    }

    .dark-mode .ui-card { box-shadow: 0 2px 8px rgba(0,0,0,0.30); }

    /* ════════════════════════════════════════════════════════════════
       SECTION 12 — INPUTS / SELECTS / TEXTAREA
    ════════════════════════════════════════════════════════════════ */
    input[type="text"],
    input[type="search"],
    input[type="email"],
    input[type="number"],
    input[type="tel"],
    input[type="password"],
    input[type="date"],
    select, textarea,
    .ui-input-style,
    .ui-select-style,
    .ui-textarea-style {
        background-color: var(--color-input-bg);
        border: 1px solid var(--color-input-border);
        color: var(--color-text) !important;
        border-radius: var(--r-md);
        padding: var(--sp-sm) var(--sp-md);
        font-family: var(--font-body);
        font-size: 0.875rem;
        width: 100%;
        transition: border-color 0.15s, box-shadow 0.15s;
        appearance: auto;
    }

    select option {
        background: var(--color-input-bg);
        color: var(--color-text);
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 13 — BOUTONS
    ════════════════════════════════════════════════════════════════ */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-xs);
        background: var(--color-primary);
        color: #fff;
        padding: 0.45rem 1rem;
        border-radius: var(--r-md);
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 0.82rem;
        letter-spacing: 0.02em;
        border: none;
        cursor: pointer;
        text-decoration: none;
        min-height: 36px;
        white-space: nowrap;
        transition: background 0.15s, transform 0.1s, box-shadow 0.15s;
    }

    .btn-primary:hover {
        background: var(--color-primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245,130,32,0.30);
    }

    .btn-primary:active { transform: none; box-shadow: none; }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-xs);
        color: var(--color-primary);
        border: 1px solid var(--color-primary-border);
        padding: 0.45rem 1rem;
        border-radius: var(--r-md);
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 0.82rem;
        letter-spacing: 0.02em;
        background: transparent;
        cursor: pointer;
        text-decoration: none;
        min-height: 36px;
        white-space: nowrap;
        transition: background 0.15s, border-color 0.15s;
    }

    .btn-secondary:hover {
        background: var(--color-primary-light);
        border-color: var(--color-primary);
    }

    .btn-partner-login {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-xs);
        background: transparent;
        color: var(--color-primary);
        border: 1.5px solid var(--color-primary);
        padding: 0.45rem 1rem;
        border-radius: var(--r-md);
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 0.78rem;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
        text-decoration: none;
    }

    .btn-partner-login:hover { background: var(--color-primary); color: #fff; }

    @media (max-width: 767px) {
        .btn-primary, .btn-secondary { min-height: 44px; }
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 14 — TABLEAUX + DATATABLES
    ════════════════════════════════════════════════════════════════ */
    .ui-table-container {
        overflow-x: auto;
        border-radius: var(--r-lg);
        border: 1px solid var(--color-border-subtle);
    }

    .ui-table {
        width: 100%;
        border-collapse: collapse;
        font-family: var(--font-body);
        font-size: 0.82rem;
    }

    .ui-table th {
        font-family: var(--font-display);
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: var(--ls-wide);
        text-transform: uppercase;
        background: var(--color-bg-subtle, var(--color-border-subtle));
        color: var(--color-text);
        padding: 0.6rem 1rem;
        text-align: left;
        border-bottom: 2px solid var(--color-primary);
        white-space: nowrap;
    }

    .ui-table td {
        font-family: var(--font-body);
        padding: 0.55rem 1rem;
        border-bottom: 1px solid var(--color-border-subtle);
        color: var(--color-text);
        vertical-align: middle;
    }

    .ui-table tr:last-child td { border-bottom: none; }
    .ui-table tr:hover td { background: var(--color-sidebar-active); }

    .dark-mode .ui-table th { background: #161b22; }
    .dark-mode .ui-table td { border-color: #30363d; }

    .dataTables_wrapper { font-family: var(--font-body); font-size: 0.82rem; color: var(--color-text); }
    .dataTables_wrapper .dataTables_filter { display: none !important; }

    .dataTables_wrapper .dataTables_length {
        margin-bottom: 1rem;
        display: flex; align-items: center; gap: 0.5rem;
        color: var(--color-secondary-text);
        font-size: 0.78rem;
    }

    .dataTables_wrapper .dataTables_length select {
        background: var(--color-input-bg) !important;
        border: 1px solid var(--color-input-border) !important;
        color: var(--color-text) !important;
        border-radius: var(--r-md);
        padding: 0.25rem 0.5rem;
        font-size: 0.78rem;
        width: auto;
        appearance: auto;
    }

    .dataTables_wrapper .dataTables_info {
        color: var(--color-secondary-text);
        font-size: 0.75rem;
        padding-top: 0.5rem;
    }

    .dataTables_wrapper .dataTables_paginate {
        display: flex; align-items: center; gap: 0.25rem;
        justify-content: flex-end;
        padding-top: 0.5rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 30px; height: 30px;
        padding: 0 0.5rem;
        border-radius: var(--r-md) !important;
        border: 1px solid var(--color-border-subtle) !important;
        background: var(--color-card) !important;
        background-image: none !important;
        box-shadow: none !important;
        color: var(--color-text) !important;
        font-size: 0.75rem;
        font-family: var(--font-body);
        cursor: pointer;
        transition: background 0.12s, color 0.12s, border-color 0.12s;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--color-primary-light) !important;
        border-color: var(--color-primary) !important;
        color: var(--color-primary) !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: var(--color-primary) !important;
        border-color: var(--color-primary) !important;
        color: #fff !important;
        font-weight: 700;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
        opacity: 0.3; cursor: not-allowed; pointer-events: none;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.previous,
    .dataTables_wrapper .dataTables_paginate .paginate_button.next {
        font-family: var(--font-display); font-size: 0.65rem; letter-spacing: 0.03em; padding: 0 0.75rem;
    }

    table.dataTable {
        border-collapse: collapse !important;
        margin: 0 !important;
        width: 100% !important;
        border: none !important;
    }

    table.dataTable thead th,
    table.dataTable thead td {
        font-family: var(--font-display) !important;
        font-size: 0.72rem !important;
        font-weight: 600 !important;
        letter-spacing: var(--ls-wide) !important;
        text-transform: uppercase;
        background: var(--color-bg-subtle, var(--color-border-subtle)) !important;
        color: var(--color-text) !important;
        border-bottom: 2px solid var(--color-primary) !important;
        padding: 0.6rem 1rem !important;
        white-space: nowrap;
    }

    table.dataTable thead th.sorting::after        { opacity: 0.35; color: var(--color-primary) !important; }
    table.dataTable thead th.sorting_asc::after,
    table.dataTable thead th.sorting_desc::after   { opacity: 1; color: var(--color-primary) !important; }

    table.dataTable tbody tr {
        background: var(--color-card) !important;
        transition: background 0.12s;
    }

    table.dataTable tbody tr:hover { background: var(--color-sidebar-active) !important; }

    table.dataTable.stripe tbody tr.odd  { background: var(--color-card) !important; }
    table.dataTable.stripe tbody tr.even { background: var(--color-bg) !important; }

    table.dataTable tbody td {
        padding: 0.55rem 1rem !important;
        color: var(--color-text) !important;
        border: none !important;
        border-bottom: 1px solid var(--color-border-subtle) !important;
        font-family: var(--font-body);
    }

    .dark-mode table.dataTable thead th { background: #161b22 !important; }
    .dark-mode table.dataTable tbody td { border-bottom-color: #30363d !important; }

    .dataTables_wrapper .dataTables_processing {
        background: var(--color-card);
        color: var(--color-primary);
        border: 1px solid var(--color-border-subtle);
        border-radius: var(--r-lg);
        box-shadow: var(--shadow-md);
        font-family: var(--font-display);
        font-size: 0.75rem;
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 15 — BADGES RADIUS
    ════════════════════════════════════════════════════════════════ */
    .immat-badge,
    .vehicles-count-badge,
    .users-count-badge,
    .nav-tab       { border-radius: var(--r-sm); }

    .role-badge,
    .alert-badge   { border-radius: var(--r-pill); }

    .modal-panel   { border-radius: var(--r-lg); }

    .tbl-action {
        position: relative;
        min-width: 32px; min-height: 32px;
    }

    .tbl-action::after {
        content: '';
        position: absolute;
        inset: -6px;
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 16 — GOOGLE MAPS
    ════════════════════════════════════════════════════════════════ */
    #fleetMap {
        width: 100%;
        height: 400px;
        min-height: 300px;
        border-radius: var(--r-lg);
        display: block;
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 17 — SSE INDICATOR ANIMATIONS
    ════════════════════════════════════════════════════════════════ */
    @keyframes ssePulse {
        0%, 100% { opacity: 1;    transform: scale(1);   }
        50%       { opacity: 0.4;  transform: scale(1.5); }
    }

    @keyframes sseReconnect {
        0%, 100% { opacity: 1;    transform: scale(1);   }
        50%       { opacity: 0.25; transform: scale(1.7); }
    }

    #sse-indicator.sse-connected    span:first-child { animation: ssePulse    2.2s ease-in-out infinite; }
    #sse-indicator.sse-reconnecting span:first-child { animation: sseReconnect 0.7s ease-in-out infinite; }

    /* ════════════════════════════════════════════════════════════════
       SECTION 18 — TOAST NOTIFICATIONS
    ════════════════════════════════════════════════════════════════ */
    #toast-container {
        position: fixed;
        top: calc(var(--navbar-h) + var(--sp-sm));
        right: var(--sp-lg);
        z-index: var(--z-toast);
        display: flex;
        flex-direction: column;
        gap: var(--sp-sm);
        pointer-events: none;
        max-width: min(520px, calc(100vw - 2rem));
    }

    .toast {
        pointer-events: auto;
        display: flex;
        align-items: flex-start;
        gap: var(--sp-md);
        padding: 14px;
        border-radius: var(--r-xl);
        border: 1px solid var(--color-border-subtle);
        background: var(--color-card);
        color: var(--color-text);
        box-shadow: var(--shadow-lg);
        transform: translateY(-8px) scale(0.97);
        opacity: 0;
        transition: transform 0.25s ease, opacity 0.25s ease;
        position: relative;
        overflow: hidden;
    }

    .toast.show { transform: none; opacity: 1; }
    .toast.hide { transform: translateY(-8px) scale(0.97); opacity: 0; }

    .toast::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        border-radius: var(--r-xl) 0 0 var(--r-xl);
    }

    .toast::after {
        content: '';
        position: absolute;
        left: 0; right: 0; bottom: 0;
        height: 2px;
        transform-origin: left;
    }

    .toast.show::after { animation: toastProgress 5s linear forwards; }

    @keyframes toastProgress { from { transform: scaleX(1); } to { transform: scaleX(0); } }

    .toast-icon {
        width: 36px; height: 36px;
        border-radius: var(--r-md);
        display: flex; align-items: center; justify-content: center;
        color: #fff; flex-shrink: 0; font-size: 1rem;
    }

    .toast-body { flex: 1; min-width: 0; }

    .toast-title {
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 0.88rem;
        line-height: 1.2;
    }

    .toast-msg {
        margin-top: 2px;
        font-family: var(--font-body);
        font-size: 0.82rem;
        color: var(--color-secondary-text);
        line-height: 1.4;
    }

    .toast-close {
        margin-left: auto;
        width: 26px; height: 26px;
        border-radius: var(--r-xs);
        border: 1px solid var(--color-border-subtle);
        background: transparent;
        color: var(--color-text);
        opacity: 0.5;
        cursor: pointer;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        transition: opacity 0.12s;
    }

    .toast-close:hover { opacity: 1; }

    .toast-success { border-color: rgba(34,197,94,.22); }
    .toast-success::before { background: #22c55e; }
    .toast-success::after  { background: linear-gradient(90deg, #22c55e, rgba(34,197,94,0)); }
    .toast-success .toast-icon   { background: #16a34a; }
    .toast-success .toast-title  { color: #16a34a; }

    .toast-error { border-color: rgba(239,68,68,.22); }
    .toast-error::before { background: #ef4444; }
    .toast-error::after  { background: linear-gradient(90deg, #ef4444, rgba(239,68,68,0)); }
    .toast-error .toast-icon  { background: #dc2626; }
    .toast-error .toast-title { color: #dc2626; }

    .toast-warning { border-color: rgba(234,179,8,.22); }
    .toast-warning::before { background: #eab308; }
    .toast-warning::after  { background: linear-gradient(90deg, #eab308, rgba(234,179,8,0)); }
    .toast-warning .toast-icon  { background: #ca8a04; }
    .toast-warning .toast-title { color: #ca8a04; }

    /* ════════════════════════════════════════════════════════════════
       SECTION 19 — SKELETON LOADING
    ════════════════════════════════════════════════════════════════ */
    @keyframes skeletonPulse {
        0%, 100% { opacity: 1;   }
        50%       { opacity: 0.4; }
    }

    .skeleton-line {
        height: 12px;
        background: var(--color-border-subtle);
        border-radius: var(--r-xs);
        animation: skeletonPulse 1.4s ease-in-out infinite;
        margin-bottom: 8px;
    }

    .skeleton-line.short  { width: 55%; }
    .skeleton-line.medium { width: 75%; }

    /* ════════════════════════════════════════════════════════════════
       SECTION 20 — RESPONSIVE MOBILE
    ════════════════════════════════════════════════════════════════ */
    @media (max-width: 1023px) {
        .navbar-nav { display: none !important; }
        .btn-mobile-menu { display: flex !important; }
    }

    @media (min-width: 1024px) {
        .btn-mobile-menu { display: none !important; }
        .mobile-drawer   { display: none !important; }
        .mobile-overlay  { display: none !important; }
    }

    @media (min-width: 768px) and (max-width: 1023px) {
        .page-inner { padding: 1rem; }
    }

    @media (max-width: 767px) {
        .page-inner { padding: 0.75rem; }
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 21 — UTILITAIRES
    ════════════════════════════════════════════════════════════════ */
    .text-primary   { color: var(--color-primary); }
    .text-secondary { color: var(--color-secondary-text); font-family: var(--font-body); }

    .sr-only {
        position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 22 — PRINT
    ════════════════════════════════════════════════════════════════ */
    @media print {
        .navbar, .kpi-sticky-bar,
        #toast-container, .btn-mobile-menu,
        .mobile-drawer, .mobile-overlay { display: none !important; }
        .main-content { margin-left: 0 !important; padding-top: 0 !important; }
        .page-inner   { padding: 0 !important; }
    }
    </style>

    @stack('styles')
</head>

{{-- ================================================================
     BODY
================================================================ --}}
<body class="light-mode" id="app-root">

    {{-- Annonces pour lecteurs d'écran --}}
    <div id="sr-live" aria-live="polite" aria-atomic="true" class="sr-only"></div>

    {{-- ════════════════════════════════════════════════════════
         NAVBAR HORIZONTALE
    ════════════════════════════════════════════════════════════ --}}
    <header class="navbar" id="navbar" role="banner">

        {{-- ── Brand Logo ────────────────────────────────────── --}}
        <a href="{{ route('dashboard') }}" class="navbar-brand" aria-label="Fleetra — Accueil">
            <img src="{{ asset('assets/images/logo_tracking.png') }}" alt="Logo Fleetra">
            <span class="logo-text">Fleetra</span>
        </a>

        {{-- ── Navigation principale (desktop) ─────────────── --}}
        <nav aria-label="Navigation principale">
            <ul class="navbar-nav" role="list">

                {{-- Dashboard --}}
                <li>
                    <a href="{{ route('dashboard') }}"
                       class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                        <span class="nav-icon" aria-hidden="true"><i class="fas fa-tachometer-alt"></i></span>
                        <span class="nav-label">Dashboard</span>
                    </a>
                </li>

                {{-- Séparateur --}}
                <li aria-hidden="true"><div class="navbar-sep"></div></li>

                {{-- Suivi & Flotte (dropdown) --}}
                <li>
                    <button class="nav-dropdown-toggle {{ request()->is('tracking*') || request()->is('users*') || request()->is('trajets*') ? 'active' : '' }}"
                            id="nav-toggle-flotte"
                            data-target="nav-drop-flotte"
                            aria-haspopup="true"
                            aria-expanded="false"
                            aria-controls="nav-drop-flotte">
                        <span class="nav-icon" aria-hidden="true"><i class="fas fa-satellite-dish"></i></span>
                        <span class="nav-label">Suivi &amp; Flotte</span>
                        <i class="fas fa-chevron-down nav-dropdown-arrow" aria-hidden="true"></i>
                    </button>

                    <ul class="nav-dropdown-menu" id="nav-drop-flotte" role="menu">
                        <li role="none">
                            <a href="{{ route('tracking.vehicles') }}"
                               role="menuitem"
                               class="{{ request()->routeIs('tracking.vehicles') ? 'active' : '' }}"
                               aria-current="{{ request()->routeIs('tracking.vehicles') ? 'page' : 'false' }}">
                                <span class="nav-icon" aria-hidden="true"><i class="fas fa-car"></i></span>
                                Véhicules
                            </a>
                        </li>
                        <li role="none">
                            <a href="{{ route('users.index') }}"
                               role="menuitem"
                               class="{{ request()->routeIs('users.*') ? 'active' : '' }}"
                               aria-current="{{ request()->routeIs('users.*') ? 'page' : 'false' }}">
                                <span class="nav-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                                Chauffeurs
                            </a>
                        </li>
                        <li role="none">
                            <a href="{{ route('trajets.index') }}"
                               role="menuitem"
                               class="{{ request()->routeIs('trajets.*') ? 'active' : '' }}"
                               aria-current="{{ request()->routeIs('trajets.*') ? 'page' : 'false' }}">
                                <span class="nav-icon" aria-hidden="true"><i class="fas fa-route"></i></span>
                                Trajets
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- Alertes --}}
                <li>
                    <a href="{{ route('alerts.view') }}"
                       class="{{ request()->routeIs('alerts.*') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('alerts.*') ? 'page' : 'false' }}">
                        <span class="nav-icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
                        <span class="nav-label">Alertes</span>
                    </a>
                </li>

                {{-- Moteur --}}
                <li>
                    <a href="{{ route('engine.action.index') }}"
                       class="{{ request()->routeIs('engine.*') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('engine.*') ? 'page' : 'false' }}">
                        <span class="nav-icon" aria-hidden="true"><i class="fas fa-power-off"></i></span>
                        <span class="nav-label">Moteur</span>
                    </a>
                </li>

            </ul>
        </nav>

        {{-- ── Actions droite ───────────────────────────────── --}}
        <div class="navbar-actions">

            {{-- Toggle dark / light --}}
            <div class="flex items-center gap-2">
                <span class="mode-label hidden lg:block" id="mode-label" aria-hidden="true">
                    Mode Clair
                </span>
                <div id="theme-toggle"
                     class="toggle-switch"
                     role="switch"
                     aria-checked="false"
                     aria-label="Basculer entre mode clair et sombre"
                     tabindex="0"
                     title="Changer de thème"></div>
            </div>

            {{-- Cloche alertes --}}
            <a href="{{ route('alerts.view') }}"
               class="navbar-icon-btn"
               aria-label="Voir les alertes et notifications"
               title="Alertes">
                <i class="fas fa-bell" aria-hidden="true"></i>
                <span aria-hidden="true" style="
                    position:absolute; top:6px; right:6px;
                    width:6px; height:6px; border-radius:50%;
                    background:var(--color-error);
                    border:1.5px solid var(--color-card);
                "></span>
            </a>

            {{-- Menu utilisateur --}}
            <div class="user-menu-wrapper" id="user-menu-container">
                <button class="user-menu-trigger"
                        id="btn-user-menu"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-controls="user-dropdown">
                    <img class="user-avatar"
                         src="https://placehold.co/32x32/F58220/ffffff?text={{ substr(auth()->user()->prenom ?? 'U', 0, 1) }}"
                         alt="Avatar de {{ auth()->user()->prenom ?? 'utilisateur' }}">
                    <span class="user-name hidden lg:block">
                        {{ auth()->user()->prenom }} {{ auth()->user()->nom }}
                    </span>
                    <i class="fas fa-chevron-down user-chevron" aria-hidden="true"></i>
                </button>

                <div class="user-dropdown" id="user-dropdown" role="menu">
                    <div class="user-dropdown-header">
                        <p class="uname">{{ auth()->user()->prenom }} {{ auth()->user()->nom }}</p>
                        <p class="uemail">{{ auth()->user()->email }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" role="menuitem">
                        <i class="fas fa-user-circle menu-icon" aria-hidden="true"></i>
                        Mon Profil
                    </a>
                    <a href="#" role="menuitem">
                        <i class="fas fa-cog menu-icon" aria-hidden="true"></i>
                        Paramètres
                    </a>
                    <a href="#"
                       role="menuitem"
                       class="danger"
                       onclick="event.preventDefault(); document.getElementById('form-logout-navbar').submit();">
                        <i class="fas fa-sign-out-alt menu-icon" aria-hidden="true"></i>
                        Déconnexion
                    </a>
                    <form id="form-logout-navbar" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                </div>
            </div>

            {{-- Hamburger mobile ─────────────────────────────── --}}
            <button class="btn-mobile-menu"
                    id="btn-mobile-menu"
                    aria-label="Ouvrir le menu de navigation"
                    aria-expanded="false"
                    aria-controls="mobile-drawer">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>

        </div>
    </header>

    {{-- ════════════════════════════════════════════════════════
         DRAWER MOBILE
    ════════════════════════════════════════════════════════════ --}}
    <div class="mobile-drawer" id="mobile-drawer" role="dialog" aria-modal="true" aria-label="Menu de navigation">

        <a href="{{ route('dashboard') }}"
           class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><i class="fas fa-tachometer-alt"></i></span>
            Dashboard
        </a>

        <div class="drawer-section-title">Flotte</div>

        <a href="{{ route('tracking.vehicles') }}"
           class="{{ request()->routeIs('tracking.vehicles') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><i class="fas fa-car"></i></span>
            Véhicules
        </a>
        <a href="{{ route('users.index') }}"
           class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
            Chauffeurs
        </a>
        <a href="{{ route('trajets.index') }}"
           class="{{ request()->routeIs('trajets.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><i class="fas fa-route"></i></span>
            Trajets
        </a>

        <div class="drawer-section-title">Supervision</div>

        <a href="{{ route('alerts.view') }}"
           class="{{ request()->routeIs('alerts.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><i class="fas fa-bell"></i></span>
            Alertes
        </a>
        <a href="{{ route('engine.action.index') }}"
           class="{{ request()->routeIs('engine.*') ? 'active' : '' }}">
            <span class="nav-icon" aria-hidden="true"><i class="fas fa-power-off"></i></span>
            Moteur
        </a>

        <div class="drawer-logout">
            <a href="#"
               onclick="event.preventDefault(); document.getElementById('form-logout-drawer').submit();">
                <span class="nav-icon" aria-hidden="true"><i class="fas fa-sign-out-alt"></i></span>
                Déconnexion
            </a>
            <form id="form-logout-drawer" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>

    </div>

    {{-- Overlay mobile --}}
    <div class="mobile-overlay" id="mobile-overlay" aria-hidden="true"></div>

    {{-- ════════════════════════════════════════════════════════
         MAIN CONTENT
    ════════════════════════════════════════════════════════════ --}}
    <main class="main-content" id="main-content" role="main">
        <div class="page-inner">

            {{-- Conteneur toast --}}
            <div id="toast-container" aria-live="polite" aria-atomic="false" role="status"></div>

            {{-- Toast session : succès --}}
            @if(session('success'))
            <div class="toast toast-success" role="alert" aria-live="assertive">
                <div class="toast-icon" aria-hidden="true"><i class="fas fa-check-circle"></i></div>
                <div class="toast-body">
                    <div class="toast-title">Succès</div>
                    <div class="toast-msg">{{ session('success') }}</div>
                </div>
                <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
            </div>
            @endif

            {{-- Toast session : erreur --}}
            @if(session('error'))
            <div class="toast toast-error" role="alert" aria-live="assertive">
                <div class="toast-icon" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="toast-body">
                    <div class="toast-title">Erreur</div>
                    <div class="toast-msg">{{ session('error') }}</div>
                </div>
                <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
            </div>
            @endif

            @yield('content')

        </div>
    </main>

    {{-- ════════════════════════════════════════════════════════
         SCRIPTS
    ════════════════════════════════════════════════════════════ --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <script>
    $(function () {
        if ($.fn.DataTable && $('#myTable').length) {
            $('#myTable').DataTable({
                responsive: true,
                processing: true,
                serverSide: false,
                language: { url: '/datatables/i18n/fr-FR.json' },
                dom: '<"flex flex-wrap items-center justify-between gap-2 mb-3"lf>' +
                     't' +
                     '<"flex flex-wrap items-center justify-center gap-4 mt-3"ip>'
            });
        }
    });
    </script>

    <script>
    /* ════════════════════════════════════════════════════════════════
       FLEETRA LAYOUT — JAVASCRIPT PRINCIPAL (navbar horizontale)

       Responsabilités :
         1.  Thème clair / sombre (persist localStorage + system pref)
         2.  Dropdowns nav (Suivi & Flotte)
         3.  Mobile drawer : open / close + overlay
         4.  Dropdown utilisateur navbar
         5.  Mesure navbar + KPI bar → CSS vars --navbar-h / --kpi-h
         6.  ResizeObserver sur KPI bar
         7.  Debounce window.resize
         8.  Google Maps resize après resize fenêtre
         9.  SSE indicator : patch classes CSS animation
         10. Focus trap modales (WCAG 2.4.3)
         11. Escape key : ferme modales + drawer mobile
         12. Toasts : affichage session + API window.showToast()
         13. Annonces aria-live pour lecteurs d'écran
    ════════════════════════════════════════════════════════════════ */
    (function () {
        'use strict';

        /* ── Références DOM ─────────────────────────────────────── */
        var ROOT          = document.documentElement;
        var APP           = document.getElementById('app-root');
        var SR_LIVE        = document.getElementById('sr-live');
        var MAIN           = document.getElementById('main-content');
        var NAVBAR         = document.getElementById('navbar');
        var BTN_MOBILE     = document.getElementById('btn-mobile-menu');
        var DRAWER         = document.getElementById('mobile-drawer');
        var OVERLAY        = document.getElementById('mobile-overlay');
        var BTN_USER       = document.getElementById('btn-user-menu');
        var USER_DROPDOWN  = document.getElementById('user-dropdown');
        var THEME_TOGGLE   = document.getElementById('theme-toggle');
        var MODE_LABEL     = document.getElementById('mode-label');

        /* ══════════════════════════════════════════════════════════
           HELPER — Annonce lecteur d'écran
        ══════════════════════════════════════════════════════════ */
        function announce(msg) {
            if (!SR_LIVE) return;
            SR_LIVE.textContent = '';
            setTimeout(function () { SR_LIVE.textContent = msg; }, 60);
        }

        /* ══════════════════════════════════════════════════════════
           1. THÈME CLAIR / SOMBRE
        ══════════════════════════════════════════════════════════ */
        function applyTheme(theme) {
            var dark = (theme === 'dark');
            APP.classList.toggle('dark-mode',  dark);
            APP.classList.toggle('light-mode', !dark);
            THEME_TOGGLE.classList.toggle('on', dark);
            THEME_TOGGLE.setAttribute('aria-checked', String(dark));
            if (MODE_LABEL) MODE_LABEL.textContent = dark ? 'Mode Sombre' : 'Mode Clair';
            localStorage.setItem('fleetra-theme', theme);
            announce(dark ? 'Mode sombre activé' : 'Mode clair activé');
        }

        function initTheme() {
            var saved = localStorage.getItem('fleetra-theme');
            if (!saved) {
                saved = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            applyTheme(saved);
        }

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
            if (!localStorage.getItem('fleetra-theme')) applyTheme(e.matches ? 'dark' : 'light');
        });

        THEME_TOGGLE.addEventListener('click', function () {
            applyTheme(APP.classList.contains('dark-mode') ? 'light' : 'dark');
        });

        THEME_TOGGLE.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); THEME_TOGGLE.click(); }
        });

        /* ══════════════════════════════════════════════════════════
           2. DROPDOWNS NAV DESKTOP
        ══════════════════════════════════════════════════════════ */
        function closeAllNavDropdowns(except) {
            document.querySelectorAll('.nav-dropdown-toggle').forEach(function (btn) {
                if (btn === except) return;
                var targetId = btn.getAttribute('data-target');
                var menu = document.getElementById(targetId);
                if (menu) {
                    menu.classList.remove('open');
                    btn.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        document.querySelectorAll('.nav-dropdown-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var targetId = btn.getAttribute('data-target');
                var menu = document.getElementById(targetId);
                if (!menu) return;

                var opening = !menu.classList.contains('open');
                closeAllNavDropdowns(btn);

                menu.classList.toggle('open', opening);
                btn.classList.toggle('open', opening);
                btn.setAttribute('aria-expanded', String(opening));
            });
        });

        /* Fermer les dropdowns nav au clic extérieur */
        document.addEventListener('click', function (e) {
            var inside = e.target.closest('.navbar-nav li');
            if (!inside) closeAllNavDropdowns(null);
        });

        /* ══════════════════════════════════════════════════════════
           3. DRAWER MOBILE — OPEN / CLOSE
        ══════════════════════════════════════════════════════════ */
        function drawerOpen() {
            if (!DRAWER) return;
            DRAWER.classList.add('open');
            OVERLAY.classList.add('visible');
            requestAnimationFrame(function () {
                requestAnimationFrame(function () { OVERLAY.classList.add('active'); });
            });
            document.body.style.overflow = 'hidden';
            if (BTN_MOBILE) BTN_MOBILE.setAttribute('aria-expanded', 'true');
            if (OVERLAY) OVERLAY.setAttribute('aria-hidden', 'false');
        }

        function drawerClose() {
            if (!DRAWER) return;
            DRAWER.classList.remove('open');
            OVERLAY.classList.remove('active');
            setTimeout(function () {
                OVERLAY.classList.remove('visible');
                if (OVERLAY) OVERLAY.setAttribute('aria-hidden', 'true');
            }, 300);
            document.body.style.overflow = '';
            if (BTN_MOBILE) BTN_MOBILE.setAttribute('aria-expanded', 'false');
        }

        if (BTN_MOBILE) {
            BTN_MOBILE.addEventListener('click', function () {
                DRAWER && DRAWER.classList.contains('open') ? drawerClose() : drawerOpen();
            });
        }

        if (OVERLAY) OVERLAY.addEventListener('click', drawerClose);

        /* Fermer drawer au clic sur un lien */
        if (DRAWER) {
            DRAWER.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', drawerClose);
            });
        }

        /* ══════════════════════════════════════════════════════════
           4. DROPDOWN UTILISATEUR NAVBAR
        ══════════════════════════════════════════════════════════ */
        if (BTN_USER && USER_DROPDOWN) {
            BTN_USER.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = USER_DROPDOWN.classList.toggle('open');
                BTN_USER.setAttribute('aria-expanded', String(open));
            });

            document.addEventListener('click', function (e) {
                if (!USER_DROPDOWN.contains(e.target) && !BTN_USER.contains(e.target)) {
                    USER_DROPDOWN.classList.remove('open');
                    BTN_USER.setAttribute('aria-expanded', 'false');
                }
            });

            USER_DROPDOWN.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    USER_DROPDOWN.classList.remove('open');
                    BTN_USER.setAttribute('aria-expanded', 'false');
                    BTN_USER.focus();
                }
            });
        }

        /* ══════════════════════════════════════════════════════════
           5. MESURE HAUTEURS → CSS VARS
        ══════════════════════════════════════════════════════════ */
        function measureHeights() {
            var navH = NAVBAR ? Math.round(NAVBAR.getBoundingClientRect().height) : 64;
            var kpiEl = document.querySelector('.kpi-sticky-bar');
            var kpiH  = kpiEl  ? Math.round(kpiEl.getBoundingClientRect().height) : 0;
            ROOT.style.setProperty('--navbar-h', navH + 'px');
            if (kpiH > 0) ROOT.style.setProperty('--kpi-h', kpiH + 'px');
        }

        /* ══════════════════════════════════════════════════════════
           6. RESIZEOBSERVER SUR KPI BAR
        ══════════════════════════════════════════════════════════ */
        (function () {
            if (!window.ResizeObserver) return;
            var kpi = document.querySelector('.kpi-sticky-bar');
            if (!kpi) return;
            var t = null;
            new ResizeObserver(function () {
                clearTimeout(t);
                t = setTimeout(measureHeights, 60);
            }).observe(kpi);
        })();

        /* ══════════════════════════════════════════════════════════
           7. WINDOW RESIZE — debounce 120ms
        ══════════════════════════════════════════════════════════ */
        function syncOnResize() {
            /* Fermer le drawer si on passe en desktop */
            if (window.innerWidth >= 1024) {
                drawerClose();
                closeAllNavDropdowns(null);
            }
            measureHeights();
            triggerMapResize();
        }

        var _rTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(_rTimer);
            _rTimer = setTimeout(syncOnResize, 120);
        });

        /* ══════════════════════════════════════════════════════════
           8. GOOGLE MAPS RESIZE
        ══════════════════════════════════════════════════════════ */
        function triggerMapResize() {
            if (window.google && window.google.maps && window.map) {
                google.maps.event.trigger(window.map, 'resize');
            }
        }

        /* ══════════════════════════════════════════════════════════
           9. SSE INDICATOR — PATCH CLASSES CSS
        ══════════════════════════════════════════════════════════ */
        (function () {
            var sseEl = document.getElementById('sse-indicator');
            if (!sseEl) return;

            var SSE_CLASSES = {
                connected:    'sse-connected',
                reconnecting: 'sse-reconnecting',
                connecting:   'sse-reconnecting',
                paused:       'sse-paused'
            };

            var SSE_LABELS = {
                connected:    'Temps réel : connecté',
                reconnecting: 'Temps réel : reconnexion en cours',
                connecting:   'Temps réel : connexion en cours',
                paused:       'Temps réel : en pause'
            };

            function applyClass(state) {
                sseEl.classList.remove('sse-connected', 'sse-reconnecting', 'sse-paused');
                if (SSE_CLASSES[state]) sseEl.classList.add(SSE_CLASSES[state]);
                if (SSE_LABELS[state])  announce(SSE_LABELS[state]);
            }

            var _orig = window.setSseIndicator;
            window.setSseIndicator = function (state) {
                if (typeof _orig === 'function') _orig(state);
                applyClass(state);
            };
        })();

        /* ══════════════════════════════════════════════════════════
           10. FOCUS TRAP MODALES (WCAG 2.4.3)
        ══════════════════════════════════════════════════════════ */
        function trapFocus(panel) {
            if (!panel) return;
            var sel = 'button:not([disabled]),[href],input:not([disabled]),'
                    + 'select:not([disabled]),textarea:not([disabled]),'
                    + '[tabindex]:not([tabindex="-1"])';
            var nodes = panel.querySelectorAll(sel);
            var first = nodes[0];
            var last  = nodes[nodes.length - 1];
            if (!first) return;

            panel.addEventListener('keydown', function handler(e) {
                if (e.key !== 'Tab') return;
                if (e.shiftKey) {
                    if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                } else {
                    if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
                }
            });

            first.focus();
        }

        if (window.MutationObserver) {
            document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
                new MutationObserver(function (muts) {
                    muts.forEach(function (m) {
                        if (m.attributeName === 'style' && overlay.style.display === 'flex') {
                            trapFocus(overlay.querySelector('.modal-panel'));
                        }
                    });
                }).observe(overlay, { attributes: true });
            });
        }

        /* ══════════════════════════════════════════════════════════
           11. ESCAPE KEY
        ══════════════════════════════════════════════════════════ */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.modal-overlay').forEach(function (m) {
                if (m.style.display === 'flex') {
                    var btn = m.querySelector('.modal-close, [data-modal-close]');
                    if (btn) btn.click();
                }
            });
            if (DRAWER && DRAWER.classList.contains('open')) drawerClose();
            if (USER_DROPDOWN && USER_DROPDOWN.classList.contains('open')) {
                USER_DROPDOWN.classList.remove('open');
                if (BTN_USER) { BTN_USER.setAttribute('aria-expanded', 'false'); BTN_USER.focus(); }
            }
            closeAllNavDropdowns(null);
        });

        /* ══════════════════════════════════════════════════════════
           12. TOASTS
        ══════════════════════════════════════════════════════════ */
        function animateToast(el, duration) {
            if (!el) return;
            duration = duration || 5000;

            requestAnimationFrame(function () {
                requestAnimationFrame(function () { el.classList.add('show'); });
            });

            var close = el.querySelector('.toast-close');

            function dismiss() {
                el.classList.remove('show');
                el.classList.add('hide');
                setTimeout(function () { if (el.parentNode) el.remove(); }, 280);
            }

            if (close) close.addEventListener('click', dismiss);
            setTimeout(dismiss, duration);
        }

        document.querySelectorAll('.toast').forEach(function (t) { animateToast(t); });

        window.showToast = function (title, msg, type) {
            type = type || 'success';
            var container = document.getElementById('toast-container');
            if (!container) return;

            var icons = { success: 'fa-check-circle', error: 'fa-exclamation-triangle', warning: 'fa-exclamation-circle' };

            var el = document.createElement('div');
            el.className = 'toast toast-' + type;
            el.setAttribute('role', 'alert');
            el.setAttribute('aria-live', 'assertive');
            el.innerHTML =
                '<div class="toast-icon" aria-hidden="true">' +
                    '<i class="fas ' + (icons[type] || icons.success) + '"></i>' +
                '</div>' +
                '<div class="toast-body">' +
                    '<div class="toast-title">' + title + '</div>' +
                    '<div class="toast-msg">'   + msg   + '</div>' +
                '</div>' +
                '<button type="button" class="toast-close" aria-label="Fermer">&times;</button>';

            container.appendChild(el);
            animateToast(el);
        };

        /* ══════════════════════════════════════════════════════════
           13. INITIALISATION
        ══════════════════════════════════════════════════════════ */
        function boot() {
            initTheme();
            measureHeights();

            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function () {
                    measureHeights();
                    setTimeout(triggerMapResize, 80);
                });
            }

            setTimeout(measureHeights, 400);
            setTimeout(function () { measureHeights(); triggerMapResize(); }, 1300);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }

    })(); /* fin IIFE Fleetra Layout */
    </script>

    @stack('scripts')

</body>
</html>