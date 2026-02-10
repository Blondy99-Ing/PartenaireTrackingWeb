@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@section('content')
@php
    // ✅ Types d’alertes affichées (uniquement non traitées)
    $types = [
        'stolen'    => ['Vol', 'fa-mask', 'bg-red-100 text-red-700'],
        'geofence'  => ['Geofence', 'fa-draw-polygon', 'bg-orange-100 text-orange-700'],
        'safe_zone' => ['Safe Zone', 'fa-shield-halved', 'bg-blue-100 text-blue-700'],
        'speed'     => ['Vitesse', 'fa-gauge-high', 'bg-yellow-100 text-yellow-800'],
        'time_zone' => ['Time Zone', 'fa-calendar-alt', 'bg-indigo-100 text-indigo-700'],
    ];
@endphp

<div class="space-y-4">

    {{-- ✅ TOP BAR FIXE (sticky) --}}
    <div class="sticky z-40" style="top: var(--navbar-h, 64px);">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-2">

            {{-- Chauffeurs --}}
            <div class="lg:col-span-2 ui-card px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-semibold text-secondary uppercase tracking-wider">Chauffeurs</p>
                    <p class="text-2xl font-bold mt-0.5 text-primary" id="stat-users">{{ (int)($usersCount ?? 0) }}</p>
                </div>
                <div class="text-xl text-primary opacity-70"><i class="fas fa-users"></i></div>
            </div>

            {{-- Véhicules --}}
            <div class="lg:col-span-2 ui-card px-4 py-3 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-semibold text-secondary uppercase tracking-wider">Véhicules</p>
                    <p class="text-2xl font-bold mt-0.5" id="stat-vehicles">{{ (int)($vehiclesCount ?? 0) }}</p>
                </div>
                <div class="text-xl opacity-70"><i class="fas fa-car-alt"></i></div>
            </div>

            {{-- Alertes (groupe) --}}
            <div class="lg:col-span-8 ui-card px-4 py-3">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-[11px] font-orbitron font-semibold" style="color: var(--color-text);">
                        Alertes ouvertes par type
                    </h3>
                    <span class="text-[11px] text-secondary">Non traitées</span>
                </div>

                {{-- ✅ 5 cartes sur 1 ligne en grand écran --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                    @foreach($types as $k => [$label, $icon, $badge])
                        <div class="rounded-lg border px-2.5 py-2 flex items-center justify-between"
                             style="border-color: var(--color-border-subtle); background: var(--color-card);">
                            <div class="min-w-0">
                                <p class="text-[9px] font-semibold text-secondary uppercase tracking-wider truncate">
                                    {{ $label }}
                                </p>
                                <p class="text-lg font-bold leading-tight mt-0.5" id="stat-alert-{{ $k }}">
                                    {{ (int)($alertStats[$k] ?? 0) }}
                                </p>
                            </div>

                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg {{ $badge }} shrink-0">
                                <i class="fas {{ $icon }} text-[12px]"></i>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>

    {{-- ✅ léger espace sous la barre sticky pour éviter l’effet “collé” --}}
    <div class="h-1"></div>

    {{-- Layout Flotte : Liste à gauche / Carte à droite --}}
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">

        {{-- Liste --}}
        <div class="lg:col-span-1">
            <div class="ui-card h-full flex flex-col gap-3" style="padding: 1.25rem;">
                <div class="flex items-center justify-between mb-1">
                    <div>
                        <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                            Flotte & Associations
                        </h2>
                        <p class="text-[11px] text-secondary mt-0.5">
                            Cliquez sur un véhicule pour le centrer sur la carte.
                        </p>
                    </div>
                    <div class="w-9 h-9 rounded-full flex items-center justify-center"
                         style="background: rgba(245,130,32,0.12);">
                        <i class="fas fa-car-side text-primary text-sm"></i>
                    </div>
                </div>

                {{-- Recherche --}}
                <div class="relative mb-2">
                    <span class="absolute inset-y-0 left-2 flex items-center text-secondary text-xs">
                        <i class="fas fa-search"></i>
                    </span>
                    <input
                        id="vehicleSearch"
                        type="text"
                        class="ui-input-style pl-8 text-xs"
                        placeholder="Rechercher immatriculation ou chauffeur..."
                    />
                </div>

                <div class="flex items-center justify-between text-[11px] text-secondary mb-1">
                    <span><i class="fas fa-circle text-green-400 text-[8px] mr-1"></i> Véhicules suivis</span>
                    <span id="fleet-count">0 véhicule(s)</span>
                </div>

                <div id="vehicleList" class="space-y-2 overflow-y-auto pr-1" style="max-height: 600px;">
                    <p class="text-sm text-secondary mt-4">Chargement de la flotte…</p>
                </div>
            </div>
        </div>

        {{-- Carte --}}
        <div class="lg:col-span-3">
            <div class="ui-card p-4">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-sm font-orbitron font-semibold" style="color: var(--color-text);">
                            Localisation de la Flotte
                        </h2>
                        <p class="text-[11px] text-secondary mt-0.5">
                            Mise à jour via SSE (event: dashboard).
                        </p>
                    </div>

                    <div class="flex items-center gap-3 text-[11px] text-secondary">
                        <span id="sse-indicator" class="inline-flex items-center gap-1">
                            <span class="inline-block w-2 h-2 rounded-full bg-gray-400"></span>
                            <span id="sse-label">Temps réel</span>
                        </span>
                        <span id="last-update" class="text-[11px] text-secondary"></span>
                    </div>
                </div>

                <div id="fleetMap"
                     class="rounded-xl shadow-inner"
                     style="height: 700px; border: 1px solid var(--color-border-subtle);"></div>
            </div>
        </div>
    </div>

    {{-- Table alertes --}}
    <div class="ui-card">
        <h2 class="text-xl font-bold mb-4 font-orbitron" style="color: var(--color-text);">
            Mes dernières alertes
        </h2>
        <table class="ui-table w-full">
            <thead>
                <tr>
                    <th>Véhicule</th>
                    <th>Type</th>
                    <th>Heure</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody id="alerts-table-body">
                @foreach(($alerts ?? []) as $a)
                    <tr>
                        <td>{{ $a['vehicle'] ?? '-' }}</td>
                        <td>{{ $a['type'] ?? '-' }}</td>
                        <td>{{ $a['time'] ?? '-' }}</td>
                        <td>
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $a['status_color'] ?? 'bg-gray-500' }}" style="color:white;">
                                {{ $a['status'] ?? '-' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

<script>
let map;
let markersById = {};
let infoWindowsById = {};
let selectedVehicleId = null;
let dashboardSSE = null;

let vehiclesData = @json($vehicles ?? []);

const ALERT_TYPES = {
  stolen:    { label:"Vol",       icon:"fa-mask" },
  geofence:  { label:"Geofence",  icon:"fa-draw-polygon" },
  safe_zone: { label:"Safe Zone", icon:"fa-shield-halved" },
  speed:     { label:"Vitesse",   icon:"fa-gauge-high" },
  time_zone: { label:"Time Zone", icon:"fa-calendar-alt" },
};

function initFleetMap() {
    map = new google.maps.Map(document.getElementById('fleetMap'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 7
    });

    renderVehicleList(vehiclesData);
    renderMarkers(vehiclesData, true);
    initVehicleSearch();
    startDashboardSSE();
}

function startDashboardSSE() {
    const url = "{{ route('dashboard.stream') }}";
    dashboardSSE = new EventSource(url, { withCredentials: true });
    setSseIndicator('connecting');

    dashboardSSE.addEventListener('hello', () => setSseIndicator('connected'));

    dashboardSSE.addEventListener('dashboard', (e) => {
        setSseIndicator('connected');

        let payload = null;
        try { payload = JSON.parse(e.data); }
        catch (err) { console.error('SSE JSON parse error', err, e.data); return; }

        if (payload.ts) {
            const lu = document.getElementById('last-update');
            if (lu) lu.textContent = `Maj: ${payload.ts}`;
        }

        if (payload.stats) {
            applyStats(payload.stats);
            const byType = payload.stats.alertsByType || payload.stats.alerts_by_type || null;
            if (byType) applyAlertTypeStats(byType);
        }

        if (Array.isArray(payload.alerts)) {
            renderAlertsTable(payload.alerts);
        }

        const fleet = Array.isArray(payload.fleet) ? payload.fleet : [];
        vehiclesData = fleet;

        renderVehicleList(fleet);
        renderMarkers(fleet, false);
        updateSelectedInfoWindow(fleet);
    });

    dashboardSSE.onerror = () => setSseIndicator('reconnecting');

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && dashboardSSE) {
            dashboardSSE.close();
            dashboardSSE = null;
            setSseIndicator('paused');
        } else if (!document.hidden && !dashboardSSE) {
            startDashboardSSE();
        }
    });

    window.addEventListener('beforeunload', () => dashboardSSE && dashboardSSE.close());
}

