@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    <div class="flex justify-end">
        <button id="toggle-form" class="btn-primary">
            <i class="fas fa-plus mr-2"></i>
            @if(isset($voitureEdit))
                Modifier le véhicule
            @else
                Ajouter un véhicule
            @endif
        </button>
    </div>

    <div id="vehicle-form" class="ui-card mt-4 max-h-0 overflow-hidden opacity-0 transition-all duration-500 ease-in-out @if($errors->any() || isset($voitureEdit)) is-error-state @endif">
        <h2 class="text-xl font-bold font-orbitron mb-6">
            @if(isset($voitureEdit))
                Modifier un Véhicule
            @else
                Ajouter un Véhicule
            @endif
        </h2>

        @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-2">
            <strong class="font-bold">Erreurs de validation:</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form id="vehicle-form-el" action="@if(isset($voitureEdit)) {{ route('tracking.vehicles.update', $voitureEdit->id) }} @else {{ route('tracking.vehicles.store') }} @endif" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if(isset($voitureEdit))
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="immatriculation" class="block text-sm font-medium text-secondary">Immatriculation</label>
                    <input type="text" class="ui-input-style mt-1" id="immatriculation" name="immatriculation" placeholder="ABC-123-XY"
                        value="{{ old('immatriculation', $voitureEdit->immatriculation ?? '') }}" required>
                </div>
                <div>
                    <label for="model" class="block text-sm font-medium text-secondary">Modèle</label>
                    <input type="text" class="ui-input-style mt-1" id="model" name="model" placeholder="SUV, Berline, etc."
                        value="{{ old('model', $voitureEdit->model ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="couleur" class="block text-sm font-medium text-secondary">Couleur</label>
                    <input type="text" class="ui-input-style mt-1" id="couleur" name="couleur" placeholder="Noir, Blanc, Rouge..."
                        value="{{ old('couleur', $voitureEdit->couleur ?? '') }}" required>
                </div>
                <div>
                    <label for="marque" class="block text-sm font-medium text-secondary">Marque</label>
                    <input type="text" class="ui-input-style mt-1" id="marque" name="marque" placeholder="Toyota, Renault..."
                        value="{{ old('marque', $voitureEdit->marque ?? '') }}" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mac_id_gps" class="block text-sm font-medium text-secondary">Numéro GPS</label>
                    <input type="text" class="ui-input-style mt-1" id="mac_id_gps" name="mac_id_gps" placeholder="GPS-XXXX-XXXX"
                        value="{{ old('mac_id_gps', $voitureEdit->mac_id_gps ?? '') }}" required>
                </div>
                <div>
                    <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                    <input type="file" class="ui-input-style mt-1" id="photo" name="photo">
                    @if(isset($voitureEdit) && $voitureEdit->photo)
                        <img src="{{ asset('storage/' . $voitureEdit->photo) }}" class="h-10 w-10 object-cover rounded mt-2">
                    @endif
                </div>
            </div>

            {{-- ========= NOUVEAU : Sélection de ville (depuis geojson) ========= --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label for="city_select" class="block text-sm font-medium text-secondary">Ville (choisir ou personnaliser)</label>
                    <select id="city_select" class="ui-input-style mt-1">
                        <option value="">-- Choisir une ville --</option>
                        {{-- options remplies dynamiquement par JS --}}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-secondary">Type de geofence</label>
                    <div class="mt-1">
                        <label class="inline-flex items-center mr-4">
                            <input type="radio" name="geofence_mode" value="city" checked class="form-radio" id="mode_city">
                            <span class="ml-2">Ville (pré-définie)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="geofence_mode" value="custom" class="form-radio" id="mode_custom">
                            <span class="ml-2">Personnalisé (dessiner)</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Carte --}}
            <div class="mt-4">
                <label class="block text-sm font-medium text-secondary mb-2">Carte - sélectionner / personnaliser</label>
                <div id="map" class="h-96 w-full rounded border border-gray-300"></div>

                <div class="mt-2 flex space-x-2">
                    <button type="button" id="start-draw" class="btn-secondary">Commencer dessin</button>
                    <button type="button" id="finish-draw" class="btn-primary">Terminer polygone</button>
                    <button type="button" id="undo-point" class="btn-warning">Annuler dernier point</button>
                    <button type="button" id="reset-to-city" class="btn-secondary">Réinitialiser vers ville</button>
                </div>

                <p class="text-xs text-secondary mt-2">Cliquer sur la carte ajoute un point au polygone personnalisé. "Terminer polygone" fermera la surface et remplira le champ prêt à l'envoi.</p>
            </div>

            {{-- Hidden fields que backend va utiliser --}}
            <input type="hidden" name="geofence_polygon" id="geofence_polygon" value='{{ old("geofence_polygon", $voitureEdit->geofence_polygon ?? "") }}'>
            <input type="hidden" name="geofence_city_code" id="geofence_city_code" value='{{ old("geofence_city_code", $voitureEdit->geofence_city_code ?? "") }}'>
            <input type="hidden" name="geofence_city_name" id="geofence_city_name" value='{{ old("geofence_city_name", $voitureEdit->geofence_city_name ?? "") }}'>
            <input type="hidden" name="geofence_is_custom" id="geofence_is_custom" value='{{ old("geofence_is_custom", $voitureEdit->geofence_is_custom ?? 0) }}'>

            <button type="submit" class="btn-primary w-full mt-4">
                <i class="fas fa-save mr-2"></i>
                @if(isset($voitureEdit))
                    Mettre à jour le véhicule
                @else
                    Enregistrer le véhicule
                @endif
            </button>
        </form>
    </div>

    {{-- Liste des véhicules (inchangée) --}}
    <div class="ui-card mt-2">
        <h2 class="text-xl font-bold font-orbitron mb-6">Liste des Véhicules</h2>
        <div class="ui-table-container shadow-md">
            <table id="example" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>GPS</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    <tr>
                        <td>{{ $voiture->immatriculation }}</td>
                        <td>{{ $voiture->model }}</td>
                        <td>{{ $voiture->marque }}</td>
                        <td>{{ $voiture->couleur }}</td>
                        <td>{{ $voiture->mac_id_gps }}</td>
                        <td>
                            @if($voiture->photo)
                            <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo" class="h-10 w-10 object-cover rounded">
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('tracking.vehicles', ['edit' => $voiture->id]) }}" class="btn-secondary btn-edit">
                                <i class="fas fa-edit mr-2"></i>
                            </a>
                            <form action="{{ route('tracking.vehicles.destroy', $voiture->id) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger btn-delete" onclick="return confirm('Voulez-vous vraiment supprimer ce véhicule ?');">
                                    <i class="fas fa-trash mr-2"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Leaflet + styles --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* styles rapides pour les marqueurs/polygones sélectionnés */
