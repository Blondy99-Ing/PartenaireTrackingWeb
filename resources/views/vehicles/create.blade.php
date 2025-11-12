<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Vehicle</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">

    <style>
        :root {
            --primary: #FF7800;
            --primary-light: #FFE2CC;
            --primary-dark: #E66A00;
            --primary-gradient: linear-gradient(135deg, #FF7800 0%, #FF9A40 100%);
            --success: #10b981;
            --success-light: #d1fae5;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-600: #6c757d;
            --gray-800: #343a40;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-orange: 0 10px 25px -5px rgba(255, 120, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--gray-100);
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
            background-image:
                radial-gradient(circle at 10% 20%, rgba(255, 120, 0, 0.03) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(255, 120, 0, 0.03) 0%, transparent 20%);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        header {
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }

        h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        h1 i {
            color: var(--primary);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background-color: var(--success-light);
            color: var(--success);
            border: 1px solid #a7f3d0;
        }

        .alert-success i {
            margin-right: 0.5rem;
        }

        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            padding: 2rem;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-gradient);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-800);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 0.5rem;
            transition: all 0.3s;
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 120, 0, 0.15);
            background-color: rgba(255, 120, 0, 0.02);
        }

        .form-label {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .form-label i {
            color: var(--primary);
        }

        .form-file {
            display: block;
            position: relative;
        }

        .file-input {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            background-color: white;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-input:focus,
        .file-input:hover {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            border: none;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: var(--shadow-orange);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-primary:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--primary-dark);
            z-index: -1;
            transition: opacity 0.3s ease;
            opacity: 0;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-orange);
        }

        .btn-primary:hover:before {
            opacity: 1;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .form-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.5rem;
            grid-column: 1 / -1;
        }

        .selected-region {
            display: flex;
            align-items: center;
            background-color: var(--primary-light);
            color: var(--primary);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 500;
            border-left: 4px solid var(--primary);
            animation: fadeIn 0.5s ease;
        }

        .selected-region i {
            margin-right: 0.5rem;
            animation: pulse 1.5s infinite;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .map-container {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .map-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-gradient);
            z-index: 1;
        }

        .map-header {
            background-color: white;
            padding: 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .map-header i {
            color: var(--primary);
            font-size: 1.2rem;
        }

        .map-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-light), transparent);
        }

        #map {
            flex: 1;
            width: 100%;
            background: var(--gray-100);
            min-height: 500px;
        }

        .region-label {
            background-color: white !important;
            border: none !important;
            box-shadow: var(--shadow-orange) !important;
            padding: 0.5rem 1rem !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            color: var(--primary) !important;
            transform: translateY(-5px) !important;
        }

        /* Animation for hover effects */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .card:hover {
            box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.1), 0 10px 15px -5px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .two-column-layout {
                grid-template-columns: 1fr;
            }

            .map-container {
                order: -1;
                margin-bottom: 2rem;
            }

            #map {
                min-height: 400px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
