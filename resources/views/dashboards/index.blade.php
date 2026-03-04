@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>

    
/* ════════════════════════════════════════════════════════════════
   DASHBOARD — Styles propres, construits SUR le Design System
   du layout (tokens, classes utilitaires, typographie).
   Aucun token redéfini ici — tout vient de app.blade.php.
════════════════════════════════════════════════════════════════ */

/* ── KPI Sticky bar ──────────────────────────────────────────── */
.kpi-sticky-bar {
    position: fixed;
    top: var(--navbar-h);
    left: var(--sidebar-w);
    right: 0;
    z-index: var(--z-kpi);
    background-color: var(--color-bg);
    padding: 6px var(--sp-xl) 0px;
    box-shadow: 0 4px 20px -4px rgba(0,0,0,0.08);
    transition: left 0.3s ease;
    pointer-events: none;
}

.kpi-sticky-bar > * {
    pointer-events: auto;
}
.dark-mode .kpi-sticky-bar {
    box-shadow: 0 4px 20px -4px rgba(0,0,0,0.45);
}

@media (max-width: 1023px) {
    .kpi-sticky-bar {
        position: fixed;
        left: 0;
        padding-block: var(--sp-xs);
    }
}

/* ── KPI Cards ───────────────────────────────────────────────── */
.kpi-card {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    padding: 0.35rem 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-sm);
    transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
    position: relative;
    overflow: hidden;
}

/* Ligne décorative gauche — identité orange */
.kpi-card::before {
    content: '';
    position: absolute;
    left: 0; top: 20%; bottom: 20%;
    width: 3px;
    background: var(--color-primary);
    border-radius: 0 var(--r-pill) var(--r-pill) 0;
    opacity: 0;
    transition: opacity 0.2s ease, top 0.2s ease, bottom 0.2s ease;
}

.kpi-card:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--color-primary-border);
    transform: translateY(-1px);
}

.kpi-card:hover::before {
    opacity: 1;
    top: 8%; bottom: 8%;
}

.kpi-label {
    font-family: var(--font-display);
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: var(--ls-wider);
    color: var(--color-secondary-text);
    line-height: 1;
    margin: 0;
}

.kpi-value {
    font-family: var(--font-display);
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: var(--ls-tight);
    color: var(--color-primary);
    margin: 1px 0 0;
    transition: color 0.3s ease;
}

.kpi-value.neutral { color: var(--color-text); }

.kpi-icon-wrap {
    width: 40px; height: 40px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    background: var(--color-primary-light);
    flex-shrink: 0;
    transition: background 0.2s ease, transform 0.2s ease;
}

.kpi-card:hover .kpi-icon-wrap {
    background: rgba(245,130,32,0.20);
    transform: scale(1.08);
}

.kpi-icon-wrap i {
    font-size: 0.95rem;
    color: var(--color-primary);
}

/* ── Alert type mini-cards ───────────────────────────────────── */
.kpi-alerts-panel {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    padding: 0.35rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 4px;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

.kpi-alerts-panel:hover {
    box-shadow: var(--shadow-sm);
    border-color: var(--color-primary-border);
}

.kpi-alerts-header {
    font-family: var(--font-display);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: var(--ls-wide);
    color: var(--color-text);
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 0;
}

.kpi-alerts-header span:last-child {
    font-family: var(--font-body);
    font-size: 0.65rem;
    font-weight: 400;
    letter-spacing: 0;
    color: var(--color-secondary-text);
    text-transform: none;
}

.alert-type-card {
    border-radius: var(--r-sm);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-bg-subtle, rgba(0,0,0,0.03));
    padding: 0.2rem 0.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--sp-sm);
    transition: border-color 0.15s ease, background 0.15s ease;
}

.dark-mode .alert-type-card { background: rgba(255,255,255,0.03); }

.alert-type-card:hover {
    border-color: var(--color-primary-border);
    background: var(--color-primary-light);
}

