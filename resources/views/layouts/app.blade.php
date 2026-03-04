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
        font-size: 18px; /* base WCAG 1.4.4 — transforme rem en px lisibles */

        /* ── Typographie ─────────────────────────────────────────── */
        --font-logo:    'Orbitron',  sans-serif;          /* logo UNIQUEMENT */
        --font-display: 'Rajdhani', system-ui, sans-serif; /* titres, menus, labels */
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

        /* Sémantiques — usage fonctionnel UNIQUEMENT */
        --color-success:         #16a34a;
        --color-success-bg:      rgba(22, 163, 74, 0.10);
        --color-error:           #dc2626;
        --color-error-bg:        rgba(220, 38, 38, 0.10);
        --color-warning:         #d97706;
        --color-warning-bg:      rgba(217, 119, 6, 0.10);
        --color-info:            #2563eb;
        --color-info-bg:         rgba(37, 99, 235, 0.10);

        /* ── Radius system ───────────────────────────────────────── */
        /* Principe : moins d'arrondis = plus de sérieux/professionnel */
        --r-none: 0;
        --r-xs:   2px;     /* chips de données, sous-lignes */
        --r-sm:   4px;     /* badges inline, nav items */
        --r-md:   6px;     /* inputs, boutons, selects */
        --r-lg:   8px;     /* cards, panels, modales */
        --r-xl:   12px;    /* toasts, drawers */
        --r-pill: 9999px;  /* badges de STATUT uniquement */

        /* ── Ombres ──────────────────────────────────────────────── */
        --shadow-xs: 0 1px 2px  rgba(0,0,0,0.06);
        --shadow-sm: 0 2px 6px  rgba(0,0,0,0.08);
        --shadow-md: 0 4px 16px rgba(0,0,0,0.10);
        --shadow-lg: 0 8px 32px rgba(0,0,0,0.14);
        --shadow-xl: 0 20px 60px rgba(0,0,0,0.20);

        /* ── Focus ring (WCAG 2.4.7) ─────────────────────────────── */
        --focus-ring: 0 0 0 3px rgba(245, 130, 32, 0.40);

        /* ── Layout ──────────────────────────────────────────────── */
        --sidebar-w:         210px;
        --sidebar-collapsed: 72px;
        --navbar-h:          72px;   /* override par JS après mesure réelle */
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
        --z-sidebar:  25;
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
       Palette reconstruite pour WCAG AA :
       bg #0d1117 / card #1c2333 → delta luminosité suffisant
       textes secondaires #b0bec5 → ratio ~5.2:1 sur #1c2333 ✓
    ════════════════════════════════════════════════════════════════ */
    .dark-mode {
        --color-bg:               #0d1117;
        --color-bg-subtle:        #161b22;
        --color-card:             #1c2333;
        --color-text:             #e6edf3;
        --color-text-muted:       #8b949e;
        --color-secondary-text:   #b0bec5;  /* ← rehaussé vs #9ca3af original */
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

    /* ── Corps de texte → Lato ──────────────────────────────────── */
    p, li, td, label,
    .toast-msg, .text-secondary,
    input, select, textarea {
        font-family: var(--font-body);
    }

    /* ── Titres / Menus / Labels → Rajdhani ─────────────────────── */
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

    /* ── Logo → Orbitron UNIQUEMENT ─────────────────────────────── */
    .brand-logo h1,
    .brand-logo .logo-text {
        font-family: var(--font-logo);
    }

    /* ── Valeurs KPI / Stats → Rajdhani bold ────────────────────── */
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
       SECTION 6 — SIDEBAR
    ════════════════════════════════════════════════════════════════ */
    .sidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        width: var(--sidebar-w);
        z-index: var(--z-sidebar);
        background-color: var(--color-sidebar-bg);
        border-right: 1px solid var(--color-border-subtle);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: width 0.3s ease, transform 0.3s ease, background-color 0.2s;
    }

    /* Scrollbar fine sur le contenu nav */
    .sidebar-scroll {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding-bottom: 4rem;
        scrollbar-width: thin;
        scrollbar-color: var(--color-border-subtle) transparent;
    }
    .sidebar-scroll::-webkit-scrollbar       { width: 3px; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: var(--color-border-subtle); border-radius: 2px; }

    .sidebar.collapsed { width: var(--sidebar-collapsed); }

    /* ── Éléments masqués en mode collapsed ─────────────────────── */
    .sidebar.collapsed .nav-label,
    .sidebar.collapsed .logo-text,
    .sidebar.collapsed .profile-text,
    .sidebar.collapsed .sidebar-section-title {
        opacity: 0;
        visibility: hidden;
        width: 0;
        white-space: nowrap;
        overflow: hidden;
        transition: opacity 0.15s ease, width 0.3s ease;
    }

    .sidebar.collapsed .dropdown-arrow   { display: none !important; }
    .sidebar.collapsed .nav-dropdown     { display: none; }


    .sidebar.collapsed ~ .main-content .kpi-sticky-bar,
