@extends('layouts.app')

@section('title', 'Associations Utilisateurs - Véhicules')

@section('content')
<div class="p-4 md:p-8 space-y-8">

    {{-- Titre Principal --}}
 

    {{-- Messages de succès / erreur (Dark Mode Ready) --}}
    @if(session('success'))
    <div
        class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded ui-card">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div
        class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded ui-card">
        {{ session('error') }}
    </div>
    @endif

    <div class="flex justify-end">
        <div>
            <button type="button" class="btn-primary" id="openAssociationModalBtn">
                <i class="fas fa-plus mr-2"></i> Ajouter une association
            </button>
        </div>
    </div>

    {{-- ================ Modale d'Association (Dark Mode Ready) ================ --}}
    <div id="associationModal"
        class="fixed inset-0 bg-black bg-opacity-75 hidden flex justify-center items-center z-[9999] transition-opacity duration-300">

        <div class="bg-card rounded-2xl w-full max-w-4xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card">
            
            <h2 class="text-xl font-bold font-orbitron mb-6" style="color: var(--color-text);">Associer un utilisateur à un ou plusieurs véhicules</h2>

            <button type="button" id="closeAssociationModalBtn"
                class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
                &times;
            </button>

            <form action="{{ route('association.store') }}" method="POST" class="space-y-4" id="associationForm">
                @csrf
                <div class="flex flex-col md:flex-row gap-6">
                    
                    {{-- Liste Utilisateurs (Dark Mode Ready) --}}
                    <div class="flex-1 bg-background p-4 rounded-xl shadow-inner border border-border-subtle dark:border-border-subtle/50">
                        <label class="block font-medium text-secondary mb-3">Sélectionner l'Utilisateur</label>
                        <input type="text" placeholder="Rechercher utilisateur..." id="searchUser"
                            class="ui-input-style mb-3">

                        <div id="userList" class="space-y-2 max-h-64 overflow-y-auto ui-scroll-style">
                            @foreach($users as $user)
                            <label
                                class="flex items-center gap-3 p-2 hover:bg-hover-subtle rounded cursor-pointer transition-colors user-item">
                                <input type="radio" name="user_unique_id" value="{{ $user->user_unique_id }}"
                                    class="form-radio checked:text-primary focus:ring-primary" required>
                                
                                @if(isset($user->photo) && $user->photo)
                                <img src="{{ asset('storage/' . $user->photo) }}" alt="{{ $user->prenom }}"
                                    class="h-8 w-8 object-cover rounded-full border border-border-subtle">
                                @else
                                <div class="h-8 w-8 rounded-full bg-primary/20 flex items-center justify-center border border-border-subtle text-primary text-sm font-semibold">U</div>
                                @endif
                                
                                <span style="color: var(--color-text);">
                                    {{ $user->nom }} {{ $user->prenom }} 
                                    <span class="text-secondary text-sm font-light">(ID: {{ $user->user_unique_id }})</span>
                                </span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Liste Véhicules (Dark Mode Ready) --}}
                    <div class="flex-1 bg-background p-4 rounded-xl shadow-inner border border-border-subtle dark:border-border-subtle/50">
                        <label class="block font-medium text-secondary mb-3">Sélectionner les Véhicules</label>
                        <input type="text" placeholder="Rechercher véhicule..." id="searchVehicle"
                            class="ui-input-style mb-3">

                        <div id="vehicleList" class="space-y-2 max-h-64 overflow-y-auto ui-scroll-style">
                            @foreach($voitures as $voiture)
                            <label
                                class="flex items-center gap-3 p-2 hover:bg-hover-subtle rounded cursor-pointer transition-colors vehicle-item">
                                <input type="checkbox" name="voiture_unique_id[]"
                                    value="{{ $voiture->voiture_unique_id }}" class="form-checkbox checked:bg-primary focus:ring-primary">
                                
                                @if(isset($voiture->photo) && $voiture->photo)
                                <img src="{{ asset('storage/' . $voiture->photo) }}"
                                    alt="{{ $voiture->immatriculation }}"
                                    class="h-8 w-8 object-cover rounded border border-border-subtle">
                                @else
                                <div class="h-8 w-8 rounded bg-primary/20 flex items-center justify-center border border-border-subtle text-primary text-sm font-semibold">V</div>
                                @endif
                                
                                <span style="color: var(--color-text);">
                                    {{ $voiture->immatriculation }} - {{ $voiture->marque }} 
                                    <span class="text-secondary text-sm font-light">(ID: {{ $voiture->voiture_unique_id }})</span>
                                </span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-6 space-x-3 pt-4 border-t border-border-subtle dark:border-border-subtle/50">
                    <button type="button" class="btn-secondary" id="cancelAssociationBtn">Annuler</button>
                    <button type="submit" class="btn-primary"><i class="fas fa-link mr-2"></i> Associer</button>
                </div>
            </form>
        </div>
    </div>


    {{-- ================ Tableau des associations (Dark Mode Ready) ================ --}}
    <div class="ui-card overflow-x-auto shadow-md">
        <h2 class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">Liste des Associations Utilisateur - Véhicule</h2>
        <div class="ui-table-container">
            <table class="ui-table w-full" id="associationTable">
                <thead>
                    <tr>
                        <th>ID Utilisateur</th>
                        <th>Nom et Prénom</th>
                        <th>ID Véhicule</th>
                        <th>Immatriculation</th>
                        <th>Marque</th>
                        <th>Date d'Association</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($associations as $assoc)
                    <tr class="hover:bg-hover-subtle transition-colors">
                        <td class="text-secondary">{{ $assoc->utilisateur->first()->user_unique_id ?? 'N/A' }}</td>
                        <td style="color: var(--color-text);">
                            {{ $assoc->utilisateur->first()->nom ?? 'N/A' }}
                            {{ $assoc->utilisateur->first()->prenom ?? 'N/A' }}
                        </td>
                        <td class="text-secondary">{{ $assoc->voiture_unique_id }}</td>
                        <td style="color: var(--color-text);">{{ $assoc->immatriculation }}</td>
                        <td>{{ $assoc->marque }}</td>
                        <td class="text-secondary">{{ optional($assoc->created_at)->format('d/m/Y H:i') ?? 'Non défini' }}</td>
                        <td class="whitespace-nowrap space-x-1">
                            {{-- Voir (Dark Mode Ready) --}}
                            <a href="{{ route('users.profile', ['id' => $assoc->utilisateur->first()->id]) }}" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200 p-2"
                                title="Voir les détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            {{-- Modifier (Dark Mode Ready) --}}
                            <a href="#" class="text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-200 p-2"
                                title="Modifier l'association">
                                <i class="fas fa-edit"></i>
                            </a>
                            {{-- Supprimer (Dissocier) (Dark Mode Ready) --}}
                            <form action="{{ route('association.destroy', $assoc->id) }}" method="POST" class="inline"
                                onsubmit="return confirm('Voulez-vous vraiment dissocier cet élément ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 p-2" title="Dissocier">
                                    <i class="fas fa-trash-alt"></i>
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- DataTables ---
        if ($.fn.DataTable) {
            $('#associationTable').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" }
            });
        }

        // --- Fonctions utilitaires pour les modales ---
        const modal = document.getElementById('associationModal');
        const modalContent = modal.firstElementChild;
        const openBtn = document.getElementById('openAssociationModalBtn');
        const closeBtn = document.getElementById('closeAssociationModalBtn');
        const cancelBtn = document.getElementById('cancelAssociationBtn');
        const associationForm = document.getElementById('associationForm');

        function openModal() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => modalContent.classList.remove('scale-95', 'opacity-0'), 10);
        }

        function closeModal() {
            modalContent.classList.add('scale-95', 'opacity-0');
            document.body.style.overflow = '';
            setTimeout(() => {
                modal.classList.add('hidden');
                associationForm.reset(); // Réinitialise le formulaire à la fermeture
            }, 200);
        }

        // Événements Modale
        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', e => {
            if (e.target === modal) closeModal();
        });

        // --- Fonction de filtre pour les listes ---
        function filterList(inputId, listSelector) {
            const input = document.getElementById(inputId);
            const list = document.getElementById(listSelector);
            if (!input || !list) return;

            input.addEventListener('input', () => {
                const filter = input.value.toLowerCase();
                list.querySelectorAll('label').forEach(label => {
                    // On filtre sur tout le contenu du label (texte visible)
                    const text = label.textContent.toLowerCase(); 
                    label.style.display = text.includes(filter) ? 'flex' : 'none'; // Utiliser 'flex' car les labels sont affichés en flex
                });
            });
        }

        // Application des filtres
        filterList('searchUser', 'userList');
        filterList('searchVehicle', 'vehicleList');
    });
</script>
@endpush

@endsection