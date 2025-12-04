@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@section('content')
<div class="space-y-8">

    {{-- Statistiques 
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Utilisateurs Actifs</p>
                <p class="text-3xl font-bold mt-1 text-primary">{{ $usersCount }}</p>
            </div>
            <div class="text-3xl text-primary opacity-70"><i class="fas fa-users"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Véhicules Assignés</p>
                <p class="text-3xl font-bold mt-1">{{ $vehiclesCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-car-alt"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Associations Actives</p>
                <p class="text-3xl font-bold mt-1">{{ $associationsCount }}</p>
            </div>
            <div class="text-3xl opacity-70"><i class="fas fa-link"></i></div>
        </div>

        <div class="ui-card p-5 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider">Alertes Non-résolues</p>
                <p class="text-3xl font-bold mt-1 text-red-500">{{ $alertsCount }}</p>
            </div>
            <div class="text-3xl text-red-500 opacity-70"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
    </div>
--}}



    {{-- Carte --}}
    <div class="ui-card">
        <h2 class="text-xl font-bold mb-4">Localisation des Véhicules</h2>
        <div id="fleetMap" class="rounded-lg shadow-inner" style="height: 700px;"></div>
    </div>

    {{-- Tableau alertes --}}
    <div class="ui-card">
        <h2 class="text-xl font-bold mb-4">Mes dernières alertes</h2>
        <table class="ui-table w-full">
            <thead>
                <tr>
                    <th>Véhicule</th>
                    <th>Type</th>
                    <th>Utilisateur</th>
                    <th>Heure</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($alerts as $alert)
                    <tr>
                        <td>{{ $alert['vehicle'] }}</td>
                        <td>{{ $alert['type'] }}</td>
                        <td>{{ $alert['users'] ?? '-' }}</td>
                        <td>{{ $alert['time'] }}</td>
                        <td>
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $alert['status_color'] }}" style="color:white;">
                                {{ $alert['status'] }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

{{-- Google Maps --}}
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async></script>

<script>
let map;

function initMap() {
    const vehicles = @json($vehicles);

    map = new google.maps.Map(document.getElementById('fleetMap'), {
        center: { lat: 4.0511, lng: 9.7679 },
        zoom: 12
    });

    const bounds = new google.maps.LatLngBounds();

    vehicles.forEach(v => {
        if(!v.lat || !v.lon) return;

        const marker = new google.maps.Marker({
            position: { lat: v.lat, lng: v.lon },
            map: map,
            title: v.immatriculation,
            icon: {
                url: "/assets/icons/car_icon.png",
                scaledSize: new google.maps.Size(40, 40)
            }
        });

        bounds.extend(marker.getPosition());

        // InfoWindow
        const infoWindow = new google.maps.InfoWindow({
            content: `
                <b>${v.immatriculation}</b><br>
                Status: ${v.status}<br>
                <button onclick="toggleGPS(${v.id}, true)" 
                    class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded mt-2">
                    Allumer
                </button>
                <button onclick="toggleGPS(${v.id}, false)" 
                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded mt-2">
                    Éteindre
                </button>
            `
        });

        marker.addListener('click', () => infoWindow.open(map, marker));
    });

    // Ajustement auto
    if (vehicles.length > 0) {
        map.fitBounds(bounds);
    }
}

function toggleGPS(id, state) {
    fetch(`/voitures/${id}/toggle-engine`, {
        method: "POST",
        headers: { 
            "X-CSRF-TOKEN": "{{ csrf_token() }}",
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ on: state })
    })
    .then(res => res.json())
    .then(data => {
        alert(state ? "Véhicule allumé" : "Véhicule éteint");
    })
    .catch(() => alert("Erreur lors de la commande moteur"));
}
</script>

@endsection
