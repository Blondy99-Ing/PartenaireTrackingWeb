<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    {{-- Viewport standard — on NE force PAS de zoom/scale ici pour respecter l'accessibilité --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'ProxymTracking Dashboard')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

    @stack('styles')
    @stack('head')

    <style>
    /* ============================================================
       DESIGN SYSTEM — TOKENS & POLICES
       L'effet "compact 90%" est obtenu par :
         - base font-size: 14.4px (soit 90% de 16px) au lieu d'utiliser transform:scale()
         - spacing tokens légèrement réduits
         - clamp() sur les titres pour fluidité entre breakpoints
       ============================================================ */
    :root {
        /* Base 14.4px = effet visuel ~90% sans casser l'accessibilité */
        font-size: 14.4px;

        /* === Couleurs === */
        --color-primary:       #F58220;
        --color-primary-light: #FF9800;
        --color-primary-dark:  #E65100;

        /* === Typographie === */
        --font-display: 'Orbitron', sans-serif;
        /* Police système sans-serif, lisible partout, légère */
        --font-body: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont,
                     "Segoe UI", Helvetica, Arial, sans-serif;

        /* === Layout === */
        --sidebar-width:           260px;
        --sidebar-collapsed-width:  72px;
        --navbar-h:                4.5rem;  /* légèrement réduit pour l'effet compact */

        /* === Spacing tokens (compact) === */
        --sp-xs:  0.25rem;   /* 3.6px  */
        --sp-sm:  0.5rem;    /* 7.2px  */
        --sp-md:  0.875rem;  /* 12.6px */
        --sp-lg:  1.25rem;   /* 18px   */
        --sp-xl:  1.75rem;   /* 25.2px */
        --sp-2xl: 2.25rem;   /* 32.4px */

        /* === Z-index stacking (propre, centralisé) === */
        --z-sidebar:  20;
        --z-overlay:  19;
        --z-navbar:   30;   /* navbar AU-DESSUS de tout sauf modals */
        --z-dropdown: 50;   /* dropdowns profil, notifications AU-DESSUS de sticky KPI */
        --z-kpi:      10;   /* sticky KPI sous navbar et dropdowns */
        --z-toast:  9999;
    }

    /* ============================================================
       RESET / BASE
       ============================================================ */
    *, *::before, *::after { box-sizing: border-box; }

    body {
        font-family: var(--font-body);
        font-size: 1rem; /* = 14.4px (notre base) */
        min-height: 100vh;
        margin: 0;
        -webkit-font-smoothing: antialiased;
    }

    /* Titres → Orbitron */
    h1, h2, h3, h4, h5, h6,
    .font-orbitron {
        font-family: var(--font-display);
    }

    /* ============================================================
       LIGHT MODE
       ============================================================ */
    .light-mode {
        --color-bg:               #f3f4f6;
        --color-card:             #ffffff;
        --color-text:             #111827;
        --color-input-bg:         #ffffff;
        --color-input-border:     #d1d5db;
        --color-secondary-text:   #6b7280;
        --color-sidebar-bg:       #ffffff;
        --color-sidebar-text:     #1f2937;
        --color-sidebar-active-bg:rgba(245, 130, 32, 0.10);
        --color-border-subtle:    #e5e7eb;
        --color-navbar-bg:        #ffffff;
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    /* ============================================================
       DARK MODE
       ============================================================ */
    .dark-mode {
        --color-bg:               #111827;
        --color-card:             #1f2937;
        --color-text:             #f3f4f6;
        --color-input-bg:         #374151;
        --color-input-border:     #4b5563;
        --color-secondary-text:   #9ca3af;
        --color-sidebar-bg:       #1f2937;
        --color-sidebar-text:     #f3f4f6;
        --color-sidebar-active-bg:rgba(245, 130, 32, 0.20);
        --color-border-subtle:    #374151;
        --color-navbar-bg:        #1f2937;
        background-color: var(--color-bg);
        color: var(--color-text);
    }

    /* ============================================================
       SIDEBAR
       ============================================================ */
    .sidebar {
        width: var(--sidebar-width);
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: var(--z-sidebar);
        transition: width 0.3s ease, transform 0.3s ease, background-color 0.3s;
        overflow-y: auto;
        overflow-x: hidden;
        border-right: 1px solid var(--color-border-subtle);
        background-color: var(--color-sidebar-bg);
        font-family: var(--font-display); /* Sidebar entière en Orbitron */
        font-size: 0.8rem;
        padding-bottom: 5rem;
    }

    /* Collapsed */
    .sidebar.collapsed { width: var(--sidebar-collapsed-width); }

    /* Éléments masqués en mode collapsed (texte uniquement) */
    .sidebar.collapsed .nav-label,
    .sidebar.collapsed .logo-text,
    .sidebar.collapsed .profile-text,
    .sidebar.collapsed .nav-dropdown,
    .sidebar.collapsed .sidebar-section-title {
        opacity: 0;
        visibility: hidden;
        width: 0;
        overflow: hidden;
        white-space: nowrap;
        transition: opacity 0.15s, width 0.3s;
    }

    /* Flèche des dropdowns masquée en collapsed */
    .sidebar.collapsed .dropdown-arrow { display: none; }

    /* ---- Logo / Branding ---- */
    .brand {
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-bottom: 1px solid var(--color-border-subtle);
        overflow: hidden;
        flex-shrink: 0;
    }

    .brand-logo {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: var(--sp-sm);
        text-align: center;
        transition: all 0.3s;
    }

    /* Le logo (image) reste toujours visible */
    .brand-logo img {
        height: 64px;
        width: auto;
        display: block;
        flex-shrink: 0;
        transition: height 0.3s;
    }

    /* En collapsed, on réduit le logo */
    .sidebar.collapsed .brand-logo img { height: 42px; }

    .brand-logo h1 {
        margin: 0;
        font-size: clamp(1rem, 1.5vw, 1.35rem);
        font-weight: 800;
        color: var(--color-primary);
        line-height: 1.1;
        white-space: nowrap;
    }

    /* ---- Nav Links ---- */
    .sidebar-nav {
        list-style: none;
        margin: 0;
        padding: var(--sp-sm) 0;
    }

    .sidebar-nav li { position: relative; }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        gap: var(--sp-sm);
        padding: var(--sp-md) var(--sp-lg);
        margin: 2px var(--sp-xs);
        color: var(--color-sidebar-text);
        text-decoration: none;
        transition: background-color 0.2s, color 0.2s;
        border-radius: 0.5rem;
        white-space: nowrap;
        position: relative;
    }

    .sidebar-nav a:hover,
    .sidebar-nav a.active {
        background-color: var(--color-sidebar-active-bg);
        color: var(--color-primary);
    }

    .sidebar-nav a .nav-icon {
        min-width: 2rem;
        text-align: center;
        font-size: 1rem;
        color: var(--color-secondary-text);
        flex-shrink: 0;
    }

    .sidebar-nav a:hover .nav-icon,
    .sidebar-nav a.active .nav-icon { color: var(--color-primary); }

    /* Collapsed : centrer icône */
    .sidebar.collapsed .sidebar-nav a {
        justify-content: center;
        padding: var(--sp-md) 0;
    }

    /* ---- Dropdown sous-menus ---- */
    .nav-dropdown {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        background-color: var(--color-sidebar-bg);
    }

    .nav-dropdown.open { max-height: 400px; }

    .nav-dropdown li a {
        padding-left: calc(var(--sp-lg) + 2rem);
        font-size: 0.78rem;
        margin: 1px var(--sp-xs);
    }

    .sidebar.collapsed .nav-dropdown { display: none; }

    /* FIX : flèche dropdown — pointe vers le bas par défaut, tourne 90° à l'ouverture */
    .dropdown-arrow {
        position: absolute;
        right: var(--sp-lg);
        font-size: 0.65rem;
        transition: transform 0.3s ease;
        transform: rotate(0deg); /* Pointe vers la droite au repos */
    }

    .dropdown-toggle.open .dropdown-arrow {
        transform: rotate(90deg); /* Pointe vers le bas à l'ouverture */
    }

    /* ---- Footer logout ---- */
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        padding: var(--sp-sm);
        border-top: 1px solid var(--color-border-subtle);
        background-color: var(--color-sidebar-bg);
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        gap: var(--sp-sm);
        padding: var(--sp-sm) var(--sp-md);
        border-radius: 0.5rem;
        color: var(--color-secondary-text);
        text-decoration: none;
        font-family: var(--font-display);
        font-size: 0.75rem;
        transition: color 0.2s, background-color 0.2s;
    }

    .sidebar-footer a:hover { color: #ef4444; }

    .sidebar.collapsed .sidebar-footer a { justify-content: center; }

    /* ============================================================
       BOUTON COLLAPSE SIDEBAR (Desktop)
       ============================================================ */
    .toggle-collapse-btn {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: var(--sp-sm) var(--sp-md);
    }

    #toggle-sidebar-desktop {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 1px solid var(--color-border-subtle);
        background: transparent;
        color: var(--color-secondary-text);
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s, transform 0.2s;
    }

    #toggle-sidebar-desktop:hover {
        background-color: var(--color-sidebar-active-bg);
        color: var(--color-primary);
    }

    /* FIX : icône tourne correctement selon état */
    #toggle-icon-desktop { transition: transform 0.3s ease; }
    /* En expanded (non-collapsed) la flèche pointe à gauche (←) */
    /* En collapsed la flèche pointe à droite (→) via rotate-180 appliqué en JS */

    /* ============================================================
       NAVBAR
       ============================================================ */
    .navbar {
        position: fixed;
        top: 0;
        right: 0;
        left: var(--sidebar-width);
        height: var(--navbar-h);
        z-index: var(--z-navbar);
        background-color: var(--color-navbar-bg);
        border-bottom: 1px solid var(--color-border-subtle);
        transition: left 0.3s ease, background-color 0.3s;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 var(--sp-xl);
        /* Crée un stacking context pour que les dropdowns de la navbar soient au-dessus */
        isolation: isolate;
    }

    .navbar.expanded { left: var(--sidebar-collapsed-width); }

    /* Titre page dans la navbar */
    .navbar-title {
        font-family: var(--font-display);
        font-weight: 700;
        font-size: clamp(1rem, 2vw, 1.4rem);
        color: var(--color-text);
        flex: 1;
        margin: 0;
    }

    /* ---- Dropdown utilisateur ---- */
    .user-menu-wrapper { position: relative; }

    .user-dropdown-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        /* z-index SUPÉRIEUR au sticky KPI (--z-kpi: 10) */
        z-index: var(--z-dropdown);
        width: 210px;
        background-color: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        border-radius: 0.625rem;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        opacity: 0;
        visibility: hidden;
        transform: translateY(-8px);
        transition: opacity 0.2s, transform 0.2s, visibility 0s 0.2s;
    }

    .user-dropdown-menu.open {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
        transition: opacity 0.2s, transform 0.2s, visibility 0s;
    }

    .user-dropdown-menu a {
        display: flex;
        align-items: center;
        gap: var(--sp-sm);
        padding: var(--sp-md) var(--sp-lg);
        color: var(--color-text);
        text-decoration: none;
        font-size: 0.85rem;
        transition: background-color 0.2s;
    }

    .user-dropdown-menu a:hover {
        background-color: var(--color-sidebar-active-bg);
        color: var(--color-primary);
    }

    .user-dropdown-menu .user-info {
        padding: var(--sp-md) var(--sp-lg);
        border-bottom: 1px solid var(--color-border-subtle);
    }

    /* ---- Icônes navbar ---- */
    .navbar-icon-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 1rem;
        color: var(--color-text);
        background: transparent;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        position: relative;
    }

    .navbar-icon-btn:hover {
        background-color: var(--color-sidebar-active-bg);
        color: var(--color-primary);
    }

    /* ---- Toggle mode sombre ---- */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 22px;
        cursor: pointer;
        border-radius: 11px;
        background-color: var(--color-input-border);
        transition: background-color 0.3s;
        flex-shrink: 0;
    }

    .toggle-switch::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background-color: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        transition: transform 0.3s ease;
    }

    .toggle-switch.toggled { background-color: var(--color-primary); }
    .toggle-switch.toggled::after { transform: translateX(22px); }

    /* ============================================================
       MAIN CONTENT
       ============================================================ */
    .main-content {
        margin-left: var(--sidebar-width);
        transition: margin-left 0.3s ease;
        min-height: 100vh;
        padding-top: var(--navbar-h);
    }

    .main-content.expanded { margin-left: var(--sidebar-collapsed-width); }

    .page-inner { padding: var(--sp-xl); }

    /* ============================================================
       STICKY KPI DASHBOARD (Option 1 — opaque, desktop seulement)
       ============================================================ */
    .dashboard-stats-sticky {
        position: sticky;
        top: var(--navbar-h);
        z-index: var(--z-kpi);
        /* Fond opaque = même couleur que le background global → pas de "trous blancs" */
        background-color: var(--color-bg);
        /* Padding vertical pour espacer du bord et couvrir proprement */
        padding-top: var(--sp-sm);
        padding-bottom: var(--sp-sm);
    }

    /* Sur petit écran : désactiver sticky → redevient flux normal */
    @media (max-width: 1023px) {
        .dashboard-stats-sticky {
            position: static;
            background-color: transparent;
            padding-top: 0;
            padding-bottom: 0;
        }
    }

    /* ============================================================
       COMPOSANTS UI
       ============================================================ */

    /* Card */
    .ui-card {
        background-color: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        color: var(--color-text);
        border-radius: 0.75rem;
        padding: var(--sp-lg);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    /* Inputs / Selects / Textareas — FIX texte visible dans les select/filtres */
    .ui-input-style,
    .ui-textarea-style,
    .ui-select-style,
    select, input[type="text"], input[type="search"],
    input[type="email"], input[type="number"], textarea {
        background-color: var(--color-input-bg);
        border: 1px solid var(--color-input-border);
        /* FIX critique : forcer la couleur du texte (évite texte invisible) */
        color: var(--color-text) !important;
        padding: var(--sp-sm) var(--sp-md);
        border-radius: 0.5rem;
        transition: border-color 0.2s, box-shadow 0.2s;
        width: 100%;
        font-family: var(--font-body);
        font-size: 0.875rem;
        appearance: auto; /* Garder l'apparence native pour la flèche select */
    }

    /* FIX : options dans les select héritent aussi la couleur */
    select option {
        background-color: var(--color-input-bg);
        color: var(--color-text);
    }

    .ui-input-style:focus,
    .ui-textarea-style:focus,
    .ui-select-style:focus,
    select:focus, input:focus, textarea:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(245, 130, 32, 0.25);
    }

    /* Table */
    .ui-table-container {
        overflow-x: auto;
        border-radius: 0.5rem;
        border: 1px solid var(--color-border-subtle);
    }

    .ui-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .ui-table th {
        font-family: var(--font-display);
        font-size: 0.75rem;
        background-color: var(--color-border-subtle);
        color: var(--color-text);
        padding: var(--sp-md) var(--sp-lg);
        text-align: left;
        border-bottom: 2px solid var(--color-primary);
        white-space: nowrap;
    }

    .ui-table td {
        padding: var(--sp-sm) var(--sp-lg);
        border-bottom: 1px solid var(--color-border-subtle);
        color: var(--color-text);
    }

    .ui-table tr:hover td { background-color: var(--color-sidebar-active-bg); }

    /* FIX DataTables footer centré */
    .dataTables_wrapper .dataTables_paginate,
    .dataTables_wrapper .dataTables_info {
        display: flex;
        justify-content: center;
        margin-top: var(--sp-md);
        color: var(--color-secondary-text);
        font-size: 0.8rem;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        color: var(--color-text) !important;
        background-color: var(--color-input-bg);
        border-color: var(--color-input-border);
    }

    /* Boutons */
    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-xs);
        background-color: var(--color-primary);
        color: white;
        padding: var(--sp-sm) var(--sp-lg);
        border-radius: 0.5rem;
        font-weight: 600;
        font-family: var(--font-display);
        font-size: 0.8rem;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s, transform 0.1s;
        text-decoration: none;
    }

    .btn-primary:hover {
        background-color: var(--color-primary-dark);
        transform: translateY(-1px);
    }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-xs);
        color: var(--color-primary);
        border: 1px solid var(--color-primary);
        padding: var(--sp-sm) var(--sp-lg);
        border-radius: 0.5rem;
        font-weight: 600;
        font-family: var(--font-display);
        font-size: 0.8rem;
        background-color: transparent;
        cursor: pointer;
        transition: background-color 0.2s;
        text-decoration: none;
    }

    .btn-secondary:hover { background-color: rgba(245, 130, 32, 0.10); }

    /* FIX "Espace partenaire" : lien stylé comme bouton */
    .btn-partner-login {
        display: inline-flex;
        align-items: center;
        gap: var(--sp-xs);
        background-color: transparent;
        color: var(--color-primary);
        border: 1.5px solid var(--color-primary);
        padding: var(--sp-sm) var(--sp-lg);
        border-radius: 0.5rem;
        font-weight: 700;
        font-family: var(--font-display);
        font-size: 0.78rem;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        text-decoration: none;
    }

    .btn-partner-login:hover {
        background-color: var(--color-primary);
        color: white;
    }

    /* ============================================================
       TEXTE UTILITAIRES
       ============================================================ */
    .text-primary { color: var(--color-primary); }
    .text-secondary { color: var(--color-secondary-text); }

    /* ============================================================
       MAP
       ============================================================ */
    #fleetMap, map {
        width: 100%;
        height: 400px;
        min-height: 300px;
        border-radius: 0.5rem;
    }

    /* ============================================================
       MOBILE TOGGLE BUTTON
       ============================================================ */
    .toggle-sidebar-mobile {
        display: none;
        position: fixed;
        top: calc((var(--navbar-h) - 36px) / 2);
        left: var(--sp-md);
        width: 36px;
        height: 36px;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        border-radius: 50%;
        cursor: pointer;
        z-index: calc(var(--z-navbar) + 1);
        background-color: var(--color-card);
        color: var(--color-primary);
        border: 1px solid var(--color-primary);
        transition: background-color 0.2s, color 0.2s;
    }

    .toggle-sidebar-mobile:hover {
        background-color: var(--color-primary);
        color: #fff;
    }

    /* ============================================================
       OVERLAY MOBILE
       ============================================================ */
    .overlay {
        display: none;
        position: fixed;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: var(--z-overlay);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .overlay.visible { display: block; }
    .overlay.active  { opacity: 1; }

    /* ============================================================
       RESPONSIVE — MOBILE (< 768px)
       ============================================================ */
    @media (max-width: 767px) {
        .sidebar {
            transform: translateX(-100%);
            width: var(--sidebar-width);
            z-index: calc(var(--z-overlay) + 1);
        }

        .sidebar.mobile-open {
            transform: translateX(0);
            box-shadow: 4px 0 16px rgba(0, 0, 0, 0.2);
        }

        .main-content {
            margin-left: 0 !important;
        }

        .navbar {
            left: 0 !important;
            padding-left: calc(var(--sp-xl) + 2.5rem); /* espace pour le bouton mobile */
        }

        .toggle-sidebar-mobile { display: flex !important; }

        /* Pas de collapsed sur mobile */
        .main-content.expanded,
        .navbar.expanded {
            margin-left: 0 !important;
            left: 0 !important;
        }
    }

    /* Cache le bouton mobile sur desktop */
    @media (min-width: 768px) {
        .toggle-sidebar-mobile { display: none !important; }
    }

    /* ============================================================
       TOAST NOTIFICATIONS
       ============================================================ */
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
        padding: 14px 14px 12px;
        border-radius: 16px;
        border: 1px solid var(--color-border-subtle);
        background: var(--color-card);
        color: var(--color-text);
        box-shadow: 0 12px 40px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.10);
        transform: translateY(-8px) scale(0.98);
        opacity: 0;
        transition: transform 0.25s ease, opacity 0.25s ease;
        position: relative;
        overflow: hidden;
    }

    .toast.show { transform: translateY(0) scale(1); opacity: 1; }
    .toast.hide { transform: translateY(-8px) scale(0.98); opacity: 0; }

    .toast::before {
        content: "";
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 5px;
        border-radius: 16px 0 0 16px;
    }

    .toast::after {
        content: "";
        position: absolute;
        left: 0; right: 0; bottom: 0;
        height: 3px;
        transform-origin: left center;
    }

    .toast.show::after { animation: toastProgress 5s linear forwards; }

    @keyframes toastProgress {
        from { transform: scaleX(1); }
        to   { transform: scaleX(0); }
    }

    .toast-icon {
        width: 40px; height: 40px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; flex-shrink: 0; font-size: 1.1rem;
    }

    .toast-title {
        font-family: var(--font-display);
        font-weight: 800;
        font-size: 0.9rem;
        line-height: 1.2;
        margin-top: 1px;
    }

    .toast-msg { margin-top: 3px; font-size: 0.85rem; color: var(--color-secondary-text); }

    .toast-close {
        margin-left: auto; width: 32px; height: 32px;
        border-radius: 10px; border: 1px solid var(--color-border-subtle);
        background: transparent; color: var(--color-text);
        opacity: 0.7; cursor: pointer; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        transition: opacity 0.2s, transform 0.1s;
    }

    .toast-close:hover { opacity: 1; transform: translateY(-1px); }

    /* Success */
    .toast-success { border-color: rgba(34,197,94,.3); }
    .toast-success::before { background: linear-gradient(180deg, #22c55e, #16a34a); }
    .toast-success::after  { background: linear-gradient(90deg, #22c55e, #16a34a); }
    .toast-success .toast-icon { background: linear-gradient(135deg, #22c55e, #16a34a); }
    .toast-success .toast-title { color: #16a34a; }

    /* Error */
    .toast-error { border-color: rgba(239,68,68,.3); }
    .toast-error::before { background: linear-gradient(180deg, #ef4444, #b91c1c); }
    .toast-error::after  { background: linear-gradient(90deg, #ef4444, #b91c1c); }
    .toast-error .toast-icon { background: linear-gradient(135deg, #ef4444, #b91c1c); }
    .toast-error .toast-title { color: #ef4444; }

    /* Warning */
    .toast-warning { border-color: rgba(234,179,8,.3); }
    .toast-warning::before { background: linear-gradient(180deg, #eab308, #ca8a04); }
    .toast-warning::after  { background: linear-gradient(90deg, #eab308, #ca8a04); }
    .toast-warning .toast-icon { background: linear-gradient(135deg, #eab308, #ca8a04); }
    .toast-warning .toast-title { color: #ca8a04; }
    </style>
</head>

<body class="light-mode" id="theme-container">

    {{-- ====================================================
         SIDEBAR
         ==================================================== --}}
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Navigation principale">

        {{-- Logo --}}
        <div class="brand">
            <div class="brand-logo">
                {{-- Image : toujours visible, même en collapsed --}}
                <img src="{{ asset('assets/images/logo_tracking.png') }}" alt="Fleetra logo">
                {{-- Texte masqué en collapsed --}}
                <h1 class="logo-text">Fleetra</h1>
            </div>
        </div>

        {{-- Bouton collapse (desktop uniquement) --}}
        <div class="toggle-collapse-btn hidden md:flex">
            <button id="toggle-sidebar-desktop" class="navbar-icon-btn" title="Rétracter/étendre la sidebar">
                {{-- Pointe à gauche par défaut (sidebar ouverte) --}}
                <i class="fas fa-chevron-left" id="toggle-icon-desktop"></i>
            </button>
        </div>

        {{-- Navigation --}}
        <ul class="sidebar-nav">

            <li>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>

            {{-- Sous-menu Suivi & Localisation --}}
            <li class="nav-item">
                {{-- FIX : data-dropdown correspond bien à l'id de la liste --}}
                <a href="#" class="dropdown-toggle {{ request()->is('tracking*') || request()->is('users*') || request()->is('trajets*') ? 'active' : '' }}"
                   data-dropdown="tracking-menu"
                   aria-expanded="false">
                    <span class="nav-icon"><i class="fas fa-satellite-dish"></i></span>
                    <span class="nav-label">Suivi &amp; Localisation</span>
                    {{-- FIX : classe dropdown-arrow (pointe droite au repos, tourne en .open) --}}
                    <i class="fas fa-chevron-right dropdown-arrow" aria-hidden="true"></i>
                </a>
                <ul class="nav-dropdown {{ request()->is('tracking*') || request()->is('users*') || request()->is('trajets*') ? 'open' : '' }}"
                    id="tracking-menu">
                    <li>
                        <a href="{{ route('tracking.vehicles') }}"
                           class="{{ request()->routeIs('tracking.vehicles') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fas fa-car"></i></span>
                            <span class="nav-label">Véhicules</span>
                        </a>
                    </li>
                    <li>
                        {{-- FIX : cet onglet était perdu après clic Associations → routeIs corrigé --}}
                        <a href="{{ route('users.index') }}"
                           class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fas fa-users"></i></span>
                            <span class="nav-label">Chauffeurs</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('trajets.index') }}"
                           class="{{ request()->routeIs('trajets.*') ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fas fa-route"></i></span>
                            <span class="nav-label">Trajets</span>
                        </a>
                    </li>
                </ul>
            </li>

            <li>
                <a href="{{ route('alerts.view') }}"
                   class="{{ request()->routeIs('alerts.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span class="nav-label">Alertes</span>
                </a>
            </li>

            <li>
                <a href="{{ route('engine.action.index') }}"
                   class="{{ request()->routeIs('engine.*') ? 'active' : '' }}">
                    <span class="nav-icon"><i class="fas fa-power-off"></i></span>
                    <span class="nav-label">Coupure Moteur</span>
                </a>
            </li>

        </ul>

        {{-- Footer déconnexion --}}
        <div class="sidebar-footer">
            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-sidebar').submit();" title="Déconnexion">
                <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                <span class="nav-label profile-text">Déconnexion</span>
            </a>
            <form id="logout-form-sidebar" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
        </div>

    </aside>

    {{-- Bouton ouvrir sidebar mobile --}}
    <button class="toggle-sidebar-mobile" id="toggle-btn" aria-label="Ouvrir le menu">
        <i class="fas fa-bars"></i>
    </button>

    {{-- Overlay mobile --}}
    <div class="overlay" id="mobile-overlay" aria-hidden="true"></div>

    {{-- ====================================================
         NAVBAR
         ==================================================== --}}
    <header class="navbar" id="navbar">

        {{-- Titre page --}}
        <h1 class="navbar-title hidden sm:block">@yield('title', 'Dashboard')</h1>

        <div style="display:flex;align-items:center;gap:1rem;flex-shrink:0;">

            {{-- Toggle dark/light --}}
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <span class="text-secondary hidden lg:block" style="font-family:var(--font-display);font-size:0.72rem;" id="mode-label">Mode Clair</span>
                <div id="theme-toggle" class="toggle-switch" role="switch" aria-checked="false" tabindex="0" title="Basculer le thème"></div>
            </div>

            {{-- Cloche notifications — FIX : lien actif vers alerts.view --}}
            <a href="{{ route('alerts.view') }}" class="navbar-icon-btn" title="Alertes &amp; Notifications" style="position:relative;">
                <i class="fas fa-bell"></i>
                {{-- Point rouge si des alertes existent --}}
                <span style="position:absolute;top:6px;right:6px;width:8px;height:8px;background:#ef4444;border-radius:50%;border:2px solid var(--color-card);"></span>
            </a>

            {{-- Menu utilisateur --}}
            <div class="user-menu-wrapper" id="user-menu-container">
                <button
                    style="display:flex;align-items:center;gap:0.5rem;padding:4px;border-radius:9999px;background:transparent;border:none;cursor:pointer;transition:background-color 0.2s;"
                    id="user-menu-toggle"
                    aria-haspopup="true"
                    aria-expanded="false">
                    <img src="https://placehold.co/36x36/F58220/ffffff?text={{ substr(auth()->user()->prenom ?? 'U', 0, 1) }}"
                         alt="Profil"
                         style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--color-primary);">
                    <span class="hidden lg:block" style="font-weight:600;font-size:0.85rem;color:var(--color-text);white-space:nowrap;">
                        {{ auth()->user()->prenom }} {{ auth()->user()->nom }}
                    </span>
                    <i class="fas fa-chevron-down" style="font-size:0.65rem;color:var(--color-secondary-text);"></i>
                </button>

                {{-- Dropdown utilisateur --}}
                <div class="user-dropdown-menu" id="user-menu" role="menu">
                    <div class="user-info">
                        <p style="font-weight:600;font-size:0.85rem;margin:0;">{{ auth()->user()->prenom }} {{ auth()->user()->nom }}</p>
                        <p style="font-size:0.75rem;color:var(--color-secondary-text);margin:2px 0 0;">{{ auth()->user()->email }}</p>
                    </div>
                    <a href="{{ route('profile.edit') }}" role="menuitem">
                        <i class="fas fa-user-circle" style="width:16px;"></i> Mon Profil
                    </a>
                    <a href="#" role="menuitem">
                        <i class="fas fa-cog" style="width:16px;"></i> Paramètres
                    </a>
                    <a href="#" class="text-red-500" role="menuitem"
                       onclick="event.preventDefault(); document.getElementById('logout-form-navbar').submit();"
                       style="color:#ef4444 !important;">
                        <i class="fas fa-sign-out-alt" style="width:16px;"></i> Déconnexion
                    </a>
                    <form id="logout-form-navbar" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                </div>
            </div>

        </div>
    </header>

    {{-- ====================================================
         MAIN CONTENT
         ==================================================== --}}
    <main class="main-content" id="main-content">
        <div class="page-inner">

            {{-- Toast container --}}
            <div id="toast-container" aria-live="polite" aria-atomic="false"></div>

            {{-- Toasts session --}}
            @if(session('success'))
            <div class="toast toast-success" role="alert" aria-live="assertive">
                <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
                <div class="toast-body">
                    <div class="toast-title">Succès</div>
                    <div class="toast-msg">{{ session('success') }}</div>
                </div>
                <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
            </div>
            @endif

            @if(session('error'))
            <div class="toast toast-error" role="alert" aria-live="assertive">
                <div class="toast-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="toast-body">
                    <div class="toast-title">Erreur</div>
                    <div class="toast-msg">{{ session('error') }}</div>
                </div>
                <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
            </div>
            @endif

            @yield('content')

        </div>
        <div style="height:2rem;"></div>
    </main>

    {{-- ====================================================
         SCRIPTS
         ==================================================== --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

    <script>
    /* ============================================================
       DATATABLES INIT
       ============================================================ */
    $(function () {
        if ($.fn.DataTable && document.getElementById('myTable')) {
            $('#myTable').DataTable({
                responsive: true,
                processing: true,
                serverSide: false,
                language: { url: '/datatables/i18n/fr-FR.json' },
                // FIX footer centré via CSS, mais on fixe aussi dom pour cohérence
                dom: '<"flex flex-wrap items-center justify-between gap-2 mb-3"lf>t<"flex flex-wrap items-center justify-center gap-4 mt-3"ip>'
            });
        }
    });
    </script>

    <script>
    /* ============================================================
       RÉFÉRENCES DOM
       ============================================================ */
    const themeContainer   = document.getElementById('theme-container');
    const themeToggle      = document.getElementById('theme-toggle');
    const modeLabel        = document.getElementById('mode-label');
    const sidebar          = document.getElementById('sidebar');
    const mainContent      = document.getElementById('main-content');
    const navbar           = document.getElementById('navbar');
    const desktopToggleBtn = document.getElementById('toggle-sidebar-desktop');
    const desktopToggleIcon= document.getElementById('toggle-icon-desktop');
    const mobileTrigger    = document.getElementById('toggle-btn');
    const mobileOverlay    = document.getElementById('mobile-overlay');
    const userMenuToggle   = document.getElementById('user-menu-toggle');
    const userMenu         = document.getElementById('user-menu');

    /* ============================================================
       THÈME CLAIR / SOMBRE
       ============================================================ */
    function setTheme(theme) {
        const isDark = theme === 'dark';
        themeContainer.classList.toggle('dark-mode',  isDark);
        themeContainer.classList.toggle('light-mode', !isDark);
        themeToggle.classList.toggle('toggled', isDark);
        themeToggle.setAttribute('aria-checked', isDark);
        if (modeLabel) modeLabel.textContent = isDark ? 'Mode Sombre' : 'Mode Clair';
        localStorage.setItem('theme', theme);
    }

    function initTheme() {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        setTheme(localStorage.getItem('theme') || (prefersDark ? 'dark' : 'light'));
    }

    themeToggle.addEventListener('click', () => {
        setTheme(themeContainer.classList.contains('dark-mode') ? 'light' : 'dark');
    });
    themeToggle.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); themeToggle.click(); }
    });

    /* ============================================================
       SIDEBAR — DESKTOP COLLAPSE
       ============================================================ */
    function collapseDesktop(force) {
        const willCollapse = force !== undefined ? force : !sidebar.classList.contains('collapsed');
        sidebar.classList.toggle('collapsed', willCollapse);
        mainContent.classList.toggle('expanded', willCollapse);
        navbar.classList.toggle('expanded', willCollapse);
        /* FIX flèche : en collapsed la flèche pointe à droite (rotate-180) */
        desktopToggleIcon.style.transform = willCollapse ? 'rotate(180deg)' : 'rotate(0deg)';
        localStorage.setItem('sidebarCollapsed', willCollapse ? 'true' : 'false');
    }

    desktopToggleBtn.addEventListener('click', () => collapseDesktop());

    /* ============================================================
       SIDEBAR — MOBILE
       ============================================================ */
    function openMobileSidebar() {
        sidebar.classList.add('mobile-open');
        mobileOverlay.classList.add('visible');
        requestAnimationFrame(() => mobileOverlay.classList.add('active'));
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        mobileOverlay.classList.remove('active');
        setTimeout(() => mobileOverlay.classList.remove('visible'), 300);
        document.body.style.overflow = '';
    }

    mobileTrigger.addEventListener('click', () => {
        sidebar.classList.contains('mobile-open') ? closeMobileSidebar() : openMobileSidebar();
    });

    mobileOverlay.addEventListener('click', closeMobileSidebar);

    /* ============================================================
       RESIZE — SYNC ÉTAT
       ============================================================ */
    function handleResize() {
        if (window.innerWidth < 768) {
            /* Mobile : reset états desktop */
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('expanded');
            navbar.classList.remove('expanded');
        } else {
            /* Desktop : restaurer état mémorisé */
            closeMobileSidebar();
            const saved = localStorage.getItem('sidebarCollapsed') === 'true';
            collapseDesktop(saved);
        }
    }

    window.addEventListener('resize', handleResize);

    /* ============================================================
       SOUS-MENUS SIDEBAR
       ============================================================ */
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', e => {
            e.preventDefault();
            if (sidebar.classList.contains('collapsed')) return; // Pas en collapsed

            const dropId = toggle.getAttribute('data-dropdown');
            const drop   = document.getElementById(dropId);
            if (!drop) return;

            const isOpen = drop.classList.contains('open');

            /* Fermer tous les autres */
            document.querySelectorAll('.nav-dropdown.open').forEach(d => {
                if (d.id !== dropId) {
                    d.classList.remove('open');
                    const t = document.querySelector(`[data-dropdown="${d.id}"]`);
                    if (t) { t.classList.remove('open'); t.setAttribute('aria-expanded','false'); }
                }
            });

            /* Toggle courant */
            drop.classList.toggle('open', !isOpen);
            toggle.classList.toggle('open', !isOpen);
            toggle.setAttribute('aria-expanded', !isOpen);
        });
    });

    /* Ouvrir le menu actif au chargement */
    document.querySelectorAll('.nav-dropdown.open').forEach(drop => {
        const toggle = document.querySelector(`[data-dropdown="${drop.id}"]`);
        if (toggle) { toggle.classList.add('open'); toggle.setAttribute('aria-expanded', 'true'); }
    });

    /* ============================================================
       DROPDOWN UTILISATEUR (NAVBAR)
       z-index: var(--z-dropdown) = 50, supérieur à KPI sticky (z-index:10)
       ============================================================ */
    userMenuToggle.addEventListener('click', e => {
        e.stopPropagation();
        const isOpen = userMenu.classList.toggle('open');
        userMenuToggle.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', e => {
        if (!userMenu.contains(e.target) && !userMenuToggle.contains(e.target)) {
            userMenu.classList.remove('open');
            userMenuToggle.setAttribute('aria-expanded', 'false');
        }
    });

    /* ============================================================
       TOASTS — AFFICHAGE AUTOMATIQUE
       ============================================================ */
    function showToast(el, duration = 5000) {
        if (!el) return;
        requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));

        const close = el.querySelector('.toast-close');
        const dismiss = () => {
            el.classList.remove('show');
            el.classList.add('hide');
            setTimeout(() => el.remove(), 300);
        };

        if (close) close.addEventListener('click', dismiss);
        setTimeout(dismiss, duration);
    }

    /* Toasts issus de session (déjà dans le DOM) */
    document.querySelectorAll('.toast').forEach(t => showToast(t));

    /* API publique pour créer des toasts dynamiques :
       window.showToastMsg('Titre', 'Message', 'success'|'error'|'warning') */
    window.showToastMsg = function (title, msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-triangle', warning: 'fa-exclamation-circle' };
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="toast-icon"><i class="fas ${icons[type] || icons.success}"></i></div>
            <div class="toast-body">
                <div class="toast-title">${title}</div>
                <div class="toast-msg">${msg}</div>
            </div>
            <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
        `;
        container.appendChild(el);
        showToast(el);
    };

    /* ============================================================
       INIT AU CHARGEMENT
       ============================================================ */
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        handleResize();
    });
    </script>

    @stack('scripts')

</body>
</html>