.alert-type-label {
    font-family: var(--font-body);
    font-size: 0.6rem;
    font-weight: 400;
    color: var(--color-secondary-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}

.alert-type-value {
    font-family: var(--font-display);
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: var(--ls-tight);
    line-height: 1;
    color: var(--color-text);
    margin: 2px 0 0;
}

.alert-type-badge {
    width: 28px; height: 28px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 0.65rem;
}

/* ════════════════════════════════════════════════════════════════
   CONTENU PRINCIPAL
════════════════════════════════════════════════════════════════ */
.dashboard-content {
    display: flex;
    flex-direction: column;
    gap: var(--dash-gap);
    margin-top: calc(var(--kpi-h) + var(--sp-sm));
    /* Animation d'entrée décalée */
    animation: contentFade 0.5s 0.15s ease both;
}

/* ── Grille Liste + Carte — Full Height Desktop ──────────────── */
@media (min-width: 1024px) {
    .list-map-grid {
        height: calc(
            100vh
            - var(--navbar-h)
            - var(--kpi-h)
            - var(--sp-lg)       /* margin-top dashboard-content */
            - var(--sp-xl)       /* padding-top page-inner */
            - var(--sp-2xl)      /* padding-bottom page-inner */
            - var(--dash-gap)    /* gap dashboard-content */
        );
        min-height: 440px;
        align-items: stretch;
    }

    .list-map-grid > * {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .list-map-grid > * > .panel-card {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    #vehicleList {
        flex: 1 1 auto;
        min-height: 0 !important;
        overflow-y: auto;
        max-height: none !important;
        padding-right: 3px;
    }

    #fleetMap {
        flex: 1 1 auto;
        min-height: 0 !important;
        height: auto !important;
        width: 100%;
    }
}

@media (min-width: 768px) and (max-width: 1023px) {
    #vehicleList { max-height: 45vh; overflow-y: auto; }
    #fleetMap    { height: 360px !important; }
}

@media (max-width: 767px) {
    #fleetMap { height: 280px !important; }
}

/* ── Panel cards (liste et carte) ───────────────────────────── */
.panel-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    transition: box-shadow 0.2s ease;
}

.panel-card:hover { box-shadow: var(--shadow-md); }
.dark-mode .panel-card { box-shadow: 0 2px 8px rgba(0,0,0,0.30); }

.panel-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: var(--sp-lg) var(--sp-lg) var(--sp-md);
    border-bottom: 1px solid var(--color-border-subtle);
    flex-shrink: 0;
}

.panel-title {
    font-family: var(--font-display);
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    letter-spacing: 0.01em;
}

.panel-subtitle {
    font-family: var(--font-body);
    font-size: 0.7rem;
    color: var(--color-secondary-text);
    margin: 3px 0 0;
}

.panel-icon {
    width: 34px; height: 34px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    background: var(--color-primary-light);
    flex-shrink: 0;
}

.panel-icon i { font-size: 0.85rem; color: var(--color-primary); }

.panel-body {
    padding: var(--sp-md) var(--sp-lg);
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
    gap: var(--sp-sm);
}

/* ── Search input dans la liste ─────────────────────────────── */
.search-wrap {
    position: relative;
    flex-shrink: 0;
}

.search-wrap .search-icon {
    position: absolute;
    left: 0.625rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-secondary-text);
    font-size: 0.7rem;
    pointer-events: none;
}

.search-wrap input {
    padding-left: 2rem;
    font-size: 0.78rem;
    height: 34px;
}

/* ── Compteur flotte ────────────────────────────────────────── */
.fleet-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-family: var(--font-body);
    font-size: 0.7rem;
    color: var(--color-secondary-text);
    flex-shrink: 0;
}

.fleet-meta-dot {
    display: inline-block;
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #4ade80;
    margin-right: 4px;
    flex-shrink: 0;
}

/* ── Scrollbar vehicleList ──────────────────────────────────── */
#vehicleList {
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-subtle) transparent;
}

#vehicleList::-webkit-scrollbar       { width: 4px; }
#vehicleList::-webkit-scrollbar-thumb { background: var(--color-border-subtle); border-radius: 2px; }

/* ── Vehicle items ──────────────────────────────────────────── */
.vehicle-item {
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-md);         /* 6px */
    padding: 0.625rem 0.75rem;
    background: var(--color-card);
    cursor: pointer;
    transition: box-shadow 0.15s ease, border-color 0.15s ease, background 0.15s ease;
    margin-bottom: 0.375rem;
    position: relative;
}

.vehicle-item:hover {
    border-color: var(--color-primary-border);
    box-shadow: var(--shadow-sm);
    background: var(--color-bg);
}

.vehicle-item.selected {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 2px var(--color-primary-border), var(--shadow-sm);
    background: var(--color-primary-light);
}

.vehicle-item-row {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}

.vehicle-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--color-primary-light);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}

.vehicle-avatar i { font-size: 0.8rem; color: var(--color-primary); }

.vehicle-info { flex: 1; min-width: 0; }

.vehicle-immat {
    font-family: var(--font-display);
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: 0.01em;
}

.vehicle-brand,
.vehicle-driver {
    font-family: var(--font-body);
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    margin: 2px 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vehicle-driver i { font-size: 0.6rem; margin-right: 3px; }

.vehicle-link {
    font-family: var(--font-display);
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--color-primary);
    text-decoration: none;
    padding: 4px;
    border-radius: var(--r-xs);
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.12s ease;
}

.vehicle-link:hover { background: var(--color-primary-light); }

.vehicle-badges {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.4rem;
    flex-wrap: wrap;
    gap: 4px;
}

.vehicle-badges-left { display: flex; gap: 4px; flex-wrap: wrap; }

.v-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: var(--r-pill);
    font-family: var(--font-body);
    font-size: 0.6rem;
    font-weight: 600;
    gap: 3px;
    line-height: 1.4;
}

.v-badge i { font-size: 0.55rem; }

