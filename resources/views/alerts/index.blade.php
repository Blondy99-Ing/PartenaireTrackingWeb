{{-- resources/views/alerts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des Alertes')

@push('styles')
    <style>
        .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .7rem;border-radius:9999px;font-size:.72rem;font-weight:700;color:#fff;white-space:nowrap}
        .status-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:700}
        .status-open{background:rgba(239,68,68,.12);color:#ef4444}
        .status-processed{background:rgba(34,197,94,.12);color:#22c55e}

        .action-btn{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .7rem;border-radius:8px;font-size:.78rem;font-weight:700;border:1px solid transparent;transition:all .15s ease;cursor:pointer}
        .action-btn:disabled{opacity:.45;cursor:not-allowed}

        .btn-process{background:rgba(34,197,94,.12);color:#16a34a;border-color:rgba(34,197,94,.25)}
        .btn-process:hover{background:#22c55e;color:#fff;border-color:#22c55e}

        .btn-view{background:rgba(59,130,246,.10);color:#2563eb;border-color:rgba(59,130,246,.20);margin-right:.4rem}
        .btn-view:hover{background:#2563eb;color:#fff;border-color:#2563eb}

        .row-unprocessed{border-left:3px solid rgba(245,130,32,.85)}
        .row-processed{border-left:3px solid transparent;opacity:.78}
    </style>
@endpush

@section('content')
    <div class="space-y-8">

        {{-- STATS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="ui-card p-5 flex items-center justify-between border-l-4 border-orange-500">
                <div>
                    <p class="text-sm text-secondary uppercase">Geofence</p>
                    <p class="text-3xl font-bold text-orange-500" id="stat-geofence">0</p>
                </div>
                <div class="text-3xl text-orange-500 opacity-70"><i class="fas fa-route"></i></div>
            </div>

            <div class="ui-card p-5 flex items-center justify-between border-l-4 border-blue-500">
                <div>
                    <p class="text-sm text-secondary uppercase">Vitesse</p>
                    <p class="text-3xl font-bold text-blue-500" id="stat-speed">0</p>
                </div>
                <div class="text-3xl text-blue-500 opacity-70"><i class="fas fa-tachometer-alt"></i></div>
            </div>

            <div class="ui-card p-5 flex items-center justify-between border-l-4 border-green-500">
                <div>
                    <p class="text-sm text-secondary uppercase">Résolues</p>
                    <p class="text-3xl font-bold text-green-500" id="stat-resolved">0</p>
                </div>
                <div class="text-3xl text-green-500 opacity-70"><i class="fas fa-check-double"></i></div>
            </div>

            <div class="ui-card p-5 flex items-center justify-between border-l-4 border-purple-500">
                <div>
                    <p class="text-sm text-secondary uppercase">Safe Zone</p>
                    <p class="text-3xl font-bold text-purple-500" id="stat-safezone">0</p>
                </div>
                <div class="text-3xl text-purple-500 opacity-70"><i class="fas fa-shield-alt"></i></div>
            </div>
        </div>

        {{-- TABLE --}}
        <div class="ui-card p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h2 class="text-xl font-bold">Liste Détaillée des Incidents</h2>
                <div class="text-sm text-secondary">
                    <i class="fas fa-sync-alt mr-1"></i>
                    <span id="lastRefresh">—</span>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 mb-4 items-center border-b pb-4">
                <input id="alertSearch" class="ui-input max-w-sm" placeholder="Recherche véhicule / lieu / utilisateur..." />

                <select id="alertTypeFilter" class="ui-select">
                    <option value="all">Tous les types</option>
                    <option value="geofence">GeoFence</option>
                    <option value="speed">Speed</option>
                    <option value="safe_zone">Safe Zone</option>
                    <option value="time_zone">Time Zone</option>
                    <option value="engine">Engine</option>
                    <option value="stolen">Stolen / Vol</option>
                    <option value="offline">Offline</option>
                    <option value="power_failure">Power Failure</option>
                    <option value="low_battery">Low Battery</option>
                    <option value="device_removal">Device Removal</option>
                    <option value="general">General</option>
                </select>

                <select id="vehicleFilter" class="ui-select">
                    <option value="all">Tous les véhicules</option>
                </select>

                <select id="userFilter" class="ui-select">
                    <option value="all">Tous les utilisateurs</option>
                </select>

                <select id="statusFilter" class="ui-select">
                    <option value="all">Tous les statuts</option>
                    <option value="open">Ouvertes</option>
                    <option value="processed">Traitées</option>
                </select>

                <button id="filterBtn" class="btn-primary">
                    <i class="fas fa-filter mr-1"></i> Filtrer
                </button>

                <button id="refreshBtn" class="btn-secondary">
                    <i class="fas fa-sync-alt mr-1"></i> Rafraîchir
                </button>
            </div>

            <div class="ui-table-container shadow-md">
                <table class="ui-table w-full">
                    <thead>
                    <tr>
                        <th>Type</th>
                        <th>Véhicule</th>
                        <th>Utilisateur(s)</th>
                        <th>Déclenchée le</th>
                        <th>Description</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody id="alerts-tbody">
                    <tr><td colspan="7" class="text-center text-secondary py-6">Chargement...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const API_INDEX = "{{ route('alerts.index') }}";
            const API_MARK_PROCESSED = "{{ url('/alerts') }}"; // /alerts/{id}/processed
            const CSRF = "{{ csrf_token() }}";

            const typeStyle = {
                geofence:      { color: 'bg-orange-500', icon: 'fas fa-route', label: 'GeoFence' },
                safe_zone:     { color: 'bg-purple-500', icon: 'fas fa-shield-alt', label: 'Safe Zone' },
                speed:         { color: 'bg-blue-500', icon: 'fas fa-tachometer-alt', label: 'Speed' },
                time_zone:     { color: 'bg-yellow-400 text-yellow-900', icon: 'fas fa-clock', label: 'Time Zone' },
                engine:        { color: 'bg-red-500', icon: 'fas fa-exclamation-triangle', label: 'Engine' },
                stolen:        { color: 'bg-red-700', icon: 'fas fa-car-crash', label: 'Stolen' },
                offline:       { color: 'bg-gray-600', icon: 'fas fa-wifi', label: 'Offline' },
                power_failure: { color: 'bg-gray-800', icon: 'fas fa-bolt', label: 'Power Failure' },
                low_battery:   { color: 'bg-red-300 text-red-900 border border-red-500', icon: 'fas fa-battery-quarter', label: 'Low Battery' },
                device_removal:{ color: 'bg-gray-800', icon: 'fas fa-tools', label: 'Device Removal' },
                general:       { color: 'bg-gray-500', icon: 'fas fa-bell', label: 'General' },
            };

            let alerts = [];

            function normalizeType(a) {
                return a?.type ?? a?.alert_type ?? 'general';
            }

            function vehicleLabel(a) {
                if (a && a.voiture) {
                    const imm = (a.voiture.immatriculation || '').trim();
                    const marque = (a.voiture.marque || '').trim();
                    if (imm && marque) return `${imm} (${marque})`;
                    if (imm) return imm;
                    if (marque) return marque;
                }
                if (a?.voiture_id) return `Véhicule #${a.voiture_id}`;
                return '—';
            }

            function userLabel(a) {
                const label = (a?.driver_label ?? a?.users_labels ?? '').trim();
                if (label) return label;
                if (a?.user_id) return `Utilisateur #${a.user_id}`;
                return 'Aucun chauffeur';
            }

            function escapeHtml(v) {
                const s = String(v ?? '');
                return s.replaceAll('&','&amp;')
                    .replaceAll('<','&lt;')
                    .replaceAll('>','&gt;')
                    .replaceAll('"','&quot;')
                    .replaceAll("'","&#039;");
            }

            async function fetchAlertsFromApi() {
                try {
                    const url = API_INDEX + (API_INDEX.includes('?') ? '&' : '?') + 'ts=' + Date.now(); // cache buster
                    const res = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache'
                        },
                        credentials: 'same-origin'
                    });

                    const text = await res.text();
                    let json = null;
                    try { json = JSON.parse(text); } catch(e) {}

                    if (!res.ok || !json || json.status !== 'success') return [];
                    return Array.isArray(json.data) ? json.data : [];
                } catch (err) {
                    console.error(err);
                    return [];
                }
            }

            function updateStats(data) {
                document.getElementById('stat-geofence').textContent =
                    data.filter(a => normalizeType(a) === 'geofence' && !a.processed).length;

                document.getElementById('stat-speed').textContent =
                    data.filter(a => normalizeType(a) === 'speed' && !a.processed).length;

                document.getElementById('stat-resolved').textContent =
                    data.filter(a => !!a.processed).length;

                document.getElementById('stat-safezone').textContent =
                    data.filter(a => normalizeType(a) === 'safe_zone' && !a.processed).length;

                const now = new Date();
                document.getElementById('lastRefresh').textContent =
                    now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
            }

            function renderAlerts(rows) {
                const tbody = document.getElementById('alerts-tbody');
                tbody.innerHTML = '';

                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-6">Aucune alerte trouvée.</td></tr>';
                    updateStats([]);
                    return;
                }

                rows.forEach(a => {
                    const t = normalizeType(a);
                    const style = typeStyle[t] ?? { color:'bg-gray-500', icon:'fas fa-bell', label: t ?? 'Unknown' };

                    const vLabel = vehicleLabel(a);
                    const uLabel = userLabel(a);
                    const alertedHuman = a.alerted_at_human ?? '-';
                    const msg = a.message ?? a.location ?? '-';

                    const row = document.createElement('tr');
                    row.className = `${a.processed ? 'row-processed' : 'row-unprocessed'} hover:bg-gray-50`;

                    const statusPill = a.processed
                        ? `<span class="status-pill status-processed"><i class="fas fa-check-circle"></i> Traitée</span>`
                        : `<span class="status-pill status-open"><i class="fas fa-dot-circle"></i> Ouverte</span>`;

                    const processBtn = a.processed
                        ? `<button class="action-btn btn-process" disabled title="Déjà traitée">
                        <i class="fas fa-check-double"></i> Traitée
                   </button>`
                        : `<button class="action-btn btn-process" onclick="markAsProcessed(${a.id})" title="Marquer comme traitée">
                        <i class="fas fa-check"></i> Traiter
                   </button>`;

                    row.innerHTML = `
                <td>
                    <span class="badge ${style.color}">
                        <i class="${style.icon}"></i> ${escapeHtml(a.type_label ?? style.label)}
                    </span>
                </td>
                <td style="color:var(--color-text)">${escapeHtml(vLabel)}</td>
                <td style="color:var(--color-text)">${escapeHtml(uLabel)}</td>
                <td class="text-secondary">${escapeHtml(alertedHuman)}</td>
                <td class="text-secondary">${escapeHtml(msg)}</td>
                <td>${statusPill}</td>
                <td class="whitespace-nowrap">
                    <button class="action-btn btn-view" title="Voir sur profil/carte"
                        onclick="goToProfile(${a.user_id ?? 'null'}, ${a.voiture_id ?? 'null'})">
                        <i class="fas fa-map-marker-alt"></i> Voir
                    </button>
                    ${processBtn}
                </td>
            `;
                    tbody.appendChild(row);
                });

                updateStats(rows);
            }

            function buildFiltersFromData(data) {
                const vehicleSelect = document.getElementById('vehicleFilter');
                const userSelect = document.getElementById('userFilter');

                const vehicles = new Map();
                const users = new Map();

                data.forEach(a => {
                    if (a?.voiture_id) vehicles.set(String(a.voiture_id), vehicleLabel(a));
                    if (a?.user_id) users.set(String(a.user_id), userLabel(a));
                });

                vehicleSelect.innerHTML = '<option value="all">Tous les véhicules</option>';
                [...vehicles.entries()].sort((a,b) => a[1].localeCompare(b[1])).forEach(([id,label]) => {
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = label;
                    vehicleSelect.appendChild(opt);
                });

                userSelect.innerHTML = '<option value="all">Tous les utilisateurs</option>';
                [...users.entries()].sort((a,b) => a[1].localeCompare(b[1])).forEach(([id,label]) => {
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = label;
                    userSelect.appendChild(opt);
                });
            }

            function applyFilters() {
                const q = (document.getElementById('alertSearch').value || '').toLowerCase().trim();
                const type = document.getElementById('alertTypeFilter').value;
                const vehicleId = document.getElementById('vehicleFilter').value;
                const userId = document.getElementById('userFilter').value;
                const status = document.getElementById('statusFilter').value;

                let filtered = alerts.slice();

                if (type !== 'all') filtered = filtered.filter(a => normalizeType(a) === type);
                if (vehicleId !== 'all') filtered = filtered.filter(a => String(a.voiture_id ?? '') === String(vehicleId));
                if (userId !== 'all') filtered = filtered.filter(a => String(a.user_id ?? '') === String(userId));

                if (status === 'open') filtered = filtered.filter(a => !a.processed);
                if (status === 'processed') filtered = filtered.filter(a => !!a.processed);

                if (q) {
                    filtered = filtered.filter(a => {
                        const v = vehicleLabel(a).toLowerCase();
                        const u = userLabel(a).toLowerCase();
                        const m = String(a.message ?? a.location ?? '').toLowerCase();
                        return v.includes(q) || u.includes(q) || m.includes(q);
                    });
                }

                renderAlerts(filtered);
            }

            // ------- Actions -------
            window.goToProfile = function(userId, vehicleId) {
                if (!vehicleId) return;
                if (!userId) {
                    window.location.href = `/voitures/${vehicleId}`;
                    return;
                }
                window.location.href = `/users/${userId}/profile?vehicle_id=${vehicleId}`;
            }

            window.markAsProcessed = async function(alertId) {
                if (!alertId) return;
                if (!confirm('Marquer cette alerte comme traitée ?')) return;

                try {
                    const res = await fetch(`${API_MARK_PROCESSED}/${alertId}/processed`, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                        },
                        body: JSON.stringify({})
                    });

                    const text = await res.text();
                    let json = null;
                    try { json = JSON.parse(text); } catch(e) {}

                    if (!res.ok || !json || json.status !== 'success') {
                        console.error('Failed mark processed', {status: res.status, text});
                        alert((json && json.message) ? json.message : 'Erreur: impossible de traiter cette alerte.');
                        return;
                    }

                    await reload();
                } catch (err) {
                    console.error(err);
                    alert('Erreur réseau.');
                }
            }

            async function reload() {
                alerts = await fetchAlertsFromApi();
                buildFiltersFromData(alerts);
                applyFilters();
            }

            // ------- Events -------
            document.getElementById('filterBtn').addEventListener('click', applyFilters);
            document.getElementById('alertSearch').addEventListener('keyup', applyFilters);
            document.getElementById('alertTypeFilter').addEventListener('change', applyFilters);
            document.getElementById('vehicleFilter').addEventListener('change', applyFilters);
            document.getElementById('userFilter').addEventListener('change', applyFilters);
            document.getElementById('statusFilter').addEventListener('change', applyFilters);
            document.getElementById('refreshBtn').addEventListener('click', reload);

            (async () => { await reload(); })();
        });
    </script>
@endpush
