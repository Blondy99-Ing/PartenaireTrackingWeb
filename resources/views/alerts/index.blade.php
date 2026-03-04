{{-- resources/views/alerts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des Alertes')

@push('styles')
<style>

/* ════════════════════════════════════════════════════════════════
   ALERTES — Layout professionnel
════════════════════════════════════════════════════════════════ */

/* ── Wrapper plein écran ────────────────────────────────────── */
.alerts-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--navbar-h) - var(--kpi-h, 0px) - (var(--sp-xl) * 2));
    overflow: hidden;
    gap: var(--sp-md);
}

/* ── Bloc KPI (card séparée) ────────────────────────────────── */
.alerts-kpi-card {
    background: var(--color-bg);
    border: 1px solid var(--color-border-subtle);
    border-radius: none;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    flex-shrink: 0;
    padding: 6px 0; 
}

/* ── Bloc tableau (card séparée) ────────────────────────────── */
.alerts-table-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

/* ════════════════════════════════════════════════════════════════
   BARRE KPI — style cohérent dashboard
════════════════════════════════════════════════════════════════ */
.alerts-kpi-bar {
    display: flex;
    align-items: stretch;
    gap: 0;
    background: var(--color-card);
    border-bottom: 1px solid var(--color-border-subtle);
    overflow-x: auto;
    scrollbar-width: none;
    border-radius: var(--r-lg) var(--r-lg) 0 0;
    padding: 0 ;
    border-bottom: none;
   
}
.alerts-kpi-bar::-webkit-scrollbar { display: none; }

.kpi-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 10px;
    border-right: 1px solid var(--color-border-subtle);
    flex: 1 1 0;
    min-width: 130px;
    position: relative;
    cursor: default;
    transition: background 0.15s;
}

.kpi-item:last-child { border-right: none; }

.kpi-item::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    border-radius: 2px 2px 0 0;
    opacity: 0;
    transition: opacity 0.2s;
}

.kpi-item:hover::after { opacity: 1; }
.kpi-item:hover { background: var(--color-bg); }