.navbar.expanded ~ .main-content .kpi-sticky-bar {
    left: var(--sidebar-collapsed);
}

    /* ── Brand / Logo ─────────────────────────────────────────── */
    .sidebar-brand {
        height: 100px; /* réduit de 160px */
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid var(--color-border-subtle);
        flex-shrink: 0;
        overflow: hidden;
        padding: 0 var(--sp-md);
    }

    .brand-logo {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--sp-xs);
        text-align: center;
    }

    .brand-logo img {
        height: 44px;
        width: auto;
        display: block;
        transition: height 0.3s ease;
        flex-shrink: 0;
    }

    .sidebar.collapsed .brand-logo img { height: 32px; }

    .brand-logo h1, .brand-logo .logo-text {
        font-family: var(--font-logo) !important;
        font-size: clamp(0.9rem, 1.4vw, 1.2rem);
        font-weight: 800;
        color: var(--color-primary);
        margin: 0;
        letter-spacing: 0.06em;
        line-height: 1;
        white-space: nowrap;
        transition: opacity 0.15s ease, width 0.3s ease;
    }

    /* ── Bouton collapse (desktop) ──────────────────────────────── */
    .sidebar-collapse-bar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: var(--sp-xs) var(--sp-md);
        border-bottom: 1px solid var(--color-border-subtle);
        flex-shrink: 0;
    }

    #btn-collapse-desktop {
        width: 26px; height: 26px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        border: 1px solid var(--color-border-subtle);
        background: transparent;
        color: var(--color-secondary-text);
        cursor: pointer;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
        flex-shrink: 0;
        font-size: 0.65rem;
    }

    #btn-collapse-desktop:hover {
        background: var(--color-primary-light);
        color: var(--color-primary);
        border-color: var(--color-primary-border);
    }

    #icon-collapse { transition: transform 0.3s ease; }

    /* ── Section titles ─────────────────────────────────────────── */
    .sidebar-section-title {
        font-family: var(--font-display);
        font-size: 0.6rem;
        font-weight: 700;
        letter-spacing: var(--ls-widest);
        text-transform: uppercase;
        color: var(--color-secondary-text);
        opacity: 0.65;
        padding: 0.875rem var(--sp-lg) 0.25rem;
        white-space: nowrap;
    }

    /* ── Nav items ──────────────────────────────────────────────── */
    .sidebar-nav {
        list-style: none;
        margin: 0;
        padding: var(--sp-xs) 0;
    }

    .sidebar-nav li { position: relative; }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding: 0.55rem var(--sp-lg);
        margin: 1px var(--sp-xs);
        color: var(--color-sidebar-text);
        text-decoration: none;
        border-radius: var(--r-sm); /* 4px — réduit de 0.5rem */
        font-family: var(--font-display);
        font-weight: 600;
        font-size: 0.82rem;
        letter-spacing: 0.01em;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s;
        position: relative;
    }

    .sidebar-nav a:hover,
    .sidebar-nav a.active {
        background: var(--color-sidebar-active);
        color: var(--color-primary);
    }

    /* ── Icônes en ORANGE par défaut (fix #5) ───────────────────── */
    .sidebar-nav a .nav-icon {
        min-width: 1.375rem;
        text-align: center;
        font-size: 0.875rem;
        color: var(--color-primary);  /* ← orange, pas gris */
        opacity: 0.80;
        flex-shrink: 0;
        transition: opacity 0.15s;
    }

    .sidebar-nav a:hover .nav-icon,
    .sidebar-nav a.active .nav-icon { opacity: 1; }

    .sidebar.collapsed .sidebar-nav a {
        justify-content: center;
        padding: 0.625rem 0;
        margin: 1px 4px;
    }

    /* ── Sous-menus dropdown ────────────────────────────────────── */
    .nav-dropdown {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .nav-dropdown.open { max-height: 500px; }

    .nav-dropdown li a {
        padding-left: calc(var(--sp-lg) + 1.625rem);
        font-size: 0.78rem;
        font-weight: 500;
        border-radius: var(--r-xs);
    }

    .dropdown-arrow {
        position: absolute;
        right: var(--sp-lg);
        font-size: 0.6rem;
        color: var(--color-secondary-text);
        transition: transform 0.3s ease;
    }

    .dropdown-toggle.open .dropdown-arrow { transform: rotate(90deg); }

    /* ── Footer sidebar (déconnexion) ───────────────────────────── */
    .sidebar-footer {
        flex-shrink: 0;
        border-top: 1px solid var(--color-border-subtle);
        padding: var(--sp-xs) var(--sp-xs);
        background: var(--color-sidebar-bg);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: var(--sp-sm);
        padding: var(--sp-sm) var(--sp-md);
        border-radius: var(--r-sm);
        color: var(--color-secondary-text);
        text-decoration: none;
        font-family: var(--font-display);
        font-size: 0.75rem;
        font-weight: 600;
        transition: color 0.15s, background 0.15s;
        white-space: nowrap;
    }

    .sidebar-footer a:hover {
        color: var(--color-error);
        background: var(--color-error-bg);
    }

    .sidebar.collapsed .sidebar-footer a {
        justify-content: center;
        padding: var(--sp-sm) 0;
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 7 — NAVBAR
    ════════════════════════════════════════════════════════════════ */
    .navbar {
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-w);
        height: var(--navbar-h);
        z-index: var(--z-navbar);
        background-color: var(--color-navbar-bg);
        border-bottom: 1px solid var(--color-border-subtle);
        display: flex;
        align-items: center;
        padding: 0 var(--sp-xl);
        gap: 0.75rem;
        transition: left 0.3s ease, background-color 0.2s;
        isolation: isolate; /* stacking context pour les dropdowns z-index 50 */
    }

    .navbar.expanded { left: var(--sidebar-collapsed); }

    .navbar-title {
        font-family: var(--font-display);
        font-weight: 700;
        font-size: clamp(1rem, 2vw, 1.35rem);
        color: var(--color-text);
        flex: 1;
        margin: 0;
        letter-spacing: 0.01em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .navbar-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
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

    .user-dropdown a:first-of-type { border-radius: 0 0 0 0; }
    .user-dropdown a:last-of-type  { border-radius: 0 0 var(--r-lg) var(--r-lg); }

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

    /* ════════════════════════════════════════════════════════════════
       SECTION 8 — MAIN CONTENT WRAPPER
    ════════════════════════════════════════════════════════════════ */
    .main-content {
        margin-left: var(--sidebar-w);
        padding-top: var(--navbar-h);
        min-height: 100vh;
        transition: margin-left 0.3s ease;
    }

    .main-content.expanded { margin-left: var(--sidebar-collapsed); }

    .page-inner {
        padding: var(--sp-xl);
        padding-bottom: var(--sp-2xl);
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 9 — STICKY KPI BAR
       z-index 10 : en dessous du navbar (30) mais au-dessus du contenu
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

    /* KPI values — taille augmentée pour lisibilité */
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

    /* Tablette : KPI en 2 colonnes */
    @media (min-width: 768px) and (max-width: 1023px) {
        .kpi-sticky-bar .grid { grid-template-columns: repeat(2, 1fr) !important; }
        .kpi-value { font-size: 1.5rem !important; }
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 10 — FULL-HEIGHT LISTE + CARTE (dashboard desktop)
       Résout le problème #1 : hauteur dynamique correcte
    ════════════════════════════════════════════════════════════════ */
  @media (min-width: 1024px) {

  /* ✅ 25%/75% MAIS la colonne gauche ne descend jamais < 350px */
  .dashboard-content .list-map-grid{
    display: grid;
    grid-template-columns: minmax(350px, 1fr) 3fr; /* gauche >= 350, sinon 25% */
    gap: 1rem; /* garde ton gap-4 Tailwind */
    align-items: stretch;
  }

  /* ✅ On neutralise les col-span Tailwind en desktop (sans toucher au HTML) */
  .dashboard-content .list-map-grid > div.lg\:col-span-1{
    grid-column: 1 / 2 !important;
    min-width: 0;
  }
  .dashboard-content .list-map-grid > div.lg\:col-span-3{
    grid-column: 2 / 3 !important;
    min-width: 0; /* CRITIQUE pour éviter que la map “saute” */
  }

  /* ✅ IMPORTANT : évite que la map disparaisse quand la colonne rétrécit */
  .dashboard-content .list-map-grid > div.lg\:col-span-3 .panel-card{
    height: 100%;
    min-width: 0;
  }
  #fleetMap{
    width: 100%;
    height: 100%;
    min-width: 0;
  }

  /* (optionnel) si tu veux garder ton full-height existant, tu peux conserver
     ton calcul height ici, mais NE TOUCHE PAS aux flex internes si ça cassait la carte */
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
        border-radius: var(--r-lg); /* 8px — réduit vs 0.75rem original */
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
        border-radius: var(--r-md); /* 6px */
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

    /* Cibles tactiles 44px sur mobile */
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

    /* DataTables : override global */
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
    .nav-tab       { border-radius: var(--r-sm); } /* 4px */

    .role-badge,
    .alert-badge   { border-radius: var(--r-pill); } /* pills statut */

    .modal-panel   { border-radius: var(--r-lg); } /* 8px */

    /* Zone de clic étendue sur les actions de tableau */
    .tbl-action {
        position: relative;
        min-width: 32px; min-height: 32px;
    }

    .tbl-action::after {
        content: '';
        position: absolute;
        inset: -6px; /* zone tactile ~44px */
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
        border-radius: var(--r-xl); /* 12px — autorisé pour les toasts */
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

    /* Variantes */
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
       SECTION 20 — MOBILE TRIGGER + OVERLAY
    ════════════════════════════════════════════════════════════════ */
    .btn-mobile-menu {
        display: none;
        position: fixed;
        top: calc((var(--navbar-h) - 34px) / 2);
        left: var(--sp-md);
        width: 34px; height: 34px;
        align-items: center; justify-content: center;
        font-size: 1rem;
        border-radius: 50%;
        cursor: pointer;
        z-index: calc(var(--z-navbar) + 1);
        background: var(--color-card);
        color: var(--color-primary);
        border: 1px solid var(--color-primary-border);
        transition: background 0.15s, color 0.15s;
    }

    .btn-mobile-menu:hover { background: var(--color-primary); color: #fff; }

    .mobile-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.55);
        z-index: var(--z-overlay);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .mobile-overlay.visible { display: block; }
    .mobile-overlay.active  { opacity: 1; }

    /* ════════════════════════════════════════════════════════════════
       SECTION 21 — RESPONSIVE MOBILE
    ════════════════════════════════════════════════════════════════ */
    @media (max-width: 767px) {
        .sidebar {
            transform: translateX(-100%);
            width: var(--sidebar-w);
            z-index: calc(var(--z-overlay) + 1);
        }

        .sidebar.mobile-open {
            transform: translateX(0);
            box-shadow: 4px 0 24px rgba(0,0,0,0.25);
        }

        .main-content { margin-left: 0 !important; }

        .navbar {
            left: 0 !important;
            padding-left: calc(var(--sp-xl) + 2.5rem);
        }

        .btn-mobile-menu { display: flex !important; }
    }

    @media (min-width: 768px) { .btn-mobile-menu { display: none !important; } }

    @media (min-width: 768px) and (max-width: 1023px) {
        .page-inner { padding: 1rem; }
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 22 — UTILITAIRES
    ════════════════════════════════════════════════════════════════ */
    .text-primary   { color: var(--color-primary); }
    .text-secondary { color: var(--color-secondary-text); font-family: var(--font-body); }

    .sr-only {
        position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
    }

    /* ════════════════════════════════════════════════════════════════
       SECTION 23 — PRINT
    ════════════════════════════════════════════════════════════════ */
    @media print {
        .sidebar, .navbar, .kpi-sticky-bar,
        #toast-container, .btn-mobile-menu { display: none !important; }
        .main-content { margin-left: 0 !important; padding-top: 0 !important; }
        .page-inner   { padding: 0 !important; }
    }
    </style>



    @stack('styles')
</head>

{{-- ================================================================
     BODY
     La classe light-mode / dark-mode contrôle les tokens couleurs
================================================================ --}}
<body class="light-mode" id="app-root">

    {{-- Annonces pour lecteurs d'écran (SSE, thème, sidebar) --}}
    <div id="sr-live" aria-live="polite" aria-atomic="true" class="sr-only"></div>

    {{-- ════════════════════════════════════════════════════════
         SIDEBAR
    ════════════════════════════════════════════════════════════ --}}
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Navigation principale">

        {{-- ── Logo ─────────────────────────────────────────── --}}
        <div class="sidebar-brand">
            <div class="brand-logo">
                <img src="{{ asset('assets/images/logo_tracking.png') }}" alt="Logo Fleetra">
                <h1 class="logo-text">Fleetra</h1>
            </div>
        </div>

        {{-- ── Bouton collapse (desktop uniquement) ─────────── --}}
        <div class="sidebar-collapse-bar hidden md:flex">
            <button id="btn-collapse-desktop"
                    aria-label="Réduire ou agrandir le menu"
                    title="Réduire/agrandir">
                <i class="fas fa-chevron-left" id="icon-collapse" aria-hidden="true"></i>
            </button>
        </div>

        {{-- ── Contenu scrollable ───────────────────────────── --}}
        <div class="sidebar-scroll">

            <ul class="sidebar-nav" role="list">

                {{-- Dashboard --}}
                <li>
                    <a href="{{ route('dashboard') }}"
                       class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                        <span class="nav-icon" aria-hidden="true"><i class="fas fa-tachometer-alt"></i></span>
                        <span class="nav-label">Dashboard</span>
                    </a>
                </li>

                {{-- Suivi & Flotte (sous-menu) --}}
                <li>
                    <div class="sidebar-section-title">Flotte</div>
                </li>

                <li>
                    <a href="#"
                       class="dropdown-toggle {{ request()->is('tracking*') || request()->is('users*') || request()->is('trajets*') ? 'active open' : '' }}"
                       data-target="menu-flotte"
                       aria-expanded="{{ request()->is('tracking*') || request()->is('users*') || request()->is('trajets*') ? 'true' : 'false' }}"
                       aria-controls="menu-flotte"
                       aria-haspopup="true">
                        <span class="nav-icon" aria-hidden="true"><i class="fas fa-satellite-dish"></i></span>
                        <span class="nav-label">Suivi &amp; Flotte</span>
                        <i class="fas fa-chevron-right dropdown-arrow" aria-hidden="true"></i>
                    </a>

                    <ul class="nav-dropdown {{ request()->is('tracking*') || request()->is('users*') || request()->is('trajets*') ? 'open' : '' }}"
                        id="menu-flotte" role="list">
                        <li>
                            <a href="{{ route('tracking.vehicles') }}"
                               class="{{ request()->routeIs('tracking.vehicles') ? 'active' : '' }}"
                               aria-current="{{ request()->routeIs('tracking.vehicles') ? 'page' : 'false' }}">
                                <span class="nav-icon" aria-hidden="true"><i class="fas fa-car"></i></span>
                                <span class="nav-label">Véhicules</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('users.index') }}"
                               class="{{ request()->routeIs('users.*') ? 'active' : '' }}"
                               aria-current="{{ request()->routeIs('users.*') ? 'page' : 'false' }}">
                                <span class="nav-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                                <span class="nav-label">Chauffeurs</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('trajets.index') }}"
                               class="{{ request()->routeIs('trajets.*') ? 'active' : '' }}"
                               aria-current="{{ request()->routeIs('trajets.*') ? 'page' : 'false' }}">
                                <span class="nav-icon" aria-hidden="true"><i class="fas fa-route"></i></span>
                                <span class="nav-label">Trajets</span>
                            </a>
                        </li>
                    </ul>
                </li>

                {{-- Séparateur --}}
                <li>
                    <div class="sidebar-section-title">Supervision</div>
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
        </div>{{-- /sidebar-scroll --}}

        {{-- ── Footer déconnexion ───────────────────────────── --}}
        <div class="sidebar-footer">
            <a href="#"
               title="Déconnexion"
               onclick="event.preventDefault(); document.getElementById('form-logout-sidebar').submit();">
                <span class="nav-icon" aria-hidden="true">
                    <i class="fas fa-sign-out-alt"></i>
                </span>
                <span class="nav-label profile-text">Déconnexion</span>
            </a>
            <form id="form-logout-sidebar" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>
        </div>

    </aside>

    {{-- ── Bouton mobile ──────────────────────────────────────── --}}
    <button class="btn-mobile-menu"
            id="btn-mobile-menu"
            aria-label="Ouvrir le menu de navigation"
            aria-expanded="false"
            aria-controls="sidebar">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    {{-- ── Overlay mobile ─────────────────────────────────────── --}}
    <div class="mobile-overlay" id="mobile-overlay" aria-hidden="true"></div>

    {{-- ════════════════════════════════════════════════════════
         NAVBAR
    ════════════════════════════════════════════════════════════ --}}
    <header class="navbar" id="navbar" role="banner">

        {{-- Titre de la page courante --}}
        <h1 class="navbar-title hidden sm:block">@yield('title', 'Dashboard')</h1>

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
                {{-- Pastille rouge (présence d'alertes non lues) --}}
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

        </div>
    </header>

    {{-- ════════════════════════════════════════════════════════
         MAIN CONTENT
    ════════════════════════════════════════════════════════════ --}}
    <main class="main-content" id="main-content" role="main">
        <div class="page-inner">

            {{-- ── Conteneur toast (dynamiques) ─────────────── --}}
            <div id="toast-container" aria-live="polite" aria-atomic="false" role="status"></div>

            {{-- ── Toast session : succès ──────────────────── --}}
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

            {{-- ── Toast session : erreur ───────────────────── --}}
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

    {{-- DataTables init global (overridable par les views) --}}
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
       FLEETRA LAYOUT — JAVASCRIPT PRINCIPAL
       Toutes les fonctions sont encapsulées dans une IIFE pour
       éviter les collisions de variables globales.

       Responsabilités :
         1.  Thème clair / sombre (persist localStorage + system pref)
         2.  Sidebar desktop : collapse / expand
         3.  Sidebar mobile : open / close + overlay
         4.  Sous-menus sidebar (dropdowns)
         5.  Dropdown utilisateur navbar
         6.  Mesure navbar + KPI bar → CSS vars --navbar-h / --kpi-h
         7.  ResizeObserver sur KPI bar
         8.  Debounce window.resize
         9.  Google Maps resize après collapse / resize fenêtre
         10. SSE indicator : patch classes CSS animation
         11. Focus trap modales (WCAG 2.4.3)
         12. Escape key : ferme modales + sidebar mobile
         13. Toasts : affichage session + API window.showToast()
         14. Annonces aria-live pour lecteurs d'écran
    ════════════════════════════════════════════════════════════════ */
    (function () {
        'use strict';

        /* ── Références DOM ─────────────────────────────────────── */
        var ROOT          = document.documentElement;
        var APP           = document.getElementById('app-root');
        var SR_LIVE        = document.getElementById('sr-live');
        var SIDEBAR        = document.getElementById('sidebar');
        var MAIN           = document.getElementById('main-content');
        var NAVBAR         = document.getElementById('navbar');
        var BTN_COLLAPSE   = document.getElementById('btn-collapse-desktop');
        var ICON_COLLAPSE  = document.getElementById('icon-collapse');
        var BTN_MOBILE     = document.getElementById('btn-mobile-menu');
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

        /* Suivi des préférences système en temps réel */
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
   2. SIDEBAR DESKTOP — COLLAPSE / EXPAND
══════════════════════════════════════════════════════════ */
function sidebarCollapse(force) {
    var collapse = (force !== undefined) ? force : !SIDEBAR.classList.contains('collapsed');
    SIDEBAR.classList.toggle('collapsed', collapse);
    MAIN.classList.toggle('expanded', collapse);
    NAVBAR.classList.toggle('expanded', collapse);
    ICON_COLLAPSE.style.transform = collapse ? 'rotate(180deg)' : 'rotate(0deg)';
    localStorage.setItem('fleetra-sidebar', collapse ? '1' : '0');
    var kpiBar = document.querySelector('.kpi-sticky-bar');
    if (kpiBar) kpiBar.style.left = collapse ? 'var(--sidebar-collapsed)' : 'var(--sidebar-w)';
    setTimeout(function () { triggerMapResize(); measureHeights(); }, 320);
    announce(collapse ? 'Menu rétracté' : 'Menu étendu');
}

if (BTN_COLLAPSE) {
    BTN_COLLAPSE.addEventListener('click', function () { sidebarCollapse(); });
}

        /* ══════════════════════════════════════════════════════════
           3. SIDEBAR MOBILE — OPEN / CLOSE
        ══════════════════════════════════════════════════════════ */
        function mobileOpen() {
            SIDEBAR.classList.add('mobile-open');
            OVERLAY.classList.add('visible');
            requestAnimationFrame(function () {
                requestAnimationFrame(function () { OVERLAY.classList.add('active'); });
            });
            document.body.style.overflow = 'hidden';
            BTN_MOBILE.setAttribute('aria-expanded', 'true');
            OVERLAY.setAttribute('aria-hidden', 'false');
        }

        function mobileClose() {
            SIDEBAR.classList.remove('mobile-open');
            OVERLAY.classList.remove('active');
            setTimeout(function () {
                OVERLAY.classList.remove('visible');
                OVERLAY.setAttribute('aria-hidden', 'true');
            }, 300);
            document.body.style.overflow = '';
            BTN_MOBILE.setAttribute('aria-expanded', 'false');
        }

        if (BTN_MOBILE) {
            BTN_MOBILE.addEventListener('click', function () {
                SIDEBAR.classList.contains('mobile-open') ? mobileClose() : mobileOpen();
            });
        }

        if (OVERLAY) OVERLAY.addEventListener('click', mobileClose);

        /* ══════════════════════════════════════════════════════════
           4. SOUS-MENUS SIDEBAR (dropdowns)
        ══════════════════════════════════════════════════════════ */
        document.querySelectorAll('.dropdown-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                if (SIDEBAR.classList.contains('collapsed')) return;

                var targetId = toggle.getAttribute('data-target');
                var drop     = document.getElementById(targetId);
                if (!drop) return;

                var opening = !drop.classList.contains('open');

                /* Fermer tous les autres */
                document.querySelectorAll('.nav-dropdown.open').forEach(function (d) {
                    if (d.id !== targetId) {
                        d.classList.remove('open');
                        var t = document.querySelector('[data-target="' + d.id + '"]');
                        if (t) { t.classList.remove('open'); t.setAttribute('aria-expanded', 'false'); }
                    }
                });

                drop.classList.toggle('open', opening);
                toggle.classList.toggle('open', opening);
                toggle.setAttribute('aria-expanded', String(opening));
            });
        });

        /* Initialiser les menus actifs */
        document.querySelectorAll('.nav-dropdown.open').forEach(function (d) {
            var t = document.querySelector('[data-target="' + d.id + '"]');
            if (t) { t.classList.add('open'); t.setAttribute('aria-expanded', 'true'); }
        });

        /* ══════════════════════════════════════════════════════════
           5. DROPDOWN UTILISATEUR NAVBAR
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
           6. MESURE HAUTEURS → CSS VARS
           Garantit que --navbar-h et --kpi-h sont exacts à tout moment
        ══════════════════════════════════════════════════════════ */
        function measureHeights() {
            var navH = NAVBAR ? Math.round(NAVBAR.getBoundingClientRect().height) : 72;
            var kpiEl = document.querySelector('.kpi-sticky-bar');
            var kpiH  = kpiEl  ? Math.round(kpiEl.getBoundingClientRect().height) : 0;
            ROOT.style.setProperty('--navbar-h', navH + 'px');
            if (kpiH > 0) ROOT.style.setProperty('--kpi-h', kpiH + 'px');
        }

        /* ══════════════════════════════════════════════════════════
           7. RESIZEOBSERVER SUR KPI BAR
           Réagit aux changements de hauteur (fonts chargées, contenu)
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
           8. WINDOW RESIZE — debounce 120ms
        ══════════════════════════════════════════════════════════ */
        function syncOnResize() {
            if (window.innerWidth < 768) {
                /* Mobile : retirer le collapsed desktop */
                SIDEBAR.classList.remove('collapsed');
                MAIN.classList.remove('expanded');
                NAVBAR.classList.remove('expanded');
            } else {
                mobileClose();
                var saved = (localStorage.getItem('fleetra-sidebar') === '1');
                sidebarCollapse(saved);
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
           9. GOOGLE MAPS RESIZE
           Nécessaire après collapse sidebar + resize fenêtre
        ══════════════════════════════════════════════════════════ */
        function triggerMapResize() {
            if (window.google && window.google.maps && window.map) {
                google.maps.event.trigger(window.map, 'resize');
            }
        }

        /* ══════════════════════════════════════════════════════════
           10. SSE INDICATOR — PATCH CLASSES CSS
           Wrappe la fonction setSseIndicator() définie dans dashboard.blade
           pour y ajouter les classes d'animation pulse CSS
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

            /* Wrapper non-destructif : préserve la fonction existante */
            var _orig = window.setSseIndicator;
            window.setSseIndicator = function (state) {
                if (typeof _orig === 'function') _orig(state);
                applyClass(state);
            };
        })();

        /* ══════════════════════════════════════════════════════════
           11. FOCUS TRAP MODALES (WCAG 2.4.3)
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

        /* Observer l'ouverture des modales pour activer le trap */
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
           12. ESCAPE KEY — ferme modales + sidebar mobile
        ══════════════════════════════════════════════════════════ */
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            /* Modales ouvertes */
            document.querySelectorAll('.modal-overlay').forEach(function (m) {
                if (m.style.display === 'flex') {
                    var btn = m.querySelector('.modal-close, [data-modal-close]');
                    if (btn) btn.click();
                }
            });
            /* Sidebar mobile */
            if (SIDEBAR.classList.contains('mobile-open')) mobileClose();
            /* Dropdown utilisateur */
            if (USER_DROPDOWN && USER_DROPDOWN.classList.contains('open')) {
                USER_DROPDOWN.classList.remove('open');
                if (BTN_USER) { BTN_USER.setAttribute('aria-expanded', 'false'); BTN_USER.focus(); }
            }
        });

        /* ══════════════════════════════════════════════════════════
           13. TOASTS
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

        /* Toasts issus de session (dans le DOM au chargement) */
        document.querySelectorAll('.toast').forEach(function (t) { animateToast(t); });

        /**
         * API publique : window.showToast(title, message, type)
         * type : 'success' | 'error' | 'warning'
         * Utilisable depuis n'importe quelle view JavaScript
         */
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
           14. INITIALISATION
        ══════════════════════════════════════════════════════════ */
        function boot() {
            initTheme();
            syncOnResize();
            measureHeights();

            /* Re-mesurer quand toutes les fonts sont prêtes */
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function () {
                    measureHeights();
                    setTimeout(triggerMapResize, 80);
                });
            }

            /* Sécurité : re-mesurer à 400ms et 1300ms
               (Google Fonts async, Google Maps init, ressources lazy) */
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