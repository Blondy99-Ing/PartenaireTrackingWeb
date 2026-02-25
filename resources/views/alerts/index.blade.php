{{-- resources/views/alerts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des Alertes')

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
table.dataTable thead th,
table.dataTable thead td {
    background-color: var(--color-border-subtle) !important;
    color: var(--color-text) !important;
    border-bottom: 2px solid var(--color-primary) !important;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    padding: 0.65rem 1rem !important;
    white-space: nowrap;
    user-select: none;
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
    padding: 0.6rem 1rem !important;
    color: var(--color-text) !important;
    border: none !important;
    vertical-align: middle;
}
table.dataTable {
    border-collapse: collapse !important;
    margin: 0 !important;
    width: 100% !important;
    border: none !important;
}
.dataTables_wrapper .dataTables_paginate {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding-top: 0.5rem;
    justify-content: flex-end;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid var(--color-border-subtle) !important;
    background: var(--color-card) !important;
    color: var(--color-text) !important;
    font-size: 0.78rem;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
    box-shadow: none !important;
    background-image: none !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--color-sidebar-active-bg) !important;
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
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: 0.35; pointer-events: none; }
.dataTables_wrapper .dataTables_info { color: var(--color-secondary-text); font-size: 0.75rem; }

/* ============================================================
   PAGE — ALERTES
   ============================================================ */

/* ---- Cartes KPI ---- */
.alert-stat-card {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.75rem;
    padding: 1rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.15s;
}
.alert-stat-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
/* Barre colorée à gauche */
.alert-stat-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: 0.75rem 0 0 0.75rem;
}
.alert-stat-card.red::before    { background: #ef4444; }
.alert-stat-card.orange::before { background: var(--color-primary); }
.alert-stat-card.blue::before   { background: #3b82f6; }
.alert-stat-card.purple::before { background: #a855f7; }
.alert-stat-card.gray::before   { background: #6b7280; }

.stat-label {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    margin: 0 0 0.2rem;
}
.stat-value {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
    margin: 0;
}
.stat-value.red    { color: #ef4444; }
.stat-value.orange { color: var(--color-primary); }
.stat-value.blue   { color: #3b82f6; }
.stat-value.purple { color: #a855f7; }
.stat-value.gray   { color: #6b7280; }

.stat-icon {
    font-size: 1.4rem;
    opacity: 0.55;
    flex-shrink: 0;
}
.stat-icon.red    { color: #ef4444; }
.stat-icon.orange { color: var(--color-primary); }
.stat-icon.blue   { color: #3b82f6; }
.stat-icon.purple { color: #a855f7; }
.stat-icon.gray   { color: #6b7280; }

/* ---- Toolbar filtres ---- */
.filters-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-bottom: 0.875rem;
    border-bottom: 1px solid var(--color-border-subtle);
    margin-bottom: 0.875rem;
}

/* ---- Badges type alerte ---- */
.alert-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 3px 9px;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 700;
    white-space: nowrap;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    letter-spacing: 0.03em;
}
.alert-badge.geofence  { background:rgba(245,130,32,0.15); color:var(--color-primary); border:1px solid rgba(245,130,32,0.3); }
.alert-badge.speed     { background:rgba(59,130,246,0.12); color:#2563eb;              border:1px solid rgba(59,130,246,0.3); }
.alert-badge.safe_zone { background:rgba(168,85,247,0.12); color:#9333ea;              border:1px solid rgba(168,85,247,0.3); }
.alert-badge.time_zone { background:rgba(107,114,128,0.12); color:#6b7280;             border:1px solid rgba(107,114,128,0.3); }
.alert-badge.stolen    { background:rgba(239,68,68,0.12);  color:#dc2626;              border:1px solid rgba(239,68,68,0.3); }

/* Immat badge inline */
.immat-badge {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.3rem;
    padding: 2px 6px;
    display: inline-block;
    white-space: nowrap;
}

/* Timestamp chip */
.time-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    white-space: nowrap;
}

/* ============================================================
   TOASTS — DESIGN SYSTEM (haut droite, sous la navbar)
   ============================================================ */
.toast-stack {
    position: fixed;
    right: 1rem;
    top: calc(var(--navbar-h, 4.5rem) + 0.75rem);
    z-index: var(--z-toast, 9999);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: min(380px, calc(100vw - 2rem));
    pointer-events: none;
}

.alert-toast {
    pointer-events: auto;
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.875rem;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    transform: translateX(12px) scale(0.97);
    opacity: 0;
    transition: transform 0.2s ease, opacity 0.2s ease;
    position: relative;
}
.alert-toast.show {
    transform: translateX(0) scale(1);
    opacity: 1;
}
/* Barre latérale colorée */
.alert-toast::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: 0.875rem 0 0 0.875rem;
}
.alert-toast.geofence::before  { background: var(--color-primary); }
.alert-toast.speed::before     { background: #3b82f6; }
.alert-toast.safe_zone::before { background: #a855f7; }
.alert-toast.time_zone::before { background: #6b7280; }
.alert-toast.stolen::before    { background: #ef4444; }

.toast-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.6rem;
    padding: 0.75rem 0.75rem 0.4rem 0.875rem;
}

.toast-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.85rem;
    color: #fff;
}
.toast-icon-wrap.geofence  { background: var(--color-primary); }
.toast-icon-wrap.speed     { background: #3b82f6; }
.toast-icon-wrap.safe_zone { background: #a855f7; }
.toast-icon-wrap.time_zone { background: #6b7280; }
.toast-icon-wrap.stolen    { background: #ef4444; }

.toast-heading {
    flex: 1;
    min-width: 0;
}
.toast-type {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    line-height: 1.2;
}
.toast-vehicle {
    font-size: 0.7rem;
    color: var(--color-secondary-text);
    margin: 2px 0 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.toast-dismiss {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    color: var(--color-secondary-text);
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: color 0.15s, background-color 0.15s;
    line-height: 1;
}
.toast-dismiss:hover { color: #ef4444; background: rgba(239,68,68,0.1); }

.toast-message {
    padding: 0 0.875rem 0.6rem;
    font-size: 0.75rem;
    color: var(--color-secondary-text);
    line-height: 1.4;
}

.toast-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.4rem;
    padding: 0.5rem 0.75rem 0.625rem;
    background: var(--color-bg);
    border-top: 1px solid var(--color-border-subtle);
}

.toast-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 5px 10px;
    border-radius: 0.4rem;
    font-size: 0.68rem;
    font-weight: 700;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    cursor: pointer;
    border: 1px solid transparent;
    transition: background-color 0.15s, color 0.15s;
}
.toast-action-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.toast-btn-read {
    background: rgba(59,130,246,0.1);
    color: #2563eb;
    border-color: rgba(59,130,246,0.25);
}
.toast-btn-read:hover:not(:disabled) { background: #2563eb; color: #fff; }

.toast-btn-ignore {
    background: var(--color-border-subtle);
    color: var(--color-secondary-text);
    border-color: var(--color-border-subtle);
}
.toast-btn-ignore:hover:not(:disabled) { background: var(--color-secondary-text); color: #fff; }

/* Barre de progression */
.toast-progress {
    height: 2px;
    background: linear-gradient(90deg, var(--color-primary), transparent);
    transform-origin: left;
    transform: scaleX(1);
}
.alert-toast.geofence  .toast-progress { background: linear-gradient(90deg, var(--color-primary), rgba(245,130,32,0.2)); }
.alert-toast.speed     .toast-progress { background: linear-gradient(90deg, #3b82f6, rgba(59,130,246,0.2)); }
.alert-toast.safe_zone .toast-progress { background: linear-gradient(90deg, #a855f7, rgba(168,85,247,0.2)); }
.alert-toast.time_zone .toast-progress { background: linear-gradient(90deg, #6b7280, rgba(107,114,128,0.2)); }
.alert-toast.stolen    .toast-progress { background: linear-gradient(90deg, #ef4444, rgba(239,68,68,0.2)); }
</style>
@endpush

@section('content')
<div class="space-y-5">

    {{-- ============================================================
         KPI STATS — 5 cartes
         ============================================================ --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;">

        <div class="alert-stat-card red">
            <div>
                <p class="stat-label">Vol</p>
                <p class="stat-value red" id="stat-stolen">0</p>
            </div>
            <span class="stat-icon red"><i class="fas fa-car-crash"></i></span>
        </div>

        <div class="alert-stat-card orange">
            <div>
                <p class="stat-label">Geofence</p>
                <p class="stat-value orange" id="stat-geofence">0</p>
            </div>
            <span class="stat-icon orange"><i class="fas fa-route"></i></span>
        </div>

        <div class="alert-stat-card blue">
            <div>
                <p class="stat-label">Vitesse</p>
                <p class="stat-value blue" id="stat-speed">0</p>
            </div>
            <span class="stat-icon blue"><i class="fas fa-tachometer-alt"></i></span>
        </div>

        <div class="alert-stat-card purple">
            <div>
                <p class="stat-label">Safe Zone</p>
                <p class="stat-value purple" id="stat-safezone">0</p>
            </div>
            <span class="stat-icon purple"><i class="fas fa-shield-alt"></i></span>
        </div>

        <div class="alert-stat-card gray">
            <div>
                <p class="stat-label">Time Zone</p>
                <p class="stat-value gray" id="stat-timezone">0</p>
            </div>
            <span class="stat-icon gray"><i class="fas fa-clock"></i></span>
        </div>

    </div>

    {{-- ============================================================
         TABLEAU DES ALERTES
         ============================================================ --}}
    <div class="ui-card" style="padding:1.25rem;">

        {{-- Toolbar --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <h2 class="font-orbitron" style="font-size:0.9rem;font-weight:700;color:var(--color-text);margin:0;">
                    Incidents détectés
                </h2>
                <span style="display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.6rem;border-radius:9999px;background:var(--color-sidebar-active-bg);border:1px solid rgba(245,130,32,0.25);color:var(--color-primary);font-family:var(--font-display,'Orbitron',sans-serif);font-size:0.62rem;font-weight:700;">
                    <i class="fas fa-sync-alt" id="refreshIcon" style="font-size:0.55rem;"></i>
                    <span id="lastRefresh">—</span>
                </span>
            </div>

            <button id="refreshBtn" class="btn-secondary" style="font-size:0.75rem;">
                <i class="fas fa-sync-alt"></i> Rafraîchir
            </button>
        </div>

        {{-- Filtres --}}
        <div class="filters-bar">
            {{-- Recherche --}}
            <div style="position:relative;flex:1;min-width:200px;max-width:280px;">
                <i class="fas fa-search" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:0.72rem;pointer-events:none;"></i>
                <input id="alertSearch"
                       type="text"
                       class="ui-input-style"
                       style="padding-left:2rem;font-size:0.78rem;"
                       placeholder="Véhicule, lieu, chauffeur...">
            </div>

            {{-- Filtre type --}}
            <select id="alertTypeFilter" class="ui-input-style" style="width:auto;min-width:140px;font-size:0.78rem;">
                <option value="all">Tous les types</option>
                <option value="geofence">GeoFence</option>
                <option value="speed">Vitesse</option>
                <option value="safe_zone">Safe Zone</option>
                <option value="time_zone">Time Zone</option>
                <option value="stolen">Vol</option>
            </select>

            {{-- Filtre véhicule --}}
            <select id="vehicleFilter" class="ui-input-style" style="width:auto;min-width:150px;font-size:0.78rem;">
                <option value="all">Tous les véhicules</option>
            </select>

            {{-- Filtre utilisateur --}}
            <select id="userFilter" class="ui-input-style" style="width:auto;min-width:150px;font-size:0.78rem;">
                <option value="all">Tous les utilisateurs</option>
            </select>

            <button id="filterBtn" class="btn-primary" style="font-size:0.75rem;white-space:nowrap;">
                <i class="fas fa-filter"></i> Filtrer
            </button>
        </div>

        {{-- Tableau --}}
        <div class="ui-table-container">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Véhicule</th>
                        <th>Chauffeur</th>
                        <th>Déclenchée le</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody id="alerts-tbody">
                    <tr>
                        <td colspan="5" style="text-align:center;padding:2.5rem;color:var(--color-secondary-text);font-size:0.82rem;">
                            <i class="fas fa-spinner fa-spin" style="color:var(--color-primary);margin-right:6px;"></i>
                            Chargement des alertes...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div style="margin-top:0.75rem;font-size:0.7rem;color:var(--color-secondary-text);display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:0.65rem;"></i>
            Actualisation automatique toutes les 30 secondes. Les nouvelles alertes apparaissent en notification.
        </div>
    </div>

</div>

{{-- Toast stack (haut droite, sous navbar) --}}
<div id="toast-stack" class="toast-stack" aria-live="polite" aria-atomic="false"></div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ============================================================
       CONFIG
       ============================================================ */
    const API_INDEX    = "{{ route('alerts.index') }}";
    const API_POLL     = "{{ url('/alerts/poll') }}";
    const API_MARK_READ= "{{ url('/alerts') }}";
    const CSRF         = "{{ csrf_token() }}";

    const POLL_MS       = 30000;
    const TOAST_TTL_MS  = 12000;
    const TOAST_MAX     = 5;

    const ALLOWED_TYPES = new Set(['geofence', 'safe_zone', 'speed', 'time_zone', 'stolen']);

    const TYPE_CONFIG = {
        geofence:   { icon: 'fas fa-route',          label: 'GeoFence',  cls: 'geofence'  },
        safe_zone:  { icon: 'fas fa-shield-alt',      label: 'Safe Zone', cls: 'safe_zone' },
        speed:      { icon: 'fas fa-tachometer-alt',  label: 'Vitesse',   cls: 'speed'     },
        time_zone:  { icon: 'fas fa-clock',           label: 'Time Zone', cls: 'time_zone' },
        stolen:     { icon: 'fas fa-car-crash',       label: 'Vol',       cls: 'stolen'    },
    };

    let alerts        = [];
    let pollTimer     = null;
    let lastSeenId    = 0;
    const shownIds    = new Set();

    /* ============================================================
       HELPERS
       ============================================================ */
    const esc = str => String(str ?? '').replace(/[&<>"']/g, m =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));

    const normalizeType = a => a?.type ?? a?.alert_type ?? 'general';
    const isAllowed     = a => ALLOWED_TYPES.has(normalizeType(a));
    const isOpen        = a => { const p = a?.processed; return p === false || p === 0 || p === null || p === undefined; };

    function parseHumanDate(fr) {
        const m = String(fr ?? '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/);
        if (!m) return null;
        const d = new Date(+m[3], +m[2]-1, +m[1], +m[4], +m[5], +m[6]);
        return isNaN(d) ? null : d;
    }

    function isToday(a) {
        let d = a?.alerted_at ? new Date(a.alerted_at) : null;
        if (!d || isNaN(d)) d = parseHumanDate(a?.alerted_at_human);
        if (!d) return false;
        const n = new Date();
        return d.getFullYear()===n.getFullYear() && d.getMonth()===n.getMonth() && d.getDate()===n.getDate();
    }

    function vehicleLabel(a) {
        if (a?.voiture) {
            const imm = (a.voiture.immatriculation||'').trim();
            const marque = (a.voiture.marque||'').trim();
            if (imm && marque) return `${imm} (${marque})`;
            return imm || marque;
        }
        return a?.voiture_id ? `Véh. #${a.voiture_id}` : '—';
    }

    function vehicleImmat(a) {
        return a?.voiture?.immatriculation || (a?.voiture_id ? `#${a.voiture_id}` : '—');
    }

    function userLabel(a) {
        const l = (a?.driver_label ?? a?.users_labels ?? '').trim();
        return l || (a?.user_id ? `Utilisateur #${a.user_id}` : 'Aucun chauffeur');
    }

    /* ============================================================
       STATS
       ============================================================ */
    function updateStats(data) {
        const base = (Array.isArray(data) ? data : []).filter(isAllowed).filter(isOpen).filter(isToday);
        document.getElementById('stat-stolen').textContent   = base.filter(a => normalizeType(a)==='stolen').length;
        document.getElementById('stat-geofence').textContent = base.filter(a => normalizeType(a)==='geofence').length;
        document.getElementById('stat-speed').textContent    = base.filter(a => normalizeType(a)==='speed').length;
        document.getElementById('stat-safezone').textContent = base.filter(a => normalizeType(a)==='safe_zone').length;
        document.getElementById('stat-timezone').textContent = base.filter(a => normalizeType(a)==='time_zone').length;

        const now = new Date();
        document.getElementById('lastRefresh').textContent =
            now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR', { hour:'2-digit', minute:'2-digit' });
    }

    /* ============================================================
       RENDU TABLEAU
       ============================================================ */
    function renderAlerts(rows) {
        const tbody = document.getElementById('alerts-tbody');
        const only  = (Array.isArray(rows) ? rows : []).filter(isAllowed);
        tbody.innerHTML = '';

        if (!only.length) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2.5rem;color:var(--color-secondary-text);font-size:0.82rem;">
                <i class="fas fa-bell-slash" style="color:var(--color-primary);margin-right:6px;font-size:1rem;"></i>
                Aucune alerte trouvée.
            </td></tr>`;
            updateStats(alerts);
            return;
        }

        only.forEach(a => {
            const t      = normalizeType(a);
            const cfg    = TYPE_CONFIG[t] ?? { icon:'fas fa-bell', label: t, cls: 'gray' };
            const immat  = vehicleImmat(a);
            const vFull  = vehicleLabel(a);
            const uLabel = userLabel(a);
            const when   = a.alerted_at_human ?? '—';
            const msg    = a.message ?? a.location ?? '—';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <span class="alert-badge ${cfg.cls}">
                        <i class="${cfg.icon}"></i> ${esc(a.type_label ?? cfg.label)}
                    </span>
                </td>
                <td>
                    <div>
                        <span class="immat-badge">${esc(immat)}</span>
                    </div>
                    <div style="font-size:0.67rem;color:var(--color-secondary-text);margin-top:2px;">
                        ${esc(a.voiture?.marque ?? '')} ${esc(a.voiture?.model ?? '')}
                    </div>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <div style="width:22px;height:22px;border-radius:50%;background:var(--color-sidebar-active-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-user" style="font-size:0.55rem;color:var(--color-primary);"></i>
                        </div>
                        <span style="font-size:0.78rem;color:var(--color-text);">${esc(uLabel)}</span>
                    </div>
                </td>
                <td>
                    <span class="time-chip">
                        <i class="fas fa-clock" style="color:var(--color-primary);font-size:0.55rem;"></i>
                        ${esc(when)}
                    </span>
                </td>
                <td>
                    <span style="font-size:0.75rem;color:var(--color-secondary-text);max-width:240px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(msg)}">
                        ${esc(msg)}
                    </span>
                </td>
            `;
            tbody.appendChild(tr);
        });

        updateStats(alerts);
    }

    /* ============================================================
       FILTRES DYNAMIQUES
       ============================================================ */
    function buildFilters(data) {
        const vehicleSel = document.getElementById('vehicleFilter');
        const userSel    = document.getElementById('userFilter');

        const vehicles = new Map();
        const users    = new Map();

        (Array.isArray(data) ? data : []).filter(isAllowed).forEach(a => {
            if (a?.voiture_id) vehicles.set(String(a.voiture_id), vehicleLabel(a));
            if (a?.user_id)    users.set(String(a.user_id), userLabel(a));
        });

        const keepV = vehicleSel.value;
        vehicleSel.innerHTML = '<option value="all">Tous les véhicules</option>';
        [...vehicles.entries()].sort((a,b) => a[1].localeCompare(b[1])).forEach(([id,lbl]) => {
            const o = document.createElement('option');
            o.value = id; o.textContent = lbl;
            vehicleSel.appendChild(o);
        });
        vehicleSel.value = keepV;

        const keepU = userSel.value;
        userSel.innerHTML = '<option value="all">Tous les utilisateurs</option>';
        [...users.entries()].sort((a,b) => a[1].localeCompare(b[1])).forEach(([id,lbl]) => {
            const o = document.createElement('option');
            o.value = id; o.textContent = lbl;
            userSel.appendChild(o);
        });
        userSel.value = keepU;
    }

    function applyFilters() {
        const q  = (document.getElementById('alertSearch').value || '').toLowerCase().trim();
        const t  = document.getElementById('alertTypeFilter').value;
        const v  = document.getElementById('vehicleFilter').value;
        const u  = document.getElementById('userFilter').value;

        let rows = alerts.slice().filter(isAllowed);
        if (t !== 'all') rows = rows.filter(a => normalizeType(a) === t);
        if (v !== 'all') rows = rows.filter(a => String(a.voiture_id ?? '') === v);
        if (u !== 'all') rows = rows.filter(a => String(a.user_id ?? '') === u);
        if (q) rows = rows.filter(a =>
            vehicleLabel(a).toLowerCase().includes(q) ||
            userLabel(a).toLowerCase().includes(q) ||
            String(a.message ?? a.location ?? '').toLowerCase().includes(q)
        );

        renderAlerts(rows);
    }

    /* ============================================================
       API
       ============================================================ */
    async function fetchAlerts() {
        try {
            const res  = await fetch(API_INDEX + '?ts=' + Date.now(), {
                headers: { Accept:'application/json', 'Cache-Control':'no-cache' },
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!res.ok || json.status !== 'success') return [];

            const data = Array.isArray(json.data) ? json.data : [];
            const mx   = data.reduce((m,a) => Math.max(m, +a?.id||0), 0);
            if (mx > lastSeenId) lastSeenId = mx;
            return data;
        } catch { return []; }
    }

    async function pollNew() {
        if (document.visibilityState !== 'visible') return;
        try {
            const res  = await fetch(`${API_POLL}?after_id=${lastSeenId}&ts=${Date.now()}`, {
                headers: { Accept:'application/json', 'Cache-Control':'no-cache' },
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!res.ok || json.status !== 'success') return;

            const data = Array.isArray(json.data) ? json.data : [];
            const mx   = +(json?.meta?.max_id || 0);
            if (mx > lastSeenId) lastSeenId = mx;
            if (!data.length) return;

            data.forEach(showToast);
            alerts = [...data, ...alerts].slice(0, 2000);
            buildFilters(alerts);
            applyFilters();
        } catch {}
    }

    async function markRead(alertId) {
        const res  = await fetch(`${API_MARK_READ}/${alertId}/read`, {
            method: 'PATCH',
            headers: { Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({}),
            credentials: 'same-origin'
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json?.ok) throw new Error(json?.message || 'Erreur');
        return json;
    }

    /* ============================================================
       TOASTS
       ============================================================ */
    function showToast(a) {
        if (!a?.id || !isAllowed(a)) return;
        const id = +a.id;
        if (shownIds.has(id)) return;
        shownIds.add(id);

        const t   = normalizeType(a);
        const cfg = TYPE_CONFIG[t] ?? { icon:'fas fa-bell', label: t, cls: 'gray' };
        const stack = document.getElementById('toast-stack');

        /* Limite de stack */
        while (stack.children.length >= TOAST_MAX) stack.removeChild(stack.lastElementChild);

        const el = document.createElement('div');
        el.className = `alert-toast ${cfg.cls}`;
        el.dataset.alertId = String(id);

        const vLabel = vehicleLabel(a);
        const uLabel = userLabel(a);
        const when   = a.alerted_at_human ?? '';
        const msg    = (a.message ?? a.location ?? '').trim();

        el.innerHTML = `
            <div class="toast-progress" id="toast-prog-${id}"></div>
            <div class="toast-header">
                <div class="toast-icon-wrap ${cfg.cls}">
                    <i class="${cfg.icon}"></i>
                </div>
                <div class="toast-heading">
                    <p class="toast-type">${esc(a.type_label ?? cfg.label)}</p>
                    <p class="toast-vehicle">
                        <strong>${esc(vehicleImmat(a))}</strong>
                        ${when ? ' · ' + esc(when) : ''}
                    </p>
                </div>
                <button class="toast-dismiss" aria-label="Fermer">&times;</button>
            </div>
            <div class="toast-message">
                <span>${esc(uLabel)}</span>
                ${msg ? `<br><span style="margin-top:2px;display:block;">${esc(msg)}</span>` : ''}
            </div>
            <div class="toast-footer">
                <button type="button" class="toast-action-btn toast-btn-read">
                    <i class="fas fa-eye"></i> Lu
                </button>
                <button type="button" class="toast-action-btn toast-btn-ignore">
                    <i class="fas fa-eye-slash"></i> Ignorer
                </button>
            </div>
        `;

        stack.insertBefore(el, stack.firstChild);
        requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));

        /* Progress bar */
        const prog = document.getElementById(`toast-prog-${id}`);
        if (prog) {
            prog.style.transition = `transform ${TOAST_TTL_MS}ms linear`;
            requestAnimationFrame(() => { prog.style.transform = 'scaleX(0)'; });
        }

        const dismiss = () => {
            el.classList.remove('show');
            setTimeout(() => el.parentNode?.removeChild(el), 200);
        };

        const setBusy = busy => el.querySelectorAll('.toast-action-btn').forEach(b => b.disabled = busy);

        const autoTimer = setTimeout(dismiss, TOAST_TTL_MS);
        el.addEventListener('mouseenter', () => clearTimeout(autoTimer), { once: true });

        el.querySelector('.toast-dismiss').addEventListener('click', () => { clearTimeout(autoTimer); dismiss(); });

        el.querySelector('.toast-btn-read').addEventListener('click', async () => {
            setBusy(true);
            try {
                await markRead(id);
                alerts = alerts.map(x => +x.id === id ? { ...x, processed: true } : x);
                applyFilters();
                clearTimeout(autoTimer);
                dismiss();
            } catch (e) {
                setBusy(false);
                window.showToastMsg?.('Erreur', String(e.message || e), 'error');
            }
        });

        el.querySelector('.toast-btn-ignore').addEventListener('click', async () => {
            setBusy(true);
            try {
                await markRead(id);
                alerts = alerts.map(x => +x.id === id ? { ...x, processed: true } : x);
                applyFilters();
                clearTimeout(autoTimer);
                dismiss();
            } catch (e) {
                setBusy(false);
                window.showToastMsg?.('Erreur', String(e.message || e), 'error');
            }
        });
    }

    /* ============================================================
       POLLING
       ============================================================ */
    function startPolling() { stopPolling(); pollTimer = setInterval(pollNew, POLL_MS); }
    function stopPolling()  { clearTimeout(pollTimer); pollTimer = null; }

    document.addEventListener('visibilitychange', () => {
        document.visibilityState === 'visible' ? startPolling() : stopPolling();
    });

    /* ============================================================
       EVENTS UI
       ============================================================ */
    document.getElementById('filterBtn').addEventListener('click', applyFilters);
    document.getElementById('refreshBtn').addEventListener('click', async () => {
        const icon = document.getElementById('refreshIcon');
        icon.classList.add('fa-spin');
        alerts = await fetchAlerts();
        buildFilters(alerts);
        applyFilters();
        icon.classList.remove('fa-spin');
    });
    document.getElementById('alertSearch').addEventListener('input', applyFilters);
    document.getElementById('alertTypeFilter').addEventListener('change', applyFilters);
    document.getElementById('vehicleFilter').addEventListener('change', applyFilters);
    document.getElementById('userFilter').addEventListener('change', applyFilters);

    /* ============================================================
       INIT
       ============================================================ */
    (async () => {
        alerts = await fetchAlerts();
        buildFilters(alerts);
        applyFilters();
        startPolling();
    })();
});
</script>
@endpush