<div class="container">
    <header>
        <h1>
            <div class="logo-container">
                <i class="fas fa-satellite-dish"></i> <span>Proxym</span><span class="highlight">Tracking</span>
            </div>
            <div class="title-divider"></div>
            <div class="page-title"><i class="fas fa-car-side"></i> Add New Vehicle</div>
        </h1>
    </header>

    <style>
        .logo-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 800;
            font-size: 1.6rem;
            color: var(--gray-800);
        }

        .logo-container i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .highlight {
            color: var(--primary);
        }

        .title-divider {
            height: 2rem;
            width: 2px;
            background-color: var(--gray-300);
            margin: 0 1rem;
        }

        .page-title {
            font-size: 1.4rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-title i {
            color: var(--primary);
        }

        h1 {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            h1 {
                flex-direction: column;
                gap: 0.75rem;
            }

            .title-divider {
                display: none;
            }
        }
    </style>

    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="two-column-layout">
        <!-- Vehicle Form -->
        <div class="card">
            <div id="selected-region-display" class="selected-region" style="display: none;">
                <i class="fas fa-map-marker-alt"></i>
                <span id="region-display-text">No region selected</span>
            </div>

            <form method="POST" action="{{ route('vehicles.save') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="immatriculation">
                            <i class="fas fa-id-card"></i> Immatriculation
                        </label>
                        <input type="text" id="immatriculation" name="immatriculation" class="form-control" required placeholder="Enter vehicle number">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="mac_id_gps">
                            <i class="fas fa-satellite-dish"></i> MAC ID GPS
                        </label>
                        <input type="text" id="mac_id_gps" name="mac_id_gps" class="form-control" required placeholder="Enter GPS MAC ID">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="marque">
                            <i class="fas fa-copyright"></i> Marque
                        </label>
                        <input type="text" id="marque" name="marque" class="form-control" required placeholder="Toyota, Honda, etc.">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="model">
                            <i class="fas fa-car"></i> Model
                        </label>
                        <input type="text" id="model" name="model" class="form-control" required placeholder="Corolla, Civic, etc.">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="couleur">
                            <i class="fas fa-palette"></i> Couleur
                        </label>
                        <input type="text" id="couleur" name="couleur" class="form-control" required placeholder="Blue, Black, etc.">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="photo">
                            <i class="fas fa-camera"></i> Photo
                        </label>
                        <div class="form-file">
                            <input type="file" id="photo" name="photo" class="file-input" accept="image/*">
                        </div>
                    </div>

                    <!-- Hidden fields for Region info -->
                    <input type="hidden" id="region_name" name="region_name">
                    <input type="hidden" id="region_polygon" name="region_polygon">

                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Add Vehicle
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Map Container -->
        <div class="map-container">
            <div class="map-header">
                <i class="fas fa-map-marked-alt"></i> Select a Region in Cameroon
            </div>
            <div id="map"></div>
        </div>
    </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize map with Cameroon's coordinates
        let map = L.map('map', {
            center: [7.3697, 12.3547], // Centered on Cameroon
            zoom: 6,
            minZoom: 5,
            maxBounds: [
                [-5, 5], // Southwest coordinates
                [15, 20]  // Northeast coordinates
            ]
        });

        // Load OpenStreetMap tiles with improved styling
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let selectedLayer = null;
        let selectedTooltip = null;

        // Load Cameroon GeoJSON
        fetch('{{ asset('geojson/cameroon_regions.json') }}')
            .then(response => response.json())
            .then(data => {
                const regions = L.geoJSON(data, {
                    style: {
                        color: '#6c757d',
                        weight: 1.5,
                        fillColor: '#FFE2CC',
                        fillOpacity: 0.4
                    },
                    onEachFeature: function(feature, layer) {
                        // Hover effect
                        layer.on('mouseover', function() {
                            if (layer !== selectedLayer) {
                                layer.setStyle({
                                    fillColor: '#FFCFA3',
                                    fillOpacity: 0.6,
                                    weight: 2
                                });
                            }
                        });

                        layer.on('mouseout', function() {
                            if (layer !== selectedLayer) {
                                layer.setStyle({
                                    color: '#6c757d',
                                    weight: 1.5,
                                    fillColor: '#FFE2CC',
                                    fillOpacity: 0.4
                                });
                            }
                        });

                        // Click event
                        layer.on('click', function() {
                            // Remove previous selection
                            if (selectedLayer) {
                                selectedLayer.setStyle({
                                    color: '#9ca3af',
                                    weight: 1.5,
                                    fillColor: '#dbeafe',
                                    fillOpacity: 0.4
                                });
                            }
                            if (selectedTooltip) {
                                map.removeLayer(selectedTooltip);
                            }

                            // Set new selection
                            selectedLayer = layer;
                            layer.setStyle({
                                color: '#2563eb',
                                weight: 3,
                                fillColor: '#93c5fd',
                                fillOpacity: 0.7
                            });

                            const regionName = feature.properties.name || "Unknown Region";
                            const coordinates = feature.geometry.coordinates[0];

                            // Fill hidden inputs
                            document.getElementById('region_name').value = regionName;
                            document.getElementById('region_polygon').value = JSON.stringify({
                                type: 'Polygon',
                                coordinates: [coordinates]
                            });

                            // Show selected region in UI
                            document.getElementById('region-display-text').textContent = `Selected Region: ${regionName}`;
                            document.getElementById('selected-region-display').style.display = 'flex';

                            // Fit map to region bounds with some padding
                            map.fitBounds(layer.getBounds(), { padding: [20, 20] });

                            // Add tooltip showing region name
                            selectedTooltip = L.tooltip({
                                permanent: true,
                                direction: 'center',
                                className: 'region-label'
                            })
                                .setContent(regionName)
                                .setLatLng(layer.getBounds().getCenter())
                                .addTo(map);

                            console.log('Selected Region:', regionName);
                        });
                    }
                }).addTo(map);

                // Fit to Cameroon's bounds
                map.fitBounds(regions.getBounds());

                // Add smooth zoom control
                map.on('zoomend', function() {
                    if (selectedLayer) {
                        selectedLayer.bringToFront();
                    }
                });
            })
            .catch(error => {
                console.error('Error loading GeoJSON:', error);
            });
    });
</script>
</body>
</html>
