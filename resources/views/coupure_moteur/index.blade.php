{{-- resources/views/coupure_moteur/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Immobilisation des Véhicules')

@push('styles')
<style>

/* ════════════════════════════════════════════════════════════════
   COUPURE MOTEUR — Layout professionnel
════════════════════════════════════════════════════════════════ */

/* ── Wrapper plein écran ────────────────────────────────────── */
.engine-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--navbar-h) - var(--kpi-h, 0px) - (var(--sp-xl) * 2));
    overflow: hidden;
    gap: var(--sp-md);
}

/* ════════════════════════════════════════════════════════════════
   BARRE KPI (card séparée)
════════════════════════════════════════════════════════════════ */
.engine-kpi-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    flex-shrink: 0;
}

.engine-kpi-bar {
    display: flex;
    align-items: stretch;
    overflow-x: auto;
    scrollbar-width: none;
    border-bottom: 1px solid var(--color-border-subtle);
}
.engine-kpi-bar::-webkit-scrollbar { display: none; }

.ekpi-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    border-right: 1px solid var(--color-border-subtle);
    flex: 1 1 0;
    min-width: 140px;
    position: relative;
    cursor: default;
    transition: background 0.15s;
}
.ekpi-item:last-child { border-right: none; }

.ekpi-item::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    border-radius: 2px 2px 0 0;
    opacity: 0;
    transition: opacity 0.2s;
}
.ekpi-item:hover { background: var(--color-bg); }
.ekpi-item:hover::after { opacity: 1; }

