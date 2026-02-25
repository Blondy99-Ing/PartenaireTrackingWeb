{{-- resources/views/coupure_moteur/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Immobilisation des Véhicules')

@push('styles')
<style>
/* ============================================================
   DATATABLES — OVERRIDE COMPLET (cohérent avec le design system)
   ============================================================ */
.dataTables_wrapper {
    font-family: var(--font-body, system-ui, sans-serif);
    font-size: 0.82rem;
    color: var(--color-text);
}
.dataTables_wrapper .dataTables_filter { display: none !important; }
.dataTables_wrapper .dataTables_length select {
    background-color: var(--color-input-bg) !important;
    border: 1px solid var(--color-input-border) !important;
    color: var(--color-text) !important;
    border-radius: 0.4rem;
    padding: 0.3rem 0.5rem;
    font-size: 0.78rem;
    cursor: pointer;
    appearance: auto;
    transition: border-color 0.2s;
}
.dataTables_wrapper .dataTables_info { color: var(--color-secondary-text); font-size: 0.75rem; padding-top: 0.5rem; }
.dataTables_wrapper .dataTables_paginate {
    display: flex; align-items: center; gap: 0.25rem; padding-top: 0.5rem; justify-content: flex-end;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 2rem; height: 2rem; padding: 0 0.5rem; border-radius: 0.4rem;
    border: 1px solid var(--color-border-subtle) !important;
    background: var(--color-card) !important; color: var(--color-text) !important;
    font-size: 0.78rem; cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
    box-shadow: none !important; background-image: none !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--color-sidebar-active-bg) !important;
    border-color: var(--color-primary) !important; color: var(--color-primary) !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: var(--color-primary) !important; border-color: var(--color-primary) !important;
    color: #fff !important; font-weight: 700;
}
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.35; pointer-events: none; }
.dataTables_wrapper .dataTables_paginate .paginate_button.previous,
.dataTables_wrapper .dataTables_paginate .paginate_button.next {
    font-family: var(--font-display, 'Orbitron', sans-serif); font-size: 0.65rem; padding: 0 0.75rem;
}
table.dataTable thead th, table.dataTable thead td {
    background-color: var(--color-border-subtle) !important; color: var(--color-text) !important;
    border-bottom: 2px solid var(--color-primary) !important;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.68rem; font-weight: 600; letter-spacing: 0.04em;
    padding: 0.65rem 1rem !important; white-space: nowrap; user-select: none;
}
table.dataTable thead th.sorting_asc::after,
table.dataTable thead th.sorting_desc::after { color: var(--color-primary) !important; opacity: 1; }
table.dataTable tbody tr {
    background-color: var(--color-card) !important;
    border-bottom: 1px solid var(--color-border-subtle) !important;
    transition: background-color 0.15s;
}
table.dataTable tbody tr:hover { background-color: var(--color-sidebar-active-bg) !important; }
table.dataTable.stripe tbody tr.odd  { background-color: var(--color-card) !important; }
table.dataTable.stripe tbody tr.even { background-color: var(--color-bg) !important; }
table.dataTable tbody td {
    padding: 0.6rem 1rem !important; color: var(--color-text) !important;
    border: none !important; vertical-align: middle;
}
table.dataTable { border-collapse: collapse !important; margin: 0 !important; width: 100% !important; border: none !important; }

/* ============================================================
   PAGE BANNER — IMMOBILISATION
   ============================================================ */
