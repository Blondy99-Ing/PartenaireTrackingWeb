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

    @stack('styles')

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
        --navbar-h: 5rem;
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

        color: black;
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


.brand{
  height: 190px;
  display: flex;
  align-items: center;    
  justify-content: center; 
}

.brand-logo{
  display: flex;
  flex-direction: column; 
  align-items: center;    
  justify-content: center;
  gap: 10px;               
  text-align: center;   
}

/* image */
.brand-logo img{
  height: 90px;           
  width: auto;
  display: block;
}

/* texte */
.brand-logo h1{
  margin: 0;
  font-size: 2rem;
  line-height: 1.1;
}




    /* ===== Toasts Modern (light/dark via CSS variables) ===== */
    /* ===== Toast base ===== */
    .toast {
        width: min(520px, calc(100vw - 28px));
        pointer-events: auto;

        display: flex;
        align-items: flex-start;
        gap: 14px;

        padding: 16px 16px 14px 16px;
        border-radius: 18px;
        border: 1px solid var(--color-border-subtle);
        background: var(--color-card);
        color: var(--color-text);

        box-shadow:
            0 18px 50px rgba(0, 0, 0, .20),
            0 2px 10px rgba(0, 0, 0, .12);

        transform: translateY(-10px) scale(.98);
        opacity: 0;
        transition: transform .25s ease, opacity .25s ease, box-shadow .25s ease;
        position: relative;
        overflow: hidden;
    }

    .toast.show {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    .toast.hide {
        transform: translateY(-10px) scale(.98);
        opacity: 0;
    }

    /* Barre gauche + halo (plus visible) */
    .toast::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
        border-radius: 18px 0 0 18px;
        opacity: .95;
    }

    /* Barre de progression (5s) */
    .toast::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 4px;
        opacity: .9;
        transform-origin: left center;
        transform: scaleX(1);
    }

    .toast.show::after {
        animation: toastProgress 5s linear forwards;
    }

    @keyframes toastProgress {
        from {
            transform: scaleX(1);
        }

        to {
            transform: scaleX(0);
        }
    }

    /* Layout interne */
    .toast-icon {
        width: 46px;
        height: 46px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex: 0 0 auto;
        font-size: 1.25rem;
    }

    .toast-title {
        font-family: var(--font-family);
        font-weight: 900;
        letter-spacing: .3px;
        font-size: 1.05rem;
        line-height: 1.1;
        margin-top: 2px;
    }

    .toast-msg {
        margin-top: 4px;
        font-size: .98rem;
        color: var(--color-secondary-text);
    }

    .toast-close {
        margin-left: auto;
        width: 38px;
        height: 38px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;

        border: 1px solid var(--color-border-subtle);
        background: transparent;
        color: var(--color-text);
        opacity: .75;
        cursor: pointer;

        transition: transform .12s ease, opacity .2s ease, background .2s ease;
    }

    .toast-close:hover {
        opacity: 1;
        background: rgba(255, 255, 255, .06);
        transform: translateY(-1px);
    }

    /* ===== SUCCESS (Vert premium) ===== */
    .toast-success {
        border-color: rgba(34, 197, 94, .35);
        background: linear-gradient(135deg,
                rgba(34, 197, 94, .18),
                rgba(255, 255, 255, 0) 45%), var(--color-card);

        box-shadow:
            0 18px 50px rgba(0, 0, 0, .18),
            0 0 0 2px rgba(34, 197, 94, .16),
            0 16px 40px rgba(34, 197, 94, .10);
    }

    .toast-success::before {
        background: linear-gradient(180deg, #22c55e, #16a34a);
    }

    .toast-success::after {
        background: linear-gradient(90deg, #22c55e, #16a34a);
    }

    .toast-success .toast-icon {
        background: linear-gradient(135deg, #22c55e, #16a34a);
        box-shadow: 0 14px 30px rgba(34, 197, 94, .25);
    }

    .toast-success .toast-title {
        color: #16a34a;
    }

    /* ===== ERROR (Rouge premium) ===== */
    .toast-error {
        border-color: rgba(239, 68, 68, .35);
        background: linear-gradient(135deg,
                rgba(239, 68, 68, .16),
                rgba(255, 255, 255, 0) 45%), var(--color-card);

        box-shadow:
            0 18px 50px rgba(0, 0, 0, .18),
            0 0 0 2px rgba(239, 68, 68, .14),
            0 16px 40px rgba(239, 68, 68, .10);
    }

    .toast-error::before {
        background: linear-gradient(180deg, #ef4444, #b91c1c);
    }

    .toast-error::after {
        background: linear-gradient(90deg, #ef4444, #b91c1c);
    }

    .toast-error .toast-icon {
        background: linear-gradient(135deg, #ef4444, #b91c1c);
        box-shadow: 0 14px 30px rgba(239, 68, 68, .22);
    }

    .toast-error .toast-title {
        color: #ef4444;
    }

    /* Dark mode: on augmente un peu la lisibilité */
    .dark-mode .toast {
        box-shadow:
            0 18px 55px rgba(0, 0, 0, .45),
            0 2px 12px rgba(0, 0, 0, .35);
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
            
            <div class="brand-logo">
                <div class="logo-text">
                    <img src="{{ asset('assets/images/logo_tracking.png') }}" alt="">
                   
                </div>
                <div class="logo-text">
                    <h1>Fleetra</h1>
                </div>
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
                   
                    <li><i class="fas fa-chevron-right text-xs ml-auto"></i> <a href="{{ route('tracking.vehicles') ?? '#' }}"
                            class="{{ request()->is('tracking.vehicles') ? 'active' : '' }}">Véhicules</a></li>
                    <li><a href="{{ route('users.index') }}" class="{{ request()->is('users') ? 'active' : '' }}">Chauffeurs</a></li>
                    <li><a href="{{ route('trajets.index') }}" class="{{ request()->is('tracking.zones') ? 'active' : '' }}">Trajets</a></li>
                </ul>
            </li>

           
            <li>
                <a href="{{ route('alerts.view') ?? '#' }}"
                    class="{{ request()->routeIs('alerts.index') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span class="title">Alertes</span>
                </a>
            </li>

             <li>
                <a href="{{ route('engine.action.index') ?? '#' }}"
                    class="{{ request()->routeIs('engine.actions') ? 'active' : '' }}">
                    <span class="icon"><i class="fas fa-power-off"></i></span>
                    <span class="title">Coupure Moteur</span>
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
                    <span class="font-semibold hidden lg:block profile-text" style="color: var(--color-text);">{{ auth()->user()->prenom }} {{ auth()->user()->nom }}</span>
                </button>

                <!-- Dropdown Utilisateur -->
                <div class="user-dropdown-menu" id="user-menu">
                    <div class="p-3 border-b" style="border-color: var(--color-border-subtle);">
                        <p class="font-semibold">{{ auth()->user()->prenom }} {{ auth()->user()->nom }}</p>
                        <p class="text-xs text-secondary">{{ auth()->user()->email }} </p>
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

             {{-- Toast container --}}
                <div id="toast-container" class="fixed top-5 right-5 z-[9999] space-y-3 pointer-events-none"></div>

                @if(session('success'))
                <div class="toast toast-success pointer-events-auto" role="alert">
                    <div class="toast-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="toast-body">
                        <div class="toast-title">Succès</div>
                        <div class="toast-msg">{{ session('success') }}</div>
                    </div>
                    <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
                </div>
                @endif

                @if(session('error'))
                <div class="toast toast-error pointer-events-auto" role="alert">
                    <div class="toast-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="toast-body">
                        <div class="toast-title">Erreur</div>
                        <div class="toast-msg">{{ session('error') }}</div>
                    </div>
                    <button type="button" class="toast-close" aria-label="Fermer">&times;</button>
                </div>
                @endif




                @yield('content')
            </div>





        </div>

        <!-- Pour s'assurer que le bas de la page n'est pas caché par la barre de déconnexion de la sidebar -->
        <div class="h-10"></div>
    </div>

    <!-- JavaScript for Sidebar Toggle, Theme, and Dropdowns -->


    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

   
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