/* Couleurs par type */
.ekpi-item.ekpi-total  .ekpi-icon { color: var(--color-primary); background: var(--color-primary-light); }
.ekpi-item.ekpi-total::after      { background: var(--color-primary); }
.ekpi-item.ekpi-on     .ekpi-icon { color: #16a34a; background: rgba(22,163,74,.10); }
.ekpi-item.ekpi-on::after         { background: #16a34a; }
.ekpi-item.ekpi-cut    .ekpi-icon { color: #dc2626; background: rgba(239,68,68,.10); }
.ekpi-item.ekpi-cut::after        { background: #dc2626; }
.ekpi-item.ekpi-online .ekpi-icon { color: #4f46e5; background: rgba(79,70,229,.10); }
.ekpi-item.ekpi-online::after     { background: #4f46e5; }

.ekpi-icon {
    width: 36px; height: 36px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
    transition: transform 0.2s;
}
.ekpi-item:hover .ekpi-icon { transform: scale(1.08); }

.ekpi-val {
    font-family: var(--font-display);
    font-size: 1.4rem;
    font-weight: 800;
    line-height: 1;
    color: var(--color-text);
    letter-spacing: -0.02em;
}
.ekpi-lbl {
    font-family: var(--font-display);
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    margin-top: 3px;
    white-space: nowrap;
}

/* Légende bas de la KPI card */
.engine-legend {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    padding: 7px var(--sp-xl);
    background: var(--color-bg);
}

/* ════════════════════════════════════════════════════════════════
   CARD TABLEAU
════════════════════════════════════════════════════════════════ */
.engine-table-card {
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

/* ── Topbar ─────────────────────────────────────────────────── */
.engine-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 9px var(--sp-xl);
    border-bottom: 1px solid var(--color-border-subtle);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.engine-topbar-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.engine-title {
    font-family: var(--font-display);
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    white-space: nowrap;
}

.engine-count-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: var(--r-pill);
    background: var(--color-primary-light);
    border: 1px solid var(--color-primary-border);
    font-family: var(--font-display);
    font-size: 0.62rem;
    font-weight: 700;
    color: var(--color-primary);
    white-space: nowrap;
}

/* Filtre recherche */
.engine-search-wrap {
    position: relative;
}
.engine-search-wrap i {
    position: absolute;
    left: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.65rem;
    color: var(--color-secondary-text);
    pointer-events: none;
}
.engine-search-wrap input {
    height: 30px;
    font-size: 0.75rem;
    padding: 0 0.5rem 0 1.75rem;
    min-width: 200px;
}

/* ── Zone scroll ────────────────────────────────────────────── */
.engine-scroll {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: auto;
    min-height: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-subtle) transparent;
}
.engine-scroll::-webkit-scrollbar       { width: 5px; height: 5px; }
.engine-scroll::-webkit-scrollbar-thumb { background: var(--color-border-subtle); border-radius: 3px; }

/* ── Tableau ────────────────────────────────────────────────── */
.en-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-body);
    font-size: 0.8rem;
    min-width: 780px;
}

.en-table thead th {
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
.dark-mode .en-table thead th { background: #161b22; }

.en-table thead th.th-center { text-align: center; }

.en-table tbody tr {
    border-bottom: 1px solid var(--color-border-subtle);
    transition: background 0.1s;
}
.en-table tbody tr:last-child { border-bottom: none; }
.en-table tbody tr:hover { background: var(--color-primary-light); }

.en-table tbody td {
    padding: 9px 14px;
    color: var(--color-text);
    vertical-align: middle;
    white-space: nowrap;
}
.en-table tbody td.td-center { text-align: center; }

/* ── Cellules spéciales ─────────────────────────────────────── */
.immat-badge {
    font-family: var(--font-display);
    font-size: 0.73rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-sm);
    padding: 2px 7px;
    display: inline-block;
    white-space: nowrap;
    color: var(--color-text);
}

.gps-tag {
    font-family: monospace;
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    background: var(--color-border-subtle);
    border-radius: var(--r-sm);
    padding: 2px 6px;
}

.color-swatch {
    width: 22px; height: 22px;
    border-radius: 4px;
    border: 2px solid var(--color-border-subtle);
    display: inline-block;
    vertical-align: middle;
    flex-shrink: 0;
    transition: border-color 0.15s, transform 0.15s;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.12);
}
.color-swatch:hover { border-color: var(--color-primary); transform: scale(1.15); }

.driver-wrap {
    display: flex;
    align-items: center;
    gap: 7px;
}

.driver-avatar {
    width: 28px; height: 28px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--color-primary-light);
    border: 1px solid var(--color-primary-border);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.55rem;
    color: var(--color-primary);
}
.driver-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ── GPS badge ──────────────────────────────────────────────── */
.gps-badge-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-display);
    font-size: 0.6rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: var(--r-pill);
    white-space: nowrap;
    letter-spacing: 0.02em;
}
.gps-badge-status.online  { background: rgba(79,70,229,.10); border: 1px solid rgba(79,70,229,.25); color: #4f46e5; }
.gps-badge-status.offline { background: var(--color-border-subtle); color: var(--color-secondary-text); border: 1px solid var(--color-border-subtle); }
.gps-badge-status.unknown { background: var(--color-border-subtle); color: var(--color-secondary-text); border: 1px solid var(--color-border-subtle); }

/* ── Engine badge ───────────────────────────────────────────── */
.engine-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-display);
    font-size: 0.62rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: var(--r-pill);
    white-space: nowrap;
    letter-spacing: 0.02em;
}
.engine-badge.loading { background: var(--color-border-subtle); color: var(--color-secondary-text); border: 1px solid var(--color-border-subtle); }
.engine-badge.on      { background: rgba(22,163,74,.12);  color: #16a34a; border: 1px solid rgba(22,163,74,.3); }
.engine-badge.cut     { background: rgba(239,68,68,.12);  color: #dc2626; border: 1px solid rgba(239,68,68,.3); }
.engine-badge.pending { background: var(--color-primary-light); color: var(--color-primary); border: 1px solid var(--color-primary-border); }

/* ── Toggle moteur ──────────────────────────────────────────── */
.engine-toggle {
    width: 56px; height: 28px;
    border-radius: var(--r-pill);
    position: relative;
    cursor: pointer;
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    box-shadow: 0 1px 6px rgba(0,0,0,.08);
    transition: background 0.22s, border-color 0.22s, opacity 0.15s;
    overflow: hidden;
    flex-shrink: 0;
}
.engine-toggle .engine-knob {
    position: absolute;
    top: 2px; left: 2px;
    width: 22px; height: 22px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: var(--color-card);
    color: var(--color-secondary-text);
    box-shadow: 0 1px 6px rgba(0,0,0,.18);
    transition: left 0.22s ease, color 0.22s;
    font-size: 9px;
}
.engine-toggle.is-on  { background: rgba(22,163,74,.18);  border-color: rgba(22,163,74,.4); }
.engine-toggle.is-on  .engine-knob { left: 30px; color: #16a34a; }
.engine-toggle.is-cut { background: rgba(239,68,68,.15);  border-color: rgba(239,68,68,.4); }
.engine-toggle.is-cut .engine-knob { left: 2px;  color: #dc2626; }
.engine-toggle.is-loading { opacity: 0.55; pointer-events: none; }
.engine-toggle.is-loading .engine-knob { animation: eknob-pulse 0.7s ease-in-out infinite alternate; }
@keyframes eknob-pulse {
    from { box-shadow: 0 1px 6px rgba(0,0,0,.18); }
    to   { box-shadow: 0 1px 12px rgba(245,130,32,.5); }
}

.motor-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.motor-badges {
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-width: 90px;
}

/* ── Vide ───────────────────────────────────────────────────── */
.en-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--color-secondary-text);
}
.en-empty i {
    display: block;
    font-size: 2rem;
    opacity: 0.2;
    margin-bottom: 0.5rem;
}
.en-empty span {
    font-family: var(--font-display);
    font-size: 0.82rem;
}

/* ── Footer pagination ──────────────────────────────────────── */
.engine-footer {
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

.pag-nav {
    display: flex;
    align-items: center;
    gap: 3px;
}

.pag-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px; height: 28px;
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
.pag-btn:disabled { opacity: 0.3; cursor: not-allowed; }

/* ════════════════════════════════════════════════════════════════
   MODALE CONFIRMATION
════════════════════════════════════════════════════════════════ */
.confirm-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 9000;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}

.confirm-panel {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    width: 100%; max-width: 400px;
    padding: 1.5rem;
    position: relative;
    box-shadow: 0 24px 60px rgba(0,0,0,.25);
    transform: translateY(12px) scale(0.97);
    opacity: 0;
    transition: transform 0.22s ease, opacity 0.22s ease;
}
.confirm-panel.open { transform: translateY(0) scale(1); opacity: 1; }

.confirm-icon-wrap {
    width: 48px; height: 48px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.1rem;
}
.confirm-icon-wrap.cut     { background: rgba(239,68,68,.12); border: 2px solid rgba(239,68,68,.3);  color: #dc2626; }
.confirm-icon-wrap.restore { background: rgba(22,163,74,.12); border: 2px solid rgba(22,163,74,.3);  color: #16a34a; }

.confirm-title {
    font-family: var(--font-display);
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--color-text);
    text-align: center;
    margin: 0 0 1rem;
}

.vehicle-info-block {
    background: var(--color-bg);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-md);
    padding: 0.75rem 1rem;
    margin-bottom: 0.75rem;
    display: flex; flex-direction: column; gap: 5px;
}
.vehicle-info-row {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.75rem;
    color: var(--color-secondary-text);
}
.vehicle-info-row strong { color: var(--color-text); font-weight: 600; }

.confirm-action-box {
    border-radius: var(--r-md);
    padding: 8px 12px;
    margin-bottom: 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex; align-items: center; gap: 6px;
}
.confirm-action-box.cut     { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.2); color: #dc2626; }
.confirm-action-box.restore { background: rgba(22,163,74,.08); border: 1px solid rgba(22,163,74,.2); color: #16a34a; }

.confirm-hint {
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    margin-bottom: 1rem;
    display: flex; align-items: flex-start; gap: 5px;
    line-height: 1.45;
}

.confirm-footer {
    display: flex; gap: 6px;
    padding-top: 0.875rem;
    border-top: 1px solid var(--color-border-subtle);
}
.confirm-footer button { flex: 1; }

.btn-danger {
    display: inline-flex; align-items: center; justify-content: center; gap: 5px;
    padding: 7px 14px; border-radius: var(--r-md);
    font-family: var(--font-display); font-size: 0.72rem; font-weight: 700;
    cursor: pointer; border: none;
    background: #ef4444; color: #fff;
    transition: background 0.15s;
}
.btn-danger:hover { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-success {
    display: inline-flex; align-items: center; justify-content: center; gap: 5px;
    padding: 7px 14px; border-radius: var(--r-md);
    font-family: var(--font-display); font-size: 0.72rem; font-weight: 700;
    cursor: pointer; border: none;
    background: #22c55e; color: #fff;
    transition: background 0.15s;
}
.btn-success:hover { background: #16a34a; }
.btn-success:disabled { opacity: 0.55; cursor: not-allowed; }

.btn-spinner {
    display: none;
    width: 13px; height: 13px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 767px) {
    .engine-page { height: auto; overflow: visible; gap: var(--sp-sm); }
    .engine-kpi-bar { flex-wrap: nowrap; }
    .ekpi-item { min-width: 110px; padding: 8px 14px; }
    .ekpi-val  { font-size: 1.1rem; }
    .engine-topbar { padding: 8px var(--sp-md); }
    .engine-search-wrap input { min-width: 160px; }
}

</style>
@endpush

@section('content')
@php
    $voitures = $voitures ?? [];
    $total    = count($voitures);
@endphp

<div class="engine-page">

    {{-- ════════════════════════════════════════════════════
         KPI CARD (séparée)
    ════════════════════════════════════════════════════ --}}
    <div class="engine-kpi-card">

        {{-- Chiffres clés --}}
        <div class="engine-kpi-bar" role="region" aria-label="Indicateurs flotte">

            <div class="ekpi-item ekpi-total">
                <div class="ekpi-icon">
                    <i class="fas fa-car" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="ekpi-val" id="headerStatTotal">{{ $total }}</div>
                    <div class="ekpi-lbl">Total flotte</div>
                </div>
            </div>

            <div class="ekpi-item ekpi-on">
                <div class="ekpi-icon">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="ekpi-val" id="headerStatOn">—</div>
                    <div class="ekpi-lbl">Moteurs actifs</div>
                </div>
            </div>

            <div class="ekpi-item ekpi-cut">
                <div class="ekpi-icon">
                    <i class="fas fa-ban" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="ekpi-val" id="headerStatCut">—</div>
                    <div class="ekpi-lbl">Moteurs coupés</div>
                </div>
            </div>

            <div class="ekpi-item ekpi-online">
                <div class="ekpi-icon">
                    <i class="fas fa-satellite-dish" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="ekpi-val" id="headerStatOnline">—</div>
                    <div class="ekpi-lbl">GPS en ligne</div>
                </div>
            </div>

        </div>

        {{-- Légende --}}
        <div class="engine-legend">
            <span style="font-family:var(--font-display);font-size:0.58rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--color-secondary-text);margin-right:4px;">
                Légende
            </span>
            <span class="engine-badge on">
                <i class="fas fa-check-circle" style="font-size:0.5rem;" aria-hidden="true"></i> Moteur actif
            </span>
            <span class="engine-badge cut">
                <i class="fas fa-ban" style="font-size:0.5rem;" aria-hidden="true"></i> Moteur coupé
            </span>
            <span class="engine-badge pending">
                <i class="fas fa-satellite-dish" style="font-size:0.5rem;" aria-hidden="true"></i> Commande en cours
            </span>
            <span class="gps-badge-status online">
                <i class="fas fa-circle" style="font-size:0.38rem;" aria-hidden="true"></i> GPS en ligne
            </span>
            <span class="gps-badge-status offline">
                <i class="fas fa-circle" style="font-size:0.38rem;" aria-hidden="true"></i> GPS hors ligne
            </span>
        </div>

    </div>

    {{-- ════════════════════════════════════════════════════
         TABLE CARD (séparée)
    ════════════════════════════════════════════════════ --}}
    <div class="engine-table-card">

        {{-- Topbar --}}
        <div class="engine-topbar">

            <div class="engine-topbar-left">
                <h1 class="engine-title">
                    <i class="fas fa-power-off" style="color:var(--color-primary);margin-right:6px;" aria-hidden="true"></i>
                    Contrôle moteur à distance
                </h1>
                <div class="engine-count-chip">
                    <i class="fas fa-car" aria-hidden="true"></i>
                    {{ $total }} véhicule(s)
                </div>
            </div>

            <div class="engine-search-wrap">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input id="engineSearch"
                       type="text"
                       class="ui-input-style"
                       placeholder="Immat, marque, chauffeur…"
                       aria-label="Rechercher un véhicule">
            </div>

        </div>

        {{-- Tableau --}}
        <div class="engine-scroll">
            <table class="en-table">
                <thead>
                    <tr>
                        <th scope="col">Immatriculation</th>
                        <th scope="col">Marque / Modèle</th>
                        <th scope="col">Couleur</th>
                        <th scope="col">Chauffeur</th>
                        <th scope="col">GPS</th>
                        <th scope="col" class="th-center">Moteur</th>
                    </tr>
                </thead>
                <tbody id="engine-tbody">

                    @foreach($voitures as $voiture)
                    @php
                        $chauffeur     = $voiture->chauffeurActuelPartner?->chauffeur;
                        $chauffeurName = $chauffeur
                            ? trim(($chauffeur->nom ?? '').' '.($chauffeur->prenom ?? ''))
                            : null;
                    @endphp
                    <tr data-search="{{ strtolower($voiture->immatriculation.' '.($voiture->marque ?? '').' '.($voiture->model ?? '').' '.($chauffeurName ?? '')) }}">

                        <td>
                            <span class="immat-badge">{{ $voiture->immatriculation }}</span>
                        </td>

                        <td>
                            <div style="font-weight:600;font-size:0.82rem;color:var(--color-text);">{{ $voiture->marque ?? '—' }}</div>
                            <div style="font-size:0.67rem;color:var(--color-secondary-text);">{{ $voiture->model ?? '' }}</div>
                        </td>

                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span class="color-swatch"
                                      style="background:{{ $voiture->couleur ?? '#e5e7eb' }};"
                                      title="{{ $voiture->couleur ?? 'N/A' }}"></span>
                                
                            </div>
                        </td>

                        <td>
                            @if($chauffeur)
                            <div class="driver-wrap">
                                <div class="driver-avatar">
                                    @if(!empty($chauffeur->photo_url))
                                        <img src="{{ $chauffeur->photo_url }}" alt="{{ $chauffeurName }}">
                                    @else
                                        <i class="fas fa-user" aria-hidden="true"></i>
                                    @endif
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:0.78rem;color:var(--color-text);line-height:1.2;">{{ $chauffeurName ?: 'Chauffeur' }}</div>
                                    @if($chauffeur->phone)
                                    <div style="font-size:0.63rem;color:var(--color-secondary-text);margin-top:1px;">
                                        <i class="fas fa-phone" style="font-size:0.5rem;color:var(--color-primary);margin-right:2px;" aria-hidden="true"></i>
                                        {{ $chauffeur->phone }}
                                    </div>
                                    @endif
                                </div>
                            </div>
                            @else
                            <span style="font-size:0.72rem;color:var(--color-secondary-text);font-style:italic;">Non assigné</span>
                            @endif
                        </td>

                        <td>
                            @if($voiture->mac_id_gps)
                                <span class="gps-tag">{{ $voiture->mac_id_gps }}</span>
                            @else
                                <span style="font-size:0.72rem;color:var(--color-secondary-text);">—</span>
                            @endif
                        </td>

                        <td class="td-center">
                            <div class="motor-cell">
                                <button
                                    type="button"
                                    class="engine-toggle"
                                    data-id="{{ $voiture->id }}"
                                    data-cut="0"
                                    data-toggle-url="{{ route('voitures.toggleEngine', ['voiture' => $voiture->id], false) }}"
                                    data-status-url="{{ route('voitures.engineStatus', ['voiture' => $voiture->id], false) }}"
                                    data-immat="{{ $voiture->immatriculation }}"
                                    data-marque="{{ $voiture->marque }}"
                                    data-model="{{ $voiture->model }}"
                                    data-couleur="{{ $voiture->couleur }}"
                                    data-chauffeur="{{ $chauffeurName ?: '' }}"
                                    data-phone="{{ $chauffeur?->phone ?? '' }}"
                                    aria-label="Toggle moteur {{ $voiture->immatriculation }}"
                                >
                                    <span class="engine-knob">
                                        <i class="fas fa-power-off" aria-hidden="true"></i>
                                    </span>
                                </button>

                                <div class="motor-badges">
                                    <span class="engine-badge loading" id="engineBadge-{{ $voiture->id }}">
                                        <i class="fas fa-spinner fa-spin" style="font-size:0.5rem;" aria-hidden="true"></i> Chargement
                                    </span>
                                    <span class="gps-badge-status unknown" id="gpsBadge-{{ $voiture->id }}">
                                        GPS: N/A
                                    </span>
                                </div>
                            </div>
                        </td>

                    </tr>
                    @endforeach

                    @if($total === 0)
                    <tr>
                        <td colspan="6" class="en-empty">
                            <i class="fas fa-car-side" aria-hidden="true"></i>
                            <span>Aucun véhicule trouvé.</span>
                        </td>
                    </tr>
                    @endif

                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="engine-footer">
            <div class="footer-info">
                <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:0.6rem;" aria-hidden="true"></i>
                <span id="footer-count">{{ $total }} véhicule(s)</span>
            </div>
            <div class="pag-nav" id="engine-pag" role="navigation" aria-label="Pagination"></div>
        </div>

    </div>

</div>

{{-- ════════════════════════════════════════════════════════════════
     MODALE CONFIRMATION
════════════════════════════════════════════════════════════════ --}}
<div id="engineConfirmModal" class="confirm-overlay" style="display:none;" aria-modal="true" role="alertdialog">
    <div id="engineConfirmPanel" class="confirm-panel">

        <div class="confirm-icon-wrap cut" id="confirmIconWrap">
            <i class="fas fa-power-off" id="confirmIconEl" aria-hidden="true"></i>
        </div>

        <h2 class="confirm-title" id="confirmTitle">Confirmation</h2>

        <div class="vehicle-info-block">
            <div class="vehicle-info-row">
                <i class="fas fa-car" style="color:var(--color-primary);font-size:0.65rem;" aria-hidden="true"></i>
                <strong id="confirmImmat">—</strong>
                <span id="confirmModel" style="font-size:0.68rem;"></span>
            </div>
            <div class="vehicle-info-row">
                <i class="fas fa-user" style="color:var(--color-primary);font-size:0.65rem;" aria-hidden="true"></i>
                <span id="confirmDriver">—</span>
            </div>
            <div class="vehicle-info-row" id="confirmPhoneRow" style="display:none;">
                <i class="fas fa-phone" style="color:var(--color-primary);font-size:0.6rem;" aria-hidden="true"></i>
                <span id="confirmPhone"></span>
            </div>
        </div>

        <div class="confirm-action-box cut" id="confirmActionBox">
            <i class="fas fa-power-off" id="confirmActionIcon" aria-hidden="true"></i>
            <span id="confirmActionText">Voulez-vous vraiment COUPER le moteur ?</span>
        </div>

        <p class="confirm-hint">
            <i class="fas fa-satellite-dish" style="color:var(--color-primary);margin-top:1px;flex-shrink:0;" aria-hidden="true"></i>
            Commande transmise au module GPS. Le statut sera mis à jour automatiquement après confirmation.
        </p>

        <div class="confirm-footer">
            <button type="button" id="cancelEngineBtn" class="btn-secondary">Annuler</button>
            <button type="button" id="confirmEngineBtn" class="btn-danger">
                <span class="btn-spinner" id="confirmSpinner"></span>
                <i id="confirmBtnIcon" class="fas fa-power-off" aria-hidden="true"></i>
                <span id="confirmBtnLabel">Couper</span>
            </button>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

/* ════════════════════════════════════════════════════════════════
   CONFIG
════════════════════════════════════════════════════════════════ */
const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content || '';
const batchUrl = @json(route('voitures.engineStatusBatch', [], false));
const PER_PAGE = 20;

const modal      = document.getElementById('engineConfirmModal');
const panel      = document.getElementById('engineConfirmPanel');
const cancelBtn  = document.getElementById('cancelEngineBtn');
const confirmBtn = document.getElementById('confirmEngineBtn');

const iconWrap   = document.getElementById('confirmIconWrap');
const iconEl     = document.getElementById('confirmIconEl');
const titleEl    = document.getElementById('confirmTitle');
const immatEl    = document.getElementById('confirmImmat');
const modelEl    = document.getElementById('confirmModel');
const driverEl   = document.getElementById('confirmDriver');
const phoneRow   = document.getElementById('confirmPhoneRow');
const phoneEl    = document.getElementById('confirmPhone');
const actionBox  = document.getElementById('confirmActionBox');
const actionIcon = document.getElementById('confirmActionIcon');
const actionText = document.getElementById('confirmActionText');
const btnIcon    = document.getElementById('confirmBtnIcon');
const btnLabel   = document.getElementById('confirmBtnLabel');
const btnSpinner = document.getElementById('confirmSpinner');

/* ════════════════════════════════════════════════════════════════
   RECHERCHE + PAGINATION
════════════════════════════════════════════════════════════════ */
const allRows   = Array.from(document.querySelectorAll('#engine-tbody tr[data-search]'));
let filtered    = allRows.slice();
let currentPage = 1;

function renderTable() {
    const pagNav = document.getElementById('engine-pag');
    const total  = filtered.length;
    const pages  = Math.max(1, Math.ceil(total / PER_PAGE));
    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * PER_PAGE;

    // Afficher/masquer lignes
    allRows.forEach(r => r.style.display = 'none');
    filtered.slice(start, start + PER_PAGE).forEach(r => r.style.display = '');

    // Ligne vide si aucun résultat
    const emptyRow = document.getElementById('engine-empty-row');
    if (emptyRow) emptyRow.style.display = filtered.length === 0 ? '' : 'none';

    // Compteur
    document.getElementById('footer-count').textContent = total > 0
        ? `${start + 1}–${Math.min(start + PER_PAGE, total)} sur ${total} véhicule(s)`
        : '0 véhicule(s)';

    // Pagination
    pagNav.innerHTML = '';
    if (pages <= 1) return;

    const mkBtn = (html, page, disabled = false, active = false) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'pag-btn' + (active ? ' active' : '');
        b.innerHTML = html;
        b.disabled = disabled;
        if (!disabled && !active) b.addEventListener('click', () => { currentPage = page; renderTable(); });
        return b;
    };

    pagNav.appendChild(mkBtn('<i class="fas fa-chevron-left"></i>', currentPage - 1, currentPage === 1));

    const delta = 2;
    const lo = Math.max(1, currentPage - delta);
    const hi = Math.min(pages, currentPage + delta);

    if (lo > 1) {
        pagNav.appendChild(mkBtn('1', 1));
        if (lo > 2) { const s = document.createElement('span'); s.className = 'pag-btn'; s.style.pointerEvents = 'none'; s.style.opacity = '.4'; s.textContent = '…'; pagNav.appendChild(s); }
    }
    for (let p = lo; p <= hi; p++) pagNav.appendChild(mkBtn(p, p, false, p === currentPage));
    if (hi < pages) {
        if (hi < pages - 1) { const s = document.createElement('span'); s.className = 'pag-btn'; s.style.pointerEvents = 'none'; s.style.opacity = '.4'; s.textContent = '…'; pagNav.appendChild(s); }
        pagNav.appendChild(mkBtn(pages, pages));
    }

    pagNav.appendChild(mkBtn('<i class="fas fa-chevron-right"></i>', currentPage + 1, currentPage === pages));
}

document.getElementById('engineSearch').addEventListener('input', function () {
    const q = this.value.toLowerCase().trim();
    filtered = q ? allRows.filter(r => r.dataset.search.includes(q)) : allRows.slice();
    currentPage = 1;
    renderTable();
});

renderTable();

/* ════════════════════════════════════════════════════════════════
   MODAL
════════════════════════════════════════════════════════════════ */
function openModal()  {
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.add('open')));
}
function closeModal() {
    panel.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(() => { modal.style.display = 'none'; pendingTarget = pendingAction = pendingExpectedCut = null; }, 220);
}

cancelBtn?.addEventListener('click', closeModal);
modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.style.display !== 'none') closeModal(); });

