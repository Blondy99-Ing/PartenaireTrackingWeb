@extends('layouts.app')

@section('title', 'Gestion des Alertes')

@section('content')
<div class="space-y-8">

    {{-- STATS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
        <div class="ui-card p-5 flex items-center justify-between border-l-4 border-red-500">
            <div>
                <p class="text-sm text-secondary uppercase">Alertes Ouvertes</p>
                <p class="text-3xl font-bold text-red-500" id="stat-open">0</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-circle"></i></div>
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
        <h2 class="text-xl font-bold mb-4">Liste Détaillée des Incidents</h2>

        <div class="flex flex-wrap gap-4 mb-4 items-center border-b pb-4">
            <input id="alertSearch" class="ui-input max-w-sm" placeholder="Recherche véhicule / lieu / utilisateur..." />
            <select id="alertTypeFilter" class="ui-select">
                <option value="all">Tous les types</option>
                <option value="geofence">GeoFence</option>
                <option value="speed">Speed</option>
                <option value="engine">Engine</option>
                <option value="safe_zone">Safe Zone</option>
                <option value="unauthorized">Unauthorized Time</option>
            </select>
            <button id="filterBtn" class="btn-primary"><i class="fas fa-filter mr-1"></i> Filtrer</button>
            <button id="refreshBtn" class="btn-secondary"><i class="fas fa-sync-alt mr-1"></i> Rafraîchir</button>
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
                    <tr><td colspan="8" class="text-center text-secondary py-4">Chargement...</td></tr>
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
    const API_MARK_PROCESSED = "{{ url('/alerts') }}";

    const typeStyle = {
        geofence:   { color: 'bg-orange-500', icon: 'fas fa-map-marker-alt', label: 'GeoFence' },
        safe_zone:  { color: 'bg-purple-500',  icon: 'fas fa-shield-alt', label: 'Safe Zone' },
        speed:      { color: 'bg-blue-500',   icon: 'fas fa-tachometer-alt', label: 'Speeding' },
        engine:     { color: 'bg-red-500',    icon: 'fas fa-exclamation-triangle', label: 'Engine' },
        unauthorized:{ color: 'bg-red-600',   icon: 'fas fa-clock', label: 'Unauthorized Time' }
    };

    let alerts = [];

    async function fetchAlertsFromApi() {
        try {
            const res = await fetch(API_INDEX);
            const json = await res.json();
            if(json.status==='success') return json.data;
            return [];
        } catch (err) { console.error(err); return []; }
    }

    function renderAlerts(rows) {
        const tbody = document.getElementById('alerts-tbody');
        tbody.innerHTML='';

        if(!rows.length){
            tbody.innerHTML='<tr><td colspan="8" class="text-center text-secondary py-6">Aucune alerte trouvée.</td></tr>';
            updateStats([]);
            return;
        }

        rows.forEach(a=>{
            const style = typeStyle[a.type] ?? { color:'bg-gray-500', icon:'fas fa-bell', label:a.type ?? 'Unknown' };
            const usersLabel = a.users_labels ?? '-';
            const vehicleLabel = a.voiture ? `${a.voiture.immatriculation} (${a.voiture.marque} ${a.voiture.model})` : 'N/A';
            const alertedHuman = a.alerted_at_human ?? '-';
            const statusText = a.processed ? 'Résolue' : 'Ouverte';
            const statusClass = a.processed ? 'text-green-500' : 'text-red-500';
            const processedBy = a.processed_by_name ?? '-';

            const row = document.createElement('tr');
            row.className='hover:bg-gray-50';
            row.innerHTML=`
                <td><span class="px-3 py-1 rounded-full text-white text-xs font-semibold ${style.color}">
                    <i class="${style.icon} mr-1"></i> ${a.type_label ?? style.label}
                </span></td>
                <td style="color:var(--color-text)">${vehicleLabel}</td>
                <td style="color:var(--color-text)">${usersLabel}</td>
                <td class="text-secondary">${alertedHuman}</td>
                <td class="text-secondary">${a.location ?? '-'}</td>
                <td class="${statusClass} font-bold">${statusText}</td>
                <td>
                    <button class="text-blue-600 hover:text-blue-800 mr-3" title="Voir sur le profil et carte" 
                        onclick="goToProfile(${a.user_id}, ${a.voiture_id})">
                        <i class="fas fa-map-marker-alt"></i>
                    </button>
                    
                </td>
            `;
            tbody.appendChild(row);
        });

        updateStats(rows);
    }

    function updateStats(data){
        document.getElementById('stat-open').textContent = data.filter(a=>!a.processed).length;
        document.getElementById('stat-geofence').textContent = data.filter(a=>a.type==='geofence').length;
        document.getElementById('stat-speed').textContent = data.filter(a=>a.type==='speed').length;
        document.getElementById('stat-resolved').textContent = data.filter(a=>a.processed).length;
        document.getElementById('stat-safezone').textContent = data.filter(a=>a.type==='safe_zone').length;
    }

    function applyFilters(){
        const q = (document.getElementById('alertSearch').value || '').toLowerCase().trim();
        const type = document.getElementById('alertTypeFilter').value;

        let filtered = alerts.slice();
        if(type && type!=='all') filtered = filtered.filter(a=>a.type===type);
        if(q) filtered = filtered.filter(a=>{
            const vehicle = (a.voiture?.immatriculation ?? '') + ' ' + (a.voiture?.marque ?? '') + ' ' + (a.voiture?.model ?? '');
            const users = a.users_labels ?? '';
            return vehicle.toLowerCase().includes(q) || (a.location ?? '').toLowerCase().includes(q) || users.toLowerCase().includes(q);
        });
        renderAlerts(filtered);
    }

    window.markAsProcessed = async function(id){
        try{
            const res = await fetch(`${API_MARK_PROCESSED}/${id}/processed`, {
                method:'PATCH',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}
            });
            const json = await res.json();
            if(json.status==='success'){ await reload(); }
        } catch(err){ console.error(err); }
    }

    window.goToProfile = function(userId, vehicleId){
        if(!userId || !vehicleId) return;
        window.location.href = `/users/${userId}/profile?vehicle_id=${vehicleId}`;
    }

    async function reload(){
        alerts = await fetchAlertsFromApi();
        applyFilters();
    }

    document.getElementById('filterBtn').addEventListener('click', applyFilters);
    document.getElementById('alertSearch').addEventListener('keyup', applyFilters);
    document.getElementById('alertTypeFilter').addEventListener('change', applyFilters);
    document.getElementById('refreshBtn').addEventListener('click', reload);

    (async ()=>{
        alerts = await fetchAlertsFromApi();
        renderAlerts(alerts);
    })();

});
</script>
@endpush
