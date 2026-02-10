@extends('layouts.app')

@section('title', 'Associations Chauffeur ↔ Véhicule')

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

    {{-- Table --}}
    <div class="ui-card">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <h2 class="text-xl font-bold font-orbitron">Associations actives</h2>

            <div class="flex gap-2">
                <a href="{{ route('tracking.vehicles') ?? '#' }}" class="btn-secondary text-sm">
                    <i class="fas fa-car mr-2"></i> Véhicules
                </a>
                <a href="{{ route('users.index') ?? '#' }}" class="btn-secondary text-sm">
                    <i class="fas fa-users mr-2"></i> Chauffeurs
                </a>
            </div>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="assocTable" class="ui-table w-full">
                <thead>
                <tr>
                    <th>Véhicule</th>
                    <th>Chauffeur</th>
                    <th>Téléphone</th>
                    <th>GPS</th>
                    <th>Date affectation</th>
                    <th>Par</th>
                    <th>Note</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach(($items ?? []) as $row)
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
                        <td class="text-xs">{{ $row->voiture?->mac_id_gps }}</td>
                        <td class="text-xs">{{ optional($row->assigned_at)->format('d/m/Y H:i') }}</td>
                        <td class="text-xs">
                            {{ $row->assigner?->prenom }} {{ $row->assigner?->nom }}
                        </td>
                        <td class="text-xs">{{ $row->note }}</td>

                        <td class="whitespace-nowrap">
                            <button type="button"
                                    class="text-red-500 hover:text-red-700 p-2"
                                    onclick="unassignAssociation({{ $row->voiture_id }}, {{ $row->chauffeur_id }})"
                                    title="Désaffecter">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 text-xs text-secondary">
            Conseil: utilisez la recherche DataTables.
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && $.fn.DataTable) {
        $('#assocTable').DataTable({
            pageLength: 25,
            ordering: true,
            searching: true,
            info: true,
            // ✅ PAS de chargement externe (évite CORS)
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

async function unassignAssociation(voitureId, chauffeurId) {
    if (!confirm("Confirmer la désaffectation de cette association ?")) return;

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const url  = @json(route('partner.affectations.unassign'));

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type':'application/json',
            'Accept':'application/json',
            'X-CSRF-TOKEN': CSRF
        },
        body: JSON.stringify({
            voiture_id: voitureId,
            chauffeur_id: chauffeurId,
            note: 'Désaffectation manuelle'
        })
    });

    const json = await res.json().catch(()=>null);
    if (!res.ok || !json?.ok) {
        alert(json?.message || 'Erreur désaffectation');
        return;
    }

    alert(json.message || 'Désaffecté');
    window.location.reload();
}
</script>
@endpush
@endsection
