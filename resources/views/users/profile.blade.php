@extends('layouts.app')

@section('title', 'Profile')

@push('head')
    {{-- Google Maps API avec callback --}}
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async defer></script>
@endpush

@section('content')
<div class="space-y-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- Colonne 1 : Informations personnelles --}}
        <div class="lg:col-span-1 space-y-6">
            <div class="ui-card p-6 flex flex-col items-center">
                <div class="relative group">
                    <img src="{{ $user->photo ?? 'https://placehold.co/120x120/F58220/ffffff?text=JD' }}" 
                         alt="Profile"
                         id="user-profile-img"
                         class="h-32 w-32 rounded-full object-cover border-4 border-primary shadow-lg mb-4 cursor-pointer transition-transform duration-200 hover:scale-105"
                         onclick="openImageModal(this.src)">
                </div>
                <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">{{ $user->prenom }} {{ $user->nom }}</h2>
                <p class="text-secondary">Propriétaire de Flotte</p>
                <button class="btn-secondary mt-4 py-2 px-4 text-sm font-normal">
                    <i class="fas fa-edit mr-2"></i> Mettre à jour les informations
                </button>
            </div>

            {{-- Détails du compte --}}
            <div class="ui-card p-6 space-y-4">
                <h3 class="text-lg font-semibold border-b pb-2" style="border-color: var(--color-border-subtle);">Détails du Compte</h3>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Prénom:</span><span class="text-primary font-semibold">{{ $user->prenom }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Nom:</span><span class="text-semibold">{{ $user->nom }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Téléphone:</span><span class="text-semibold">{{ $user->phone }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Email:</span><span class="text-semibold">{{ $user->email }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Identifiant:</span><span class="text-semibold">{{ $user->user_unique_id }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Ville:</span><span class="text-semibold">{{ $user->ville }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Quartier:</span><span class="text-semibold">{{ $user->quartier }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">CIN / Passport:</span><span class="text-semibold">{{ $user->cin ?? '-' }}</span></div>
                <div class="flex justify-between items-center text-secondary"><span class="font-medium">Statut:</span><span class="font-semibold text-green-500">Actif</span></div>
            </div>
        </div>

        {{-- Colonne 2/3 : Carte et tableau --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Carte --}}
            <div class="ui-card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);" id="map-title">
                        Carte de Suivi : Tous Mes Véhicules
                    </h2>
                    <button id="showAllVehiclesBtn" class="btn-secondary py-1 px-3 text-sm font-normal hidden">
                        <i class="fas fa-list-ul mr-1"></i> Afficher Tous
                    </button>
                </div>
                <div id="userMap" class="rounded-lg shadow-inner" style="height:450px;"></div>
            </div>

            {{-- Tableau des véhicules --}}
            <div class="ui-card p-6">
                <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
                    Véhicules Associés ({{ $vehiclesCount }})
                </h2>
                <div class="ui-table-container shadow-md">
                    <table id="vehiclesTable" class="ui-table w-full">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Immatriculation</th>
                                <th>Marque/Modèle</th>
                                <th>Statut</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->voitures as $vehicle)
                            <tr id="row-{{ $vehicle->id }}">
                                <td>
                                    <div class="relative group w-10 h-10">
                                        <img src="{{ $vehicle->photo ?? 'https://placehold.co/40x40' }}" 
                                             class="w-10 h-10 object-cover rounded-md cursor-pointer border border-border-subtle transition-transform duration-200 hover:scale-105"
                                             onclick="openImageModal('{{ $vehicle->photo ?? '' }}')">
                                    </div>
                                </td>
                                <td>{{ $vehicle->immatriculation }}</td>
                                <td>{{ $vehicle->marque }} / {{ $vehicle->model }}</td>
                                <td>
                                   {{ $vehicle->couleur }}
                                </td>
                                <td>
                                    <button onclick="zoomToVehicle({{ $vehicle->id }})" class="text-primary hover:text-primary-dark transition-colors p-1">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </button>
                                    <button class="ml-3 text-secondary hover:text-red-500 transition-colors p-1">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Modal image --}}
<div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-75">
    <div class="relative bg-white rounded-lg shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden">
        <button id="closeModalBtn" class="absolute top-4 right-4 text-3xl font-bold text-white hover:text-primary transition-colors bg-gray-900 rounded-full h-10 w-10 flex items-center justify-center">&times;</button>
        <img id="modalImage" src="" alt="Image en grand plan" class="w-full h-auto object-contain max-h-[85vh] p-2">
    </div>
</div>
@endsection

@push('scripts')
<script>
let map;
let markers = [];
const vehiclesData = [
    @foreach($user->voitures as $vehicle)
    {
        id: {{ $vehicle->id }},
        immat: '{{ $vehicle->immatriculation }}',
        model: '{{ $vehicle->marque }} {{ $vehicle->model }}',
        lat: {{ $vehicle->latestLocation->latitude ?? 0 }},
        lng: {{ $vehicle->latestLocation->longitude ?? 0 }},
        status: '{{ $vehicle->latestLocation->status ?? "Arrêté" }}',
        photo_url: '{{ $vehicle->photo ?? "https://placehold.co/600x400" }}'
    },
    @endforeach
];

function initMap() {
    map = new google.maps.Map(document.getElementById('userMap'), {
        center: {lat: 36.82, lng: 10.20},
        zoom: 11
    });
    displayVehiclesOnMap(vehiclesData, true);
}

function getCarIcon(status){
    const color = status==='Alerte'?'red':(status==='En Mouvement'?'green':'yellow');
    return {
        url: '/assets/icons/car_icon_'+color+'.png', // Vérifie que les icônes existent
        scaledSize: new google.maps.Size(50, 50)
    };
}

function displayVehiclesOnMap(data, zoomToFit=false){
    markers.forEach(m=>m.setMap(null));
    markers=[];

    data.forEach(v=>{
        const marker = new google.maps.Marker({
            position:{lat:v.lat,lng:v.lng},
            map: map,
            title: `${v.model} (${v.immat})`,
            icon: getCarIcon(v.status)
        });

        const infowindow = new google.maps.InfoWindow({
            content: `
                <div style="font-size:14px;">
                    <b>${v.model} (${v.immat})</b><br>
                    Statut: ${v.status}<br>
                    <a href="#" onclick="zoomToVehicle(${v.id});return false;" style="color:blue; text-decoration:underline;">Localiser</a>
                </div>`
        });

        marker.addListener('click', ()=>infowindow.open(map, marker));
        markers.push(marker);
    });

    if(zoomToFit && data.length>0){
        const bounds = new google.maps.LatLngBounds();
        data.forEach(v=>bounds.extend({lat:v.lat,lng:v.lng}));
        map.fitBounds(bounds);
        const listener = google.maps.event.addListener(map, "idle", function() {
            if(map.getZoom() > 16) map.setZoom(16);
            google.maps.event.removeListener(listener);
        });
    }
}

function zoomToVehicle(vehicleId){
    const v = vehiclesData.find(x=>x.id===vehicleId);
    if(v){
        displayVehiclesOnMap([v], true);
        document.getElementById('map-title').textContent = `Suivi en Direct : ${v.model} (${v.immat})`;
        document.getElementById('showAllVehiclesBtn').classList.remove('hidden');
    }
}

document.getElementById('showAllVehiclesBtn').addEventListener('click', function(){
    displayVehiclesOnMap(vehiclesData,true);
    document.getElementById('map-title').textContent='Carte de Suivi : Tous Mes Véhicules';
    this.classList.add('hidden');
});

// --- Modal image ---
const imageModal = document.getElementById('imageModal');
const modalImage = document.getElementById('modalImage');
const closeModalBtn = document.getElementById('closeModalBtn');
window.openImageModal = function(url){
    modalImage.src = url;
    imageModal.classList.remove('hidden');
    imageModal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
closeModalBtn.addEventListener('click', function(){
    imageModal.classList.add('hidden');
    imageModal.classList.remove('flex');
    document.body.style.overflow = '';
});
imageModal.addEventListener('click', (e)=>{if(e.target.id==='imageModal'){closeModalBtn.click();}});
</script>
@endpush
