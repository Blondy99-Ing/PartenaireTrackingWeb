@extends('layouts.app')

@section('title', 'Centre de Gestion des Alertes')

@section('content')

<div class="space-y-8 p-4 md:p-8">

    {{-- Titre et Statistiques --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
        
        <div class="mt-4 md:mt-0 flex space-x-4">
            <div class="ui-card p-3 text-center">
                <p class="text-lg font-bold text-red-500">5</p>
                <p class="text-xs text-secondary">Critiques Non Traitées</p>
            </div>
            <div class="ui-card p-3 text-center">
                <p class="text-lg font-bold text-yellow-500">12</p>
                <p class="text-xs text-secondary">En Cours</p>
            </div>
        </div>
    </div>

    {{-- ================ Section Filtres (Dark Mode Ready) ================ --}}
    <div class="ui-card p-4 flex flex-col sm:flex-row gap-4 items-end">
        
        {{-- Filtre Statut --}}
        <div class="w-full sm:w-1/4">
            <label for="filter_status" class="block text-sm font-medium text-secondary mb-1">Statut</label>
            <select id="filter_status" class="ui-input-style">
                <option value="Ouvertes" selected>Ouvertes</option>
                <option value="En Cours">En Cours</option>
                <option value="Résolues">Résolues</option>
                <option value="Toutes">Toutes</option>
            </select>
        </div>

        {{-- Filtre Sévérité --}}
        <div class="w-full sm:w-1/4">
            <label for="filter_severity" class="block text-sm font-medium text-secondary mb-1">Sévérité</label>
            <select id="filter_severity" class="ui-input-style">
                <option value="Critique" selected>Critique</option>
                <option value="Avertissement">Avertissement</option>
                <option value="Information">Information</option>
            </select>
        </div>

        {{-- Filtre Période --}}
        <div class="w-full sm:w-1/4">
            <label for="filter_date" class="block text-sm font-medium text-secondary mb-1">Période</label>
            <input type="date" id="filter_date" class="ui-input-style" value="{{ date('Y-m-d') }}">
        </div>

        <button id="applyFilterBtn" class="btn-primary w-full sm:w-auto h-[38px] flex items-center justify-center mt-1 sm:mt-0">
            <i class="fas fa-filter mr-2"></i> Filtrer
        </button>
    </div>

    {{-- ================ Tableau des Alertes (Dark Mode Ready) ================ --}}
    <div class="ui-card overflow-x-auto shadow-md">
        <div class="ui-table-container">
            <table id="alertsTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sévérité</th>
                        <th>Immatriculation (Véhicule)</th>
                        <th>Utilisateur</th>
                        <th>Cause</th>
                        <th>Statut</th>
                        <th>Assigné à</th>
                        <th>Date de l'Alerte</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Lignes de données simulées par Blade --}}
                    @php
                        $simulatedAlerts = [
                            ['id' => 'A001', 'severity' => 'Critique', 'immat' => 'TRK-1002', 'user' => 'Dupont Jean', 'cause' => 'Vibration Excessive', 'status' => 'Ouverte', 'assignee' => 'N/A', 'date' => '22/11/2025 09:30', 'status_class' => 'badge-danger', 'row_class' => 'bg-red-50 dark:bg-red-900/20', 'action' => 'Gérer', 'btn_class' => 'btn-primary'],
                            ['id' => 'A002', 'severity' => 'Critique', 'immat' => 'TRK-550A', 'user' => 'Muller Sophie', 'cause' => 'Arrêt Moteur Inattendu', 'status' => 'En Cours', 'assignee' => 'Agent DUPONT', 'date' => '21/11/2025 15:45', 'status_class' => 'badge-warning', 'row_class' => 'bg-yellow-50 dark:bg-yellow-900/20', 'action' => 'Suivre', 'btn_class' => 'btn-warning'],
                            ['id' => 'A003', 'severity' => 'Avertissement', 'immat' => 'CAR-999Z', 'user' => 'Martin Paul', 'cause' => 'Batterie Faible', 'status' => 'Résolue', 'assignee' => 'Agent SMITH', 'date' => '20/11/2025 10:00', 'status_class' => 'badge-success', 'row_class' => '', 'action' => 'Historique', 'btn_class' => 'btn-secondary'],
                        ];
                    @endphp

                    @foreach($simulatedAlerts as $alert)
                    <tr class="hover:bg-hover-subtle transition-colors {{ $alert['row_class'] }}">
                        <td class="font-semibold text-secondary">{{ $alert['id'] }}</td>
                        <td><span class="{{ $alert['status_class'] }}">{{ $alert['severity'] }}</span></td>
                        <td class="font-semibold" style="color: var(--color-text);">{{ $alert['immat'] }}</td>
                        <td>{{ $alert['user'] }}</td>
                        <td>{{ $alert['cause'] }}</td>
                        <td><span class="{{ $alert['status_class'] }}">{{ $alert['status'] }}</span></td>
                        <td>{{ $alert['assignee'] }}</td>
                        <td>{{ $alert['date'] }}</td>
                        <td class="whitespace-nowrap">
                            {{-- Utilisation de l'attribut data-alert-id et de l'événement JS à la place d'une route --}}
                            <button type="button" 
                                class="action-btn {{ $alert['btn_class'] }} text-sm p-2" 
                                title="{{ $alert['action'] }}"
                                data-alert-id="{{ $alert['id'] }}"
                                data-action-type="{{ $alert['action'] }}">
                                <i class="fas fa-{{ $alert['action'] == 'Gérer' ? 'ticket-alt' : ($alert['action'] == 'Suivre' ? 'eye' : 'history') }} mr-1"></i> {{ $alert['action'] }}
                            </button>
                        </td>
                    </tr>
                    @endforeach
                    
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialisation de DataTables
    if ($.fn.DataTable) {
        $('#alertsTable').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" },
            "order": [[0, 'desc']] // Tri par ID (le plus récent) par défaut
        });
    }

    // 2. Simulation du Dynamisme : Gestion du clic sur les boutons d'action
    document.querySelectorAll('.action-btn').forEach(button => {
        button.addEventListener('click', function() {
            const alertId = this.getAttribute('data-alert-id');
            const actionType = this.getAttribute('data-action-type');
            
            // Simulation d'une redirection ou d'une action dynamique
            alert(`Action simulée : ${actionType} l'alerte ${alertId}.\n\n(En production, ceci redirigerait vers la page de gestion ou ouvrirait une modale.)`);
            
            // Pour simuler le passage à "En Cours" après "Gérer"
            if (actionType === 'Gérer') {
                 console.log(`Alerte ${alertId} est maintenant "En Cours".`);
                 // Le code réel mettrait à jour le DOM ou rechargerait la table.
            }
        });
    });

    // 3. Simulation du Dynamisme : Gestion du bouton Filtrer
    document.getElementById('applyFilterBtn').addEventListener('click', function() {
        const filterStatus = document.getElementById('filter_status').value;
        const filterSeverity = document.getElementById('filter_severity').value;
        const filterDate = document.getElementById('filter_date').value;

        // Simulation de l'application du filtre
        alert(`Filtres appliqués (Simulation) :\n- Statut: ${filterStatus}\n- Sévérité: ${filterSeverity}\n- Période: ${filterDate}`);
        
        // En production, vous utiliseriez DataTables API ou une requête AJAX ici.
    });
});
</script>
@endpush

@push('styles')
<style>
/* Styles pour les badges de statut (conservés pour l'affichage visuel) */
.badge-danger {
    @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100;
}
.badge-warning {
    @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100;
}
.badge-success {
    @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100;
}
</style>
@endpush