.page-banner {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.875rem;
    overflow: hidden;
    position: relative;
}
.page-banner-glow {
    position: absolute;
    top: -40px; left: -40px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(245,130,32,0.10) 0%, transparent 70%);
    pointer-events: none;
}
.page-banner-inner {
    display: flex; align-items: center; justify-content: space-between;
    gap: 1.25rem; padding: 1.1rem 1.25rem 0.875rem; flex-wrap: wrap;
}
.page-banner-icon {
    width: 48px; height: 48px; border-radius: 0.75rem;
    background: rgba(245,130,32,0.14); border: 1.5px solid rgba(245,130,32,0.3);
    display: flex; align-items: center; justify-content: center;
    color: var(--color-primary); font-size: 1.2rem;
    flex-shrink: 0; position: relative;
    box-shadow: 0 0 0 6px rgba(245,130,32,0.05);
}
.page-banner-icon::after {
    content: ''; position: absolute; inset: -5px;
    border-radius: 1rem; border: 1px solid rgba(245,130,32,0.2);
    animation: banner-ring 2.5s ease-in-out infinite;
}
@keyframes banner-ring {
    0%, 100% { opacity: 0.6; transform: scale(1); }
    50%       { opacity: 0;   transform: scale(1.15); }
}
.page-banner-stats {
    display: flex; align-items: center;
    background: var(--color-bg); border: 1px solid var(--color-border-subtle);
    border-radius: 0.625rem; overflow: hidden; flex-shrink: 0;
}
.banner-stat {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 0.6rem 1.1rem; gap: 0.1rem;
}
.banner-stat-value {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 1.35rem; font-weight: 800; line-height: 1;
    color: var(--color-text); transition: color 0.3s;
}
.banner-stat-value.on  { color: #22c55e; }
.banner-stat-value.cut { color: #ef4444; }
.banner-stat-label {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.55rem; font-weight: 600;
    letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--color-secondary-text);
}
.banner-stat-sep { width: 1px; height: 36px; background: var(--color-border-subtle); flex-shrink: 0; }
.page-banner-legend {
    display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem;
    padding: 0.55rem 1.25rem;
    background: var(--color-bg); border-top: 1px solid var(--color-border-subtle);
}

/* ============================================================
   PAGE — IMMOBILISATION MOTEUR
   ============================================================ */

/* Immatriculation badge */
.immat-badge {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;
    background: var(--color-border-subtle); border: 1px solid var(--color-border-subtle);
    border-radius: 0.35rem; padding: 2px 7px; display: inline-block; white-space: nowrap;
    color: var(--color-text);
}

/* GPS mono tag */
.gps-tag {
    font-family: monospace; font-size: 0.68rem;
    color: var(--color-secondary-text); background: var(--color-border-subtle);
    border-radius: 0.3rem; padding: 2px 6px;
}

/* Pastille couleur véhicule */
.color-swatch {
    width: 26px; height: 26px; border-radius: 5px;
    border: 2px solid var(--color-border-subtle);
    display: inline-block;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.15);
    transition: border-color 0.2s, transform 0.15s;
    vertical-align: middle; flex-shrink: 0;
}
.color-swatch:hover { border-color: var(--color-primary); transform: scale(1.15); }

/* Avatar chauffeur */
.driver-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    overflow: hidden; flex-shrink: 0;
    background: var(--color-sidebar-active-bg);
    border: 2px solid var(--color-border-subtle);
    display: flex; align-items: center; justify-content: center;
}
.driver-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* ============================================================
   TOGGLE MOTEUR
   ============================================================ */