.city-polygon { fill-opacity: 0.15; color: #2b8a3e; weight: 2; }
.city-polygon-highlight { fill-opacity: 0.25; color: #ff7f50; weight: 3; }
.custom-polygon { color: #4361ee; fillOpacity: 0.12; weight: 3; dashArray: null; }
.temp-point { background: #fff; border: 2px solid #4361ee; border-radius: 50%; width: 8px; height: 8px; display: block; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM éléments
    const toggleBtn = document.getElementById('toggle-form');
    const form = document.getElementById('vehicle-form');

    const citySelect = document.getElementById('city_select');
    const modeCity = document.getElementById('mode_city');
    const modeCustom = document.getElementById('mode_custom');

    const startDrawBtn = document.getElementById('start-draw');
    const finishDrawBtn = document.getElementById('finish-draw');
    const undoBtn = document.getElementById('undo-point');
    const resetBtn = document.getElementById('reset-to-city');

    const hiddenPolygon = document.getElementById('geofence_polygon');
    const hiddenCityCode = document.getElementById('geofence_city_code');
    const hiddenCityName = document.getElementById('geofence_city_name');
    const hiddenIsCustom = document.getElementById('geofence_is_custom');

    // Variables Leaflet
    let map, cityLayerGroup, customLayer, tempLayer;
    let geojsonData = null; // contiendra le contenu du fichier /geojson/ville.geojson
    let selectedCityFeature = null; // feature sélectionnée
    let drawing = false;
    let currentPoints = []; // points LatLng du dessin courant (ordre)
    let tempMarkers = []; // markers pour points temporaires

    // Initialisation map
    function initMap() {
        map = L.map('map').setView([4.0500, 9.7000], 8);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        cityLayerGroup = L.geoJSON(null, {
            style: feature => ({ color: '#2b8a3e', weight: 2, fillOpacity: 0.12 }),
            onEachFeature: onEachCityFeature
        }).addTo(map);

        customLayer = L.layerGroup().addTo(map); // contiendra le polygone personnalisé final
        tempLayer = L.layerGroup().addTo(map); // points/ligne temporaire pendant dessin

        // enable click to add point when drawing=true
        map.on('click', function(e) {
            if (drawing) {
                addPointToDrawing(e.latlng);
            }
        });

        // charger le geojson
        loadCitiesGeoJSON();
    }

    // Ajoute listeners aux features (polygones de villes)
    function onEachCityFeature(feature, layer) {
        // stockage identifiant pour retrouver la feature
        layer.feature = feature;
        layer.on('click', function(e) {
            // sélectionner la ville cliquée
            selectCity(feature, layer);
            // lorsque ville sélectionnée, si mode "city" est actif on coche le select
            if (!drawing) {
                if (feature.properties && feature.properties.code) {
                    citySelect.value = feature.properties.code;
                } else if (feature.id) {
                    citySelect.value = feature.id;
                }
            }
        });
        // tooltip ou popup (nom)
        let name = (feature.properties && (feature.properties.nom_ville || feature.properties.name)) || 'Ville';
        layer.bindTooltip(name, {sticky: true});
    }

    // Charger le fichier geojson/ville.geojson
    async function loadCitiesGeoJSON() {
        try {
            const resp = await fetch('/geojson/ville.geojson', { cache: 'no-cache' });
            if (!resp.ok) throw new Error('Impossible de charger /geojson/ville.geojson');
            // On suppose que le fichier peut être soit FeatureCollection soit objet avec codes (comme dans ton exemple)
            const data = await resp.json();
            geojsonData = normalizeGeoJSON(data);
            populateCitySelect(geojsonData);
            cityLayerGroup.clearLayers();
            cityLayerGroup.addData(geojsonData);
            // zoom to all cities
            try {
                map.fitBounds(cityLayerGroup.getBounds(), { padding: [20,20] });
            } catch(e) { /* no bounds */ }
            // Si la page est en mode édition et valeur existe, charger le polygone initial
            applyInitialFromHidden();
        } catch (err) {
            console.error(err);
            alert('Erreur lors du chargement des villes. Vérifie /geojson/ville.geojson');
        }
    }

    // Normalise deux formats possibles en FeatureCollection:
    // - Format "objet par code" (ton exemple: { "DLA": {nom_ville, polygone: [...]}, ... })
    // - Format GeoJSON classique FeatureCollection
    function normalizeGeoJSON(raw) {
        if (!raw) return { type: 'FeatureCollection', features: [] };

        // Si c'est déjà un FeatureCollection
        if (raw.type === 'FeatureCollection' && Array.isArray(raw.features)) {
            // ensure properties.nom_ville & properties.code exist if possible
            raw.features.forEach(f => {
                if (!f.properties) f.properties = {};
                if (!f.properties.nom_ville) {
                    f.properties.nom_ville = f.properties.name || f.properties.nom || '';
                }
                if (!f.properties.code && f.id) f.properties.code = f.id;
            });
            return raw;
        }

        // Sinon, si c'est un objet par code (ton exemple)
        // Exemple: { "DLA": { "nom_ville": "Douala", "polygone": [ [lat,lng], ... ] }, ... }
        const features = [];
        for (const code in raw) {
            if (!raw.hasOwnProperty(code)) continue;
            const item = raw[code];
            const name = item.nom_ville || item.name || code;
            const poly = item.polygone || item.polygon || item.coordinates;
            if (!poly) continue;
            // ton exemple semble liste [ [lat,lng], ... ] -> Leaflet/GeoJSON attend [ [lng,lat], ... ]
            const coords = poly.map(pt => {
                // si pt = [lat, lng] (ex: [3.86,11.52]) => retourner [lng, lat]
                if (pt.length >= 2) return [pt[1], pt[0]];
                return pt;
            });
            // ensure closed ring for GeoJSON (first == last)
            if (coords.length > 0) {
                const first = coords[0];
                const last = coords[coords.length - 1];
                if (first[0] !== last[0] || first[1] !== last[1]) coords.push(first);
            }
            const feature = {
                type: 'Feature',
                properties: { nom_ville: name, code: code },
                geometry: {
                    type: 'Polygon',
                    coordinates: [ coords ]
                }
            };
            features.push(feature);
        }
        return { type: 'FeatureCollection', features };
    }

    // Remplit le select citySelect
    function populateCitySelect(geojson) {
        citySelect.innerHTML = '<option value="">-- Choisir une ville --</option>';
        geojson.features.forEach((f, idx) => {
            const code = (f.properties && (f.properties.code || f.id)) || ('C' + idx);
            const name = (f.properties && (f.properties.nom_ville || f.properties.name)) || code;
            const opt = document.createElement('option');
            opt.value = code;
            opt.textContent = `${name} — ${code}`;
            citySelect.appendChild(opt);
            // stocker l'id généré si absent
            if (!f.properties) f.properties = {};
            f.properties.code = code;
            f.properties.nom_ville = name;
        });
    }

    // Sélection d'une ville (depuis click layer ou select)
    function selectCity(feature, layer) {
        // deselect previous visuals
        cityLayerGroup.eachLayer(l => cityLayerGroup.resetStyle(l));

        // highlight this layer (style)
        if (layer && layer.setStyle) {
            layer.setStyle({ color: '#ff7f50', weight: 3, fillOpacity: 0.25 });
        } else {
            // fallback: find the layer in the group and style it
            cityLayerGroup.eachLayer(l => {
                if (l.feature === feature) l.setStyle({ color: '#ff7f50', weight: 3, fillOpacity: 0.25 });
            });
        }

        selectedCityFeature = feature;
        // remplir champs cachés city
        hiddenCityCode.value = feature.properties.code || '';
        hiddenCityName.value = feature.properties.nom_ville || '';

        // si le mode est city, on copie le polygone dans geofence_polygon
        if (modeCity.checked) {
            const geojson = {
                type: 'Feature',
                properties: { source: 'city', code: feature.properties.code, nom_ville: feature.properties.nom_ville },
                geometry: feature.geometry
            };
            hiddenPolygon.value = JSON.stringify(geojson);
            hiddenIsCustom.value = 0;
        }

        // zoom sur la ville
        const layerBounds = null;
        // find actual layer bounds
        cityLayerGroup.eachLayer(l => {
            if (l.feature === feature) {
                try { map.fitBounds(l.getBounds(), { padding: [20,20] }); } catch(e){ /* ignore */ }
            }
        });
    }

    // Appliquer valeur initiale à partir des hidden (ex: en edit)
    function applyInitialFromHidden() {
        // si champ geofence_polygon existant, on le parse
        const existing = hiddenPolygon.value;
        if (existing) {
            try {
                const parsed = JSON.parse(existing);
                if (parsed && parsed.geometry) {
                    // afficher le polygon dans customLayer
                    customLayer.clearLayers();
                    const lv = L.geoJSON(parsed.geometry, { style: { color: '#4361ee', weight: 3, fillOpacity: 0.12 } }).addTo(customLayer);
                    // set mode custom si c'est un polygon non city
                    if (hiddenIsCustom.value === '1') {
                        modeCustom.checked = true;
                    } else {
                        modeCity.checked = true;
                    }
                }
            } catch(e) { console.warn('geofence_polygon non JSON'); }
        }

        // si city code preset, sélectionner la bonne option + highlight sur carte
        const code = hiddenCityCode.value;
        if (code && geojsonData) {
            const f = geojsonData.features.find(ff => (ff.properties && ff.properties.code) == code);
            if (f) {
                // trigger select visual
                cityLayerGroup.eachLayer(l => {
                    if (l.feature === f) {
                        selectCity(f, l);
                        citySelect.value = code;
                    }
                });
            }
        }
    }

    // Gestion du select change
    citySelect.addEventListener('change', function() {
        const code = this.value;
        if (!code) return;
        if (!geojsonData) return;
        const f = geojsonData.features.find(ff => (ff.properties && ff.properties.code) == code);
        if (f) {
            // trouver layer et sélectionner
            cityLayerGroup.eachLayer(l => {
                if (l.feature === f) selectCity(f, l);
            });
            // si mode city on met hidden polygon
            if (modeCity.checked) {
                hiddenIsCustom.value = 0;
                hiddenPolygon.value = JSON.stringify({ type: 'Feature', properties: { source: 'city', code: f.properties.code }, geometry: f.geometry });
            }
        }
    });

    // Démarrer dessin
    startDrawBtn.addEventListener('click', function() {
        drawing = true;
        currentPoints = [];
        clearTemp();
        hiddenIsCustom.value = 1;
        modeCustom.checked = true;
        // deselect cities visuals
        cityLayerGroup.eachLayer(l => cityLayerGroup.resetStyle(l));
        selectedCityFeature = null;
    });

    // Ajouter un point au dessin courant
    function addPointToDrawing(latlng) {
        currentPoints.push([latlng.lat, latlng.lng]); // stocker lat,lng pour simplicité
        // ajouter marker visuel
        const m = L.circleMarker(latlng, { radius: 6, fillColor: '#4361ee', color: '#fff', weight: 2 }).addTo(tempLayer);
        tempMarkers.push(m);
        // redessiner la ligne/polygon temporaire
        redrawTempShape();
    }

    // redraw ligne/polygon temporaire
    function redrawTempShape() {
        // enlever shape temp précédente
        tempLayer.eachLayer(l => {
            if (l.options && l.options.pane !== 'markerPane') {
                // we will remove existing polyline/polygon markers but we keep circleMarkers for points
            }
        });
        // retirer toutes les polylines/polygons mais pas les point markers
        tempLayer.eachLayer(layer => {
            if (!(layer instanceof L.CircleMarker)) tempLayer.removeLayer(layer);
        });

        if (currentPoints.length === 0) return;

        if (currentPoints.length < 3) {
            // dessiner une polyline
            const latlngs = currentPoints.map(p => [p[0], p[1]]);
            L.polyline(latlngs, { color: '#4361ee', weight: 2, dashArray: '6 4' }).addTo(tempLayer);
        } else {
            // dessiner polygon en cours (non fermé visuellement)
            const latlngs = currentPoints.map(p => [p[0], p[1]]);
            L.polygon(latlngs, { color: '#4361ee', weight: 2, fillOpacity: 0.06 }).addTo(tempLayer);
        }
    }

    // Annuler dernier point
    undoBtn.addEventListener('click', function() {
        if (!drawing || currentPoints.length === 0) return;
        currentPoints.pop();
        const last = tempMarkers.pop();
        if (last) tempLayer.removeLayer(last);
        redrawTempShape();
    });

    // Terminer polygone (fermer et enregistrer)
    finishDrawBtn.addEventListener('click', function() {
        if (!drawing) return;
        if (currentPoints.length < 3) {
            alert('Un polygone doit contenir au moins 3 points.');
            return;
        }
        // construire GeoJSON polygon (GeoJSON attend [ [ [lng,lat], ... ] ])
        const coords = currentPoints.map(p => [p[1], p[0]]);
        // ensure closed ring
        if (coords.length > 0) {
            const first = coords[0];
            const last = coords[coords.length - 1];
            if (first[0] !== last[0] || first[1] !== last[1]) coords.push(first);
        }
        const feature = { type: 'Feature', properties: { source: 'custom' }, geometry: { type: 'Polygon', coordinates: [ coords ] } };
        // afficher dans couche custom
        customLayer.clearLayers();
        L.geoJSON(feature.geometry, { style: { color: '#4361ee', weight: 3, fillOpacity: 0.12 } }).addTo(customLayer);
        // remplir champ hidden prêt pour backend
        hiddenPolygon.value = JSON.stringify(feature);
        hiddenIsCustom.value = 1;
        // arrêter dessin et nettoyer temporaire
        drawing = false;
        currentPoints = [];
        clearTemp();
        alert('Polygone personnalisé prêt.');
    });

    // Reset to selected city (remet la ville sélectionnée dans la couche custom et hidden)
    resetBtn.addEventListener('click', function() {
        if (!selectedCityFeature) {
            alert('Aucune ville sélectionnée.');
            return;
        }
        // replace custom layer with city geometry
        customLayer.clearLayers();
        L.geoJSON(selectedCityFeature.geometry, { style: { color: '#4361ee', weight: 3, fillOpacity: 0.12 } }).addTo(customLayer);
        hiddenPolygon.value = JSON.stringify({ type: 'Feature', properties: { source: 'city', code: selectedCityFeature.properties.code }, geometry: selectedCityFeature.geometry });
        hiddenIsCustom.value = 0;
        drawing = false;
        currentPoints = [];
        clearTemp();
    });

    // Supprime les couches temporaires (markers + polylines)
    function clearTemp() {
        tempLayer.clearLayers();
        tempMarkers = [];
    }

    // Fonction utilitaire pour reset style (Leaflet style reset)
    if (!L.GeoJSON.prototype.resetStyle) {
        // crude fallback if not present
        L.GeoJSON.prototype.resetStyle = function(layer) {
            this.setStyle && this.setStyle(layer);
        };
    }

    // Toggle formulaire
    toggleBtn.addEventListener('click', () => {
        const isHidden = form.classList.contains('max-h-0');
        if (isHidden) {
            form.classList.remove('max-h-0', 'opacity-0');
            form.classList.add('max-h-[2000px]', 'opacity-100');
            setTimeout(() => {
                if (!map) initMap();
                setTimeout(() => map.invalidateSize(), 200);
            }, 300);
        } else {
            form.classList.remove('max-h-[2000px]', 'opacity-100');
            form.classList.add('max-h-0', 'opacity-0');
        }
    });

    // init map if form already visible due to errors or edit
    @if(isset($voitureEdit) || $errors->any())
        initMap();
        setTimeout(() => { map.invalidateSize(); }, 400);
    @endif

    // initial call if user opens form manually
    // (we don't call initMap immediately to avoid tile load before user opens form)
});
</script>

@endsection
