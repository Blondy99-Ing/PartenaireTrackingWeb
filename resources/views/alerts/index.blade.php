@extends('layouts.app')

@section('styles')
    <style>
        .alert-card {
            transition: all 0.3s ease;
            border-left: 4px solid #dc3545;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .alert-card.read {
            border-left-color: #6c757d;
            opacity: 0.8;
        }
        .alert-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .vehicle-info {
            display: flex;
            align-items: center;
        }
        .vehicle-details {
            margin-left: 15px;
        }
        .alert-badge {
            font-size: 0.7rem;
            padding: 0.2em 0.6em;
            border-radius: 50px;
            font-weight: 600;
        }
        .location-map {
            height: 120px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .alert-timestamp {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .filters {
            margin-bottom: 20px;
        }
        .unread-count {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 10px;
        }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                🚨 Cars Alerts
                @if($unreadCount > 0)
                    <span class="unread-count">({{ $unreadCount }})</span>
                @endif
            </h2>
            <div class="filters">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-outline-primary filter-btn active" data-filter="all">All</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-filter="unread">Unread</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-filter="read">Read</button>
                </div>
            </div>
        </div>

        <div class="row" id="alerts-container">
            @foreach ($alerts as $alert)
                @php
                    $voiture = $alert->voiture;
                    $location = $voiture->latestLocation;
                    $user = $voiture->user->first(); // just get one user
                @endphp
                <div class="col-md-6 col-lg-4 alert-item" data-status="{{ $alert->read ? 'read' : 'unread' }}">
                    <div class="card alert-card {{ $alert->read ? 'read' : '' }}" id="alert-{{ $alert->id }}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                @if(!$alert->read)
                                    <span class="badge bg-danger alert-badge">NEW</span>
                                @endif
                                <span class="alert-timestamp">{{ $alert->alerted_at->diffForHumans() }}</span>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    @if(!$alert->read)
                                        <form method="POST" action="{{ route('alerts.markAsRead', $alert->id) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                <i class="fas fa-check"></i> Mark as Read
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('alerts.markAsUnread', $alert->id) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item">
                                                <i class="fas fa-undo"></i> Mark as Unread
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="vehicle-info">
                                <div class="vehicle-avatar bg-light rounded-circle text-center d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="fas fa-car"></i>
                                </div>
                                <div class="vehicle-details">
                                    <h5 class="mb-0">{{ $voiture->marque }} {{ $voiture->model }}</h5>
                                    <p class="text-muted mb-0">{{ $voiture->immatriculation }}</p>
                                </div>
                            </div>

                            <hr>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Vehicle ID</small>
                                    <span>{{ $voiture->voiture_unique_id }}</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">MAC ID</small>
                                    <span>{{ $voiture->mac_id_gps }}</span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-12">
                                    <small class="text-muted d-block">Owner</small>
                                    <span>{{ $user->nom ?? '-' }} {{ $user->prenom ?? '' }}</span>
                                    <small class="text-muted d-block mt-1">Phone</small>
                                    <span>{{ $user->phone ?? '-' }}</span>
                                </div>
                            </div>

                            <div class="location-map mb-3" id="map-{{ $alert->id }}">
                                @if($location)
                                    <small class="text-muted d-block">Location</small>
                                    <span>{{ $location->latitude }}, {{ $location->longitude }}</span>
                                    <!-- Map will be initialized here with JavaScript -->
                                @else
                                    <div class="d-flex h-100 align-items-center justify-content-center">
                                        <span class="text-muted">Location unavailable</span>
                                    </div>
                                @endif
                            </div>

                            <div class="action-buttons">
                                <form method="POST" action="{{ route('alerts.turnoff', $voiture->id) }}" class="w-100">
                                    @csrf
                                    <button type="submit" class="btn btn-danger w-100">
                                        <i class="fas fa-power-off"></i> Turn Off Car
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if(count($alerts) == 0)
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                </div>
                <h4>No alerts at this time</h4>
                <p class="text-muted">All vehicles are operating normally</p>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const filterButtons = document.querySelectorAll('.filter-btn');
            const alertItems = document.querySelectorAll('.alert-item');

            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');

                    const filter = this.getAttribute('data-filter');

                    alertItems.forEach(item => {
                        if (filter === 'all') {
                            item.style.display = 'block';
                        } else if (filter === 'read' && item.getAttribute('data-status') === 'read') {
                            item.style.display = 'block';
                        } else if (filter === 'unread' && item.getAttribute('data-status') === 'unread') {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });

            // Initialize maps if using Google Maps or any other map service
            // This is a placeholder - you'll need to implement actual map initialization
            initializeMaps();
        });

        function initializeMaps() {
            // Placeholder for map initialization
            // You would typically call the maps API here
            console.log('Maps should be initialized here');
        }
    </script>
@endsection