.engine-toggle {
    width: 70px; height: 34px; border-radius: 999px;
    position: relative; cursor: pointer;
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    transition: background 0.22s ease, border-color 0.22s ease, opacity 0.15s;
    overflow: hidden;
    flex-shrink: 0;
}
.engine-toggle .engine-knob {
    position: absolute; top: 3px; left: 3px;
    width: 26px; height: 26px; border-radius: 999px;
    display: flex; align-items: center; justify-content: center;
    background: var(--color-card); color: var(--color-secondary-text);
    box-shadow: 0 2px 8px rgba(0,0,0,0.18);
    transition: left 0.22s ease, color 0.22s ease;
    font-size: 11px;
}
/* ON — moteur actif */
.engine-toggle.is-on {
    background: rgba(34,197,94,0.18);
    border-color: rgba(34,197,94,0.4);
}
.engine-toggle.is-on .engine-knob { left: 40px; color: #16a34a; }

/* CUT — moteur coupé */
.engine-toggle.is-cut {
    background: rgba(239,68,68,0.15);
    border-color: rgba(239,68,68,0.4);
}
.engine-toggle.is-cut .engine-knob { left: 3px; color: #dc2626; }

/* LOADING */
.engine-toggle.is-loading {
    opacity: 0.6; pointer-events: none;
}
.engine-toggle.is-loading .engine-knob {
    animation: engine-pulse 0.8s ease-in-out infinite alternate;
}
@keyframes engine-pulse {
    from { box-shadow: 0 2px 8px rgba(0,0,0,0.18); }
    to   { box-shadow: 0 2px 12px rgba(245,130,32,0.5); }
}

/* Badges statut */
.engine-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.65rem; font-weight: 700; padding: 2px 9px;
    border-radius: 9999px; white-space: nowrap;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    letter-spacing: 0.03em;
}
.engine-badge.loading { background: var(--color-border-subtle); color: var(--color-secondary-text); }
.engine-badge.on      { background: rgba(34,197,94,0.14);  border: 1px solid rgba(34,197,94,0.3);  color: #16a34a; }
.engine-badge.cut     { background: rgba(239,68,68,0.12);  border: 1px solid rgba(239,68,68,0.3);  color: #dc2626; }
.engine-badge.pending { background: rgba(245,130,32,0.14); border: 1px solid rgba(245,130,32,0.3); color: var(--color-primary); }

.gps-badge-status {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.62rem; font-weight: 600; padding: 1px 7px;
    border-radius: 9999px; white-space: nowrap;
}
.gps-badge-status.online  { background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.25); color: #4f46e5; }
.gps-badge-status.offline { background: var(--color-border-subtle); color: var(--color-secondary-text); border: 1px solid var(--color-border-subtle); }
.gps-badge-status.unknown { background: var(--color-border-subtle); color: var(--color-secondary-text); border: 1px solid var(--color-border-subtle); }

/* ============================================================
   MODALE CONFIRMATION
   ============================================================ */
.confirm-overlay {
    position: fixed; inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9000;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(3px);
}

.confirm-panel {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 1rem;
    width: 100%; max-width: 420px;
    padding: 1.75rem;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    transform: translateY(12px) scale(0.97);
    opacity: 0;
    transition: transform 0.22s ease, opacity 0.22s ease;
}
.confirm-panel.open { transform: translateY(0) scale(1); opacity: 1; }

.confirm-icon-wrap {
    width: 52px; height: 52px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
    font-size: 1.2rem;
}
.confirm-icon-wrap.cut     { background: rgba(239,68,68,0.12); border: 2px solid rgba(239,68,68,0.3);  color: #dc2626; }
.confirm-icon-wrap.restore { background: rgba(34,197,94,0.12); border: 2px solid rgba(34,197,94,0.3);  color: #16a34a; }

.confirm-title {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.92rem; font-weight: 700; color: var(--color-text);
    text-align: center; margin: 0 0 1rem;
}

/* Info véhicule dans la modale */
.vehicle-info-block {
    background: var(--color-bg);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.625rem;
    padding: 0.875rem 1rem;
    margin-bottom: 0.875rem;
    display: flex; flex-direction: column; gap: 0.4rem;
}
.vehicle-info-row {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.78rem; color: var(--color-secondary-text);
}
.vehicle-info-row strong { color: var(--color-text); font-weight: 600; }

/* Avertissement action */
.confirm-action-box {
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    font-size: 0.78rem;
    font-weight: 600;
    display: flex; align-items: center; gap: 0.5rem;
}
.confirm-action-box.cut     { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #dc2626; }
.confirm-action-box.restore { background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.2); color: #16a34a; }

.confirm-hint {
    font-size: 0.7rem; color: var(--color-secondary-text);
    margin-bottom: 1.25rem;
    display: flex; align-items: flex-start; gap: 0.35rem;
    line-height: 1.4;
}

.confirm-footer {
    display: flex; gap: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--color-border-subtle);
}
.confirm-footer button { flex: 1; }

/* Bouton danger */
.btn-danger {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.55rem 1rem; border-radius: 0.5rem;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em;
    cursor: pointer; border: none;
    background: #ef4444; color: #fff;
    transition: background-color 0.15s;
}
.btn-danger:hover { background: #b91c1c; }
.btn-danger:disabled { opacity: 0.55; cursor: not-allowed; }

/* Bouton succès */
.btn-success {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.55rem 1rem; border-radius: 0.5rem;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.72rem; font-weight: 700; letter-spacing: 0.03em;
    cursor: pointer; border: none;
    background: #22c55e; color: #fff;
    transition: background-color 0.15s;
}
.btn-success:hover { background: #16a34a; }
.btn-success:disabled { opacity: 0.55; cursor: not-allowed; }

/* Spinner dans btn */
.btn-spinner {
    display: none; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff; border-radius: 50%;
    animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
@endpush

@section('content')

<div class="space-y-5">

    {{-- ============================================================
         PAGE BANNER — IMMOBILISATION
         ============================================================ --}}
    <div class="page-banner">

        {{-- Fond décoratif --}}
        <div class="page-banner-glow"></div>

        <div class="page-banner-inner">

            {{-- Gauche : icône + texte --}}
            <div style="display:flex;align-items:center;gap:1rem;min-width:0;flex:1;">

                {{-- Icône centrale --}}
                <div class="page-banner-icon">
                    <i class="fas fa-power-off"></i>
                </div>

                <div style="min-width:0;">
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.2rem;">
                        <h1 class="font-orbitron" style="font-size:1rem;font-weight:800;color:var(--color-text);margin:0;line-height:1.2;">
                            Contrôle moteur à distance
                        </h1>
                        <span style="display:inline-flex;align-items:center;gap:0.25rem;padding:1px 8px;border-radius:9999px;background:rgba(245,130,32,0.12);border:1px solid rgba(245,130,32,0.3);color:var(--color-primary);font-family:var(--font-display,'Orbitron',sans-serif);font-size:0.58rem;font-weight:700;letter-spacing:0.05em;">
                            <i class="fas fa-satellite-dish" style="font-size:0.5rem;"></i>
                            GPS LIVE
                        </span>
                    </div>
                    <p style="font-size:0.75rem;color:var(--color-secondary-text);margin:0;line-height:1.5;max-width:520px;">
                        Immobilisez ou réactivez n'importe quel véhicule de votre flotte en un clic.
                        La commande est transmise instantanément au module GPS embarqué et confirmée en temps réel.
                    </p>
                </div>
            </div>

            {{-- Droite : compteurs live --}}
            <div class="page-banner-stats">

                <div class="banner-stat">
                    <span class="banner-stat-value" id="headerStatTotal">{{ count($voitures ?? []) }}</span>
                    <span class="banner-stat-label">Total</span>
                </div>

                <div class="banner-stat-sep"></div>

                <div class="banner-stat">
                    <span class="banner-stat-value on" id="headerStatOn">—</span>
                    <span class="banner-stat-label">Actifs</span>
                </div>

                <div class="banner-stat-sep"></div>

                <div class="banner-stat">
                    <span class="banner-stat-value cut" id="headerStatCut">—</span>
                    <span class="banner-stat-label">Coupés</span>
                </div>

            </div>
        </div>

        {{-- Barre de légende en bas --}}
        <div class="page-banner-legend">
            <span style="font-size:0.65rem;color:var(--color-secondary-text);font-weight:600;letter-spacing:0.04em;text-transform:uppercase;margin-right:0.5rem;">
                Légende
            </span>
            <span class="engine-badge on" style="font-size:0.62rem;">
                <i class="fas fa-check-circle" style="font-size:0.5rem;"></i> Moteur actif
            </span>
            <span class="engine-badge cut" style="font-size:0.62rem;">
                <i class="fas fa-ban" style="font-size:0.5rem;"></i> Moteur coupé
            </span>
            <span class="engine-badge pending" style="font-size:0.62rem;">
                <i class="fas fa-satellite-dish" style="font-size:0.5rem;"></i> Commande en cours
            </span>
            <span class="gps-badge-status online" style="font-size:0.62rem;">
                <i class="fas fa-circle" style="font-size:0.4rem;"></i> GPS en ligne
            </span>
            <span class="gps-badge-status offline" style="font-size:0.62rem;">
                <i class="fas fa-circle" style="font-size:0.4rem;"></i> GPS hors ligne
            </span>
        </div>

    </div>

    {{-- ============================================================
         TABLEAU
         ============================================================ --}}
    <div class="ui-card" style="padding:1.25rem;">

        {{-- Toolbar --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <h2 class="font-orbitron" style="font-size:0.9rem;font-weight:700;color:var(--color-text);margin:0;">
                    Flotte de véhicules
                </h2>
                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.6rem;border-radius:9999px;background:var(--color-sidebar-active-bg);border:1px solid rgba(245,130,32,0.25);color:var(--color-primary);font-family:var(--font-display,'Orbitron',sans-serif);font-size:0.62rem;font-weight:700;">
                    <i class="fas fa-car" style="font-size:0.55rem;"></i>
                    {{ count($voitures ?? []) }} véhicule(s)
                </span>
            </div>

            {{-- Recherche --}}
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:0.72rem;pointer-events:none;"></i>
                <input id="engineSearchInput"
                       type="text"
                       class="ui-input-style"
                       style="padding-left:2rem;min-width:200px;font-size:0.78rem;"
                       placeholder="Immat, marque, chauffeur...">
            </div>
        </div>

        {{-- Tableau --}}
        <div class="ui-table-container">
            <table id="engineTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Marque / Modèle</th>
                        <th>Couleur</th>
                        <th>Chauffeur</th>
                        <th>GPS</th>
                        <th style="text-align:center;">Moteur</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    @php
                        $chauffeur     = $voiture->chauffeurActuelPartner?->chauffeur;
                        $chauffeurName = $chauffeur
                            ? trim(($chauffeur->nom ?? '') . ' ' . ($chauffeur->prenom ?? ''))
                            : null;
                    @endphp
                    <tr>
                        {{-- Immatriculation --}}
                        <td>
                            <span class="immat-badge">{{ $voiture->immatriculation }}</span>
                        </td>

                        {{-- Marque / Modèle --}}
                        <td>
                            <div style="font-weight:600;font-size:0.82rem;color:var(--color-text);">{{ $voiture->marque ?? '—' }}</div>
                            <div style="font-size:0.68rem;color:var(--color-secondary-text);">{{ $voiture->model ?? '' }}</div>
                        </td>

                        {{-- Couleur --}}
                        <td>
                            <div style="display:flex;align-items:center;gap:0.4rem;">
                                <span class="color-swatch"
                                      style="background-color:{{ $voiture->couleur ?? '#e5e7eb' }};"
                                      title="{{ $voiture->couleur ?? 'N/A' }}"></span>
                                <span style="font-size:0.65rem;color:var(--color-secondary-text);font-family:monospace;">
                                    {{ $voiture->couleur ?? '—' }}
                                </span>
                            </div>
                        </td>

                        {{-- Chauffeur --}}
                        <td>
                            @if($chauffeur)
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <div class="driver-avatar">
                                    @if(!empty($chauffeur->photo_url))
                                        <img src="{{ $chauffeur->photo_url }}" alt="Photo {{ $chauffeurName }}">
                                    @else
                                        <i class="fas fa-user" style="font-size:0.65rem;color:var(--color-primary);"></i>
                                    @endif
                                </div>
                                <div style="line-height:1.3;">
                                    <div style="font-weight:600;font-size:0.8rem;color:var(--color-text);">{{ $chauffeurName ?: 'Chauffeur' }}</div>
                                    <div style="font-size:0.65rem;color:var(--color-secondary-text);">
                                        <i class="fas fa-phone" style="font-size:0.55rem;color:var(--color-primary);margin-right:3px;"></i>
                                        {{ $chauffeur->phone ?? '' }}
                                    </div>
                                </div>
                            </div>
                            @else
                            <span style="font-size:0.72rem;color:var(--color-secondary-text);font-style:italic;">Non assigné</span>
                            @endif
                        </td>

                        {{-- GPS --}}
                        <td>
                            @if($voiture->mac_id_gps)
                            <span class="gps-tag">{{ $voiture->mac_id_gps }}</span>
                            @else
                            <span style="font-size:0.72rem;color:var(--color-secondary-text);">—</span>
                            @endif
                        </td>

                        {{-- Moteur --}}
                        <td>
                            <div style="display:flex;align-items:center;justify-content:center;gap:0.6rem;">
                                {{-- Toggle --}}
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
                                        <i class="fas fa-power-off"></i>
                                    </span>
                                </button>

                                {{-- Badges --}}
                                <div style="display:flex;flex-direction:column;gap:3px;min-width:80px;">
                                    <span class="engine-badge loading" id="engineBadge-{{ $voiture->id }}">
                                        <i class="fas fa-spinner fa-spin" style="font-size:0.5rem;"></i> Chargement
                                    </span>
                                    <span class="gps-badge-status unknown" id="gpsBadge-{{ $voiture->id }}">
                                        GPS: N/A
                                    </span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach

                    @if(empty($voitures) || count($voitures) === 0)
                    <tr>
                        <td colspan="6" style="text-align:center;padding:2.5rem;color:var(--color-secondary-text);font-size:0.82rem;">
                            <i class="fas fa-car-side" style="color:var(--color-primary);font-size:1.2rem;margin-bottom:0.5rem;display:block;"></i>
                            Aucun véhicule trouvé.
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div style="margin-top:0.75rem;font-size:0.7rem;color:var(--color-secondary-text);display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:0.65rem;"></i>
            Cliquez sur le toggle pour couper ou rétablir le moteur. Le statut est confirmé automatiquement via le GPS.
        </div>
    </div>

</div>

{{-- ============================================================
     MODALE CONFIRMATION MOTEUR
     ============================================================ --}}
<div id="engineConfirmModal" class="confirm-overlay" style="display:none;" aria-modal="true" role="alertdialog">
    <div id="engineConfirmPanel" class="confirm-panel">

        {{-- Icône dynamique (cut / restore) --}}
        <div class="confirm-icon-wrap cut" id="confirmIconWrap">
            <i class="fas fa-power-off" id="confirmIconEl"></i>
        </div>

        <h2 class="confirm-title" id="confirmTitle">Confirmation</h2>

        {{-- Infos véhicule --}}
        <div class="vehicle-info-block">
            <div class="vehicle-info-row">
                <i class="fas fa-car" style="color:var(--color-primary);font-size:0.7rem;"></i>
                <strong id="confirmImmat">—</strong>
                <span id="confirmModel" style="font-size:0.7rem;"></span>
            </div>
            <div class="vehicle-info-row" id="confirmDriverRow">
                <i class="fas fa-user" style="color:var(--color-primary);font-size:0.7rem;"></i>
                <span id="confirmDriver">—</span>
            </div>
            <div class="vehicle-info-row" id="confirmPhoneRow" style="display:none;">
                <i class="fas fa-phone" style="color:var(--color-primary);font-size:0.65rem;"></i>
                <span id="confirmPhone"></span>
            </div>
        </div>

        {{-- Action --}}
        <div class="confirm-action-box cut" id="confirmActionBox">
            <i class="fas fa-power-off" id="confirmActionIcon"></i>
            <span id="confirmActionText">Voulez-vous vraiment COUPER le moteur ?</span>
        </div>

        {{-- Hint --}}
        <p class="confirm-hint">
            <i class="fas fa-satellite-dish" style="color:var(--color-primary);margin-top:1px;flex-shrink:0;"></i>
            Cette commande sera envoyée au module GPS. Le statut sera mis à jour automatiquement après confirmation.
        </p>

        {{-- Footer --}}
        <div class="confirm-footer">
            <button type="button" id="cancelEngineBtn" class="btn-secondary">
                Annuler
            </button>
            <button type="button" id="confirmEngineBtn" class="btn-danger" id="confirmEngineBtn">
                <span class="btn-spinner" id="confirmSpinner"></span>
                <i id="confirmBtnIcon" class="fas fa-power-off"></i>
                <span id="confirmBtnLabel">Couper</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    /* ============================================================
       DATATABLES INIT
       ============================================================ */
    let dt = null;
    if (window.jQuery && $.fn.DataTable) {
        dt = $('#engineTable').DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            searching: true,
            info: true,
            dom: '<"flex items-center justify-between flex-wrap gap-2 mb-3"l>' +
                 't' +
                 '<"flex items-center justify-between flex-wrap gap-2 mt-3"ip>',
            language: {
                processing:   'Chargement...',
                lengthMenu:   'Afficher _MENU_ lignes',
                info:         '_START_–_END_ sur _TOTAL_',
                infoEmpty:    '0–0 sur 0',
                infoFiltered: '(filtré parmi _MAX_)',
                zeroRecords:  'Aucun véhicule trouvé',
                emptyTable:   'Aucun véhicule enregistré',
                paginate: { first:'«', previous:'‹', next:'›', last:'»' }
            },
            columnDefs: [
                { orderable: false, targets: 5 } /* colonne Moteur non triable */
            ]
        });

        const searchInput = document.getElementById('engineSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                dt.search(this.value).draw();
            });
        }
    }

    /* ============================================================
       CONFIG
       ============================================================ */
    const CSRF       = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const batchUrl   = @json(route('voitures.engineStatusBatch', [], false));

    const modal      = document.getElementById('engineConfirmModal');
    const panel      = document.getElementById('engineConfirmPanel');
    const cancelBtn  = document.getElementById('cancelEngineBtn');
    const confirmBtn = document.getElementById('confirmEngineBtn');

    /* Éléments dynamiques modale */
    const iconWrap       = document.getElementById('confirmIconWrap');
    const iconEl         = document.getElementById('confirmIconEl');
    const titleEl        = document.getElementById('confirmTitle');
    const immatEl        = document.getElementById('confirmImmat');
    const modelEl        = document.getElementById('confirmModel');
    const driverEl       = document.getElementById('confirmDriver');
    const phoneRow       = document.getElementById('confirmPhoneRow');
    const phoneEl        = document.getElementById('confirmPhone');
    const actionBox      = document.getElementById('confirmActionBox');
    const actionIcon     = document.getElementById('confirmActionIcon');
    const actionText     = document.getElementById('confirmActionText');
    const btnIcon        = document.getElementById('confirmBtnIcon');
    const btnLabel       = document.getElementById('confirmBtnLabel');
    const btnSpinner     = document.getElementById('confirmSpinner');

    const switches = Array.from(document.querySelectorAll('.engine-toggle'));
    const ids      = switches.map(b => b.dataset.id).filter(Boolean);

    let pendingTarget     = null;
    let pendingAction     = null;      // 'cut' | 'restore'
    let pendingExpectedCut= null;

    /* ============================================================
       MODAL OPEN / CLOSE
       ============================================================ */
    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.add('open')));
    }

    function closeModal() {
        panel.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => {
            modal.style.display = 'none';
            pendingTarget      = null;
            pendingAction      = null;
            pendingExpectedCut = null;
        }, 220);
    }

    cancelBtn?.addEventListener('click', closeModal);
    modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
    });

    /* ============================================================
       UI HELPERS
       ============================================================ */
    function setUI(id, payload) {
        const btn         = document.querySelector(`.engine-toggle[data-id="${id}"]`);
        const engineBadge = document.getElementById(`engineBadge-${id}`);
        const gpsBadge    = document.getElementById(`gpsBadge-${id}`);
        if (!btn || !engineBadge || !gpsBadge) return;

        btn.classList.remove('is-loading');

        if (!payload || payload.success === false) {
            btn.classList.remove('is-on', 'is-cut');
            engineBadge.innerHTML = '<i class="fas fa-question-circle" style="font-size:0.5rem;"></i> UNKNOWN';
            engineBadge.className = 'engine-badge loading';
            gpsBadge.textContent  = 'GPS: N/A';
            gpsBadge.className    = 'gps-badge-status unknown';
            updateHeaderCounters();
            return;
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

        if (online === true)  { gpsBadge.textContent = 'GPS: ONLINE';  gpsBadge.className = 'gps-badge-status online'; }
        else if (online === false) { gpsBadge.textContent = 'GPS: OFFLINE'; gpsBadge.className = 'gps-badge-status offline'; }
        else { gpsBadge.textContent = 'GPS: N/A'; gpsBadge.className = 'gps-badge-status unknown'; }

        updateHeaderCounters();
    }

    function updateHeaderCounters() {
        const all    = Array.from(document.querySelectorAll('.engine-toggle'));
        const onEl   = document.getElementById('headerStatOn');
        const cutEl  = document.getElementById('headerStatCut');
        if (!onEl || !cutEl) return;

        let countOn  = 0;
        let countCut = 0;
        all.forEach(btn => {
            if (btn.classList.contains('is-on'))  countOn++;
            if (btn.classList.contains('is-cut')) countCut++;
        });

        onEl.textContent  = countOn;
        cutEl.textContent = countCut;
    }

    function setPending(id, label) {
        const btn         = document.querySelector(`.engine-toggle[data-id="${id}"]`);
        const engineBadge = document.getElementById(`engineBadge-${id}`);
        if (!btn || !engineBadge) return;

        btn.classList.add('is-loading');
        engineBadge.innerHTML = `<span class="btn-spinner" style="display:inline-block;width:10px;height:10px;border:2px solid rgba(245,130,32,0.3);border-top-color:var(--color-primary);border-radius:50%;animation:spin 0.6s linear infinite;"></span> ${label}`;
        engineBadge.className = 'engine-badge pending';
    }

    /* ============================================================
       FETCH HELPERS
       ============================================================ */
    const fetchJson = async (url, opt = {}, ms = 12000) => {
        const ctrl = new AbortController();
        const t    = setTimeout(() => ctrl.abort(), ms);
        try {
            const res  = await fetch(url, { ...opt, signal: ctrl.signal });
            const json = await res.json().catch(() => null);
            return { ok: res.ok, status: res.status, json };
        } finally { clearTimeout(t); }
    };

    const pollConfirm = async (statusUrl, expectedCut, tries = 10, intervalMs = 900) => {
        for (let i = 0; i < tries; i++) {
            await new Promise(r => setTimeout(r, intervalMs));
            const r = await fetchJson(`${statusUrl}?_t=${Date.now()}`, {
                cache: 'no-store', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!r.ok || !r.json?.success) continue;
            if (!!r.json.engine?.cut === expectedCut) return { confirmed: true, json: r.json };
        }
        return { confirmed: false, json: null };
    };

    /* ============================================================
       BATCH LOAD STATUTS
       ============================================================ */
    fetchJson(`${batchUrl}?ids=${encodeURIComponent(ids.join(','))}&_t=${Date.now()}`, {
        cache: 'no-store', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }, 15000)
    .then(({ ok, json }) => {
        if (!ok || !json) throw new Error('batch failed');
        ids.forEach(id => setUI(id, json.data?.[id] ?? { success: false }));
    })
    .catch(() => ids.forEach(id => setUI(id, { success: false })));

    /* ============================================================
       OUVRIR MODALE AU CLIC SUR TOGGLE
       ============================================================ */
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

            /* Icon & couleurs */
            iconWrap.className  = 'confirm-icon-wrap ' + (isCut ? 'cut' : 'restore');
            iconEl.className    = isCut ? 'fas fa-power-off' : 'fas fa-rotate-right';
            titleEl.textContent = isCut ? 'Couper le moteur' : 'Rétablir le moteur';

            immatEl.textContent = immat;
            modelEl.textContent = marque + (model ? ' ' + model : '');
            driverEl.textContent= chauff || 'Non assigné';

            if (phone) {
                phoneEl.textContent     = phone;
                phoneRow.style.display  = 'flex';
            } else {
                phoneRow.style.display  = 'none';
            }

            actionBox.className  = 'confirm-action-box ' + (isCut ? 'cut' : 'restore');
            actionIcon.className = isCut ? 'fas fa-ban' : 'fas fa-check-circle';
            actionText.textContent = isCut
                ? 'Voulez-vous vraiment COUPER le moteur de ce véhicule ?'
                : 'Voulez-vous vraiment RÉTABLIR le moteur de ce véhicule ?';

            /* Bouton confirmer */
            confirmBtn.className  = isCut ? 'btn-danger' : 'btn-success';
            btnIcon.className     = isCut ? 'fas fa-power-off' : 'fas fa-rotate-right';
            btnLabel.textContent  = isCut ? 'Couper' : 'Allumer';
            btnSpinner.style.display = 'none';
            confirmBtn.disabled   = false;

            openModal();
        });
    });

    /* ============================================================
       CONFIRMER ACTION
       ============================================================ */
    confirmBtn?.addEventListener('click', async () => {
        if (!pendingTarget) return;

        /* Capture avant closeModal */
        const btn          = pendingTarget;
        const id           = btn.dataset.id;
        const toggleUrl    = btn.dataset.toggleUrl;
        const statusUrl    = btn.dataset.statusUrl;
        const action       = pendingAction;
        const expectedCut  = !!pendingExpectedCut;
        const pendingLabel = expectedCut ? 'Coupure…' : 'Allumage…';

        /* État loading sur le bouton */
        confirmBtn.disabled = true;
        btnSpinner.style.display = 'inline-block';

        closeModal();
        setPending(id, pendingLabel);

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
                window.showToastMsg?.('Erreur commande', data?.message || data?.return_msg || 'Échec de la commande moteur.', 'error');
                setUI(id, { success: false });
                return;
            }

            /* UI optimiste immédiate */
            setUI(id, { success: true, engine: { cut: expectedCut }, gps: { online: null } });
            window.showToastMsg?.(
                'Commande envoyée',
                (data.message || 'Commande en cours…') + (data.cmd_no ? ` · CmdNo: ${data.cmd_no}` : ''),
                'success'
            );

            /* Sondage confirmation GPS */
            const p = await pollConfirm(statusUrl, expectedCut, 10, 900);
            if (p.confirmed && p.json) {
                setUI(id, p.json);
                window.showToastMsg?.(
                    'Confirmé',
                    expectedCut ? 'Moteur coupé — confirmé par le GPS.' : 'Moteur rétabli — confirmé par le GPS.',
                    'success'
                );
            } else {
                /* Statut final fallback */
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

});
</script>
@endpush

@endsection