.v-badge-engine-off  { background: var(--color-error-bg);   color: var(--color-error); }
.v-badge-engine-on   { background: var(--color-success-bg); color: var(--color-success); }
.v-badge-engine-unk  { background: rgba(107,114,128,0.12);  color: #6b7280; }
.v-badge-gps-on      { background: var(--color-success-bg); color: var(--color-success); }
.v-badge-gps-off     { background: rgba(107,114,128,0.12);  color: #6b7280; }
.v-badge-gps-unk     { background: rgba(107,114,128,0.12);  color: #6b7280; }

.vehicle-center-hint {
    font-family: var(--font-body);
    font-size: 0.6rem;
    color: var(--color-secondary-text);
    display: inline-flex;
    align-items: center;
    gap: 3px;
    opacity: 0;
    transition: opacity 0.15s ease;
}

.vehicle-item:hover .vehicle-center-hint { opacity: 1; }

.vehicle-center-hint i { color: var(--color-primary); font-size: 0.58rem; }

/* ── État vide / chargement ─────────────────────────────────── */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: var(--sp-2xl) var(--sp-lg);
    text-align: center;
    gap: var(--sp-sm);
    flex: 1 1 auto;
}

.empty-state-icon {
    font-size: 2rem;
    color: var(--color-border-subtle);
    line-height: 1;
}

.empty-state-title {
    font-family: var(--font-display);
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--color-secondary-text);
    margin: 0;
}

.empty-state-sub {
    font-family: var(--font-body);
    font-size: 0.72rem;
    color: var(--color-secondary-text);
    opacity: 0.7;
    margin: 0;
}

/* ── Carte header (SSE indicator) ───────────────────────────── */
.sse-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: var(--r-pill);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-bg-subtle, rgba(0,0,0,0.04));
    font-family: var(--font-body);
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    transition: border-color 0.3s, background 0.3s;
}

.sse-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #9ca3af;
    flex-shrink: 0;
    transition: background 0.3s;
}

.last-update-label {
    font-family: var(--font-body);
    font-size: 0.65rem;
    color: var(--color-secondary-text);
}

/* ── Map container ──────────────────────────────────────────── */
#fleetMap {
    border-radius: 0;  /* arrondi géré par le panel-card */
    border: none;
    display: block;
    width: 100%;
    height: 100%;
    min-height: 300px;
}

/* ── Section alertes ────────────────────────────────────────── */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--sp-md);
}

.section-title {
    font-family: var(--font-display);
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    letter-spacing: 0.01em;
}

.section-subtitle {
    font-family: var(--font-body);
    font-size: 0.7rem;
    color: var(--color-secondary-text);
    margin: 3px 0 0;
}

/* ── Tableau alertes ────────────────────────────────────────── */
.alerts-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-body);
    font-size: 0.82rem;
}

.alerts-table thead th {
    font-family: var(--font-display);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: var(--ls-wide);
    color: var(--color-secondary-text);
    padding: 0.5rem 1rem;
    text-align: left;
    background: var(--color-bg);
    border-bottom: 2px solid var(--color-primary);
    white-space: nowrap;
}

.dark-mode .alerts-table thead th { background: var(--color-bg-subtle); }

.alerts-table tbody tr {
    border-bottom: 1px solid var(--color-border-subtle);
    transition: background 0.12s ease;
}

.alerts-table tbody tr:last-child { border-bottom: none; }

.alerts-table tbody tr:hover { background: var(--color-primary-light); }

.alerts-table tbody td {
    padding: 0.55rem 1rem;
    color: var(--color-text);
    vertical-align: middle;
}

.alerts-table tbody td:first-child {
    font-family: var(--font-display);
    font-weight: 600;
    font-size: 0.8rem;
    letter-spacing: 0.01em;
}

/* Badges de statut (pill — autorisé) */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: var(--r-pill);
    font-family: var(--font-body);
    font-size: 0.68rem;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
}

/* ── Empty state spécifique au tableau ──────────────────────── */
.table-empty {
    text-align: center;
    padding: var(--sp-2xl) var(--sp-lg);
    color: var(--color-secondary-text);
}

.table-empty i {
    display: block;
    font-size: 1.75rem;
    margin-bottom: var(--sp-sm);
    color: var(--color-border-subtle);
}

.table-empty p {
    font-family: var(--font-body);
    font-size: 0.8rem;
    margin: 0;
}
</style>
@endpush

@section('content')
@php
    $types = [
        'stolen'    => ['Vol',       'fa-mask',          'var(--color-error-bg)',   'var(--color-error)'],
        'geofence'  => ['Geofence',  'fa-draw-polygon',  'rgba(245,130,32,.12)',    'var(--color-primary)'],
        'safe_zone' => ['Safe Zone', 'fa-shield-halved', 'var(--color-info-bg)',    'var(--color-info)'],
        'speed'     => ['Vitesse',   'fa-gauge-high',    'var(--color-warning-bg)', 'var(--color-warning)'],
        'time_zone' => ['Time Zone', 'fa-calendar-alt',  'rgba(99,102,241,.12)',    '#6366f1'],
    ];
