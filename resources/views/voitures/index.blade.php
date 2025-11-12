@extends('layouts.app')

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
<!-- Custom CSS -->
<style>
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --accent-color: #4895ef;
        --success-color: #4cc9f0;
        --warning-color: #f72585;
        --danger-color: #e63946;
        --light-bg: #f8f9fa;
        --dark-bg: #212529;
        --text-color: #333;
        --light-text: #f8f9fa;
        --border-radius: 0.5rem;
        --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f5f7fa;
        color: var(--text-color);
    }

    .navbar {
        background-color: white !important;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .navbar-nav .nav-link {
        font-weight: 500;
        color: var(--text-color);
        padding: 0.8rem 1.2rem;
        border-radius: var(--border-radius);
        transition: var(--transition);
    }

    .navbar-nav .nav-link:hover,
    .navbar-nav .nav-item.active .nav-link {
        color: var(--primary-color);
        background-color: rgba(67, 97, 238, 0.1);
    }

    .details {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        padding: 1rem;
        max-width: 100%;
        overflow-x: hidden;
    }

    @media (min-width: 1200px) {
        .details {

            .recentOrders, .recentCustomers {
                background-color: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                padding: 1.5rem;
                transition: var(--transition);
            }

            .recentOrders:hover, .recentCustomers:hover {
                box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            }

            .cardHeader {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
            }

            .cardHeader h2 {
                font-weight: 600;
                color: var(--primary-color);
                font-size: 1.5rem;
                position: relative;
                padding-left: 1rem;
            }

            .cardHeader h2::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                width: 4px;
                background-color: var(--primary-color);
                border-radius: 10px;
            }

            .cardHeader .btn {
                background-color: var(--primary-color);
                color: white;
                border: none;
                border-radius: var(--border-radius);
                padding: 0.5rem 1rem;
                font-weight: 500;
                transition: var(--transition);
            }

            .cardHeader .btn:hover {
                background-color: var(--secondary-color);
                transform: translateY(-2px);
            }

            table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                overflow-x: auto;
            }

            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-bottom: 1rem;
            }

            table th {
                background-color: var(--primary-color);
                color: white;
                font-weight: 500;
                padding: 0.75rem;
                text-align: left;
                white-space: nowrap;
            }

            table th:first-child {
                border-top-left-radius: var(--border-radius);
            }

            table th:last-child {
                border-top-right-radius: var(--border-radius);
            }

            table td {
                padding: 0.75rem;
                border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                vertical-align: middle;
            }

            table tbody tr {
                transition: var(--transition);
            }

            table tbody tr:hover {
                background-color: rgba(67, 97, 238, 0.05);
            }

            table tbody tr:last-child td {
                border-bottom: none;
            }

            table img {
                border-radius: var(--border-radius);
                border: 3px solid white;
                box-shadow: var(--box-shadow);
                object-fit: cover;
                width: 50px;
                height: 50px;
            }

            .btn {
                border-radius: var(--border-radius);
                font-weight: 500;
                padding: 0.6rem 1rem;
                transition: var(--transition);
            }

            .btn-warning {
                background-color: #ff9f1c;
                border: none;
                color: white;
            }

            .btn-danger {
                background-color: var(--danger-color);
                border: none;
            }

            .btn-primary {
                background-color: var(--primary-color);
                border: none;
                padding: 0.8rem 1.5rem;
                font-weight: 600;
                letter-spacing: 0.5px;
            }

            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-row {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                margin-bottom: 1rem;
            }

            @media (min-width: 768px) {
                .form-row {
                    flex-direction: row;
                    flex-wrap: wrap;
                }

                .form-row .form-group {
                    flex: 1;
                    min-width: 200px;
                    margin-bottom: 0;
                }
            }

            label {
                font-weight: 500;
                margin-bottom: 0.5rem;
                display: block;
                color: var(--text-color);
            }

            .form-control {
                width: 100%;
                padding: 0.8rem 1rem;
                border: 1px solid rgba(0, 0, 0, 0.1);
                border-radius: var(--border-radius);
                transition: var(--transition);
            }

            .form-control:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.25);
                outline: none;
            }

            .alert {
                padding: 1rem;
                border-radius: var(--border-radius);
                margin-bottom: 1.5rem;
            }

            .alert-danger {
                background-color: rgba(230, 57, 70, 0.1);
                border-left: 4px solid var(--danger-color);
                color: var(--danger-color);
            }

            #map {
                height: 400px;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                margin-top: 1rem;
                overflow: hidden;
            }

            .debug-info {
                background-color: var(--light-bg);
                padding: 0.75rem;
                border-radius: var(--border-radius);
                margin-bottom: 1.5rem;
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .debug-info p {
                margin: 0;
                font-size: 0.85rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0.5rem;
                background-color: white;
                border-radius: var(--border-radius);
                box-shadow: var(--box-shadow);
                flex: 1;
                min-width: 100px;
            }

            @media (min-width: 576px) {
                .debug-info {
                    padding: 1rem;
                    gap: 1rem;
                }

                .debug-info p {
                    padding: 0.8rem;
                    font-size: 0.9rem;
                }
            }

            .debug-info span {
                font-weight: 600;
                margin-top: 0.3rem;
                color: var(--primary-color);
            }

            .debug-info .icon {
                font-size: 1.2rem;
                margin-bottom: 0.3rem;
            }

            .dataTables_wrapper .dataTables_length select,
            .dataTables_wrapper .dataTables_filter input {
                padding: 0.5rem;
                border-radius: var(--border-radius);
                border: 1px solid rgba(0, 0, 0, 0.1);
            }

            .dataTables_wrapper .dataTables_filter input:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.25);
                outline: none;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button {
                border-radius: var(--border-radius);
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                background: var(--primary-color);
                color: white !important;
                border: none;
            }
