@extends('layouts.app')

@section('title', 'Trajets sur carte')

@push('head')
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async defer></script>

<style>
    #map {
        width: 100%;
        height: calc(.100vh - 260px);
        min-height: 480px;
        border-radius: 0;
        margin: 0;
    }

    .filter-bar {
        width: 100%;
        padding: 12px;
        border-radius: 0;
        background: var(--color-card);
        border: 1px solid var(--color-border-subtle);
        margin-bottom: 0;
        box-shadow: none;
    }
</style>
@endpush


@section('content')
<div class="max-w-7xxl mx-auto p-2 space-y-4">

    <!-- HEADER -->
    <header class="flex justify-between items-center">
        <h1 class="text-3xl font-orbitron font-bold" style="color: var(--color-text);">
            Trajets de <span class="text-primary">{{ $voiture->immatriculation }}</span>
        </h1>

        <a href="{{ route('trajets.index', $filters) }}"
           class="text-secondary hover:text-primary flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </header>



    <!-- BANDE DE FILTRES FULL WIDTH -->
    <div class="filter-bar ui-card shadow-sm">

        <form method="GET" action="{{ url()->current() }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">

            <!-- SELECT PRINCIPAL -->
            <div>
                <label class="text-sm font-medium text-secondary">Filtrer par</label>
                <select id="filter-type" name="quick" class="ui-input-style w-full">
                    <option value="today"    {{ request('quick','today')=='today'?'selected':'' }}>Aujourd'hui</option>
                    <option value="yesterday"{{ request('quick')=='yesterday'?'selected':'' }}>Hier</option>
                    <option value="week"     {{ request('quick')=='week'?'selected':'' }}>Cette semaine</option>
                    <option value="month"    {{ request('quick')=='month'?'selected':'' }}>Ce mois</option>
                    <option value="year"     {{ request('quick')=='year'?'selected':'' }}>Cette année</option>
                    <option value="date"     {{ request('quick')=='date'?'selected':'' }}>Date spécifique</option>
                    <option value="range"    {{ request('quick')=='range'?'selected':'' }}>Plage de dates</option>
                </select>
            </div>

            <!-- DATE UNIQUE -->
            <div id="single-date" class="hidden">
                <label class="text-sm font-medium text-secondary">Date</label>
                <input type="date" name="date" class="ui-input-style w-full"
                       value="{{ request('date') }}">
            </div>

            <!-- PLAGE DE DATES -->
            <div id="date-range" class="hidden">
                <label class="text-sm font-medium text-secondary">Plage de dates</label>
                <div class="grid grid-cols-2 gap-3">
                    <input type="date" name="start_date" class="ui-input-style" value="{{ request('start_date') }}">
                    <input type="date" name="end_date"   class="ui-input-style" value="{{ request('end_date') }}">
                </div>
            </div>

            <!-- HEURES -->
            <div>
                <label class="text-sm font-medium text-secondary">Heures</label>
                <div class="grid grid-cols-2 gap-3">
                    <input type="time" name="start_time" class="ui-input-style"
                           value="{{ request('start_time') }}">
                    <input type="time" name="end_time" class="ui-input-style"
                           value="{{ request('end_time') }}">
                </div>
            </div>

            <!-- BOUTON -->
            <div class="flex justify-end">
                <button class="btn-primary px-8 h-[42px] flex items-center justify-center">
                    <i class="fas fa-filter mr-2"></i> Filtrer
                </button>
            </div>

        </form>
    </div>


    <!-- GOOGLE MAP -->
    <div id="map" class="shadow-md border border-border-subtle"></div>



    <!-- RÉSUMÉ COLLÉ À LA CARTE -->
    <div class="ui-card flex flex-wrap justify-around text-center mt-0 rounded-none border-t-0">

        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $trajets->count() }}</p>
            <p class="text-sm text-secondary">Trajets</p>
        </div>

        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $totalDistance }} km</p>
            <p class="text-sm text-secondary">Distance totale</p>
        </div>

        <div class="p-3">
            @php 
                $h = floor($totalDuration / 60); 
                $m = $totalDuration % 60; 
            @endphp
            <p class="text-3xl font-orbitron text-primary">{{ $h }}h {{ $m }}m</p>
            <p class="text-sm text-secondary">Durée totale</p>
        </div>

        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $maxSpeed }} km/h</p>
            <p class="text-sm text-secondary">Vitesse max</p>
        </div>

        <div class="p-3">
            <p class="text-3xl font-orbitron text-primary">{{ $avgSpeed }} km/h</p>
            <p class="text-sm text-secondary">Vitesse moyenne</p>
        </div>

    </div>

</div>



{{-- SCRIPT SELECT UI --}}
<script>
document.addEventListener("DOMContentLoaded", () => {

    const type = document.getElementById("filter-type");
    const single = document.getElementById("single-date");
    const range  = document.getElementById("date-range");

    function updateUI() {
        single.classList.add("hidden");
        range.classList.add("hidden");

        if (type.value === "date")  single.classList.remove("hidden");
        if (type.value === "range") range.classList.remove("hidden");
    }

    updateUI();
    type.addEventListener("change", updateUI);
});
</script>



{{-- GOOGLE MAPS --}}
<script>
function initMap() {

    const mapDiv = document.getElementById("map");
    if (!mapDiv) return;

    let center = { lat: 4.05, lng: 9.7 };

    const trajets = @json($trajets);

    if (trajets.length && trajets[0].start_latitude) {
        center = {
            lat: parseFloat(trajets[0].start_latitude),
            lng: parseFloat(trajets[0].start_longitude)
        };
    }

    const map = new google.maps.Map(mapDiv, {
        zoom: 13,
        center,
        mapTypeId: "roadmap",
        styles: [{ featureType: "poi", stylers: [{ visibility: "off" }] }]
    });

    trajets.forEach(t => {
        if (!t.start_latitude || !t.end_latitude) return;

        const start = { lat: parseFloat(t.start_latitude), lng: parseFloat(t.start_longitude) };
        const end   = { lat: parseFloat(t.end_latitude),   lng: parseFloat(t.end_longitude) };

        new google.maps.Polyline({
            path: [start, end],
            strokeColor: getComputedStyle(document.documentElement)
                        .getPropertyValue('--color-primary'),
            strokeOpacity: 1,
            strokeWeight: 4,
            map
        });
    });
}
</script>

@endsection