@endphp

{{-- ════════════════════════════════════════════════════════════
     BARRE KPI STICKY
════════════════════════════════════════════════════════════════ --}}
<div class="kpi-sticky-bar" id="kpi-bar">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">

        {{-- Chauffeurs --}}
        <div class="lg:col-span-2 kpi-card" role="figure" aria-label="Nombre de chauffeurs">
            <div>
                <p class="kpi-label">Chauffeurs</p>
                <p class="kpi-value" id="stat-users">{{ (int)($usersCount ?? 0) }}</p>
            </div>
            <div class="kpi-icon-wrap" aria-hidden="true">
                <i class="fas fa-users"></i>
            </div>
        </div>

        {{-- Véhicules --}}
        <div class="lg:col-span-2 kpi-card" role="figure" aria-label="Nombre de véhicules">
            <div>
                <p class="kpi-label">Véhicules</p>
                <p class="kpi-value neutral" id="stat-vehicles">{{ (int)($vehiclesCount ?? 0) }}</p>
            </div>
            <div class="kpi-icon-wrap" aria-hidden="true" style="background:rgba(107,114,128,0.10);">
                <i class="fas fa-car-alt" style="color:var(--color-secondary-text);"></i>
            </div>
        </div>

        {{-- Alertes par type --}}
        <div class="lg:col-span-8 kpi-alerts-panel" role="region" aria-label="Alertes ouvertes par type">
            <p class="kpi-alerts-header">
                <span>Alertes ouvertes par type</span>
                <span>Non traitées</span>
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                @foreach($types as $k => [$label, $icon, $bgColor, $textColor])
                <div class="alert-type-card">
                    <div style="min-width:0;">
                        <p class="alert-type-label">{{ $label }}</p>
                        <p class="alert-type-value" id="stat-alert-{{ $k }}">
                            {{ (int)($alertStats[$k] ?? 0) }}
                        </p>
                    </div>
                    <div class="alert-type-badge" style="background:{{ $bgColor }};" aria-hidden="true">
                        <i class="fas {{ $icon }}" style="color:{{ $textColor }};font-size:0.65rem;"></i>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>
</div>

