@extends('layouts.app')

@section('title', 'Liste des Trajets')

@section('content')
<div class="p-2 md:p-8 space-y-8">


    {{-- ===================== --}}
    {{-- FILTRES UNIQUEMENT   --}}
    {{-- ===================== --}}
    <div class="ui-card p-6 border border-border-subtle bg-background shadow-md rounded-xl">

        <h2 class="text-xl font-bold mb-4 font-orbitron" style="color: var(--color-text);">
            Filtres et Recherche
        </h2>

        <form method="GET" class="space-y-4">

            {{-- ==== FILTRES RAPIDES ==== --}}
            <div class="flex flex-wrap gap-2 border-b border-border-subtle pb-4 mb-4">

                @php
                $quick = request('quick');
                @endphp

                <button type="submit" name="quick" value="today"
                    class="px-4 py-2 text-sm rounded-full transition 
                    {{ $quick=='today' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Aujourd'hui
                </button>

                <button type="submit" name="quick" value="yesterday"
                    class="px-4 py-2 text-sm rounded-full transition 
                    {{ $quick=='yesterday' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Hier
                </button>

                <button type="submit" name="quick" value="week"
                    class="px-4 py-2 text-sm rounded-full transition 
                    {{ $quick=='week' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Cette semaine
                </button>

                <button type="submit" name="quick" value="month"
                    class="px-4 py-2 text-sm rounded-full transition 
                    {{ $quick=='month' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Ce mois
                </button>

                <button type="submit" name="quick" value="year"
                    class="px-4 py-2 text-sm rounded-full transition 
                    {{ $quick=='year' ? 'bg-primary text-white shadow' : 'bg-hover-subtle text-secondary hover:bg-hover' }}">
                    Cette année
                </button>

            </div>



            {{-- ==== FILTRES AVANCÉS ==== --}}
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">

                {{-- RECHERCHE VÉHICULE --}}
                <div class="md:col-span-2">
                    <label class="text-sm font-medium text-secondary">Recherche Véhicule</label>
                    <div class="relative">
                        <input type="text" name="vehicule" placeholder="Immatriculation"
                            value="{{ request('vehicule') }}" class="ui-input-style pl-10 w-full" />
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-secondary"></i>
                    </div>
                </div>

                {{-- DATES --}}
                <div>
                    <label class="text-sm font-medium text-secondary">Date début</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}"
                        class="ui-input-style w-full" />
                </div>

                <div>
                    <label class="text-sm font-medium text-secondary">Date fin</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}"
                        class="ui-input-style w-full" />
                </div>

                {{-- BOUTON --}}
                <div class="flex justify-end">
                    <button class="btn-primary w-full md:w-auto h-[42px] px-6">
                        <i class="fas fa-filter mr-1"></i> Appliquer
                    </button>
                </div>

            </div>

            {{-- HEURES --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                <div>
                    <label class="text-sm font-medium text-secondary">Heure début</label>
                    <input type="time" name="start_time" value="{{ request('start_time') }}"
                        class="ui-input-style w-full">
                </div>

                <div>
                    <label class="text-sm font-medium text-secondary">Heure fin</label>
                    <input type="time" name="end_time" value="{{ request('end_time') }}" class="ui-input-style w-full">
                </div>

            </div>

        </form>

        <hr class="my-6 border-border-subtle">



        {{-- ===================== --}}
        {{-- TABLEAU DES TRAJETS   --}}
        {{-- ===================== --}}
        <div class="overflow-x-auto">
            <table class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th>Départ</th>
                        <th>Arrivée</th>
                        <th>Distance</th>
                        <th>Vit. Moy</th>
                        <th>Vit. Max</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($trajets as $trajet)
                    <tr class="hover:bg-hover-subtle transition">

                        <td class="font-semibold text-primary">
                            {{ $trajet->voiture->immatriculation ?? 'N/A' }}
                        </td>

                        <td>
                            <span
                                class="font-medium">{{ \Carbon\Carbon::parse($trajet->start_time)->format('d/m/Y H:i') }}</span><br>
                            <span class="text-xs text-secondary">(
                                long:{{ number_format($trajet->start_longitude, 5) }},lat:{{ number_format($trajet->start_latitude, 5) }})</span>
                        </td>

                        <td>
                            <span
                                class="font-medium">{{ \Carbon\Carbon::parse($trajet->end_time)->format('d/m/Y H:i') }}</span><br>
                            <span class="text-xs text-secondary">(
                                long:{{ number_format($trajet->end_longitude, 5) }},lat:{{ number_format($trajet->end_latitude, 5) }})</span>
                        </td>

                        <td class="font-bold text-blue-600">
                            {{ number_format($trajet->total_distance_km, 2) }} km
                        </td>

                        <td class="text-orange-600">
                            {{ number_format($trajet->avg_speed_kmh, 1) }} km/h
                        </td>

                        <td class="text-red-600">
                            {{ number_format($trajet->max_speed_kmh, 1) }} km/h
                        </td>

                        <td>
                            <a href="{{ route('voitures.trajet.detail', ['vehicle_id' => $trajet->vehicle_id, 'trajet_id' => $trajet->id] + request()->query()) }}"
                                class="text-primary hover:text-primary-dark font-medium">
                                <i class="fas fa-eye mr-1"></i> Détails
                            </a>

                        </td>

                    </tr>

                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-6 text-secondary bg-hover-subtle">
                            <i class="fas fa-info-circle mr-1"></i> Aucun trajet trouvé.
                        </td>
                    </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="mt-4 flex justify-end">
            {{ $trajets->appends(request()->query())->links() }}
        </div>

    </div>

</div>
@endsection