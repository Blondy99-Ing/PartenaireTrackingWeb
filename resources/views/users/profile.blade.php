@extends('layouts.app')

@section('title', 'Profil & Suivi')

@push('head')
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async defer></script>
@endpush

@section('content')

<div class="space-y-8">

    

    {{-- Carte --}}
    <div class="ui-card p-6">
        <h2 class="text-xl font-bold mb-3">Localisation de mes véhicules ({{ $vehiclesCount }})</h2>
        <div id="userMap" style="height: 450px;" class="rounded"></div>
    </div>

    {{-- Tableau --}}
    <div class="ui-card p-6">
        <h2 class="text-xl font-bold mb-4">Mes véhicules</h2>

        <table class="ui-table w-full">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Immatriculation</th>
                    <th>Marque / Modèle</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

            @foreach($voitures as $v)
                <tr>
                    <td>
                        <img src="{{ asset('storage/'.$v->photo) }}" class="w-12 h-12 rounded object-cover">
                    </td>

                    <td>{{ $v->immatriculation }}</td>

                    <td>{{ $v->marque }} {{ $v->model }}</td>



                    <td>
                        <button onclick="zoomToVehicle({{ $v->id }})" 
                            class="text-primary hover:text-orange-600">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                    </td>
                </tr>
            @endforeach

            </tbody>
        </table>

    </div>

</div>

@endsection


@push('scripts')
<script>
let map;
let markersById = {}; // { vehicleId: google.maps.Marker }
let vehicles = @json($voitures);

// URL backend pour récupérer les positions à jour
const positionsUrl = "{{ route('profile.vehicles.positions') }}";

// -------------------------
// INIT MAP (appelé par Google Maps callback)
// -------------------------
function initMap() {
    map = new google.maps.Map(document.getElementById('userMap'), {
        center: { lat: 4.05, lng: 9.7 },
        zoom: 12
    });

    // Première initialisation des markers
    initMarkersFromInitialData();

    // Rafraîchissement régulier des positions
    setInterval(refreshVehiclePositions, 10000); // 10s
}

// Créer les markers au premier chargement
function initMarkersFromInitialData() {
    vehicles.forEach(v => {
        if (!v.latest_location) return;

        const lat = parseFloat(v.latest_location.latitude);
        const lng = parseFloat(v.latest_location.longitude);
        if (isNaN(lat) || isNaN(lng)) return;

        const marker = new google.maps.Marker({
            position: { lat, lng },
            map: map,
            title: v.immatriculation,
            icon: {
                url: "/assets/icons/car_icon.png",
                scaledSize: new google.maps.Size(40, 40)
            }
        });

        markersById[v.id] = marker;
    });
}

// -------------------------
// RAFRAÎCHIR LES POSITIONS
// -------------------------
function refreshVehiclePositions() {
    fetch(positionsUrl, {
        headers: {
            "Accept": "application/json"
        }
    })
    .then(res => res.json())
    .then(payload => {
        if (!payload.success) return;

        const updatedVehicles = payload.vehicles || [];

        updatedVehicles.forEach(v => {
            if (!v.lat || !v.lng) return;

            const lat = parseFloat(v.lat);
            const lng = parseFloat(v.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            const pos = { lat, lng };

            // 1️⃣ Marker déjà existant ⇒ on le déplace
            if (markersById[v.id]) {
                markersById[v.id].setPosition(pos);
            } else {
                // 2️⃣ Nouveau véhicule ⇒ on crée un marker
                const marker = new google.maps.Marker({
                    position: pos,
                    map: map,
                    title: v.immatriculation,
                    icon: {
                        url: "/assets/icons/car_icon.png",
                        scaledSize: new google.maps.Size(40, 40)
                    }
                });
                markersById[v.id] = marker;
            }

            // 3️⃣ Mettre à jour aussi l’objet vehicles (pour zoomToVehicle)
            const local = vehicles.find(x => x.id === v.id);
            if (local) {
                local.latest_location = {
                    latitude: v.lat,
                    longitude: v.lng,
                };
            }
        });
    })
    .catch(err => {
        console.error("Erreur refresh positions:", err);
    });
}

// -------------------------
// ZOOM depuis le tableau
// -------------------------
function zoomToVehicle(id) {
    const v = vehicles.find(x => x.id === id);
    if (!v || !v.latest_location) return;

    const lat = parseFloat(v.latest_location.latitude);
    const lng = parseFloat(v.latest_location.longitude);
    if (isNaN(lat) || isNaN(lng)) return;

    map.setCenter({ lat, lng });
    map.setZoom(15);

    // Optionnel : animer le marker
    if (markersById[id]) {
        markersById[id].setAnimation(google.maps.Animation.BOUNCE);
        setTimeout(() => markersById[id].setAnimation(null), 1400);
    }
}

// -------------------------
// (Optionnel) MOTEUR : TON CODE EXISTANT
// -------------------------
document.addEventListener("DOMContentLoaded", () => {

    document.querySelectorAll(".engineBtn").forEach(btn => {

        const id = btn.dataset.id;

        // ⚠️ ta route engine-status est GET dans tes routes !
        fetch(`/voitures/${id}/engine-status`)
            .then(res => res.json())
            .then(data => renderButton(btn, data.engine_on))
            .catch(() => renderButton(btn, false));

        btn.addEventListener("click", () => {
            btn.textContent = "Traitement...";
            btn.style.opacity = 0.5;

            fetch(`/voitures/${id}/toggle-engine`, {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                }
            })
                .then(res => res.json())
                .then(data => {
                    renderButton(btn, data.new_state);
                })
                .catch(() => {
                    btn.textContent = "Erreur";
                    btn.style.background = "gray";
                });
        });

    });
});

function renderButton(btn, state) {
    if (state) {
        btn.textContent = "Éteindre";
        btn.style.background = "red";
    } else {
        btn.textContent = "Allumer";
        btn.style.background = "green";
    }
    btn.style.color = "white";
    btn.style.opacity = 1;
}
</script>
@endpush
