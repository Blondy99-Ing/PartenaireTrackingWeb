@extends('layouts.app')

@section('title', 'Alertes — Flotte Partenaire')

@push('styles')
    <style>
        .alert-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #fff;
        }
        .alert-badge.geofence  { background: #f97316; }
        .alert-badge.safe_zone { background: #8b5cf6; }
        .alert-badge.speed     { background: #3b82f6; }
        .alert-badge.time_zone { background: #eab308; color: #1a1a1a; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .status-badge.open      { background: rgba(239,68,68,0.12); color: #ef4444; }
        .status-badge.processed { background: rgba(34,197,94,0.12);  color: #22c55e; }

        .stat-card {
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover { transform: translateY(-2px); }
        .stat-card::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.04;
            border-radius: inherit;
        }

        .filter-form select,
        .filter-form input[type="date"],
        .filter-form input[type="text"] {
            height: 38px;
            font-size: 0.85rem;
        }

        .process-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            background: rgba(34,197,94,0.1);
            color: #16a34a;
            border: 1px solid rgba(34,197,94,0.25);
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .process-btn:hover {
            background: #22c55e;
            color: #fff;
            border-color: #22c55e;
        }
        .process-btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: rgba(107,114,128,0.1);
            color: #6b7280;
            border-color: rgba(107,114,128,0.2);
        }

        .table-row-unread { border-left: 3px solid #f97316; }
        .table-row-processed { border-left: 3px solid transparent; opacity: 0.72; }

        .flash-success {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            color: #16a34a;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .flash-info {
            background: rgba(59,130,246,0.1);
            border: 1px solid rgba(59,130,246,0.3);
            color: #2563eb;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeInDown 0.3s ease both; }
    </style>
@endpush

@section('content')
    <div class="space-y-6">

        {{-- HEADER --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold" style="color:var(--color-text)">
                    <i class="fas fa-bell text-orange-500 mr-2"></i>Alertes de la Flotte
                </h1>
                <p class="text-sm mt-0.5" style="color:var(--color-text-secondary)">
                    Geofence · Safe Zone · Vitesse · Zone Horaire
                </p>
            </div>
            <div class="text-sm" style="color:var(--color-text-secondary)">
                <i class="fas fa-sync-alt mr-1"></i>
                Actualisé le {{ now()->format('d/m/Y à H:i') }}
            </div>
        </div>

        {{-- FLASH --}}
        @if(session('success'))
            <div class="flash-success animate-fade-in">
                <i class="fas fa-check-circle"></i>
                {{ session('success') }}
            </div>
        @endif
        @if(session('info'))
            <div class="flash-info animate-fade-in">
                <i class="fas fa-info-circle"></i>
                {{ session('info') }}
            </div>
        @endif

        {{-- STATS --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="ui-card stat-card p-5 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color:var(--color-text-secondary)">
                            Non traitées
                        </p>
                        <p class="text-3xl font-black mt-1 text-red-500">
                            {{ $stats->unprocessed ?? 0 }}
                        </p>
                    </div>
                    <div class="text-2xl text-red-400 opacity-60">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <p class="text-xs mt-2" style="color:var(--color-text-secondary)">
                    sur {{ $stats->total ?? 0 }} total
                </p>
            </div>

            <div class="ui-card stat-card p-5 border-l-4 border-orange-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color:var(--color-text-secondary)">
                            Geofence
                        </p>
                        <p class="text-3xl font-black mt-1 text-orange-500">
                            {{ $stats->geofence ?? 0 }}
                        </p>
                    </div>
                    <div class="text-2xl text-orange-400 opacity-60">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                </div>
                <p class="text-xs mt-2" style="color:var(--color-text-secondary)">en attente</p>
            </div>

            <div class="ui-card stat-card p-5 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color:var(--color-text-secondary)">
                            Vitesse
                        </p>
                        <p class="text-3xl font-black mt-1 text-blue-500">
                            {{ $stats->speed ?? 0 }}
                        </p>
                    </div>
                    <div class="text-2xl text-blue-400 opacity-60">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                </div>
                <p class="text-xs mt-2" style="color:var(--color-text-secondary)">en attente</p>
            </div>

            <div class="ui-card stat-card p-5 border-l-4 border-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color:var(--color-text-secondary)">
                            Traitées
                        </p>
                        <p class="text-3xl font-black mt-1 text-green-500">
                            {{ $stats->processed_count ?? 0 }}
                        </p>
                    </div>
                    <div class="text-2xl text-green-400 opacity-60">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
                <p class="text-xs mt-2" style="color:var(--color-text-secondary)">résolues</p>
            </div>
        </div>

        {{-- FILTERS --}}
        <div class="ui-card p-5">
            <form method="GET" action="{{ route('partner.alerts.index') }}" class="filter-form">
                <div class="flex flex-wrap gap-3 items-end">

                    <div class="flex flex-col gap-1 min-w-[160px]">
                        <label class="text-xs font-semibold uppercase tracking-wide" style="color:var(--color-text-secondary)">
                            Véhicule
                        </label>
                        <select name="voiture_id" class="ui-input">
                            <option value="">Tous les véhicules</option>
                            @foreach($voitures as $v)
                                <option value="{{ $v->id }}"
                                    {{ (string)($filters['voiture_id'] ?? '') === (string)$v->id ? 'selected' : '' }}>
                                    {{ $v->immatriculation }} — {{ $v->marque }} {{ $v->model }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 min-w-[150px]">
                        <label class="text-xs font-semibold uppercase tracking-wide" style="color:var(--color-text-secondary)">
                            Type d'alerte
                        </label>
                        <select name="alert_type" class="ui-input">
                            <option value="">Tous les types</option>
                            @foreach($alertTypes as $key => $label)
                                <option value="{{ $key }}"
                                    {{ ($filters['alert_type'] ?? '') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 min-w-[130px]">
                        <label class="text-xs font-semibold uppercase tracking-wide" style="color:var(--color-text-secondary)">
                            Statut
                        </label>
                        <select name="processed" class="ui-input">
                            <option value="">Tous</option>
                            <option value="0" {{ ($filters['processed'] ?? '') === '0' ? 'selected' : '' }}>
                                Non traitées
                            </option>
                            <option value="1" {{ ($filters['processed'] ?? '') === '1' ? 'selected' : '' }}>
                                Traitées
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-1 min-w-[140px]">
                        <label class="text-xs font-semibold uppercase tracking-wide" style="color:var(--color-text-secondary)">
                            Du
                        </label>
                        <input type="date" name="date_from" class="ui-input"
                               value="{{ $filters['date_from'] ?? '' }}">
                    </div>

                    <div class="flex flex-col gap-1 min-w-[140px]">
                        <label class="text-xs font-semibold uppercase tracking-wide" style="color:var(--color-text-secondary)">
                            Au
                        </label>
                        <input type="date" name="date_to" class="ui-input"
                               value="{{ $filters['date_to'] ?? '' }}">
                    </div>

                    <div class="flex gap-2 items-end pb-0.5">
                        <button type="submit" class="btn-primary h-[38px] px-4 flex items-center gap-2">
                            <i class="fas fa-filter text-xs"></i>
                            Filtrer
                        </button>
                        <a href="{{ route('partner.alerts.index') }}"
                           class="btn-secondary h-[38px] px-4 flex items-center gap-2">
                            <i class="fas fa-times text-xs"></i>
                            Reset
                        </a>
                    </div>

                </div>
            </form>
        </div>

        {{-- TABLE --}}
        <div class="ui-card overflow-hidden">

            <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color:var(--color-border)">
                <h2 class="font-bold text-base" style="color:var(--color-text)">
                    Liste des Alertes
                </h2>
                <span class="text-sm" style="color:var(--color-text-secondary)">
                    {{ $alerts->total() }} alerte(s) — page {{ $alerts->currentPage() }}/{{ $alerts->lastPage() }}
                </span>
            </div>

            <div class="ui-table-container overflow-x-auto">
                <table class="ui-table w-full">
                    <thead>
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap">Type</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap">Véhicule</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap">Message</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap">Déclenchée le</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap">Statut</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wider whitespace-nowrap">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($alerts as $alert)

                        @php
                            // controller uses alert_type, and only returns allowed types
                            $type  = $alert->alert_type ?? 'general';
                            $style = $typeStyle[$type] ?? ['color' => 'bg-gray-500', 'icon' => 'fa-bell'];
                            $label = $alertTypes[$type] ?? ucfirst(str_replace('_', ' ', $type));
                            $isProcessed = (bool) $alert->processed;

                            // your DB does NOT show alert_subtype column, so keep it safe:
                            $subtype = $alert->alert_subtype ?? null; // will be null if column doesn't exist
                        @endphp

                        <tr class="{{ $isProcessed ? 'table-row-processed' : 'table-row-unread' }} transition-opacity duration-200">

                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="alert-badge {{ $type }}">
                                    <i class="fas {{ $style['icon'] }}"></i>
                                    {{ $label }}
                                </span>

                                @if(!empty($subtype))
                                    <span class="block text-xs mt-1" style="color:var(--color-text-secondary)">
                                        {{ $subtype }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($alert->voiture)
                                    <div class="font-semibold text-sm" style="color:var(--color-text)">
                                        {{ $alert->voiture->immatriculation }}
                                    </div>
                                    <div class="text-xs mt-0.5" style="color:var(--color-text-secondary)">
                                        {{ $alert->voiture->marque }} {{ $alert->voiture->model }}
                                    </div>
                                @else
                                    <span class="text-sm" style="color:var(--color-text-secondary)">N/A</span>
                                @endif
                            </td>

                            <td class="px-4 py-3 max-w-xs">
                                <span class="text-sm line-clamp-2" style="color:var(--color-text)">
                                    {{ $alert->message ?? '—' }}
                                </span>
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap text-sm" style="color:var(--color-text-secondary)">
                                @if($alert->alerted_at)
                                    <div>{{ $alert->alerted_at->format('d/m/Y') }}</div>
                                    <div class="text-xs">{{ $alert->alerted_at->format('H:i:s') }}</div>
                                @else
                                    —
                                @endif
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($isProcessed)
                                    <span class="status-badge processed">
                                        <i class="fas fa-check-circle text-xs"></i>
                                        Traitée
                                    </span>
                                @else
                                    <span class="status-badge open">
                                        <i class="fas fa-dot-circle text-xs"></i>
                                        Ouverte
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 whitespace-nowrap">
                                @if(! $isProcessed)
                                    <form method="POST"
                                          action="{{ route('partner.alerts.markProcessed', $alert->id) }}"
                                          onsubmit="return confirm('Marquer cette alerte comme traitée ?')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="process-btn">
                                            <i class="fas fa-check text-xs"></i>
                                            Traiter
                                        </button>
                                    </form>
                                @else
                                    <button class="process-btn" disabled>
                                        <i class="fas fa-check-double text-xs"></i>
                                        Traitée
                                    </button>
                                @endif
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center" style="color:var(--color-text-secondary)">
                                <div class="flex flex-col items-center gap-3">
                                    <i class="fas fa-bell-slash text-4xl opacity-30"></i>
                                    <p class="font-medium">Aucune alerte trouvée</p>
                                    <p class="text-sm">Essayez de modifier les filtres</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($alerts->hasPages())
                <div class="px-6 py-4 border-t flex items-center justify-between" style="border-color:var(--color-border)">
                    <div class="text-sm" style="color:var(--color-text-secondary)">
                        Affichage de {{ $alerts->firstItem() }}–{{ $alerts->lastItem() }}
                        sur {{ $alerts->total() }} alertes
                    </div>
                    <div>
                        {{ $alerts->links() }}
                    </div>
                </div>
            @endif

        </div>

    </div>
@endsection