function setSseIndicator(state) {
    const dot = document.querySelector('#sse-indicator span');
    const label = document.getElementById('sse-label');
    if (!dot || !label) return;

    const set = (cls, txt) => {
        dot.className = `inline-block w-2 h-2 rounded-full ${cls}`;
        label.textContent = txt;
    };

    if (state === 'connected') set('bg-green-500', 'Connecté');
    else if (state === 'connecting') set('bg-yellow-500', 'Connexion…');
    else if (state === 'reconnecting') set('bg-orange-500', 'Reconnexion…');
    else if (state === 'paused') set('bg-gray-400', 'En pause');
    else set('bg-gray-400', 'Temps réel');
}

function applyStats(stats) {
    const set = (id, v) => {
        const el = document.getElementById(id);
        if (el && v !== undefined && v !== null) el.textContent = String(v);
    };

    set('stat-users', stats.usersCount);
    set('stat-vehicles', stats.vehiclesCount);
}

function applyAlertTypeStats(obj) {
    Object.keys(ALERT_TYPES).forEach(k => {
        const el = document.getElementById('stat-alert-' + k);
        if (!el) return;
        el.textContent = (obj && obj[k] !== undefined && obj[k] !== null) ? String(obj[k]) : '0';
    });
}

function renderAlertsTable(alerts) {
    const body = document.getElementById('alerts-table-body');
    if (!body) return;

    if (!alerts.length) {
        body.innerHTML = `<tr><td colspan="4" class="text-center text-sm text-secondary py-4">Aucune alerte</td></tr>`;
        return;
    }

    body.innerHTML = alerts.map(a => {
        const c = a.status_color || 'bg-gray-500';
        return `
            <tr>
              <td>${escapeHtml(a.vehicle)}</td>
              <td>${escapeHtml(a.type)}</td>
              <td>${escapeHtml(a.time)}</td>
              <td><span class="inline-block px-2 py-0.5 text-xs rounded-full ${c}" style="color:white;">${escapeHtml(a.status)}</span></td>
            </tr>
        `;
    }).join('');
}

