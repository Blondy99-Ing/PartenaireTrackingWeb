@extends('layouts.app')

@section('content')
<style>
    .styled-input {
        border: 2px solid #0d6efd;
        padding: 10px;
        border-radius: 6px;
        background: #f8f9fa;
        font-size: 15px;
    }
    .styled-input:focus {
        border-color: #6610f2;
        background: #ffffff;
        outline: none;
        box-shadow: 0 0 4px rgba(102,16,242,0.4);
    }
</style>

<div class="container mt-4">
    <h3>Créer une Ville + Géofence</h3>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('villes.store') }}" method="POST" class="mt-3" id="villeForm">
        @csrf
        <div class="mb-3">
            <label><strong>Code Ville</strong></label>
            <input type="text" name="code_ville" id="code_ville" class="form-control styled-input">
        </div>

        <div class="mb-3">
            <label><strong>Nom de la Ville</strong></label>
            <input type="text" name="name" id="name" class="form-control styled-input" required>
        </div>

        <div class="mb-3">
            <label><strong>Géométrie (GeoJSON)</strong></label>
            <textarea name="geom" id="geom" class="form-control styled-input" rows="4" required readonly></textarea>
        </div>

        <button type="submit" class="btn btn-primary" id="saveBtn">Sauvegarder la Ville</button>
    </form>

    <hr class="my-4">

    <div class="mb-3">
        <button id="modeDataBtn" class="btn btn-outline-primary btn-sm">Mode : Sélection zones ONU</button>
        <button id="modeDrawBtn" class="btn btn-outline-secondary btn-sm">Mode : Dessin manuel</button>
        <button id="undoBtn" class="btn btn-warning btn-sm" disabled>⟲ Annuler dernier point</button>
        <button id="closeBtn" class="btn btn-success btn-sm" disabled>✔ Fermer le contour</button>
        <button id="clearSelectionBtn" class="btn btn-danger btn-sm">✖ Annuler sélection</button>
    </div>

    <div id="map" style="height:600px; border-radius:8px; border:3px solid #0d6efd;"></div>
    <div id="info" class="mt-2"></div>
</div>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initMap" async></script>

<script>
let map;
let mode = 'data';
let selectedFeature = null;

// Correct URL (fichier dans public/geojson/)
const geojsonUrl = "/geojson/BNDA_CMR.geojson";

function initMap() {
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 3.8480, lng: 11.5021 },
        zoom: 7
    });

    map.data.loadGeoJson(geojsonUrl, null, function(features) {
        console.log("✔ GeoJSON ONU chargé :", features.length, "polygones");
    });

    map.data.setStyle({
        fillColor: '#2196F3',
        fillOpacity: 0.15,
        strokeColor: '#0D47A1',
        strokeWeight: 1
    });

    map.data.addListener('click', function(event) {
        if (mode !== 'data') return;

        if (selectedFeature) map.data.revertStyle(selectedFeature);

        selectedFeature = event.feature;

        map.data.overrideStyle(selectedFeature, {
            fillColor: '#FF9800',
            strokeColor: '#E65100',
            fillOpacity: 0.45
        });

        const name =
            selectedFeature.getProperty("adm2nm") ||
            selectedFeature.getProperty("adm1nm") ||
            selectedFeature.getProperty("cty_nm") ||
            "Zone";

        document.getElementById("name").value = name;
        document.getElementById("info").innerHTML = `<b>Zone sélectionnée :</b> ${name}`;

        const geom = selectedFeature.getGeometry().toJSON();
        document.getElementById("geom").value = JSON.stringify(geom);
    });
}

window.initMap = initMap;
</script>
@endsection
