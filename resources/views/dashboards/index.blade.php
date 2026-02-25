@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@push('styles')
<style>
/* ============================================================
   DASHBOARD — STICKY KPI BAR
   - Desktop (≥1024px)  : sticky, fond opaque = var(--color-bg)
   - Tablette / Mobile  : static (flux normal, scrollable)
   ============================================================ */

/* Wrapper qui englobe TOUT le contenu scrollable.
   Il reçoit un padding-top égal à la hauteur de la barre sticky
   pour que rien ne passe "sous" elle. */
.dashboard-scroll-area {
    /* Pas de padding ici — on gère via le spacer ci-dessous */
}

/* La barre sticky elle-même */
.kpi-sticky-bar {
    /* Desktop : sticky sous la navbar */
    position: sticky;
    top: var(--navbar-h, 4.5rem);
    z-index: var(--z-kpi, 10);

    /* CRITIQUE : fond exactement identique au background du dashboard
       pour masquer les éléments qui défilent en dessous */
    background-color: var(--color-bg);

    /* Padding vertical pour donner de l'air et bien couvrir */
    padding-top: 0.5rem;
    padding-bottom: 0.625rem;

    /* Ombre discrète vers le bas pour séparer visuellement */
    box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.08);
}

/* Petits écrans (tablette < 1024px et mobile) :
   La barre redevient un élément normal dans le flux */
@media (max-width: 1023px) {
    .kpi-sticky-bar {
        position: static;
        background-color: transparent;
        padding-top: 0;
        padding-bottom: 0;
        box-shadow: none;
    }
}

/* ---- KPI Cards ---- */
.kpi-card {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    transition: box-shadow 0.2s;
}

.kpi-card:hover {
    box-shadow: 0 4px 16px rgba(245, 130, 32, 0.12);
}

.kpi-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--color-secondary-text);
    font-family: var(--font-display, 'Orbitron', sans-serif);
}

.kpi-value {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 0.2rem;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    color: var(--color-primary);
}

.kpi-icon {
    font-size: 1.1rem;
    color: var(--color-primary);
    opacity: 0.65;
    flex-shrink: 0;
}

/* ---- Alert type mini-cards ---- */
.alert-type-card {
    border-radius: 0.5rem;
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    padding: 0.4rem 0.6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.alert-type-label {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-secondary-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.alert-type-value {
    font-size: 1.1rem;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 0.1rem;
    color: var(--color-text);
}

/* ---- Alertes section header ---- */
.kpi-alerts-header {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--color-text);
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* ============================================================
   CONTENU SCROLLABLE SOUS LA BARRE
   ============================================================ */
.dashboard-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ---- Fleet list ---- */
#vehicleList {
    max-height: 580px;
    overflow-y: auto;
    padding-right: 2px;
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-subtle) transparent;
}

#vehicleList::-webkit-scrollbar { width: 4px; }
#vehicleList::-webkit-scrollbar-thumb { background: var(--color-border-subtle); border-radius: 4px; }

/* ---- Map container ---- */
#fleetMap {
    height: 640px;
    border-radius: 0.75rem;
    border: 1px solid var(--color-border-subtle);
}

@media (max-width: 767px) {
    #fleetMap { height: 350px; }
}
















/* ==========================================
   FULL HEIGHT sous la barre KPI (desktop)
   Sans modifier le HTML
   ========================================== */

/* Valeurs fallback si JS n'a pas encore tourné */
:root{
  --navbar-h: 5rem;  /* tu l'as déjà en var */
  --kpi-h: 140px;    /* fallback */
  --dash-gap: 16px;  /* espace entre KPI et contenu */
  --dash-pad-bottom: 16px;
}

/* Desktop: on calcule la hauteur disponible sous KPI */
@media (min-width: 1024px) {
  /* La zone "Liste + Carte" (grid lg-cols-4) */
  .dashboard-content > .grid.grid-cols-1.lg\:grid-cols-4 {
    /* Hauteur totale visible sous la barre KPI */
    height: calc(100vh - var(--navbar-h) - var(--kpi-h) - var(--dash-gap) - var(--dash-pad-bottom));
    align-items: stretch; /* les 2 colonnes prennent la même hauteur */
  }

  /* La card gauche + la card droite remplissent la hauteur du grid */
  .dashboard-content > .grid.grid-cols-1.lg\:grid-cols-4 > .lg\:col-span-1 > .ui-card,
  .dashboard-content > .grid.grid-cols-1.lg\:grid-cols-4 > .lg\:col-span-3 > .ui-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    min-height: 0; /* IMPORTANT pour que les enfants puissent scroller */
  }

  /* La liste à gauche devient scrollable et remplit le restant */
  #vehicleList {
    flex: 1 1 auto;
    min-height: 0;             /* CRITIQUE en flex */
    overflow-y: auto;
    max-height: none !important; /* annule ton max-height fixe */
  }

  /* La carte remplit tout l'espace restant dans sa card */
  #fleetMap {
    flex: 1 1 auto;
    min-height: 0;
    height: auto !important;  /* on laisse flex gérer */
  }
}

