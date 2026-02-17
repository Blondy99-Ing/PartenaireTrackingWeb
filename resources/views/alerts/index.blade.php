{{-- resources/views/alerts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Gestion des Alertes')

@push('styles')
<style>
    .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .7rem;border-radius:9999px;font-size:.72rem;font-weight:700;color:#fff;white-space:nowrap}

    /* ---------------- TOASTS (Telegram-style) ---------------- */
    .toast-stack{
        position: fixed;
        right: 18px;
        bottom: 18px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 12px;
        width: min(380px, calc(100vw - 36px));
        pointer-events: none;
    }

    .toast{
        pointer-events: auto;
        border-radius: 14px;
        background: rgba(255,255,255,.98);
        border: 1px solid rgba(0,0,0,.08);
        box-shadow: 0 12px 28px rgba(0,0,0,.12);
        overflow: hidden;
        transform: translateY(12px);
        opacity: 0;
        transition: all .18s ease;
    }
    .toast.show{ transform: translateY(0); opacity: 1; }

    .toast-head{
        display:flex; align-items:flex-start; justify-content:space-between;
        gap: 10px;
        padding: 12px 12px 8px 12px;
    }
    .toast-title{
        display:flex; align-items:center; gap: 10px;
        font-weight: 900;
        font-size: 13px;
        color: rgba(0,0,0,.82);
        margin-bottom: 2px;
    }
    .toast-meta{
        font-size: 12px;
        color: rgba(0,0,0,.55);
        line-height: 1.2;
    }
    .toast-close{
        border: none;
        background: transparent;
        color: rgba(0,0,0,.45);
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        padding: 2px 6px;
        border-radius: 8px;
    }
    .toast-close:hover{ background: rgba(0,0,0,.06); color: rgba(0,0,0,.7); }

    .toast-body{
        padding: 0 12px 10px 12px;
        font-size: 13px;
        color: rgba(0,0,0,.70);
    }

    .toast-actions{
        display:flex; gap: 8px; justify-content:flex-end;
        padding: 10px 12px 12px 12px;
        background: rgba(0,0,0,.02);
        border-top: 1px solid rgba(0,0,0,.06);
    }
    .toast-btn{
        display:inline-flex; align-items:center; gap: 6px;
        padding: 8px 10px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all .15s ease;
        white-space: nowrap;
    }
    .toast-btn:disabled{opacity:.55; cursor:not-allowed}

    .toast-btn-ignore{ background: rgba(107,114,128,.10); color:#374151; border-color: rgba(107,114,128,.18); }
    .toast-btn-ignore:hover{ background:#374151; color:#fff; border-color:#374151; }

    .toast-btn-read{ background: rgba(59,130,246,.10); color:#2563eb; border-color: rgba(59,130,246,.18); }
    .toast-btn-read:hover{ background:#2563eb; color:#fff; border-color:#2563eb; }

    .toast-dot{
        width: 10px; height: 10px; border-radius: 999px;
        background: rgba(245,130,32,.95);
        box-shadow: 0 0 0 3px rgba(245,130,32,.18);
        margin-top: 2px;
        flex: none;
    }

    .dot-orange{ background:#f58220; box-shadow: 0 0 0 3px rgba(245,130,32,.18); }
    .dot-blue{ background:#3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.18); }
    .dot-purple{ background:#a855f7; box-shadow: 0 0 0 3px rgba(168,85,247,.18); }
    .dot-gray{ background:#6b7280; box-shadow: 0 0 0 3px rgba(107,114,128,.18); }
</style>
@endpush

@section('content')
<div class="space-y-8">

    {{-- STATS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-red-500">
            <div>
                <p class="text-sm text-secondary uppercase">Vol</p>
                <p class="text-3xl font-bold text-red-500" id="stat-stolen">0</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-car-crash"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-orange-500">
            <div>
                <p class="text-sm text-secondary uppercase">Geofence</p>
                <p class="text-3xl font-bold text-orange-500" id="stat-geofence">0</p>
            </div>
            <div class="text-3xl text-orange-500 opacity-70"><i class="fas fa-route"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-blue-500">
            <div>
                <p class="text-sm text-secondary uppercase">Speed</p>
                <p class="text-3xl font-bold text-blue-500" id="stat-speed">0</p>
            </div>
            <div class="text-3xl text-blue-500 opacity-70"><i class="fas fa-tachometer-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-purple-500">
            <div>
                <p class="text-sm text-secondary uppercase">Safe Zone</p>
                <p class="text-3xl font-bold text-purple-500" id="stat-safezone">0</p>
            </div>
            <div class="text-3xl text-purple-500 opacity-70"><i class="fas fa-shield-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-gray-600">
            <div>
                <p class="text-sm text-secondary uppercase">Time Zone</p>
                <p class="text-3xl font-bold text-gray-700" id="stat-timezone">0</p>
            </div>
            <div class="text-3xl text-gray-700 opacity-70"><i class="fas fa-clock"></i></div>
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
                <option value="stolen">Stolen / Vol</option>
            </select>

            <select id="vehicleFilter" class="ui-select">
                <option value="all">Tous les véhicules</option>
            </select>

            <select id="userFilter" class="ui-select">
                <option value="all">Tous les utilisateurs</option>
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
                </tr>
                </thead>
                <tbody id="alerts-tbody">
                <tr><td colspan="5" class="text-center text-secondary py-6">Chargement...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div id="toast-stack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const API_INDEX = "{{ route('alerts.index') }}";
    const API_POLL = "{{ url('/alerts/poll') }}";
    const API_MARK_READ = "{{ url('/alerts') }}";
    const CSRF = "{{ csrf_token() }}";

    const POLL_MS = 30000;
    const TOAST_TTL_MS = 12000;
    const TOAST_MAX_STACK = 6;

    const ALLOWED_TYPES = new Set(['geofence','safe_zone','speed','time_zone']);

    const typeStyle = {
        geofence:   { color: 'bg-orange-500', icon: 'fas fa-route', label: 'GeoFence' , dot: 'dot-orange' },
        safe_zone:  { color: 'bg-purple-500', icon: 'fas fa-shield-alt', label: 'Safe Zone', dot:'dot-purple' },
        speed:      { color: 'bg-blue-500', icon: 'fas fa-tachometer-alt', label: 'Speed', dot:'dot-blue' },
        time_zone:  { color: 'bg-gray-700', icon: 'fas fa-clock', label: 'Time Zone', dot:'dot-gray' },
        stolen:     { color: 'bg-red-700', icon: 'fas fa-car-crash', label: 'Stolen', dot:'dot-red' },
    };

    let alerts = [];
    let pollTimer = null;
    let lastSeenId = 0;
    const shownToastIds = new Set();

    function normalizeType(a) { return a?.type ?? a?.alert_type ?? 'general'; }
    function isAllowedAlert(a) { return ALLOWED_TYPES.has(normalizeType(a)); }

    // ✅ OUVERT = processed false/0/null
    function isOpenAlert(a) {
        const p = a?.processed;
        return (p === false || p === 0 || p === null || typeof p === 'undefined');
    }

    // ✅ Parse "d/m/Y H:i:s" -> Date local
    function parseHumanDate(fr) {
        if (!fr) return null;
        const s = String(fr).trim();
        const m = s.match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/);
        if (!m) return null;
        const dd = Number(m[1]), MM = Number(m[2]) - 1, yyyy = Number(m[3]);
        const hh = Number(m[4]), mm = Number(m[5]), ss = Number(m[6]);
        const d = new Date(yyyy, MM, dd, hh, mm, ss);
        return isNaN(d.getTime()) ? null : d;
    }

    // ✅ AUJOURD'HUI (local) sur alerted_at (ISO) sinon alerted_at_human
    function isTodayAlert(a) {
        let d = null;

        // si ton API renvoie un ISO (recommandé)
        if (a?.alerted_at) {
            const tmp = new Date(a.alerted_at);
            if (!isNaN(tmp.getTime())) d = tmp;
        }

        if (!d) d = parseHumanDate(a?.alerted_at_human);

        if (!d) return false;

        const now = new Date();
        return d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
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

    function toastDotClassByType(t) {
        if (t === 'speed') return 'dot-blue';
        if (t === 'safe_zone') return 'dot-purple';
        if (t === 'geofence') return 'dot-orange';
        if (t === 'time_zone') return 'dot-gray';
        return 'dot-gray';
    }

    // ✅ STATS = uniquement "ouvertes + aujourd'hui" (et allowed types)
    function updateStats(allData) {
        const base = (Array.isArray(allData) ? allData : [])
            .filter(isAllowedAlert)
            .filter(isOpenAlert)
            .filter(isTodayAlert);

        document.getElementById('stat-geofence').textContent =
            base.filter(a => normalizeType(a) === 'geofence').length;

        document.getElementById('stat-speed').textContent =
            base.filter(a => normalizeType(a) === 'speed').length;

        document.getElementById('stat-safezone').textContent =
            base.filter(a => normalizeType(a) === 'safe_zone').length;

        document.getElementById('stat-timezone').textContent =
            base.filter(a => normalizeType(a) === 'time_zone').length;

        // si tu veux aussi le vol, décommente:
        // document.getElementById('stat-stolen').textContent =
        //     base.filter(a => normalizeType(a) === 'stolen').length;

        const now = new Date();
        document.getElementById('lastRefresh').textContent =
            now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
    }

    function renderAlerts(rows) {
        const tbody = document.getElementById('alerts-tbody');
        tbody.innerHTML = '';

        const only = (Array.isArray(rows) ? rows : []).filter(isAllowedAlert);

        if (!only.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-secondary py-6">Aucune alerte trouvée.</td></tr>';
            updateStats(alerts); // ✅ stats basées sur la mémoire globale (pas seulement rows filtrées)
            return;
        }

        only.forEach(a => {
            const t = normalizeType(a);
            const style = typeStyle[t] ?? { color:'bg-gray-500', icon:'fas fa-bell', label: t ?? 'Unknown' };

            const vLabel = vehicleLabel(a);
            const uLabel = userLabel(a);
            const alertedHuman = a.alerted_at_human ?? '-';
            const msg = a.message ?? a.location ?? '-';

            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50';

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
            `;
            tbody.appendChild(row);
        });

        // ✅ stats toujours "aujourd'hui + ouvertes" sur l'ensemble
        updateStats(alerts);
    }

    function buildFiltersFromData(data) {
        const vehicleSelect = document.getElementById('vehicleFilter');
        const userSelect = document.getElementById('userFilter');

        const vehicles = new Map();
        const users = new Map();

        (Array.isArray(data) ? data : []).filter(isAllowedAlert).forEach(a => {
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

        let filtered = alerts.slice().filter(isAllowedAlert);

        if (type !== 'all') filtered = filtered.filter(a => normalizeType(a) === type);
        if (vehicleId !== 'all') filtered = filtered.filter(a => String(a.voiture_id ?? '') === String(vehicleId));
        if (userId !== 'all') filtered = filtered.filter(a => String(a.user_id ?? '') === String(userId));

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

    async function fetchAlertsFromApi() {
        try {
            const url = API_INDEX + (API_INDEX.includes('?') ? '&' : '?') + 'ts=' + Date.now();
            const res = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'Cache-Control':'no-cache', 'Pragma':'no-cache' },
                credentials: 'same-origin'
            });

            const text = await res.text();
            let json = null;
            try { json = JSON.parse(text); } catch(e) {}

            if (!res.ok || !json || json.status !== 'success') return [];

            const data = Array.isArray(json.data) ? json.data : [];

            const maxId = data.reduce((m, a) => Math.max(m, Number(a?.id || 0)), 0);
            if (maxId > lastSeenId) lastSeenId = maxId;

            return data;
        } catch (err) {
            return [];
        }
    }

    async function pollNewAlerts() {
        if (document.visibilityState !== 'visible') return;

        try {
            const url = API_POLL + (API_POLL.includes('?') ? '&' : '?')
                + 'after_id=' + encodeURIComponent(String(lastSeenId))
                + '&ts=' + Date.now();

            const res = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'Cache-Control':'no-cache', 'Pragma':'no-cache' },
                credentials: 'same-origin'
            });

            const text = await res.text();
            let json = null;
            try { json = JSON.parse(text); } catch(e) {}

            if (!res.ok || !json || json.status !== 'success') return;

            let data = Array.isArray(json.data) ? json.data : [];
            const metaMax = Number(json?.meta?.max_id || 0);
            if (metaMax > lastSeenId) lastSeenId = metaMax;

            if (!data.length) return;

            data.forEach(a => showToastForAlert(a));

            alerts = data.concat(alerts);
            if (alerts.length > 2000) alerts = alerts.slice(0, 2000);

            buildFiltersFromData(alerts);
            applyFilters();

        } catch (err) {}
    }

    async function apiMarkRead(alertId) {
        const res = await fetch(`${API_MARK_READ}/${alertId}/read`, {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify({}),
            credentials: 'same-origin'
        });

        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch(e) {}

        if (!res.ok || !json || json.status !== 'success') {
            throw new Error((json && json.message) ? json.message : 'Erreur: impossible de marquer comme lu/ignorer.');
        }
        return json;
    }

    function showToastForAlert(a) {
        if (!a || !a.id) return;
        if (!isAllowedAlert(a)) return;

        const id = Number(a.id);
        if (shownToastIds.has(id)) return;
        shownToastIds.add(id);

        const t = normalizeType(a);
        const style = typeStyle[t] ?? { icon:'fas fa-bell', label: t ?? 'Alert' };

        const vLabel = vehicleLabel(a);
        const uLabel = userLabel(a);
        const when = a.alerted_at_human ?? '';
        const msg = (a.message ?? a.location ?? '').trim();

        const stack = document.getElementById('toast-stack');
        while (stack.children.length >= TOAST_MAX_STACK) {
            stack.removeChild(stack.lastElementChild);
        }

        const el = document.createElement('div');
        el.className = 'toast';
        el.dataset.alertId = String(id);

        el.innerHTML = `
            <div class="toast-head">
                <div style="display:flex; gap:10px; align-items:flex-start;">
                    <div class="toast-dot ${toastDotClassByType(t)}"></div>
                    <div>
                        <div class="toast-title">
                            <i class="${escapeHtml(style.icon)}"></i>
                            <span>${escapeHtml(a.type_label ?? style.label)}</span>
                        </div>
                        <div class="toast-meta">
                            <div><b>${escapeHtml(vLabel)}</b>${when ? ' • ' + escapeHtml(when) : ''}</div>
                            <div>${escapeHtml(uLabel)}</div>
                        </div>
                    </div>
                </div>
                <button class="toast-close" type="button" aria-label="Close">&times;</button>
            </div>
            <div class="toast-body">
                ${escapeHtml(msg || '—')}
            </div>

            <div class="toast-actions">
                <button class="toast-btn toast-btn-read" type="button">
                    <i class="fas fa-eye"></i> Lu
                </button>
                <button class="toast-btn toast-btn-ignore" type="button">
                    <i class="fas fa-eye-slash"></i> Ignorer
                </button>
            </div>
        `;

        stack.insertBefore(el, stack.firstChild);
        requestAnimationFrame(() => el.classList.add('show'));

        const btnClose = el.querySelector('.toast-close');
        const btnRead = el.querySelector('.toast-btn-read');
        const btnIgnore = el.querySelector('.toast-btn-ignore');

        btnClose.addEventListener('click', () => dismissToast(el));

        btnRead.addEventListener('click', async () => {
            setToastBusy(el, true);
            try {
                await apiMarkRead(id);
                alerts = alerts.map(x => (Number(x.id) === id ? {...x, read:true} : x));
                applyFilters();
                dismissToast(el);
            } catch (e) {
                alert(String(e.message || e));
                setToastBusy(el, false);
            }
        });

        btnIgnore.addEventListener('click', async () => {
            setToastBusy(el, true);
            try {
                await apiMarkRead(id);
                alerts = alerts.map(x => (Number(x.id) === id ? {...x, read:true} : x));
                applyFilters();
                dismissToast(el);
            } catch (e) {
                alert(String(e.message || e));
                setToastBusy(el, false);
            }
        });

        const timer = setTimeout(() => {
            if (document.body.contains(el)) dismissToast(el);
        }, TOAST_TTL_MS);

        el.addEventListener('mouseenter', () => clearTimeout(timer), { once: true });
    }

    function setToastBusy(toastEl, busy) {
        const buttons = toastEl.querySelectorAll('button');
        buttons.forEach(b => {
            if (b.classList.contains('toast-close')) return;
            b.disabled = !!busy;
        });
    }

    function dismissToast(el) {
        el.classList.remove('show');
        setTimeout(() => {
            if (el && el.parentNode) el.parentNode.removeChild(el);
        }, 180);
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(pollNewAlerts, POLL_MS);
    }
    function stopPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible') stopPolling();
        else startPolling();
    });

    async function reload() {
        alerts = await fetchAlertsFromApi();
        buildFiltersFromData(alerts);
        applyFilters();
        updateStats(alerts);
    }

    document.getElementById('filterBtn').addEventListener('click', applyFilters);
    document.getElementById('alertSearch').addEventListener('keyup', applyFilters);
    document.getElementById('alertTypeFilter').addEventListener('change', applyFilters);
    document.getElementById('vehicleFilter').addEventListener('change', applyFilters);
    document.getElementById('userFilter').addEventListener('change', applyFilters);
    document.getElementById('refreshBtn').addEventListener('click', reload);

    (async () => {
        await reload();
        startPolling();
    })();
});
</script>
@endpush