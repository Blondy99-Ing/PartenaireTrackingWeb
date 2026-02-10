@extends('layouts.app')

@section('title', 'Historique des associations')

@section('content')
<div class="space-y-6 p-4 md:p-8">

      {{-- Navigation --}}
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
     style="border-color: var(--color-border-subtle);">

    <div class="flex mt-4 sm:mt-0 space-x-4">
        <a href="{{ route('partner.affectations.index') }}"
           class="py-2 px-4 rounded-lg font-semibold {{ request()->routeIs('partner.affectations.index') ? 'text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary' }} transition-colors">
            <i class="fas fa-link mr-2"></i> Associations
        </a>

        <a href="{{ route('partner.affectations.history') }}"
           class="py-2 px-4 rounded-lg font-semibold {{ request()->routeIs('partner.affectations.history') ? 'text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary' }} transition-colors">
            <i class="fas fa-clock-rotate-left mr-2"></i> Historique
        </a>
    </div>
</div>

    {{-- Flash --}}
    @if(session('status'))
        <div class="p-3 rounded-lg bg-green-100 text-green-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Erreurs --}}
    @if($errors->any())
        <div class="p-3 rounded-lg bg-red-100 text-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Table --}}
    <div class="ui-card">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <h3 class="text-lg font-bold font-orbitron" style="color: var(--color-text);">
                Historique des associations
            </h3>

            <div class="text-xs text-secondary">
                Total : {{ $items->total() ?? 0 }}
            </div>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="historyTable" class="ui-table w-full">
                <thead>
                <tr>
                    <th>Véhicule</th>
                    <th>Chauffeur</th>
                    <th>Téléphone</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Durée</th>
                    <th>Par</th>
                    <th>Note</th>
                </tr>
                </thead>

                <tbody>
                @foreach(($items ?? []) as $row)
                    @php
                        $start = $row->started_at ? \Carbon\Carbon::parse($row->started_at) : null;
                        $end   = $row->ended_at ? \Carbon\Carbon::parse($row->ended_at) : null;

                        $duration = null;
                        if ($start && $end) {
                            $duration = $start->diffForHumans($end, true); // ex: "2 hours"
                        } elseif ($start && !$end) {
                            $duration = 'En cours';
                        }
                    @endphp

                    <tr>
                        <td>
                            <div class="font-semibold">
                                {{ $row->voiture?->immatriculation }}
                            </div>
                            <div class="text-xs text-secondary">
                                {{ $row->voiture?->marque }} {{ $row->voiture?->model }}
                            </div>
                        </td>

                        <td>{{ $row->chauffeur?->prenom }} {{ $row->chauffeur?->nom }}</td>
                        <td class="text-secondary">{{ $row->chauffeur?->phone }}</td>

                        <td class="text-xs">
                            {{ $start ? $start->format('d/m/Y H:i') : '-' }}
                        </td>

                        <td class="text-xs">
                            {{ $end ? $end->format('d/m/Y H:i') : '-' }}
                        </td>

                        <td class="text-xs">
                            {{ $duration ?? '-' }}
                        </td>

                        <td class="text-xs">
                            {{ $row->assigner?->prenom }} {{ $row->assigner?->nom }}
                        </td>

                        <td class="text-xs">
                            {{ $row->note }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination Laravel --}}
        <div class="mt-4">
            {{ $items->links() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.DataTable) {
        $('#historyTable').DataTable({
            pageLength: 25,
            ordering: true,
            searching: true,
            info: true,
            paging: false, // ✅ Important: car on utilise la pagination Laravel
            language: {
                search: "Rechercher :",
                lengthMenu: "Afficher _MENU_ lignes",
                info: "Affichage _START_ à _END_ sur _TOTAL_",
                paginate: { previous: "Précédent", next: "Suivant" },
                zeroRecords: "Aucun résultat",
                infoEmpty: "Aucune donnée",
                infoFiltered: "(filtré sur _MAX_ lignes)"
            }
        });
    }
});
</script>
@endpush
@endsection