/* ════════════════════════════════════════════════════════════════
   UI HELPERS
════════════════════════════════════════════════════════════════ */
const switches = Array.from(document.querySelectorAll('.engine-toggle'));
const ids      = switches.map(b => b.dataset.id).filter(Boolean);

let pendingTarget      = null;
let pendingAction      = null;
let pendingExpectedCut = null;

function setUI(id, payload) {
    const btn         = document.querySelector(`.engine-toggle[data-id="${id}"]`);
    const engineBadge = document.getElementById(`engineBadge-${id}`);
    const gpsBadge    = document.getElementById(`gpsBadge-${id}`);
    if (!btn || !engineBadge || !gpsBadge) return;

    btn.classList.remove('is-loading');

    if (!payload || payload.success === false) {
        btn.classList.remove('is-on', 'is-cut');
        engineBadge.innerHTML = '<i class="fas fa-question-circle" style="font-size:0.5rem;"></i> N/A';
        engineBadge.className = 'engine-badge loading';
        gpsBadge.textContent  = 'GPS: N/A';
        gpsBadge.className    = 'gps-badge-status unknown';
        updateKpi(); return;
    }

    const cut    = !!payload.engine?.cut;
    const online = payload.gps?.online;

    btn.dataset.cut = cut ? '1' : '0';
    btn.classList.toggle('is-cut', cut);
    btn.classList.toggle('is-on',  !cut);
    btn.title = cut ? 'Rétablir le moteur' : 'Couper le moteur';

    engineBadge.innerHTML = cut
        ? '<i class="fas fa-ban" style="font-size:0.5rem;"></i> COUPÉ'
        : '<i class="fas fa-check-circle" style="font-size:0.5rem;"></i> ACTIF';
    engineBadge.className = 'engine-badge ' + (cut ? 'cut' : 'on');

    if (online === true)       { gpsBadge.textContent = 'GPS: ONLINE';  gpsBadge.className = 'gps-badge-status online'; }
    else if (online === false) { gpsBadge.textContent = 'GPS: OFFLINE'; gpsBadge.className = 'gps-badge-status offline'; }
    else                       { gpsBadge.textContent = 'GPS: N/A';     gpsBadge.className = 'gps-badge-status unknown'; }

    updateKpi();
}