.kpi-item.kpi-stolen  .kpi-icon { color: #ef4444; background: rgba(239,68,68,.10); }
.kpi-item.kpi-stolen::after     { background: #ef4444; }
.kpi-item.kpi-geo     .kpi-icon { color: var(--color-primary); background: var(--color-primary-light); }
.kpi-item.kpi-geo::after        { background: var(--color-primary); }
.kpi-item.kpi-speed   .kpi-icon { color: #2563eb; background: rgba(37,99,235,.10); }
.kpi-item.kpi-speed::after      { background: #2563eb; }
.kpi-item.kpi-safe    .kpi-icon { color: #16a34a; background: rgba(22,163,74,.10); }
.kpi-item.kpi-safe::after       { background: #16a34a; }
.kpi-item.kpi-time    .kpi-icon { color: #9333ea; background: rgba(147,51,234,.10); }
.kpi-item.kpi-time::after       { background: #9333ea; }

.kpi-icon {
    width: 34px; height: 34px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    flex-shrink: 0;
    transition: transform 0.2s;
}

.kpi-item:hover .kpi-icon { transform: scale(1.08); }

.kpi-text { min-width: 0; }

.kpi-val {
    font-family: var(--font-display);
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1;
    color: var(--color-text);
    letter-spacing: -0.02em;
}

.kpi-lbl {
    font-family: var(--font-display);
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    margin-top: 3px;
    white-space: nowrap;
}

/* ════════════════════════════════════════════════════════════════
   TOPBAR TABLEAU
════════════════════════════════════════════════════════════════ */
.alerts-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 9px var(--sp-xl);
    background: var(--color-card);
    border-bottom: 1px solid var(--color-border-subtle);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.alerts-topbar-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alerts-title {
    font-family: var(--font-display);
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    white-space: nowrap;
}

.refresh-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: var(--r-pill);
    background: var(--color-primary-light);
    border: 1px solid var(--color-primary-border);
    font-family: var(--font-display);
    font-size: 0.6rem;
    font-weight: 700;
    color: var(--color-primary);
    white-space: nowrap;
}

/* ── Filtres inline ─────────────────────────────────────────── */
.alerts-filters {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.alerts-filters input,
.alerts-filters select {
    height: 30px;
    font-size: 0.75rem;
    padding: 0 0.5rem;
    width: auto;
}

.filter-search-wrap {
    position: relative;
    min-width: 180px;
}

.filter-search-wrap i {
    position: absolute;
    left: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.65rem;
    color: var(--color-secondary-text);
    pointer-events: none;
}

.filter-search-wrap input {
    padding-left: 1.75rem !important;
}

/* ════════════════════════════════════════════════════════════════
   ZONE TABLEAU
════════════════════════════════════════════════════════════════ */
.alerts-table-zone {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    background: var(--color-card);
    border-radius: 0 0 var(--r-lg) var(--r-lg);
}

.alerts-scroll {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: auto;
    min-height: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-subtle) transparent;
}

.alerts-scroll::-webkit-scrollbar       { width: 5px; height: 5px; }
.alerts-scroll::-webkit-scrollbar-thumb { background: var(--color-border-subtle); border-radius: 3px; }

/* ── Tableau ────────────────────────────────────────────────── */
.al-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-body);
    font-size: 0.8rem;
    min-width: 700px;
}

.al-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    font-family: var(--font-display);
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--color-bg-subtle, #e8eaed);
    color: var(--color-secondary-text);
    padding: 9px 14px;
    text-align: left;
    border-bottom: 2px solid var(--color-primary);
    white-space: nowrap;
}

.dark-mode .al-table thead th { background: #161b22; }

.al-table tbody tr {
    border-bottom: 1px solid var(--color-border-subtle);
    transition: background 0.1s;
}

.al-table tbody tr:last-child { border-bottom: none; }
.al-table tbody tr:hover { background: var(--color-primary-light); }

.al-table tbody td {
    padding: 8px 14px;
    color: var(--color-text);
    vertical-align: middle;
    white-space: nowrap;
}

/* ── Badges type ────────────────────────────────────────────── */
.al-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: 0.68rem;
    font-weight: 700;
    white-space: nowrap;
    letter-spacing: 0.02em;
}

.al-badge.geofence  { background: var(--color-primary-light);    color: var(--color-primary); border: 1px solid var(--color-primary-border); }
.al-badge.speed     { background: rgba(37,99,235,.10);            color: #2563eb;              border: 1px solid rgba(37,99,235,.25); }
.al-badge.safe_zone { background: rgba(22,163,74,.10);            color: #16a34a;              border: 1px solid rgba(22,163,74,.25); }
.al-badge.time_zone { background: rgba(147,51,234,.10);           color: #9333ea;              border: 1px solid rgba(147,51,234,.25); }
.al-badge.stolen    { background: rgba(239,68,68,.10);            color: #dc2626;              border: 1px solid rgba(239,68,68,.25); }

/* ── Immat ──────────────────────────────────────────────────── */
.al-immat {
    font-family: var(--font-display);
    font-size: 0.75rem;
    font-weight: 700;
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-sm);
    padding: 2px 7px;
    display: inline-block;
    letter-spacing: 0.04em;
    color: var(--color-text);
}

/* ── Time chip ──────────────────────────────────────────────── */
.al-time {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.72rem;
    color: var(--color-secondary-text);
    white-space: nowrap;
    font-family: var(--font-mono, monospace);
}

/* ── Chauffeur ──────────────────────────────────────────────── */
.al-driver {
    display: flex;
    align-items: center;
    gap: 6px;
}

.al-avatar {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: var(--color-primary-light);
    border: 1px solid var(--color-primary-border);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: 0.55rem;
    color: var(--color-primary);
}

/* ── Status ─────────────────────────────────────────────────── */
.al-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: 0.65rem;
    font-weight: 700;
    white-space: nowrap;
}

.al-status.open     { background: rgba(239,68,68,.10);   color: #dc2626; border: 1px solid rgba(239,68,68,.2); }
.al-status.resolved { background: rgba(22,163,74,.10);   color: #16a34a; border: 1px solid rgba(22,163,74,.2); }

/* ── Vide ───────────────────────────────────────────────────── */
.al-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--color-secondary-text);
}
.al-empty i {
    display: block;
    font-size: 2rem;
    opacity: 0.2;
    margin-bottom: 0.5rem;
}
.al-empty span {
    font-family: var(--font-display);
    font-size: 0.82rem;
}

/* ════════════════════════════════════════════════════════════════
   PAGINATION + FOOTER
════════════════════════════════════════════════════════════════ */
.alerts-footer {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px var(--sp-xl);
    border-top: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    gap: 1rem;
    flex-wrap: wrap;
}

.footer-info {
    font-family: var(--font-body);
    font-size: 0.7rem;
    color: var(--color-secondary-text);
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

/* Pagination JS ─── */
.pag-nav {
    display: flex;
    align-items: center;
    gap: 3px;
}

.pag-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 6px;
    border-radius: var(--r-md);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    color: var(--color-text);
    font-family: var(--font-display);
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.12s;
    white-space: nowrap;
    user-select: none;
}

.pag-btn:hover:not(:disabled):not(.active) {
    background: var(--color-primary-light);
    border-color: var(--color-primary-border);
    color: var(--color-primary);
}

.pag-btn.active {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
    font-weight: 700;
}

.pag-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* ════════════════════════════════════════════════════════════════
   TOASTS — DESIGN SYSTEM
════════════════════════════════════════════════════════════════ */
.toast-stack {
    position: fixed;
    right: 1rem;
    top: calc(var(--navbar-h, 4.5rem) + 0.75rem);
    z-index: var(--z-toast, 9999);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: min(360px, calc(100vw - 2rem));
    pointer-events: none;
}

.alert-toast {
    pointer-events: auto;
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    transform: translateX(16px) scale(0.97);
    opacity: 0;
    transition: transform 0.22s ease, opacity 0.22s ease;
    position: relative;
}

.alert-toast.show { transform: translateX(0) scale(1); opacity: 1; }

.alert-toast::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 3px;
    border-radius: var(--r-xl) 0 0 var(--r-xl);
}

.alert-toast.geofence::before  { background: var(--color-primary); }
.alert-toast.speed::before     { background: #2563eb; }
.alert-toast.safe_zone::before { background: #16a34a; }
.alert-toast.time_zone::before { background: #9333ea; }
.alert-toast.stolen::before    { background: #dc2626; }

.toast-head {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 10px 6px 12px;
}

.toast-ico {
    width: 30px; height: 30px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem;
    color: #fff;
    flex-shrink: 0;
}

.toast-ico.geofence  { background: var(--color-primary); }
.toast-ico.speed     { background: #2563eb; }
.toast-ico.safe_zone { background: #16a34a; }
.toast-ico.time_zone { background: #9333ea; }
.toast-ico.stolen    { background: #dc2626; }

.toast-head-text { flex: 1; min-width: 0; }

.toast-head-type {
    font-family: var(--font-display);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    line-height: 1.2;
}

.toast-head-veh {
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    margin: 1px 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.toast-close-btn {
    width: 22px; height: 22px;
    border-radius: var(--r-sm);
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    color: var(--color-secondary-text);
    font-size: 0.85rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: color 0.12s, background 0.12s;
    line-height: 1;
}
.toast-close-btn:hover { color: #dc2626; background: rgba(239,68,68,.1); }

.toast-body {
    padding: 0 12px 8px;
    font-size: 0.72rem;
    color: var(--color-secondary-text);
    line-height: 1.45;
}

.toast-actions {
    display: flex;
    gap: 5px;
    padding: 6px 10px 8px;
    border-top: 1px solid var(--color-border-subtle);
    background: var(--color-bg);
}

.toast-act-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: var(--r-md);
    font-family: var(--font-display);
    font-size: 0.65rem;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.12s;
}
.toast-act-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.toast-act-read   { background: rgba(37,99,235,.1); color: #2563eb; border-color: rgba(37,99,235,.25); }
.toast-act-read:hover:not(:disabled)   { background: #2563eb; color: #fff; }
.toast-act-ignore { background: var(--color-border-subtle); color: var(--color-secondary-text); }
.toast-act-ignore:hover:not(:disabled) { background: var(--color-secondary-text); color: #fff; }

.toast-prog {
    height: 2px;
    transform-origin: left;
    transform: scaleX(1);
}
.alert-toast.geofence  .toast-prog { background: linear-gradient(90deg, var(--color-primary), transparent); }
.alert-toast.speed     .toast-prog { background: linear-gradient(90deg, #2563eb, transparent); }
.alert-toast.safe_zone .toast-prog { background: linear-gradient(90deg, #16a34a, transparent); }
.alert-toast.time_zone .toast-prog { background: linear-gradient(90deg, #9333ea, transparent); }
.alert-toast.stolen    .toast-prog { background: linear-gradient(90deg, #dc2626, transparent); }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 900px) {
    .alerts-filters select { display: none; }
}

@media (max-width: 767px) {
    .alerts-page { height: auto; overflow: visible; }
    .alerts-kpi-bar { flex-wrap: nowrap; }
    .kpi-item { min-width: 110px; padding: 8px 14px; }
    .kpi-val  { font-size: 1.1rem; }
    .alerts-topbar { padding: 8px var(--sp-md); }
}

</style>
@endpush

@section('content')
<div class="alerts-page">

    {{-- ════════════════════════════════════════════════════
         BARRE KPI (card séparée)
    ════════════════════════════════════════════════════ --}}
    <div class="alerts-kpi-card">
    <div class="alerts-kpi-bar" role="region" aria-label="Indicateurs alertes">

        <div class="kpi-item kpi-stolen">
            <div class="kpi-icon">
                <i class="fas fa-car-crash" aria-hidden="true"></i>
            </div>
            <div class="kpi-text">
                <div class="kpi-val" id="stat-stolen">0</div>
                <div class="kpi-lbl">Vol</div>
            </div>
        </div>

        <div class="kpi-item kpi-geo">
            <div class="kpi-icon">
                <i class="fas fa-route" aria-hidden="true"></i>
            </div>
            <div class="kpi-text">
                <div class="kpi-val" id="stat-geofence">0</div>
                <div class="kpi-lbl">Geofence</div>
            </div>
        </div>

        <div class="kpi-item kpi-speed">
            <div class="kpi-icon">
                <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
            </div>
            <div class="kpi-text">
                <div class="kpi-val" id="stat-speed">0</div>
                <div class="kpi-lbl">Vitesse</div>
            </div>
        </div>

        <div class="kpi-item kpi-safe">
            <div class="kpi-icon">
                <i class="fas fa-shield-alt" aria-hidden="true"></i>
            </div>
            <div class="kpi-text">
                <div class="kpi-val" id="stat-safezone">0</div>
                <div class="kpi-lbl">Safe Zone</div>
            </div>
        </div>

        <div class="kpi-item kpi-time">
            <div class="kpi-icon">
                <i class="fas fa-clock" aria-hidden="true"></i>
            </div>
            <div class="kpi-text">
                <div class="kpi-val" id="stat-timezone">0</div>
                <div class="kpi-lbl">Time Zone</div>
            </div>
        </div>

    </div>
    </div>{{-- /alerts-kpi-card --}}

    <div class="alerts-table-card">
    {{-- ════════════════════════════════════════════════════
         TOPBAR : titre + filtres
    ════════════════════════════════════════════════════ --}}
    <div class="alerts-topbar">

        <div class="alerts-topbar-left">
            <h1 class="alerts-title">
                <i class="fas fa-bell" style="color:var(--color-primary);margin-right:6px;" aria-hidden="true"></i>
                Incidents détectés
            </h1>
            <div class="refresh-chip">
                <i class="fas fa-circle" style="font-size:0.45rem;" id="sse-dot" aria-hidden="true"></i>
                <span id="lastRefresh">—</span>
            </div>
        </div>

        <div class="alerts-filters">

            {{-- Recherche --}}
            <div class="filter-search-wrap">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input id="alertSearch" type="text" class="ui-input-style"
                       placeholder="Véhicule, chauffeur…"
                       aria-label="Rechercher">
            </div>

            {{-- Type --}}
            <select id="alertTypeFilter" class="ui-input-style" aria-label="Filtrer par type">
                <option value="all">Tous les types</option>
                <option value="geofence">GeoFence</option>
                <option value="speed">Vitesse</option>
                <option value="safe_zone">Safe Zone</option>
                <option value="time_zone">Time Zone</option>
                <option value="stolen">Vol</option>
            </select>

            {{-- Véhicule --}}
            <select id="vehicleFilter" class="ui-input-style" aria-label="Filtrer par véhicule">
                <option value="all">Tous les véhicules</option>
            </select>

            {{-- Chauffeur --}}
            <select id="userFilter" class="ui-input-style" aria-label="Filtrer par chauffeur">
                <option value="all">Tous les chauffeurs</option>
            </select>

            {{-- Rafraîchir --}}
            <button id="refreshBtn" class="btn-secondary" style="padding:4px 12px;min-height:30px;font-size:0.72rem;" title="Rafraîchir">
                <i class="fas fa-sync-alt" id="refreshIcon" aria-hidden="true"></i>
                <span class="hidden sm:inline">Rafraîchir</span>
            </button>

        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         ZONE TABLEAU FIXE
    ════════════════════════════════════════════════════ --}}
    <div class="alerts-table-zone">

        <div class="alerts-scroll">
            <table class="al-table" role="grid" aria-label="Liste des alertes">
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Véhicule</th>
                        <th scope="col">Chauffeur</th>
                        <th scope="col">Date / Heure</th>
                        <th scope="col">Description</th>
                    </tr>
                </thead>
                <tbody id="alerts-tbody">
                    <tr>
                        <td colspan="5" class="al-empty">
                            <i class="fas fa-spinner fa-spin" style="opacity:0.5;" aria-hidden="true"></i>
                            <span>Chargement des alertes…</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ── Footer pagination ───────────────────────── --}}
        <div class="alerts-footer">

            <div class="footer-info">
                <i class="fas fa-sync-alt" style="color:var(--color-primary);font-size:0.6rem;" aria-hidden="true"></i>
                Actualisation auto. toutes les 30 s —
                <span id="footer-count">0 alerte(s)</span>
            </div>

            <div class="pag-nav" id="pag-nav" role="navigation" aria-label="Pagination"></div>

        </div>
    </div>

    </div>{{-- /alerts-table-card --}}

</div>

{{-- Toast stack --}}
<div id="toast-stack" class="toast-stack" aria-live="polite" aria-atomic="false" role="status"></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

/* ════════════════════════════════════════════════════════════════
   CONFIG
════════════════════════════════════════════════════════════════ */
const API_INDEX     = "{{ route('alerts.index') }}";
const API_POLL      = "{{ url('/alerts/poll') }}";
const API_MARK_READ = "{{ url('/alerts') }}";
const CSRF          = "{{ csrf_token() }}";

const POLL_MS      = 30000;
const TOAST_TTL_MS = 12000;
const TOAST_MAX    = 5;
const PER_PAGE     = 20;

const ALLOWED_TYPES = new Set(['geofence', 'safe_zone', 'speed', 'time_zone', 'stolen']);

const TYPE_CFG = {
    geofence:   { icon: 'fas fa-route',         label: 'GeoFence',  cls: 'geofence'  },
    safe_zone:  { icon: 'fas fa-shield-alt',     label: 'Safe Zone', cls: 'safe_zone' },
    speed:      { icon: 'fas fa-tachometer-alt', label: 'Vitesse',   cls: 'speed'     },
    time_zone:  { icon: 'fas fa-clock',          label: 'Time Zone', cls: 'time_zone' },
    stolen:     { icon: 'fas fa-car-crash',      label: 'Vol',       cls: 'stolen'    },
};

let allAlerts   = [];   // données brutes
let filtered    = [];   // après filtres
let currentPage = 1;
let pollTimer   = null;
let lastSeenId  = 0;
const shownIds  = new Set();

/* ════════════════════════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════════════════════════ */
const esc = s => String(s ?? '').replace(/[&<>"']/g, m =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

const normalizeType = a => a?.type ?? a?.alert_type ?? 'general';
const isAllowed     = a => ALLOWED_TYPES.has(normalizeType(a));
const isOpen        = a => { const p = a?.processed; return p===false||p===0||p===null||p===undefined; };

function parseHumanDate(fr) {
    const m = String(fr??'').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/);
    if (!m) return null;
    const d = new Date(+m[3],+m[2]-1,+m[1],+m[4],+m[5],+m[6]);
    return isNaN(d) ? null : d;
}

function isToday(a) {
    let d = a?.alerted_at ? new Date(a.alerted_at) : null;
    if (!d||isNaN(d)) d = parseHumanDate(a?.alerted_at_human);
    if (!d) return false;
    const n = new Date();
    return d.getFullYear()===n.getFullYear()&&d.getMonth()===n.getMonth()&&d.getDate()===n.getDate();
}

const vehicleImmat = a => a?.voiture?.immatriculation||(a?.voiture_id?`#${a.voiture_id}`:'—');

function vehicleLabel(a) {
    if (a?.voiture) {
        const i=(a.voiture.immatriculation||'').trim(), m=(a.voiture.marque||'').trim();
        if(i&&m) return `${i} (${m})`; return i||m;
    }
    return a?.voiture_id?`Véh. #${a.voiture_id}`:'—';
}

function userLabel(a) {
    const l=(a?.driver_label??a?.users_labels??'').trim();
    return l||(a?.user_id?`Utilisateur #${a.user_id}`:'Aucun chauffeur');
}

/* ════════════════════════════════════════════════════════════════
   STATS KPI
════════════════════════════════════════════════════════════════ */
function updateStats() {
    const base = allAlerts.filter(isAllowed).filter(isOpen).filter(isToday);
    document.getElementById('stat-stolen').textContent   = base.filter(a=>normalizeType(a)==='stolen').length;
    document.getElementById('stat-geofence').textContent = base.filter(a=>normalizeType(a)==='geofence').length;
    document.getElementById('stat-speed').textContent    = base.filter(a=>normalizeType(a)==='speed').length;
    document.getElementById('stat-safezone').textContent = base.filter(a=>normalizeType(a)==='safe_zone').length;
    document.getElementById('stat-timezone').textContent = base.filter(a=>normalizeType(a)==='time_zone').length;

    const now = new Date();
    document.getElementById('lastRefresh').textContent =
        now.toLocaleDateString('fr-FR')+' '+now.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});
}

/* ════════════════════════════════════════════════════════════════
   RENDU TABLEAU + PAGINATION
════════════════════════════════════════════════════════════════ */
function renderPage() {
    const tbody  = document.getElementById('alerts-tbody');
    const pagNav = document.getElementById('pag-nav');
    const total  = filtered.length;
    const pages  = Math.max(1, Math.ceil(total / PER_PAGE));

    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * PER_PAGE;
    const rows  = filtered.slice(start, start + PER_PAGE);

    // Compteur
    document.getElementById('footer-count').textContent =
        total > 0 ? `${start+1}–${Math.min(start+PER_PAGE,total)} sur ${total} alerte(s)` : '0 alerte(s)';

    // Tableau
    tbody.innerHTML = '';

    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="al-empty">
            <i class="fas fa-bell-slash" aria-hidden="true"></i>
            <span>Aucune alerte trouvée.</span>
        </td></tr>`;
    } else {
        rows.forEach(a => {
            const t    = normalizeType(a);
            const cfg  = TYPE_CFG[t] ?? { icon:'fas fa-bell', label: t, cls: 'gray' };
            const imm  = vehicleImmat(a);
            const vLbl = vehicleLabel(a);
            const uLbl = userLabel(a);
            const when = a.alerted_at_human ?? '—';
            const msg  = (a.message ?? a.location ?? '—').slice(0, 80);

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <span class="al-badge ${cfg.cls}">
                        <i class="${cfg.icon}" aria-hidden="true"></i>
                        ${esc(a.type_label ?? cfg.label)}
                    </span>
                </td>
                <td>
                    <span class="al-immat">${esc(imm)}</span>
                    <div style="font-size:0.65rem;color:var(--color-secondary-text);margin-top:2px;">
                        ${esc(a.voiture?.marque??'')} ${esc(a.voiture?.model??'')}
                    </div>
                </td>
                <td>
                    <div class="al-driver">
                        <div class="al-avatar"><i class="fas fa-user" aria-hidden="true"></i></div>
                        <span style="font-size:0.78rem;">${esc(uLbl)}</span>
                    </div>
                </td>
                <td>
                    <span class="al-time">
                        <i class="fas fa-clock" style="color:var(--color-primary);font-size:0.55rem;" aria-hidden="true"></i>
                        ${esc(when)}
                    </span>
                </td>
                <td>
                    <span style="font-size:0.75rem;color:var(--color-secondary-text);max-width:220px;display:block;overflow:hidden;text-overflow:ellipsis;"
                          title="${esc(a.message??a.location??'')}">
                        ${esc(msg)}
                    </span>
                </td>

            `;
            tbody.appendChild(tr);
        });
    }

    // Pagination
    pagNav.innerHTML = '';
    if (pages <= 1) return;

    const mkBtn = (label, page, disabled=false, active=false) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'pag-btn' + (active?' active':'');
        b.innerHTML = label;
        b.disabled = disabled;
        if (!disabled && !active) b.addEventListener('click', () => { currentPage=page; renderPage(); });
        return b;
    };

    pagNav.appendChild(mkBtn('<i class="fas fa-chevron-left"></i>', currentPage-1, currentPage===1));

    const delta = 2;
    let lo = Math.max(1, currentPage-delta);
    let hi = Math.min(pages, currentPage+delta);
    if (lo > 1) { pagNav.appendChild(mkBtn('1',1)); if(lo>2){ const s=document.createElement('span'); s.className='pag-btn'; s.style.pointerEvents='none'; s.style.opacity='.4'; s.textContent='…'; pagNav.appendChild(s);} }
    for (let p=lo; p<=hi; p++) pagNav.appendChild(mkBtn(p, p, false, p===currentPage));
    if (hi < pages) { if(hi<pages-1){ const s=document.createElement('span'); s.className='pag-btn'; s.style.pointerEvents='none'; s.style.opacity='.4'; s.textContent='…'; pagNav.appendChild(s);} pagNav.appendChild(mkBtn(pages,pages)); }

    pagNav.appendChild(mkBtn('<i class="fas fa-chevron-right"></i>', currentPage+1, currentPage===pages));
}

/* ════════════════════════════════════════════════════════════════
   FILTRES
════════════════════════════════════════════════════════════════ */
function buildSelects() {
    const vSel = document.getElementById('vehicleFilter');
    const uSel = document.getElementById('userFilter');
    const vv = vSel.value, uv = uSel.value;
    const vehicles = new Map(), users = new Map();

    allAlerts.filter(isAllowed).forEach(a => {
        if (a?.voiture_id) vehicles.set(String(a.voiture_id), vehicleLabel(a));
        if (a?.user_id)    users.set(String(a.user_id), userLabel(a));
    });

    vSel.innerHTML = '<option value="all">Tous les véhicules</option>';
    [...vehicles.entries()].sort((a,b)=>a[1].localeCompare(b[1])).forEach(([id,l])=>{
        const o=document.createElement('option'); o.value=id; o.textContent=l; vSel.appendChild(o);
    });
    vSel.value = vv;

    uSel.innerHTML = '<option value="all">Tous les chauffeurs</option>';
    [...users.entries()].sort((a,b)=>a[1].localeCompare(b[1])).forEach(([id,l])=>{
        const o=document.createElement('option'); o.value=id; o.textContent=l; uSel.appendChild(o);
    });
    uSel.value = uv;
}

function applyFilters() {
    const q  = (document.getElementById('alertSearch').value||'').toLowerCase().trim();
    const t  = document.getElementById('alertTypeFilter').value;
    const v  = document.getElementById('vehicleFilter').value;
    const u  = document.getElementById('userFilter').value;

    filtered = allAlerts.filter(isAllowed);
    if (t!=='all') filtered = filtered.filter(a=>normalizeType(a)===t);
    if (v!=='all') filtered = filtered.filter(a=>String(a.voiture_id??'')===v);
    if (u!=='all') filtered = filtered.filter(a=>String(a.user_id??'')===u);
    if (q) filtered = filtered.filter(a=>
        vehicleLabel(a).toLowerCase().includes(q)||
        userLabel(a).toLowerCase().includes(q)||
        String(a.message??a.location??'').toLowerCase().includes(q)
    );

    currentPage = 1;
    renderPage();
    updateStats();
}

/* ════════════════════════════════════════════════════════════════
   API
════════════════════════════════════════════════════════════════ */
async function fetchAlerts() {
    try {
        const res  = await fetch(API_INDEX+'?ts='+Date.now(), {
            headers:{Accept:'application/json','Cache-Control':'no-cache'},
            credentials:'same-origin'
        });
        const json = await res.json();
        if (!res.ok||json.status!=='success') return [];
        const data = Array.isArray(json.data)?json.data:[];
        const mx   = data.reduce((m,a)=>Math.max(m,+a?.id||0),0);
        if(mx>lastSeenId) lastSeenId=mx;
        return data;
    } catch { return []; }
}

async function pollNew() {
    if (document.visibilityState!=='visible') return;
    try {
        const res  = await fetch(`${API_POLL}?after_id=${lastSeenId}&ts=${Date.now()}`,{
            headers:{Accept:'application/json','Cache-Control':'no-cache'},
            credentials:'same-origin'
        });
        const json = await res.json();
        if (!res.ok||json.status!=='success') return;
        const data = Array.isArray(json.data)?json.data:[];
        const mx   = +(json?.meta?.max_id||0);
        if(mx>lastSeenId) lastSeenId=mx;
        if(!data.length) return;
        data.forEach(showToast);
        allAlerts=[...data,...allAlerts].slice(0,2000);
        buildSelects();
        applyFilters();
    } catch {}
}

async function markRead(alertId) {
    const res  = await fetch(`${API_MARK_READ}/${alertId}/read`,{
        method:'PATCH',
        headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body:JSON.stringify({}),
        credentials:'same-origin'
    });
    const json = await res.json().catch(()=>null);
    if (!res.ok||!json?.ok) throw new Error(json?.message||'Erreur');
    return json;
}

/* ════════════════════════════════════════════════════════════════
   TOASTS
════════════════════════════════════════════════════════════════ */
function showToast(a) {
    if (!a?.id||!isAllowed(a)) return;
    const id=+a.id;
    if(shownIds.has(id)) return;
    shownIds.add(id);

    const t   = normalizeType(a);
    const cfg = TYPE_CFG[t]??{icon:'fas fa-bell',label:t,cls:'gray'};
    const stack=document.getElementById('toast-stack');
    while(stack.children.length>=TOAST_MAX) stack.removeChild(stack.lastElementChild);

    const el=document.createElement('div');
    el.className=`alert-toast ${cfg.cls}`;
    el.dataset.alertId=String(id);

    const msg=(a.message??a.location??'').trim();
    const uLbl=userLabel(a);
    const when=a.alerted_at_human??'';

    el.innerHTML=`
        <div class="toast-prog" id="tp-${id}"></div>
        <div class="toast-head">
            <div class="toast-ico ${cfg.cls}"><i class="${cfg.icon}" aria-hidden="true"></i></div>
            <div class="toast-head-text">
                <p class="toast-head-type">${esc(a.type_label??cfg.label)}</p>
                <p class="toast-head-veh"><strong>${esc(vehicleImmat(a))}</strong>${when?' · '+esc(when):''}</p>
            </div>
            <button class="toast-close-btn" aria-label="Fermer">&times;</button>
        </div>
        <div class="toast-body">
            ${esc(uLbl)}${msg?`<br>${esc(msg)}`:''}
        </div>
        <div class="toast-actions">
            <button type="button" class="toast-act-btn toast-act-read"><i class="fas fa-eye"></i> Lu</button>
            <button type="button" class="toast-act-btn toast-act-ignore"><i class="fas fa-eye-slash"></i> Ignorer</button>
        </div>
    `;

    stack.insertBefore(el, stack.firstChild);
    requestAnimationFrame(()=>requestAnimationFrame(()=>el.classList.add('show')));

    // Prog bar
    const prog=document.getElementById(`tp-${id}`);
    if(prog){
        prog.style.transition=`transform ${TOAST_TTL_MS}ms linear`;
        requestAnimationFrame(()=>{ prog.style.transform='scaleX(0)'; });
    }

    const dismiss=()=>{ el.classList.remove('show'); setTimeout(()=>el.parentNode?.removeChild(el),220); };
    const setBusy=b=>el.querySelectorAll('.toast-act-btn').forEach(x=>x.disabled=b);
    const autoTimer=setTimeout(dismiss, TOAST_TTL_MS);
    el.addEventListener('mouseenter',()=>clearTimeout(autoTimer),{once:true});
    el.querySelector('.toast-close-btn').addEventListener('click',()=>{ clearTimeout(autoTimer); dismiss(); });

    const handleAction = async () => {
        setBusy(true);
        try {
            await markRead(id);
            allAlerts=allAlerts.map(x=>+x.id===id?{...x,processed:true}:x);
            applyFilters();
            clearTimeout(autoTimer);
            dismiss();
        } catch(e) { setBusy(false); }
    };

    el.querySelector('.toast-act-read').addEventListener('click', handleAction);
    el.querySelector('.toast-act-ignore').addEventListener('click', handleAction);
}

/* ════════════════════════════════════════════════════════════════
   POLLING
════════════════════════════════════════════════════════════════ */
function startPolling(){ stopPolling(); pollTimer=setInterval(pollNew,POLL_MS); }
function stopPolling() { clearTimeout(pollTimer); pollTimer=null; }
document.addEventListener('visibilitychange',()=>document.visibilityState==='visible'?startPolling():stopPolling());

/* ════════════════════════════════════════════════════════════════
   EVENTS
════════════════════════════════════════════════════════════════ */
document.getElementById('alertSearch').addEventListener('input', applyFilters);
document.getElementById('alertTypeFilter').addEventListener('change', applyFilters);
document.getElementById('vehicleFilter').addEventListener('change', applyFilters);
document.getElementById('userFilter').addEventListener('change', applyFilters);

document.getElementById('refreshBtn').addEventListener('click', async () => {
    const icon=document.getElementById('refreshIcon');
    icon.classList.add('fa-spin');
    allAlerts=await fetchAlerts();
    buildSelects();
    applyFilters();
    icon.classList.remove('fa-spin');
});

/* ════════════════════════════════════════════════════════════════
   INIT
════════════════════════════════════════════════════════════════ */
(async () => {
    allAlerts=await fetchAlerts();
    buildSelects();
    applyFilters();
    startPolling();
})();

}); // DOMContentLoaded
</script>
@endpush