{{-- ════════════════════════════════════════════════════════════
     CONTENU PRINCIPAL
════════════════════════════════════════════════════════════════ --}}
<div class="dashboard-content">

    {{-- ── Grille Liste Flotte + Carte ──────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 list-map-grid">

        {{-- ── Panneau Liste Flotte ─────────────────────────── --}}
        <div class="lg:col-span-1">
            <div class="panel-card h-full">

                {{-- Header --}}
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Flotte &amp; Associations</h2>
                        <p class="panel-subtitle">Cliquez pour centrer sur la carte.</p>
                    </div>
                    <div class="panel-icon" aria-hidden="true">
                        <i class="fas fa-car-side"></i>
                    </div>
                </div>

                {{-- Corps --}}
                <div class="panel-body">

                    {{-- Search --}}
                    <div class="search-wrap">
                        <span class="search-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                        <input
                            id="vehicleSearch"
                            type="search"
                            placeholder="Immatriculation ou chauffeur…"
                            aria-label="Rechercher un véhicule"
                            autocomplete="off"
                        />
                    </div>

                    {{-- Meta (légende + compteur) --}}
                    <div class="fleet-meta">
                        <span>
                            <span class="fleet-meta-dot" aria-hidden="true"></span>
                            Véhicules suivis
                        </span>
                        <span id="fleet-count" aria-live="polite" aria-atomic="true">0 véhicule(s)</span>
                    </div>

                    {{-- Liste (scroll interne desktop) --}}
                    <div id="vehicleList" role="list" aria-label="Liste des véhicules">
                        <div class="empty-state">
                            <div class="empty-state-icon" aria-hidden="true">
                                <i class="fas fa-car"></i>
                            </div>
                            <p class="empty-state-title">Chargement…</p>
                        </div>
                    </div>

                </div>{{-- /panel-body --}}
            </div>
        </div>

        {{-- ── Panneau Carte ────────────────────────────────── --}}
        <div class="lg:col-span-3">
            <div class="panel-card h-full" style="overflow:hidden;">

                {{-- Header carte --}}
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Localisation de la Flotte</h2>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                        {{-- SSE Indicator --}}
                        <div class="sse-badge" id="sse-indicator" aria-live="polite" aria-atomic="true">
                            <span class="sse-dot" id="sse-dot" aria-hidden="true"></span>
                            <span id="sse-label">Temps réel</span>
                        </div>
                        {{-- Dernière mise à jour --}}
                        <span class="last-update-label" id="last-update"></span>
                    </div>
                </div>

                {{-- Carte Google Maps (prend tout l'espace restant) --}}
                <div id="fleetMap" role="application" aria-label="Carte de localisation de la flotte"></div>

            </div>
        </div>

    </div>
    {{-- /list-map-grid --}}

    {{-- ── Tableau des dernières alertes ───────────────────── --}}
    <div class="panel-card">
        <div style="padding:var(--sp-lg);">

            {{-- Header section --}}
            <div class="section-header">
                <div>
                    <h2 class="section-title">Dernières alertes</h2>
                    <p class="section-subtitle">Alertes non traitées — mise à jour automatique.</p>
                </div>
                <a href="{{ route('alerts.view') }}" class="btn-secondary" style="font-size:0.75rem;padding:0.35rem 0.875rem;min-height:32px;">
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    Tout voir
                </a>
            </div>

            {{-- Table --}}
            <div class="ui-table-container">
                <table class="alerts-table">
                    <thead>
                        <tr>
                            <th scope="col">Véhicule</th>
                            <th scope="col">Type</th>
                            <th scope="col">Heure</th>
                            <th scope="col">Statut</th>
                        </tr>
                    </thead>
                    <tbody id="alerts-table-body">
                        @forelse(($alerts ?? []) as $a)
                        <tr>
                            <td>{{ $a['vehicle'] ?? '—' }}</td>
                            <td>{{ $a['type']    ?? '—' }}</td>
                            <td style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-secondary-text);">
                                {{ $a['time'] ?? '—' }}
                            </td>
                            <td>
                                <span class="status-badge" style="background:{{ $a['status_bg'] ?? '#6b7280' }};">
                                    {{ $a['status'] ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="table-empty">
                                <i class="fas fa-bell-slash" aria-hidden="true"></i>
                                <p>Aucune alerte pour le moment.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
{{-- /dashboard-content --}}


{{-- ════════════════════════════════════════════════════════════
     SCRIPTS DASHBOARD
════════════════════════════════════════════════════════════════ --}}
@push('scripts')
<script>
/* ────────────────────────────────────────────────────────────
   DASHBOARD JS — encapsulé dans une IIFE
   Dépend du layout pour :
     - window.showToast(title, msg, type)
     - window.map (référence carte partagée avec triggerMapResize)
     - --navbar-h / --kpi-h (mesurés par le layout JS)
──────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    /* ── État global ────────────────────────────────────────── */
    let map              = null;
    let markersById      = {};
    let infoWindowsById  = {};
    let selectedVehicleId = null;
    let dashSSE          = null;

    let vehiclesData = @json($vehicles ?? []);

    const ALERT_TYPES = {
        stolen:    { label: 'Vol',       icon: 'fa-mask'          },
        geofence:  { label: 'Geofence',  icon: 'fa-draw-polygon'  },
        safe_zone: { label: 'Safe Zone', icon: 'fa-shield-halved' },
        speed:     { label: 'Vitesse',   icon: 'fa-gauge-high'    },
        time_zone: { label: 'Time Zone', icon: 'fa-calendar-alt'  },
    };

    /* ════════════════════════════════════════════════════════
       MESURE KPI — met à jour --kpi-h pour le calcul full-height
    ════════════════════════════════════════════════════════ */
    function measureKpi() {
        const kpi    = document.getElementById('kpi-bar');
        const navbar = document.getElementById('navbar');
        if (!kpi || !navbar) return;
        const kpiH   = Math.round(kpi.getBoundingClientRect().height);
        const navH   = Math.round(navbar.getBoundingClientRect().height);
        document.documentElement.style.setProperty('--kpi-h',    kpiH + 'px');
        document.documentElement.style.setProperty('--navbar-h', navH + 'px');
    }

    /* ════════════════════════════════════════════════════════
       GOOGLE MAPS
    ════════════════════════════════════════════════════════ */
    window.initFleetMap = function () {
        map = new google.maps.Map(document.getElementById('fleetMap'), {
            center:    { lat: 4.0511, lng: 9.7679 },
            zoom:      7,
            mapTypeId: 'roadmap',
            styles:    getMapStyle(),
        });

        /* Exposer au layout JS pour triggerMapResize */
        window.map = map;

        renderVehicleList(vehiclesData);
        renderMarkers(vehiclesData, true);
        initVehicleSearch();
        startDashSSE();

        /* Mesure après que la carte est initialisée */
        setTimeout(measureKpi, 300);
    };

    function loadGoogleMaps() {
        if (window.google && window.google.maps) { window.initFleetMap(); return; }
        const s   = document.createElement('script');
        s.src     = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initFleetMap';
        s.async   = true;
        s.defer   = true;
        document.head.appendChild(s);
    }

    /* Style carte : sobre, cohérent avec le dark mode */
function getMapStyle() {
  const dark = document.getElementById('app-root')?.classList.contains('dark-mode');

  // Même en dark mode UI, on garde la carte claire
  if (dark) return [];

  return [];
}
    /* ════════════════════════════════════════════════════════
       SSE
    ════════════════════════════════════════════════════════ */
    function startDashSSE() {
        const url = "{{ route('dashboard.stream') }}";
        dashSSE   = new EventSource(url, { withCredentials: true });
        setSseState('connecting');

        dashSSE.addEventListener('hello',     () => setSseState('connected'));

        dashSSE.addEventListener('dashboard', e => {
            setSseState('connected');
            let payload;
            try { payload = JSON.parse(e.data); } catch { return; }

            if (payload.ts) {
                const el = document.getElementById('last-update');
                if (el) el.textContent = `Maj: ${payload.ts}`;
            }
            if (payload.stats) {
                applyStats(payload.stats);
                const byType = payload.stats.alertsByType || payload.stats.alerts_by_type;
                if (byType) applyAlertTypeStats(byType);
            }
            if (Array.isArray(payload.alerts))  renderAlertsTable(payload.alerts);

            const fleet = Array.isArray(payload.fleet) ? payload.fleet : [];
            vehiclesData = fleet;
            renderVehicleList(fleet);
            renderMarkers(fleet, false);
            updateSelectedInfoWindow(fleet);
        });

        dashSSE.onerror = () => setSseState('reconnecting');

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                dashSSE?.close(); dashSSE = null; setSseState('paused');
            } else if (!dashSSE) {
                startDashSSE();
            }
        });

        window.addEventListener('beforeunload', () => dashSSE?.close());
    }

    /* Mettre à jour le badge SSE visible */
    function setSseState(state) {
        const dot   = document.getElementById('sse-dot');
        const label = document.getElementById('sse-label');
        if (!dot || !label) return;

        const CONFIG = {
            connected:    { color: '#22c55e', text: 'Connecté',    badge: '#22c55e20' },
            connecting:   { color: '#eab308', text: 'Connexion…',  badge: '#eab30820' },
            reconnecting: { color: '#f97316', text: 'Reconnexion…',badge: '#f9731620' },
            paused:       { color: '#9ca3af', text: 'En pause',    badge: 'transparent' },
        };

        const cfg = CONFIG[state] || CONFIG.paused;
        dot.style.background = cfg.color;
        label.textContent    = cfg.text;

        /* Exposer pour le patch d'animation du layout JS */
        if (typeof window.setSseIndicator === 'function') window.setSseIndicator(state);
    }

    /* ════════════════════════════════════════════════════════
       STATS / KPI
    ════════════════════════════════════════════════════════ */
    function applyStats(stats) {
        const set = (id, v) => { const el = document.getElementById(id); if (el && v != null) animateNumber(el, v); };
        set('stat-users',    stats.usersCount);
        set('stat-vehicles', stats.vehiclesCount);
    }

    function applyAlertTypeStats(obj) {
        Object.keys(ALERT_TYPES).forEach(k => {
            const el = document.getElementById('stat-alert-' + k);
            if (el) animateNumber(el, obj?.[k] ?? 0);
        });
    }

    /* Animation compteur numérique (simple, CSS-free) */
    function animateNumber(el, target) {
        const current = parseInt(el.textContent, 10) || 0;
        if (current === target) return;
        const diff  = target - current;
        const steps = Math.min(Math.abs(diff), 20);
        const step  = diff / steps;
        let count   = 0;
        const id = setInterval(() => {
            count++;
            el.textContent = Math.round(current + step * count);
            if (count >= steps) { clearInterval(id); el.textContent = target; }
        }, 30);
    }

    /* ════════════════════════════════════════════════════════
       TABLEAU ALERTES
    ════════════════════════════════════════════════════════ */
    function renderAlertsTable(alerts) {
        const body = document.getElementById('alerts-table-body');
        if (!body) return;

        if (!alerts.length) {
            body.innerHTML = `
                <tr>
                    <td colspan="4" class="table-empty">
                        <i class="fas fa-bell-slash" aria-hidden="true"></i>
                        <p>Aucune alerte pour le moment.</p>
                    </td>
                </tr>`;
            return;
        }

        body.innerHTML = alerts.map(a => `
            <tr>
                <td>${esc(a.vehicle)}</td>
                <td>${esc(a.type)}</td>
                <td style="font-family:var(--font-mono);font-size:0.75rem;color:var(--color-secondary-text);">
                    ${esc(a.time)}
                </td>
                <td>
                    <span class="status-badge" style="background:${a.status_bg ?? '#6b7280'};">
                        ${esc(a.status)}
                    </span>
                </td>
            </tr>`
        ).join('');
    }

    /* ════════════════════════════════════════════════════════
       LISTE VÉHICULES
    ════════════════════════════════════════════════════════ */
    function renderVehicleList(fleet) {
        const list  = document.getElementById('vehicleList');
        const count = document.getElementById('fleet-count');
        if (!list) return;

        if (count) count.textContent = `${fleet.length} véhicule(s)`;

        if (!fleet.length) {
            list.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon" aria-hidden="true"><i class="fas fa-car"></i></div>
                    <p class="empty-state-title">Aucun véhicule localisé</p>
                    <p class="empty-state-sub">Vérifiez la connexion GPS des appareils.</p>
                </div>`;
            return;
        }

        list.innerHTML = fleet.map(buildVehicleItem).join('');

        /* Events click */
        list.querySelectorAll('.vehicle-item').forEach(item => {
            item.addEventListener('click', function () {
                /* Désélectionner le précédent */
                list.querySelectorAll('.vehicle-item.selected').forEach(i => i.classList.remove('selected'));
                this.classList.add('selected');
                focusVehicle(parseInt(this.dataset.id, 10));
            });
        });

        /* Ré-appliquer le filtre si actif */
        const q = document.getElementById('vehicleSearch')?.value.trim().toLowerCase();
        if (q) applyFilter(q);
    }

    function buildVehicleItem(v) {
        const id        = v.id;
        const immat     = esc(v.immatriculation ?? '—');
        const brand     = esc(`${v.marque ?? ''} ${v.model ?? ''}`.trim() || '—');
        const driver    = esc(v.driver?.label ?? v.users ?? 'Non associé');
        const label     = `${v.immatriculation ?? ''} ${v.driver?.label ?? v.users ?? ''}`.toLowerCase();
        const trajetUrl = `{{ route('trajets.index', ['vehicle_id' => '__ID__']) }}`.replace('__ID__', id);

        const engineCut = v.engine?.cut;
        const gpsOnline = v.gps?.online;

        const engineClass = engineCut === true  ? 'v-badge-engine-off'
                          : engineCut === false ? 'v-badge-engine-on'
                          : 'v-badge-engine-unk';
        const engineText  = engineCut === true  ? 'Moteur coupé'
                          : engineCut === false ? 'Moteur actif'
                          : 'Moteur inconnu';
        const engineIcon  = 'fa-power-off';

        const gpsClass    = gpsOnline === true  ? 'v-badge-gps-on'
                          : gpsOnline === false ? 'v-badge-gps-off'
                          : 'v-badge-gps-unk';
        const gpsText     = gpsOnline === true  ? 'GPS en ligne'
                          : gpsOnline === false ? 'GPS hors ligne'
                          : 'GPS inconnu';

        return `
        <div class="vehicle-item" id="vi-${id}" data-id="${id}" data-label="${esc(label)}" role="listitem">
            <div class="vehicle-item-row">
                <div class="vehicle-avatar" aria-hidden="true">
                    <i class="fas fa-car"></i>
                </div>
                <div class="vehicle-info">
                    <p class="vehicle-immat">${immat}</p>
                    <p class="vehicle-brand">${brand}</p>
                    <p class="vehicle-driver">
                        <i class="fas fa-user" aria-hidden="true"></i>${driver}
                    </p>
                </div>
                <a href="${trajetUrl}"
                   class="vehicle-link"
                   title="Voir les trajets"
                   onclick="event.stopPropagation();">
                    <i class="fas fa-route" aria-hidden="true"></i> Trajets
                </a>
            </div>
            <div class="vehicle-badges">
                <div class="vehicle-badges-left">
                    <span class="v-badge ${engineClass}">
                        <i class="fas ${engineIcon}" aria-hidden="true"></i>${engineText}
                    </span>
                    <span class="v-badge ${gpsClass}">
                        <i class="fas fa-satellite-dish" aria-hidden="true"></i>${gpsText}
                    </span>
                </div>
                <span class="vehicle-center-hint" aria-hidden="true">
                    <i class="fas fa-crosshairs"></i>Centrer
                </span>
            </div>
        </div>`;
    }

    /* ════════════════════════════════════════════════════════
       RECHERCHE / FILTRE
    ════════════════════════════════════════════════════════ */
    function initVehicleSearch() {
        const input = document.getElementById('vehicleSearch');
        if (!input) return;
        input.addEventListener('input', function () {
            applyFilter(this.value.trim().toLowerCase());
        });
    }

    function applyFilter(q) {
        const list  = document.getElementById('vehicleList');
        const count = document.getElementById('fleet-count');
        const items = list?.querySelectorAll('.vehicle-item') ?? [];
        let visible = 0;

        items.forEach(item => {
            const match = !q || (item.dataset.label ?? '').includes(q);
            item.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        if (count) count.textContent = `${visible} véhicule(s)`;
    }

    /* ════════════════════════════════════════════════════════
       MARQUEURS GOOGLE MAPS
    ════════════════════════════════════════════════════════ */
    function renderMarkers(vehicles, fitBounds) {
        if (!map) return;
        const bounds = new google.maps.LatLngBounds();
        const newIds = new Set();

        vehicles.forEach(v => {
            if (v.lat == null || v.lon == null) return;
            const id  = v.id;
            const pos = { lat: parseFloat(v.lat), lng: parseFloat(v.lon) };
            newIds.add(String(id));

            let marker = markersById[id];
            if (!marker) {
                marker = new google.maps.Marker({
                    position: pos,
                    map,
                    title: v.immatriculation ?? '',
                    icon: {
                        url: '/assets/icons/car_icon.png',
                        scaledSize: new google.maps.Size(38, 38),
                    },
                    animation: google.maps.Animation.DROP,
                });

                const iw = new google.maps.InfoWindow({ content: buildInfoWindow(v) });
                marker.addListener('click', () => {
                    selectedVehicleId = id;
                    iw.open(map, marker);
                    /* Sélectionner l'item dans la liste */
                    document.querySelectorAll('.vehicle-item.selected').forEach(i => i.classList.remove('selected'));
                    document.getElementById('vi-' + id)?.classList.add('selected');
                });

                markersById[id]     = marker;
                infoWindowsById[id] = iw;
            } else {
                marker.setPosition(pos);
            }

            bounds.extend(pos);
        });

        /* Supprimer les marqueurs obsolètes */
        Object.keys(markersById).forEach(id => {
            if (!newIds.has(String(id))) {
                markersById[id].setMap(null);
                delete markersById[id];
                delete infoWindowsById[id];
            }
        });

        if (fitBounds && vehicles.length) {
            map.fitBounds(bounds);
            const listener = google.maps.event.addListener(map, 'idle', () => {
                if (map.getZoom() > 14) map.setZoom(14);
                google.maps.event.removeListener(listener);
            });
        }
    }

    function updateSelectedInfoWindow(fleet) {
        if (selectedVehicleId == null) return;
        const v = fleet.find(x => String(x.id) === String(selectedVehicleId));
        if (!v) return;
        infoWindowsById[selectedVehicleId]?.setContent(buildInfoWindow(v));
    }

    function buildInfoWindow(v) {
        const driver     = v.driver?.label ?? v.users ?? '—';
        const engineCut  = v.engine?.cut;
        const gpsOnline  = v.gps?.online;
        const eLabel     = engineCut === true ? 'Moteur coupé' : engineCut === false ? 'Moteur actif' : 'Moteur inconnu';
        const eColor     = engineCut === true ? '#ef4444' : engineCut === false ? '#22c55e' : '#9ca3af';
        const gLabel     = gpsOnline  === true ? 'GPS en ligne'  : gpsOnline === false ? 'GPS hors ligne' : 'GPS inconnu';
        const gColor     = gpsOnline  === true ? '#22c55e' : gpsOnline === false ? '#9ca3af' : '#6b7280';
        const trajetUrl  = `{{ route('trajets.index', ['vehicle_id' => '__ID__']) }}`.replace('__ID__', v.id);

        return `
        <div style="font-family:'Lato',system-ui,sans-serif;font-size:12px;min-width:220px;line-height:1.5;">
            <div style="font-family:'Rajdhani',sans-serif;font-size:14px;font-weight:700;margin-bottom:6px;color:#1e293b;">
                ${esc(v.immatriculation)}
            </div>
            <div style="margin-bottom:4px;color:#6b7280;">
                Chauffeur : <span style="color:#1e293b;font-weight:600;">${esc(driver)}</span>
            </div>
            <div style="display:flex;gap:12px;margin-top:6px;flex-wrap:wrap;">
                <span style="color:${eColor};font-weight:600;font-size:11px;">
                    ● ${eLabel}
                </span>
                <span style="color:${gColor};font-weight:600;font-size:11px;">
                    ● ${gLabel}
                </span>
            </div>
            <div style="margin-top:10px;padding-top:8px;border-top:1px solid #e2e8f0;">
                <a href="${trajetUrl}" style="color:#F58220;font-weight:600;text-decoration:none;font-size:11px;">
                    ▶ Voir les trajets
                </a>
            </div>
        </div>`;
    }

    function focusVehicle(id) {
        const marker = markersById[id];
        if (!marker || !map) return;
        selectedVehicleId = id;
        map.setCenter(marker.getPosition());
        map.setZoom(15);
        infoWindowsById[id]?.open(map, marker);
    }

    /* ════════════════════════════════════════════════════════
       UTILITAIRES
    ════════════════════════════════════════════════════════ */
    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, m =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    /* ════════════════════════════════════════════════════════
       INIT
    ════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {
        /* Mesures initiales */
        measureKpi();
        setTimeout(measureKpi, 250);
        setTimeout(measureKpi, 900);

        /* Debounce resize */
        let rTimer = null;
        window.addEventListener('resize', () => {
            clearTimeout(rTimer);
            rTimer = setTimeout(measureKpi, 120);
        });

        /* Charger Google Maps */
        loadGoogleMaps();

        /* Observer les changements de thème pour re-styler la carte */
        const appRoot = document.getElementById('app-root');
        if (appRoot && window.MutationObserver) {
            new MutationObserver(function () {
                if (map) map.setOptions({ styles: getMapStyle() });
            }).observe(appRoot, { attributes: true, attributeFilter: ['class'] });
        }
    });

})();
</script>
@endpush

@endsection