</style>

@section('content')

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <button data-mdb-collapse-init class="navbar-toggler" type="button" data-mdb-target="#navbarSupportedContent"
                    aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <!-- Véhicules -->
                    <li class="nav-item {{ request()->is('tracking_vehicles') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('tracking.vehicles') }}">
                            <i class="fas fa-car me-2"></i>Véhicules
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Content Container -->
    <div class="details">
        <!-- Liste des Véhicules -->
        <div class="recentOrders">
            <div class="cardHeader">
                <h2>Liste des Véhicules</h2>
                <a href="#" class="btn">
                    <i class="fas fa-eye me-1"></i>Voir Tout
                </a>
            </div>

            <div class="table-responsive">
                <table id="example" class="table">
                    <thead>
                    <tr>
                        <th>Id</th>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Couleur</th>
                        <th>Marque</th>
                        <th>Numéro GPS</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($voitures as $voiture)
                        <tr>
                            <td>{{ $voiture->voiture_unique_id }}</td>
                            <td>{{ $voiture->immatriculation }}</td>
                            <td>{{ $voiture->model }}</td>
                            <td>{{ $voiture->couleur }}</td>
                            <td>{{ $voiture->marque }}</td>
                            <td>{{ $voiture->mac_id_gps }}</td>
                            <td>
                                <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo" class="img-fluid">
                            </td>
                            <td>
                                <div class="d-flex flex-column flex-md-row gap-2">
                                    <a href="{{ route('tracking.vehicles.edit', $voiture->id) }}" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit me-1"></i>Modifier
                                    </a>
                                    <form action="{{ route('tracking.vehicles.destroy', $voiture->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Êtes-vous sûr ?')">
                                            <i class="fas fa-trash me-1"></i>Supprimer
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Formulaire d'Ajout/Mise à Jour des Véhicules -->
        <div class="recentCustomers">
            <div class="cardHeader">
                <h2>Ajouter ou Modifier un Véhicule</h2>
            </div>

            <div class="debug-info">
                <p><span class="icon">🛰️</span> Latitude <span id="debug-lat"></span></p>
                <p><span class="icon">🛰️</span> Longitude <span id="debug-lng"></span></p>
                <p><span class="icon">📏</span> Radius <span id="debug-radius"></span>m</p>
            </div>

            <script>
                setInterval(() => {
                    document.getElementById('debug-lat').innerText = document.getElementById('geofence_latitude').value;
                    document.getElementById('debug-lng').innerText = document.getElementById('geofence_longitude').value;
                    document.getElementById('debug-radius').innerText = document.getElementById('geofence_radius_input').value;
                }, 1000);
            </script>

            <form action="{{ route('tracking.vehicles.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="form-group">
                    <label for="geofence_radius_input">Geofence Radius (meters)</label>
                    <input type="range" class="form-range" id="geofence_radius_input" value="1000" min="100" max="10000" step="100">
                    <div class="d-flex justify-content-between">
                        <small>100m</small>
                        <small>10000m</small>
                    </div>
                </div>

                <input type="hidden" name="id" value="{{ $voiture->id ?? '' }}">

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-row">
                    <div class="form-group">
                        <label for="immatriculation">Immatriculation</label>
                        <input type="text" class="form-control" id="immatriculation" name="immatriculation" placeholder="ABC-123-XY" value="{{ $voiture->immatriculation ?? old('immatriculation') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="model">Modèle</label>
                        <input type="text" class="form-control" id="model" name="model" placeholder="SUV, Berline, etc." value="{{ $voiture->model ?? old('model') }}" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="couleur">Couleur</label>
                        <input type="text" class="form-control" id="couleur" name="couleur" placeholder="Noir, Blanc, Rouge, etc." value="{{ $voiture->couleur ?? old('couleur') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="marque">Marque</label>
                        <input type="text" class="form-control" id="marque" name="marque" placeholder="Toyota, Renault, etc." value="{{ $voiture->marque ?? old('marque') }}" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mac_id_gps">Numéro GPS</label>
                        <input type="text" class="form-control" id="mac_id_gps" name="mac_id_gps" placeholder="GPS-XXXX-XXXX" value="{{ $voiture->mac_id_gps ?? old('numero_gps') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="photo">Photo du véhicule</label>
                        <input type="file" class="form-control" id="photo" name="photo">
                        @if (isset($voiture) && $voiture->photo)
                            <div class="mt-2">
                                <img src="{{ asset('storage/' . $voiture->photo) }}" alt="Photo actuelle" width="100" class="img-thumbnail">
                            </div>
                        @endif
                    </div>
                </div>

                <input type="hidden" id="geofence_latitude" name="geofence_latitude" value="{{ old('geofence_latitude') }}">
                <input type="hidden" id="geofence_longitude" name="geofence_longitude" value="{{ old('geofence_longitude') }}">
                <input type="hidden" id="geofence_radius" name="geofence_radius" value="{{ old('geofence_radius', 1000) }}">

                <div class="form-group">
                    <label>
                        <i class="fas fa-map-marker-alt me-2"></i>Choisir l'emplacement du véhicule
                    </label>
                    <div id="map"></div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-3">
                    <i class="fas fa-save me-2"></i>Enregistrer le véhicule
                </button>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#example').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                }
            });
        });
    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script>
        const map = L.map('map').setView([4.0500, 9.7000], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Initial circle values
        const defaultLatLng = [4.0500, 9.7000];
        let radius = parseInt(document.getElementById('geofence_radius_input').value);
        let circle = L.circle(defaultLatLng, {
            radius: radius,
            color: '#4361ee',
            fillColor: '#4895ef',
            fillOpacity: 0.3,
            weight: 2
        }).addTo(map);

        // Add marker at center
        let marker = L.marker(defaultLatLng, {
            draggable: true
        }).addTo(map);

        // Initial sync to hidden fields
        updateHiddenFields(defaultLatLng[0], defaultLatLng[1], radius);

        // On map click — move circle and marker
        map.on('click', function (e) {
            circle.setLatLng(e.latlng);
            marker.setLatLng(e.latlng);
            updateHiddenFields(e.latlng.lat, e.latlng.lng, circle.getRadius());
        });

        // When marker is dragged
        marker.on('dragend', function (e) {
            const pos = marker.getLatLng();
            circle.setLatLng(pos);
            updateHiddenFields(pos.lat, pos.lng, circle.getRadius());
        });

        // On radius input change
        document.getElementById('geofence_radius_input').addEventListener('input', function () {
            radius = parseInt(this.value);
            circle.setRadius(radius);
            const center = circle.getLatLng();
            updateHiddenFields(center.lat, center.lng, radius);
        });

        function updateHiddenFields(lat, lng, radius) {
            document.getElementById('geofence_latitude').value = lat.toFixed(8);
            document.getElementById('geofence_longitude').value = lng.toFixed(8);
            document.getElementById('geofence_radius').value = radius.toFixed(0);
        }
    </script>

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

@endsection