function updateKpi() {
    const all = Array.from(document.querySelectorAll('.engine-toggle'));
    let on = 0, cut = 0, online = 0;
    all.forEach(b => {
        if (b.classList.contains('is-on'))  on++;
        if (b.classList.contains('is-cut')) cut++;
        const gps = document.getElementById(`gpsBadge-${b.dataset.id}`);
        if (gps?.classList.contains('online')) online++;
    });
    document.getElementById('headerStatOn').textContent     = on;
    document.getElementById('headerStatCut').textContent    = cut;
    document.getElementById('headerStatOnline').textContent = online;
}

function setPending(id, label) {
    const btn         = document.querySelector(`.engine-toggle[data-id="${id}"]`);
    const engineBadge = document.getElementById(`engineBadge-${id}`);
    if (!btn || !engineBadge) return;
    btn.classList.add('is-loading');
    engineBadge.innerHTML = `<span style="display:inline-block;width:9px;height:9px;border:2px solid rgba(245,130,32,.3);border-top-color:var(--color-primary);border-radius:50%;animation:spin 0.6s linear infinite;"></span> ${label}`;
    engineBadge.className = 'engine-badge pending';
}

/* ════════════════════════════════════════════════════════════════
   FETCH HELPERS
════════════════════════════════════════════════════════════════ */
const fetchJson = async (url, opt = {}, ms = 12000) => {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort(), ms);
    try {
        const res  = await fetch(url, { ...opt, signal: ctrl.signal });
        const json = await res.json().catch(() => null);
        return { ok: res.ok, status: res.status, json };
    } finally { clearTimeout(t); }
};