function renderVehicleList(fleet) {
    const list = document.getElementById('vehicleList');
    if (!list) return;

    const fleetCount = document.getElementById('fleet-count');
    if (fleetCount) fleetCount.textContent = `${fleet.length} véhicule(s)`;

    if (!fleet.length) {
        list.innerHTML = `<p class="text-sm text-secondary mt-4">Aucun véhicule avec position connue.</p>`;
        return;
    }

    list.innerHTML = fleet.map(v => buildVehicleItemHtml(v)).join('');

    list.querySelectorAll('.vehicle-item').forEach(item => {
        item.addEventListener('click', function () {
            const id = parseInt(this.dataset.id, 10);

            list.querySelectorAll('.vehicle-item').forEach(i => i.classList.remove('ring-2', 'ring-[var(--color-primary)]'));
            this.classList.add('ring-2', 'ring-[var(--color-primary)]');
            focusVehicleOnMap(id);
        });
    });

    const searchInput = document.getElementById('vehicleSearch');
    if (searchInput) {
        const q = searchInput.value.toLowerCase().trim();
        if (q) applyVehicleFilter(q);
    }
}

function buildVehicleItemHtml(v) {
    const id = v.id;
    const immat = escapeHtml(v.immatriculation ?? '—');
    const brand = escapeHtml(`${v.marque ?? ''} ${v.model ?? ''}`.trim());
    const driverLabel = escapeHtml(v.driver?.label ?? v.users ?? 'Non associé');

    const engineCut = v.engine?.cut;
    const gpsOnline = v.gps?.online;

    const engineClass = engineCut === true ? 'bg-red-100 text-red-700' : (engineCut === false ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700');
    const engineText  = engineCut === true ? 'Moteur coupé' : (engineCut === false ? 'Moteur actif' : 'Moteur inconnu');

    const gpsClass = gpsOnline === true ? 'bg-green-100 text-green-700' : (gpsOnline === false ? 'bg-gray-200 text-gray-700' : 'bg-gray-100 text-gray-700');
    const gpsText  = gpsOnline === true ? 'GPS en ligne' : (gpsOnline === false ? 'GPS hors ligne' : 'GPS inconnu');

    const label = `${(v.immatriculation ?? '')} ${(v.driver?.label ?? v.users ?? '')}`.toLowerCase();
    const trajetsUrl = `{{ route('trajets.index', ['vehicle_id' => '__VID__']) }}`.replace('__VID__', String(id));

    return `
        <div class="vehicle-item border rounded-lg px-3 py-2.5 cursor-pointer transition-all duration-150
                    hover:shadow-md hover:border-[var(--color-primary)]
                    bg-[color:var(--color-card)]"
             id="vehicle-item-${id}"
             data-id="${id}"
             data-label="${escapeHtml(label)}">

            <div class="flex items-start gap-2">
                <div class="mt-0.5">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs
                                bg-[var(--color-sidebar-active-bg)]">
                        <i class="fas fa-car text-primary"></i>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold truncate" style="color: var(--color-text);">${immat}</p>
                    <p class="text-[11px] text-secondary truncate">${brand}</p>

                    <p class="text-[11px] mt-1 text-secondary line-clamp-1">
                        <i class="fas fa-user mr-1 text-[10px]"></i>${driverLabel}
                    </p>
                </div>

                <div class="shrink-0">
                    <a href="${trajetsUrl}"
                       class="text-primary hover:underline text-xs font-semibold p-2"
                       onclick="event.stopPropagation();">
                        <i class="fas fa-route mr-1"></i> Trajets
                    </a>
                </div>
            </div>

            <div class="flex items-center justify-between mt-2">
                <span class="inline-flex items-center gap-1 text-[10px] flex-wrap">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full ${engineClass} mb-1">
                        <i class="fas fa-power-off mr-1 text-[9px]"></i> ${engineText}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full ${gpsClass} mb-1">
                        <i class="fas fa-satellite-dish mr-1 text-[9px]"></i> ${gpsText}
                    </span>
                </span>

                <span class="inline-flex items-center gap-1 text-[10px] text-secondary">
                    <i class="fas fa-location-arrow text-[10px] text-primary"></i> Centrer
                </span>
            </div>
        </div>
    `;
}

function initVehicleSearch() {
    const searchInput = document.getElementById('vehicleSearch');
    if (!searchInput) return;
    searchInput.addEventListener('input', function () {
        applyVehicleFilter(this.value.toLowerCase().trim());
    });
}

function applyVehicleFilter(q) {
    const list = document.getElementById('vehicleList');
    if (!list) return;
    list.querySelectorAll('.vehicle-item').forEach(item => {
        const label = (item.dataset.label || '').toLowerCase();
        item.style.display = (!q || label.includes(q)) ? '' : 'none';
    });
}

function renderMarkers(vehicles, fitBounds = false) {
    if (!map) return;

    const bounds = new google.maps.LatLngBounds();
    const newIds = new Set();

    vehicles.forEach(v => {
        if (v.lat == null || v.lon == null) return;

        const id = v.id;
        newIds.add(String(id));
        const position = { lat: parseFloat(v.lat), lng: parseFloat(v.lon) };

        let marker = markersById[id];
        if (!marker) {
            marker = new google.maps.Marker({
                position,
                map,
                title: v.immatriculation ?? '',
                icon: { url: "/assets/icons/car_icon.png", scaledSize: new google.maps.Size(40, 40) }
            });

            const infoWindow = new google.maps.InfoWindow({ content: buildInfoWindowContent(v) });

            marker.addListener('click', () => {
                selectedVehicleId = id;
                infoWindow.open(map, marker);
            });

            markersById[id] = marker;
            infoWindowsById[id] = infoWindow;
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

    if (fitBounds && vehicles.length > 0) {
        map.fitBounds(bounds);
        const listener = google.maps.event.addListener(map, "idle", function () {
            if (map.getZoom() > 14) map.setZoom(14);
            google.maps.event.removeListener(listener);
        });
    }
}

function updateSelectedInfoWindow(fleet) {
    if (selectedVehicleId == null) return;
    const v = fleet.find(x => String(x.id) === String(selectedVehicleId));
    if (!v) return;
    const iw = infoWindowsById[selectedVehicleId];
    const marker = markersById[selectedVehicleId];
    if (iw && marker) iw.setContent(buildInfoWindowContent(v));
}

function buildInfoWindowContent(vehicle) {
    const driver = vehicle.driver?.label || vehicle.users || '-';

    const engineCut = vehicle.engine?.cut;
    const gpsOnline = vehicle.gps?.online;

    const engineLabel = engineCut === true ? 'Moteur coupé' : (engineCut === false ? 'Moteur actif' : 'Moteur inconnu');
    const engineColor = engineCut === true ? '#ef4444' : (engineCut === false ? '#22c55e' : '#6b7280');

    const gpsLabel = gpsOnline === true ? 'GPS en ligne' : (gpsOnline === false ? 'GPS hors ligne' : 'GPS inconnu');
    const gpsColor = gpsOnline === true ? '#22c55e' : (gpsOnline === false ? '#9ca3af' : '#6b7280');

    const trajetsUrl = `{{ route('trajets.index', ['vehicle_id' => '__VID__']) }}`.replace('__VID__', String(vehicle.id));

    return `
        <div style="font-size:12px;min-width:240px;">
            <b>${escapeHtml(vehicle.immatriculation)}</b><br>
            Chauffeur: ${escapeHtml(driver)}<br>

            <div style="margin-top:6px;">
                <span style="display:inline-flex;align-items:center;margin-right:10px;">
                    <i class="fas fa-power-off" style="margin-right:4px;color:${engineColor};"></i>
                    <span style="color:${engineColor};font-weight:600;">${engineLabel}</span>
                </span>
                <span style="display:inline-flex;align-items:center;">
                    <i class="fas fa-satellite-dish" style="margin-right:4px;color:${gpsColor};"></i>
                    <span style="color:${gpsColor};font-weight:600;">${gpsLabel}</span>
                </span>
            </div>

            <div style="margin-top:8px;">
                <a href="${trajetsUrl}" style="color:#3b82f6;text-decoration:underline;">
                    <i class="fas fa-route"></i> Voir les trajets
                </a>
            </div>
        </div>
    `;
}

function focusVehicleOnMap(vehicleId) {
    const marker = markersById[vehicleId];
    if (!marker || !map) return;

    map.setCenter(marker.getPosition());
    map.setZoom(15);

    selectedVehicleId = vehicleId;
    const iw = infoWindowsById[vehicleId];
    if (iw) iw.open(map, marker);
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (m) => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[m]));
}

function loadGoogleMaps() {
    const script = document.createElement('script');
    script.src = "https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo";
    script.async = true;
    script.defer = true;
    script.onload = () => initFleetMap();
    document.head.appendChild(script);
}

document.addEventListener('DOMContentLoaded', loadGoogleMaps);
</script>
@endsection