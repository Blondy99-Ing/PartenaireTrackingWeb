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
                    <th>GPS</th>
                    <th>Moteur</th>
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
                        <span class="px-2 py-1 text-xs rounded 
                            {{ $v->gps_status === 'Connected' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' }}">
                            {{ $v->gps_status }}
                        </span>
                    </td>

                    <td>
                        <button 
                            class="engineBtn px-3 py-1 rounded text-white"
                            data-id="{{ $v->id }}">
                            Chargement...
                        </button>
                    </td>

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
let markers = [];
let vehicles = @json($voitures);

// -------------------------
// INIT MAP
// -------------------------
function initMap() {
    map = new google.maps.Map(document.getElementById('userMap'), {
        center: { lat: 4.05, lng: 9.7 },
        zoom: 12
    });

    vehicles.forEach(v => {
        if (!v.latest_location) return;

        let marker = new google.maps.Marker({
            position: {
                lat: parseFloat(v.latest_location.latitude),
                lng: parseFloat(v.latest_location.longitude)
            },
            map: map,
            title: v.immatriculation,
            icon: {
                url: "/assets/icons/car_icon.png",
                scaledSize: new google.maps.Size(40, 40)
            }
        });

        markers.push(marker);
    });
}

// Zoom depuis le tableau
function zoomToVehicle(id) {
    let v = vehicles.find(x => x.id === id);
    if (!v || !v.latest_location) return;

    map.setCenter({
        lat: parseFloat(v.latest_location.latitude),
        lng: parseFloat(v.latest_location.longitude)
    });

    map.setZoom(15);
}

// -------------------------
// MOTEUR : STATUT + TOGGLE
// -------------------------

document.addEventListener("DOMContentLoaded", () => {

    document.querySelectorAll(".engineBtn").forEach(btn => {

        const id = btn.dataset.id;

        // Charger le statut moteur réel
        fetch(`/voitures/${id}/engine-status`, {
            method: "POST",
            headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
        })
        .then(res => res.json())
        .then(data => renderButton(btn, data.engine_on))
        .catch(() => renderButton(btn, false));

        // Gestion du clic
        btn.addEventListener("click", () => {

            btn.textContent = "Traitement...";
            btn.style.opacity = 0.5;

            fetch(`/voitures/${id}/toggle-engine`, {
                method: "POST",
                headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
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

// Rendu du bouton
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
