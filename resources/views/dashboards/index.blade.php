@extends('layouts.app')

@section('title', 'Dashboard de Suivi de Flotte')

@push('head')
    {{-- On ajoute le CSS de Leaflet ici (si non déjà dans app.blade) --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endpush

@section('content')
    <div class="space-y-8">
        {{-- Section du Titre --}}
     

        {{-- Section des Statistiques Clés (Stat Cards) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

            {{-- Carte 1 : Utilisateurs Actifs --}}
            <div class="ui-card p-5 flex items-center justify-between transition duration-300 hover:shadow-lg">
                <div>
                    <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Utilisateurs Actifs</p>
                    <p class="text-3xl font-bold font-orbitron mt-1 text-primary" id="stat-users">154</p>
                </div>
                <div class="text-3xl text-primary opacity-70">
                    <i class="fas fa-users"></i>
                </div>
            </div>

            {{-- Carte 2 : Véhicules Enregistrés --}}
            <div class="ui-card p-5 flex items-center justify-between transition duration-300 hover:shadow-lg">
                <div>
                    <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Véhicules de la Flotte</p>
                    <p class="text-3xl font-bold font-orbitron mt-1" id="stat-vehicles" style="color: var(--color-text);">89</p>
                </div>
                <div class="text-3xl opacity-70" style="color: var(--color-text);">
                    <i class="fas fa-car-alt"></i>
                </div>
            </div>

            {{-- Carte 3 : Véhicules Associés --}}
            <div class="ui-card p-5 flex items-center justify-between transition duration-300 hover:shadow-lg">
                <div>
                    <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Associations Actives</p>
                    <p class="text-3xl font-bold font-orbitron mt-1 text-primary-light" id="stat-associations">72</p>
                </div>
                <div class="text-3xl text-primary-light opacity-70">
                    <i class="fas fa-link"></i>
                </div>
            </div>

            {{-- Carte 4 : Alertes en Cours --}}
            <div class="ui-card p-5 flex items-center justify-between transition duration-300 hover:shadow-lg">
                <div>
                    <p class="text-sm font-semibold text-secondary uppercase tracking-wider">Alertes Non-résolues</p>
                    <p class="text-3xl font-bold font-orbitron mt-1 text-red-500" id="stat-alerts">5</p>
                </div>
                <div class="text-3xl text-red-500 opacity-70">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>

        {{-- Section de la Carte de Suivi de Flotte --}}
        <div class="ui-card">
            <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">Localisation de la Flotte Globale</h2>
            
            {{-- Conteneur de la Carte (Leaflet ou Google Maps) --}}
            <div id="fleetMap" class="rounded-lg shadow-inner" style="height: 500px; background-color: var(--color-bg); border: 1px solid var(--color-border-subtle);">
                <div class="flex items-center justify-center h-full text-secondary">
                    <i class="fas fa-map-marked-alt text-4xl mr-3"></i> 
                    <span>Carte de suivi de la flotte (Simulation)</span>
                </div>
            </div>

            {{-- Légende de la Simulation --}}
            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                <span class="text-secondary"><i class="fas fa-circle text-green-500 mr-1"></i> En mouvement</span>
                <span class="text-secondary"><i class="fas fa-circle text-yellow-500 mr-1"></i> Arrêté</span>
                <span class="text-secondary"><i class="fas fa-circle text-red-500 mr-1"></i> Alerte !</span>
                <button id="refreshMapBtn" class="btn-secondary ml-auto py-1 px-3 text-xs font-normal">
                    <i class="fas fa-redo-alt mr-1"></i> Actualiser (Simuler)
                </button>
            </div>
        </div>

        {{-- Section des Dernières Alertes (Tableau) --}}
        <div class="ui-card">
            <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">Historique des Dernières Alertes</h2>

            <div class="ui-table-container">
                <table id="recentAlertsTable" class="ui-table w-full">
                    <thead>
                        <tr>
                            <th>Véhicule</th>
                            <th>Type d'Alerte</th>
                            <th>Localisation</th>
                            <th>Heure</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody id="alerts-tbody">
                        {{-- Les lignes seront remplies par JS pour la simulation --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>



     {{-- Leaflet JS (pour la carte) --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20n6gzH326fQkP0Yh5Y3e8L53jL9S35t6f1q23xL1/A=" crossorigin=""></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- 1. Simulation des Données Statiques ---
            const stats = {
                users: 154,
                vehicles: 89,
                associations: 72,
                alerts: 5
            };

            const initialAlerts = [
                { vehicle: 'Toyota Hilux (TUN1234)', type: 'Géofence Sortie', location: 'Zone Industrielle', time: '10:05', status: 'Nouveau', color: 'bg-red-500' },
                { vehicle: 'Peugeot 308 (TUN5678)', type: 'Mouvement Inopiné', location: 'Parking Domicile', time: '09:40', status: 'En Cours', color: 'bg-yellow-500' },
                { vehicle: 'Camion Renault (TUN9012)', type: 'Vitesse Excess.', location: 'Autoroute A1', time: 'Hier 18:30', status: 'Résolu', color: 'bg-green-500' },
            ];
            
            // --- 2. Fonctions de Remplissage de l'UI ---

            function fillAlertsTable(alerts) {
                const tbody = document.getElementById('alerts-tbody');
                tbody.innerHTML = ''; 
                alerts.forEach(alert => {
                    const row = `
                        <tr>
                            <td class="font-semibold" style="color: var(--color-text);">${alert.vehicle}</td>
                            <td>${alert.type}</td>
                            <td class="text-secondary">${alert.location}</td>
                            <td>${alert.time}</td>
                            <td><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full ${alert.color}" style="color:white;">${alert.status}</span></td>
                        </tr>
                    `;
                    tbody.insertAdjacentHTML('beforeend', row);
                });
            }

            // --- 3. Initialisation de la Carte (Leaflet pour la simulation) ---

            let map;

            function initMap() {
                // S'assurer que le conteneur a la bonne taille avant d'initialiser Leaflet
                const mapContainer = document.getElementById('fleetMap');
                mapContainer.innerHTML = ''; 
                
                // Coordonnées simulées (Tunis, Tunisie)
                const tunisCoords = [36.8065, 10.1815]; 
                
                map = L.map('fleetMap').setView(tunisCoords, 10);

                // Utilisation des tuiles OpenStreetMap
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(map);

                // Simulation de quelques marqueurs de véhicules (3)
                const vehiclesData = [
                    { lat: 36.85, lon: 10.20, name: 'V101', status: 'Alerte', icon: 'red' },
                    { lat: 36.70, lon: 10.05, name: 'V102', status: 'Mouvement', icon: 'green' },
                    { lat: 36.90, lon: 10.30, name: 'V103', status: 'Arrêté', icon: 'yellow' }
                ];

                vehiclesData.forEach(vehicle => {
                    const iconColor = vehicle.icon === 'red' ? 'text-red-500' : 
                                      vehicle.icon === 'green' ? 'text-green-500' : 'text-yellow-500';
                    
                    const htmlIcon = L.divIcon({
                        className: 'custom-icon', 
                        html: `<div class="p-1 rounded-full ${iconColor} bg-white shadow-xl border-2" style="border-color: currentColor;"><i class="fas fa-car-side"></i></div>`,
                        iconSize: [30, 30], 
                        iconAnchor: [15, 15]
                    });

                    L.marker([vehicle.lat, vehicle.lon], {icon: htmlIcon})
                        .addTo(map)
                        .bindPopup(`<b>Véhicule ${vehicle.name}</b><br>Statut: ${vehicle.status}`);
                });

                // Simuler le changement de style des marqueurs en mode sombre
                // (Bien que l'icône soit en HTML, le style de la carte elle-même ne change pas sans tuiles sombres dédiées ou une couche CSS)
                // Pour la simulation visuelle, nous avons utilisé un divIcon pour que la couleur soit adaptative.
            }

            // --- 4. Logique de Simulation Dynamique (Bouton Actualiser) ---

            document.getElementById('refreshMapBtn').addEventListener('click', function() {
                // Simuler une mise à jour des stats
                const newUsers = stats.users + Math.floor(Math.random() * 5);
                const newAlerts = Math.max(0, stats.alerts + (Math.random() > 0.5 ? 1 : -1));
                
                document.getElementById('stat-users').textContent = newUsers;
                document.getElementById('stat-alerts').textContent = newAlerts;

                // Simuler une nouvelle alerte
                const newAlert = { vehicle: `Véhicule U${Math.floor(Math.random() * 999)}`, type: 'Nouveau Mouvement', location: 'Rue Aléatoire', time: 'Maintenant', status: 'Nouveau', color: 'bg-red-500' };
                initialAlerts.unshift(newAlert); // Ajoute au début du tableau
                if (initialAlerts.length > 5) initialAlerts.pop(); // Garde seulement les 5 dernières

                fillAlertsTable(initialAlerts);
                
                // Re-initialiser la carte pour simuler de nouvelles positions
                map.remove();
                initMap();

                alert('Données et carte de la flotte simulées et actualisées !');
            });

            // --- 5. Exécution ---
            fillAlertsTable(initialAlerts);
            initMap();
        });
    </script>

@endsection

   