const pollConfirm = async (statusUrl, expectedCut, tries = 10, interval = 900) => {
    for (let i = 0; i < tries; i++) {
        await new Promise(r => setTimeout(r, interval));
        const r = await fetchJson(`${statusUrl}?_t=${Date.now()}`, {
            cache: 'no-store', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (r.ok && r.json?.success && !!r.json.engine?.cut === expectedCut) return { confirmed: true, json: r.json };
    }
    return { confirmed: false, json: null };
};

/* ════════════════════════════════════════════════════════════════
   BATCH STATUS
════════════════════════════════════════════════════════════════ */
fetchJson(`${batchUrl}?ids=${encodeURIComponent(ids.join(','))}&_t=${Date.now()}`, {
    cache: 'no-store', credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
}, 15000)
.then(({ ok, json }) => {
    if (!ok || !json) throw new Error();
    ids.forEach(id => setUI(id, json.data?.[id] ?? { success: false }));
})
.catch(() => ids.forEach(id => setUI(id, { success: false })));

/* ════════════════════════════════════════════════════════════════
   OUVRIR MODALE
════════════════════════════════════════════════════════════════ */
switches.forEach(btn => {
    btn.addEventListener('click', () => {
        if (btn.classList.contains('is-loading')) return;

        const currentCut   = btn.dataset.cut === '1';
        pendingAction      = currentCut ? 'restore' : 'cut';
        pendingExpectedCut = !currentCut;
        pendingTarget      = btn;

        const isCut  = pendingAction === 'cut';
        const immat  = btn.dataset.immat  || '—';
        const marque = btn.dataset.marque || '';
        const model  = btn.dataset.model  || '';
        const chauff = btn.dataset.chauffeur || '';
        const phone  = btn.dataset.phone  || '';

        iconWrap.className  = 'confirm-icon-wrap ' + (isCut ? 'cut' : 'restore');
        iconEl.className    = isCut ? 'fas fa-power-off' : 'fas fa-rotate-right';
        titleEl.textContent = isCut ? 'Couper le moteur' : 'Rétablir le moteur';
        immatEl.textContent = immat;
        modelEl.textContent = marque + (model ? ' ' + model : '');
        driverEl.textContent= chauff || 'Non assigné';

        if (phone) { phoneEl.textContent = phone; phoneRow.style.display = 'flex'; }
        else       { phoneRow.style.display = 'none'; }

        actionBox.className   = 'confirm-action-box ' + (isCut ? 'cut' : 'restore');
        actionIcon.className  = isCut ? 'fas fa-ban' : 'fas fa-check-circle';
        actionText.textContent = isCut
            ? 'Voulez-vous vraiment COUPER le moteur de ce véhicule ?'
            : 'Voulez-vous vraiment RÉTABLIR le moteur de ce véhicule ?';

        confirmBtn.className  = isCut ? 'btn-danger' : 'btn-success';
        btnIcon.className     = isCut ? 'fas fa-power-off' : 'fas fa-rotate-right';
        btnLabel.textContent  = isCut ? 'Couper' : 'Allumer';
        btnSpinner.style.display = 'none';
        confirmBtn.disabled   = false;

        openModal();
    });
});

/* ════════════════════════════════════════════════════════════════
   CONFIRMER ACTION
════════════════════════════════════════════════════════════════ */
confirmBtn?.addEventListener('click', async () => {
    if (!pendingTarget) return;

    const btn         = pendingTarget;
    const id          = btn.dataset.id;
    const toggleUrl   = btn.dataset.toggleUrl;
    const statusUrl   = btn.dataset.statusUrl;
    const action      = pendingAction;
    const expectedCut = !!pendingExpectedCut;
    const label       = expectedCut ? 'Coupure…' : 'Allumage…';

    confirmBtn.disabled = true;
    btnSpinner.style.display = 'inline-block';

    closeModal();
    setPending(id, label);

    try {
        const res = await fetch(toggleUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ action })
        });

        if (res.status === 419) {
            window.showToastMsg?.('Session expirée', 'Rechargement en cours…', 'error');
            setTimeout(() => window.location.reload(), 1000);
            return;
        }

        const data = await res.json().catch(() => null);
        const ok   = res.ok && data?.success;

        if (!ok) {
            window.showToastMsg?.('Erreur commande', data?.message || 'Échec de la commande moteur.', 'error');
            setUI(id, { success: false });
            return;
        }

        setUI(id, { success: true, engine: { cut: expectedCut }, gps: { online: null } });
        window.showToastMsg?.(
            'Commande envoyée',
            (data.message || 'Commande en cours…') + (data.cmd_no ? ` · CmdNo: ${data.cmd_no}` : ''),
            'success'
        );

        const p = await pollConfirm(statusUrl, expectedCut, 10, 900);
        if (p.confirmed && p.json) {
            setUI(id, p.json);
            window.showToastMsg?.(
                'Confirmé',
                expectedCut ? 'Moteur coupé — confirmé par le GPS.' : 'Moteur rétabli — confirmé par le GPS.',
                'success'
            );
        } else {
            const r = await fetchJson(`${statusUrl}?_t=${Date.now()}`, {
                cache: 'no-store', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (r.ok && r.json?.success) setUI(id, r.json);
            window.showToastMsg?.('En attente', "Commande envoyée — le GPS n'a pas encore confirmé.", 'error');
        }

    } catch {
        window.showToastMsg?.('Erreur réseau', 'Impossible de contacter le serveur.', 'error');
    } finally {
        document.querySelector(`.engine-toggle[data-id="${id}"]`)?.classList.remove('is-loading');
    }
});

}); // DOMContentLoaded
</script>
@endpush