/* Tablette/Mobile: on garde un comportement naturel (scroll global) */
@media (max-width: 1023px) {
  .dashboard-content > .grid.grid-cols-1.lg\:grid-cols-4 {
    height: auto;
  }
  #fleetMap { height: 350px; }
}
</style>
@endpush

@section('content')
@php
    $types = [
        'stolen'    => ['Vol',       'fa-mask',          'bg-red-100 text-red-700'],
        'geofence'  => ['Geofence',  'fa-draw-polygon',  'bg-orange-100 text-orange-700'],
        'safe_zone' => ['Safe Zone', 'fa-shield-halved', 'bg-blue-100 text-blue-700'],
        'speed'     => ['Vitesse',   'fa-gauge-high',    'bg-yellow-100 text-yellow-800'],
        'time_zone' => ['Time Zone', 'fa-calendar-alt',  'bg-indigo-100 text-indigo-700'],
    ];
@endphp

{{-- ============================================================
     WRAPPER GLOBAL DU DASHBOARD
     ============================================================ --}}
<div class="dashboard-scroll-area">

    {{-- ============================================================
         BARRE KPI STICKY (desktop) / statique (tablette + mobile)
         ============================================================ --}}
    <div class="kpi-sticky-bar">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">

            {{-- Chauffeurs --}}
            <div class="lg:col-span-2 kpi-card">
                <div>
                    <p class="kpi-label">Chauffeurs</p>
                    <p class="kpi-value" id="stat-users">{{ (int)($usersCount ?? 0) }}</p>
                </div>
                <span class="kpi-icon"><i class="fas fa-users"></i></span>
            </div>

            {{-- Véhicules --}}
            <div class="lg:col-span-2 kpi-card">
                <div>
                    <p class="kpi-label">Véhicules</p>
                    <p class="kpi-value" style="color: var(--color-text);" id="stat-vehicles">{{ (int)($vehiclesCount ?? 0) }}</p>
                </div>
                <span class="kpi-icon" style="color: var(--color-secondary-text);"><i class="fas fa-car-alt"></i></span>
            </div>

            {{-- Alertes par type --}}
            <div class="lg:col-span-8 kpi-card" style="flex-direction: column; align-items: stretch;">
                <div class="kpi-alerts-header">
                    <span>Alertes ouvertes par type</span>
                    <span style="color: var(--color-secondary-text); font-weight:400;">Non traitées</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    @foreach($types as $k => [$label, $icon, $badge])
                    <div class="alert-type-card">
                        <div style="min-width:0;">
                            <p class="alert-type-label">{{ $label }}</p>
                            <p class="alert-type-value" id="stat-alert-{{ $k }}">
                                {{ (int)($alertStats[$k] ?? 0) }}
                            </p>
                        </div>
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg {{ $badge }} flex-shrink-0">
                            <i class="fas {{ $icon }}" style="font-size:11px;"></i>
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
    {{-- FIN barre sticky --}}

    {{-- ============================================================
         CONTENU SCROLLABLE (défile SOUS la barre sticky)
         ============================================================ --}}
    <div class="dashboard-content" style="margin-top: 1rem;">

        {{-- Flotte + Carte --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

            {{-- Liste Flotte --}}
            <div class="lg:col-span-1">
                <div class="ui-card h-full" style="display:flex;flex-direction:column;gap:0.75rem;padding:1.1rem;">

                    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:0.25rem;">
                        <div>
                            <h2 class="font-orbitron" style="font-size:0.8rem;font-weight:700;color:var(--color-text);margin:0;">
                                Flotte &amp; Associations
                            </h2>
                            <p style="font-size:0.7rem;color:var(--color-secondary-text);margin:4px 0 0;">
                                Cliquez sur un véhicule pour le centrer.
                            </p>
                        </div>
                        <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(245,130,32,0.12);flex-shrink:0;">
                            <i class="fas fa-car-side" style="color:var(--color-primary);font-size:0.85rem;"></i>
                        </div>
                    </div>

                    {{-- Recherche --}}
                    <div style="position:relative;">
                        <span style="position:absolute;inset-y:0;left:8px;display:flex;align-items:center;color:var(--color-secondary-text);font-size:0.75rem;">
                            <i class="fas fa-search"></i>
                        </span>
                        <input
                            id="vehicleSearch"
                            type="text"
                            class="ui-input-style"
                            style="padding-left:2rem;font-size:0.75rem;"
                            placeholder="Immatriculation ou chauffeur…"
                        />
                    </div>

                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.7rem;color:var(--color-secondary-text);">
                        <span>
                            <i class="fas fa-circle" style="color:#4ade80;font-size:0.5rem;margin-right:4px;"></i>
                            Véhicules suivis
                        </span>
                        <span id="fleet-count">0 véhicule(s)</span>
                    </div>

                    <div id="vehicleList">
                        <p style="font-size:0.8rem;color:var(--color-secondary-text);margin-top:1rem;">Chargement…</p>
                    </div>
                </div>
            </div>

            {{-- Carte --}}
            <div class="lg:col-span-3">
                <div class="ui-card" style="padding:1rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                        <div>
                            <h2 class="font-orbitron" style="font-size:0.8rem;font-weight:700;color:var(--color-text);margin:0;">
                                Localisation de la Flotte
                            </h2>
                            <p style="font-size:0.7rem;color:var(--color-secondary-text);margin:3px 0 0;">
                                Mise à jour temps réel via SSE.
                            </p>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;font-size:0.7rem;color:var(--color-secondary-text);">
                            <span id="sse-indicator" style="display:inline-flex;align-items:center;gap:4px;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#9ca3af;"></span>
                                <span id="sse-label">Temps réel</span>
                            </span>
                            <span id="last-update" style="font-size:0.7rem;color:var(--color-secondary-text);"></span>
                        </div>
                    </div>
                    <div id="fleetMap"></div>
                </div>
            </div>
        </div>

        {{-- Table alertes --}}
        <div class="ui-card">
            <h2 class="font-orbitron" style="font-size:1rem;font-weight:700;color:var(--color-text);margin:0 0 1rem;">
                Mes dernières alertes
            </h2>
            <div class="ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Véhicule</th>
                            <th>Type</th>
                            <th>Heure</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody id="alerts-table-body">
                        @forelse(($alerts ?? []) as $a)
                        <tr>
                            <td>{{ $a['vehicle'] ?? '-' }}</td>
                            <td>{{ $a['type']    ?? '-' }}</td>
                            <td>{{ $a['time']    ?? '-' }}</td>
                            <td>
                                <span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:0.7rem;background:{{ $a['status_bg'] ?? '#6b7280' }};color:white;">
                                    {{ $a['status'] ?? '-' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="text-align:center;padding:1.5rem;color:var(--color-secondary-text);font-size:0.8rem;">
                                Aucune alerte pour le moment.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    {{-- FIN dashboard-content --}}

</div>
{{-- FIN dashboard-scroll-area --}}


{{-- ============================================================
     SCRIPTS DASHBOARD
     ============================================================ --}}
<script>
let map;
let markersById       = {};
let infoWindowsById   = {};
let selectedVehicleId = null;
let dashboardSSE      = null;

let vehiclesData = @json($vehicles ?? []);

const ALERT_TYPES = {
    stolen:    { label: 'Vol',       icon: 'fa-mask'          },
    geofence:  { label: 'Geofence',  icon: 'fa-draw-polygon'  },
    safe_zone: { label: 'Safe Zone', icon: 'fa-shield-halved' },
    speed:     { label: 'Vitesse',   icon: 'fa-gauge-high'    },
    time_zone: { label: 'Time Zone', icon: 'fa-calendar-alt'  },
};

/* ---- Google Maps ---- */
function initFleetMap() {
    map = new google.maps.Map(document.getElementById('fleetMap'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 7,
    });
    renderVehicleList(vehiclesData);
    renderMarkers(vehiclesData, true);
    initVehicleSearch();
    startDashboardSSE();
}

function loadGoogleMaps() {
    const s  = document.createElement('script');
    s.src    = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo';
    s.async  = true;
    s.defer  = true;
    s.onload = () => initFleetMap();
    document.head.appendChild(s);
}

document.addEventListener('DOMContentLoaded', loadGoogleMaps);

/* ---- SSE ---- */
function startDashboardSSE() {
    const url = "{{ route('dashboard.stream') }}";
    dashboardSSE = new EventSource(url, { withCredentials: true });
    setSseIndicator('connecting');

    dashboardSSE.addEventListener('hello', () => setSseIndicator('connected'));

    dashboardSSE.addEventListener('dashboard', e => {
        setSseIndicator('connected');
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

        const fleet  = Array.isArray(payload.fleet) ? payload.fleet : [];
        vehiclesData = fleet;
        renderVehicleList(fleet);
        renderMarkers(fleet, false);
        updateSelectedInfoWindow(fleet);
    });

    dashboardSSE.onerror = () => setSseIndicator('reconnecting');

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && dashboardSSE) {
            dashboardSSE.close(); dashboardSSE = null; setSseIndicator('paused');
        } else if (!document.hidden && !dashboardSSE) {
            startDashboardSSE();
        }
    });

    window.addEventListener('beforeunload', () => dashboardSSE?.close());
}

function setSseIndicator(state) {
    const dot   = document.querySelector('#sse-indicator span:first-child');
    const label = document.getElementById('sse-label');
    if (!dot || !label) return;
    const map2 = {
        connected:    ['#22c55e', 'Connecté'],
        connecting:   ['#eab308', 'Connexion…'],
        reconnecting: ['#f97316', 'Reconnexion…'],
        paused:       ['#9ca3af', 'En pause'],
    };
    const [color, text] = map2[state] || ['#9ca3af', 'Temps réel'];
    dot.style.background = color;
    label.textContent    = text;
}

/* ---- Stats ---- */
function applyStats(stats) {
    const set = (id, v) => { const el = document.getElementById(id); if (el && v != null) el.textContent = v; };
    set('stat-users',    stats.usersCount);
    set('stat-vehicles', stats.vehiclesCount);
}

function applyAlertTypeStats(obj) {
    Object.keys(ALERT_TYPES).forEach(k => {
        const el = document.getElementById('stat-alert-' + k);
        if (el) el.textContent = (obj?.[k] ?? 0);
    });
}

/* ---- Alertes table ---- */
function renderAlertsTable(alerts) {
    const body = document.getElementById('alerts-table-body');
    if (!body) return;
    if (!alerts.length) {
        body.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:1.5rem;color:var(--color-secondary-text);font-size:0.8rem;">Aucune alerte</td></tr>`;
        return;
    }
    body.innerHTML = alerts.map(a => `
        <tr>
          <td>${escapeHtml(a.vehicle)}</td>
          <td>${escapeHtml(a.type)}</td>
          <td>${escapeHtml(a.time)}</td>
          <td><span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:0.7rem;background:${a.status_bg ?? '#6b7280'};color:white;">${escapeHtml(a.status)}</span></td>
        </tr>`).join('');
}

/* ---- Vehicle list ---- */
function renderVehicleList(fleet) {
    const list  = document.getElementById('vehicleList');
    const count = document.getElementById('fleet-count');
    if (!list) return;
    if (count) count.textContent = `${fleet.length} véhicule(s)`;

    if (!fleet.length) {
        list.innerHTML = `<p style="font-size:0.8rem;color:var(--color-secondary-text);margin-top:1rem;">Aucun véhicule avec position connue.</p>`;
        return;
    }

    list.innerHTML = fleet.map(buildVehicleItemHtml).join('');

    list.querySelectorAll('.vehicle-item').forEach(item => {
        item.addEventListener('click', function () {
            list.querySelectorAll('.vehicle-item').forEach(i => i.style.outline = '');
            this.style.outline = '2px solid var(--color-primary)';
            focusVehicleOnMap(parseInt(this.dataset.id, 10));
        });
    });

    const q = document.getElementById('vehicleSearch')?.value.toLowerCase().trim();
    if (q) applyVehicleFilter(q);
}

function buildVehicleItemHtml(v) {
    const id          = v.id;
    const immat       = escapeHtml(v.immatriculation ?? '—');
    const brand       = escapeHtml(`${v.marque ?? ''} ${v.model ?? ''}`.trim());
    const driver      = escapeHtml(v.driver?.label ?? v.users ?? 'Non associé');
    const engineCut   = v.engine?.cut;
    const gpsOnline   = v.gps?.online;
    const engineCls   = engineCut === true  ? 'bg-red-100 text-red-700'   : engineCut === false ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
    const engineTxt   = engineCut === true  ? 'Moteur coupé' : engineCut === false ? 'Moteur actif'  : 'Moteur inconnu';
    const gpsCls      = gpsOnline  === true ? 'bg-green-100 text-green-700': gpsOnline  === false ? 'bg-gray-200 text-gray-700'  : 'bg-gray-100 text-gray-700';
    const gpsTxt      = gpsOnline  === true ? 'GPS en ligne'  : gpsOnline  === false ? 'GPS hors ligne' : 'GPS inconnu';
    const label       = `${v.immatriculation ?? ''} ${v.driver?.label ?? v.users ?? ''}`.toLowerCase();
    const trajetsUrl  = `{{ route('trajets.index', ['vehicle_id' => '__VID__']) }}`.replace('__VID__', String(id));

    return `
    <div class="vehicle-item"
         id="vehicle-item-${id}"
         data-id="${id}"
         data-label="${escapeHtml(label)}"
         style="border:1px solid var(--color-border-subtle);border-radius:0.5rem;padding:0.6rem 0.75rem;cursor:pointer;
                background:var(--color-card);transition:box-shadow 0.15s,border-color 0.15s;margin-bottom:0.4rem;">

        <div style="display:flex;align-items:flex-start;gap:0.5rem;">
            <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                        background:var(--color-sidebar-active-bg);flex-shrink:0;margin-top:1px;">
                <i class="fas fa-car" style="color:var(--color-primary);font-size:0.85rem;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <p style="font-size:0.78rem;font-weight:600;color:var(--color-text);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${immat}</p>
                <p style="font-size:0.68rem;color:var(--color-secondary-text);margin:1px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${brand}</p>
                <p style="font-size:0.68rem;color:var(--color-secondary-text);margin:3px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <i class="fas fa-user" style="font-size:0.6rem;margin-right:3px;"></i>${driver}
                </p>
            </div>
            <div style="flex-shrink:0;">
                <a href="${trajetsUrl}"
                   style="color:var(--color-primary);font-size:0.7rem;font-weight:600;text-decoration:none;padding:4px;"
                   onclick="event.stopPropagation();">
                    <i class="fas fa-route" style="margin-right:2px;"></i>Trajets
                </a>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:0.4rem;flex-wrap:wrap;gap:4px;">
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <span class="${engineCls}" style="display:inline-flex;align-items:center;padding:2px 7px;border-radius:9999px;font-size:0.62rem;">
                    <i class="fas fa-power-off" style="margin-right:3px;font-size:0.55rem;"></i>${engineTxt}
                </span>
                <span class="${gpsCls}" style="display:inline-flex;align-items:center;padding:2px 7px;border-radius:9999px;font-size:0.62rem;">
                    <i class="fas fa-satellite-dish" style="margin-right:3px;font-size:0.55rem;"></i>${gpsTxt}
                </span>
            </div>
            <span style="font-size:0.62rem;color:var(--color-secondary-text);display:inline-flex;align-items:center;gap:3px;">
                <i class="fas fa-location-arrow" style="color:var(--color-primary);font-size:0.6rem;"></i>Centrer
            </span>
        </div>
    </div>`;
}

function initVehicleSearch() {
    const input = document.getElementById('vehicleSearch');
    if (!input) return;
    input.addEventListener('input', function () { applyVehicleFilter(this.value.toLowerCase().trim()); });
}

function applyVehicleFilter(q) {
    document.querySelectorAll('#vehicleList .vehicle-item').forEach(item => {
        item.style.display = (!q || (item.dataset.label ?? '').toLowerCase().includes(q)) ? '' : 'none';
    });
}

/* ---- Markers ---- */
function renderMarkers(vehicles, fitBounds = false) {
    if (!map) return;
    const bounds = new google.maps.LatLngBounds();
    const newIds = new Set();

    vehicles.forEach(v => {
        if (v.lat == null || v.lon == null) return;
        const id       = v.id;
        const position = { lat: parseFloat(v.lat), lng: parseFloat(v.lon) };
        newIds.add(String(id));

        let marker = markersById[id];
        if (!marker) {
            marker = new google.maps.Marker({
                position, map,
                title: v.immatriculation ?? '',
                icon: { url: '/assets/icons/car_icon.png', scaledSize: new google.maps.Size(40, 40) },
            });
            const iw = new google.maps.InfoWindow({ content: buildInfoWindowContent(v) });
            marker.addListener('click', () => { selectedVehicleId = id; iw.open(map, marker); });
            markersById[id]     = marker;
            infoWindowsById[id] = iw;
        } else {
            marker.setPosition(position);
        }
        bounds.extend(position);
    });

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
    infoWindowsById[selectedVehicleId]?.setContent(buildInfoWindowContent(v));
}

function buildInfoWindowContent(v) {
    const driver      = v.driver?.label || v.users || '-';
    const engineCut   = v.engine?.cut;
    const gpsOnline   = v.gps?.online;
    const engineLabel = engineCut === true  ? 'Moteur coupé' : engineCut === false ? 'Moteur actif'    : 'Moteur inconnu';
    const engineColor = engineCut === true  ? '#ef4444'      : engineCut === false ? '#22c55e'          : '#6b7280';
    const gpsLabel    = gpsOnline  === true ? 'GPS en ligne'  : gpsOnline  === false ? 'GPS hors ligne' : 'GPS inconnu';
    const gpsColor    = gpsOnline  === true ? '#22c55e'       : gpsOnline  === false ? '#9ca3af'        : '#6b7280';
    const trajetsUrl  = `{{ route('trajets.index', ['vehicle_id' => '__VID__']) }}`.replace('__VID__', String(v.id));

    return `
    <div style="font-size:12px;min-width:220px;font-family:system-ui,sans-serif;">
        <b style="font-size:13px;">${escapeHtml(v.immatriculation)}</b><br>
        <span style="color:#6b7280;">Chauffeur:</span> ${escapeHtml(driver)}<br>
        <div style="margin-top:6px;display:flex;gap:10px;flex-wrap:wrap;">
            <span style="display:inline-flex;align-items:center;gap:4px;color:${engineColor};font-weight:600;">
                <i class="fas fa-power-off"></i>${engineLabel}
            </span>
            <span style="display:inline-flex;align-items:center;gap:4px;color:${gpsColor};font-weight:600;">
                <i class="fas fa-satellite-dish"></i>${gpsLabel}
            </span>
        </div>
        <div style="margin-top:8px;">
            <a href="${trajetsUrl}" style="color:#3b82f6;text-decoration:underline;">
                <i class="fas fa-route"></i> Voir les trajets
            </a>
        </div>
    </div>`;
}

function focusVehicleOnMap(vehicleId) {
    const marker = markersById[vehicleId];
    if (!marker || !map) return;
    map.setCenter(marker.getPosition());
    map.setZoom(15);
    selectedVehicleId = vehicleId;
    infoWindowsById[vehicleId]?.open(map, marker);
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, m =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
}









function refreshMapSize() {
  if (!window.google || !window.google.maps || !map) return;
  google.maps.event.trigger(map, "resize");
}

window.addEventListener("resize", () => setTimeout(refreshMapSize, 180));
document.addEventListener("DOMContentLoaded", () => setTimeout(refreshMapSize, 600));
</script>



<script>
(function () {
  function setDashboardHeights(){
    const root = document.documentElement;

    // navbar: soit ta variable CSS (5rem), soit on mesure
    const navbar = document.getElementById('navbar');
    const navbarH = navbar ? Math.round(navbar.getBoundingClientRect().height) : 80;

    // KPI bar: on mesure la vraie hauteur (responsive)
    const kpi = document.querySelector('.kpi-sticky-bar');
    const kpiH = kpi ? Math.round(kpi.getBoundingClientRect().height) : 140;

    root.style.setProperty('--navbar-h', navbarH + 'px');
    root.style.setProperty('--kpi-h', kpiH + 'px');
  }

  // initial + resize
  window.addEventListener('load', setDashboardHeights);
  window.addEventListener('resize', () => {
    // petit debounce
    clearTimeout(window.__dashResizeT);
    window.__dashResizeT = setTimeout(setDashboardHeights, 120);
  });

  // si fonts / cartes changent la hauteur après coup
  setTimeout(setDashboardHeights, 250);
  setTimeout(setDashboardHeights, 900);
})();
</script>

@endsection