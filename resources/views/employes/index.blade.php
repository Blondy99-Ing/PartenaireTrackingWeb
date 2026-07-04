@extends('layouts.app')

@section('title', 'Gestion des Employés')

@section('content')

<div class="space-y-8 p-4 md:p-8">

    {{-- Titre Principal --}}
 

    {{-- Bande de navigation secondaire (Dark Mode Ready) --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
        style="border-color: var(--color-border-subtle);">
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('tracking.users') }}"
                class="py-2 px-4 rounded-lg font-semibold text-secondary hover:text-primary transition-colors">
                <i class="fas fa-users mr-2"></i> Utilisateurs
            </a>
            <a href="#"
                class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                <i class="fas fa-user-tie mr-2"></i> Employés
            </a>
        </div>
    </div>

    {{-- Messages de succès et erreurs (Dark Mode Ready) --}}
    @if(session('success'))
    <div
        class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4 ui-card">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div
        class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4 ui-card">
        <strong>Erreurs de validation :</strong>
        <ul class="list-disc list-inside mt-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="ui-card">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">Liste des Employés</h2>
            <button type="button" id="openAddModalBtn" class="btn-primary text-sm">
                <i class="fas fa-plus mr-2"></i> Ajouter un Employé
            </button>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="employeTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Nom et Prénom</th>
                        <th>Téléphone</th>
                        <th>Ville</th>
                        <th>Quartier</th>
                        <th>Email</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($employes ?? [] as $employe)
                    <tr class="hover:bg-hover-subtle transition-colors">
                        <td style="color: var(--color-text);">{{ $employe->nom }} {{ $employe->prenom }}</td>
                        <td class="text-secondary">{{ $employe->phone }}</td>
                        <td>{{ $employe->ville }}</td>
                        <td>{{ $employe->quartier }}</td>
                        <td>{{ $employe->email }}</td>
                        <td>
                            <img src="{{ $employe->photo ? asset('storage/' . $employe->photo) : 'https://placehold.co/40x40/F58220/ffffff?text=NP' }}"
                                alt="Photo" class="h-10 w-10 object-cover rounded-full border border-border-subtle">
                        </td>
                        <td class="space-x-2 whitespace-nowrap">
                            {{-- Bouton Modifier (Dark Mode Ready) --}}
                            <button class="text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-200 p-2 openEditModalBtn"
                                data-id="{{ $employe->id }}" data-nom="{{ $employe->nom }}"
                                data-prenom="{{ $employe->prenom }}" data-phone="{{ $employe->phone }}"
                                data-email="{{ $employe->email }}" data-ville="{{ $employe->ville }}"
                                data-quartier="{{ $employe->quartier }}" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>

                            {{-- Bouton Supprimer (Dark Mode Ready) --}}
                            <form action="{{ route('employes.destroy', $employe->id) }}" method="POST"
                                class="inline" onsubmit="return confirm('Voulez-vous vraiment supprimer cet employé ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 p-2" title="Supprimer">
                                    <i class="fas fa-trash"></i>
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

<div id="addEmployeModal"
    class="fixed inset-0 bg-black bg-opacity-75 hidden flex items-center justify-center z-[9999] transition-opacity duration-300">
    <div
        class="bg-card rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card">
        
        <button id="closeAddModalBtn"
            class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">&times;</button>
        
        <h2 class="text-xl font-bold font-orbitron mb-6" style="color: var(--color-text);">Ajouter un Employé</h2>

        <form action="{{ route('employes.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="nom" name="nom" required class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="prenom" name="prenom" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="phone" name="phone" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="email" name="email" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ville" class="block text-sm font-medium text-secondary">Ville</label>
                    <input type="text" id="ville" name="ville" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="quartier" class="block text-sm font-medium text-secondary">Quartier</label>
                    <input type="text" id="quartier" name="quartier" class="ui-input-style mt-1">
                </div>
            </div>

            <div class="space-y-2">
                <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                <label for="photo_add"
                    class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                    Choisir un fichier
                </label>
                <input type="file" class="hidden" id="photo_add" name="photo" accept="image/*">
                <div id="file-name-add" class="text-xs text-secondary italic">Aucun fichier sélectionné</div>
                <img id="preview-add" src="#" alt="Aperçu" class="mt-2 h-24 w-24 object-cover rounded-full hidden border border-border-subtle">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-secondary">Mot de passe</label>
                    <input type="password" id="password" name="password" required class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-secondary">Confirmer le
                        mot de passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                        class="ui-input-style mt-1">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6">
                <i class="fas fa-user-plus mr-2"></i> Ajouter l'Employé
            </button>
        </form>
    </div>
</div>

<div id="editEmployeModal"
    class="fixed inset-0 bg-black bg-opacity-75 hidden flex items-center justify-center z-[9999] transition-opacity duration-300">
    <div
        class="bg-card rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card">
        
        <button id="closeEditModalBtn"
            class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">&times;</button>
        
        <h2 class="text-xl font-bold font-orbitron mb-6" style="color: var(--color-text);">Modifier l'Employé</h2>

        <form id="editForm" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            <input type="hidden" id="edit_id" name="id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="edit_nom" name="nom" required class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="edit_prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="edit_prenom" name="prenom" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="edit_phone" name="phone" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="edit_email" name="email" required class="ui-input-style mt-1">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_ville" class="block text-sm font-medium text-secondary">Ville</label>
                    <input type="text" id="edit_ville" name="ville" class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="edit_quartier" class="block text-sm font-medium text-secondary">Quartier</label>
                    <input type="text" id="edit_quartier" name="quartier" class="ui-input-style mt-1">
                </div>
            </div>
            
            {{-- Champ de modification de photo --}}
            <div class="space-y-2">
                <label for="photo_edit" class="block text-sm font-medium text-secondary">Nouvelle Photo (Optionnel)</label>
                <label for="photo_edit"
                    class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                    Choisir un fichier
                </label>
                <input type="file" class="hidden" id="photo_edit" name="photo" accept="image/*">
                <div id="file-name-edit" class="text-xs text-secondary italic">Laisser vide pour conserver la photo actuelle</div>
                <img id="preview-edit" src="#" alt="Aperçu" class="mt-2 h-24 w-24 object-cover rounded-full hidden border border-border-subtle">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="edit_password" class="block text-sm font-medium text-secondary">Nouveau mot de
                        passe</label>
                    <input type="password" id="edit_password" name="password" class="ui-input-style mt-1"
                        placeholder="Laisser vide si inchangé">
                </div>
                <div>
                    <label for="edit_password_confirmation" class="block text-sm font-medium text-secondary">Confirmer
                        mot de passe</label>
                    <input type="password" id="edit_password_confirmation" name="password_confirmation"
                        class="ui-input-style mt-1" placeholder="Laisser vide si inchangé">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6">
                <i class="fas fa-save mr-2"></i> Enregistrer les modifications
            </button>
        </form>
    </div>
</div>


@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- DataTables ---
        if ($.fn.DataTable) {
            $('#employeTable').DataTable({
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" }
            });
        }

        // --- Fonctions utilitaires pour les modales ---
        function openModal(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('opacity-100');
            document.body.style.overflow = 'hidden';
            setTimeout(() => modal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
        }

        function closeModal(modal, formId = null) {
            modal.firstElementChild.classList.add('scale-95', 'opacity-0');
            document.body.style.overflow = '';
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('opacity-100');
            }, 200);

            if(formId) {
                document.getElementById(formId).reset();
            }
        }

        // --- Modale d'Ajout ---
        const addModal = document.getElementById('addEmployeModal');
        const openAddBtn = document.getElementById('openAddModalBtn');
        const closeAddBtn = document.getElementById('closeAddModalBtn');
        const photoAddInput = document.getElementById('photo_add');
        const fileNameAddDisplay = document.getElementById('file-name-add');
        const previewAdd = document.getElementById('preview-add');
        
        openAddBtn.addEventListener('click', () => {
            openModal(addModal);
            // Réinitialiser les champs spécifiques à l'ajout si la modale est rouverte
            previewAdd.classList.add('hidden');
            fileNameAddDisplay.textContent = 'Aucun fichier sélectionné';
        });
        closeAddBtn.addEventListener('click', () => closeModal(addModal, 'addEmployeModal form'));
        addModal.addEventListener('click', (e) => {
            if (e.target === addModal) closeModal(addModal, 'addEmployeModal form');
        });

        // Photo preview Ajout
        photoAddInput.addEventListener('change', function() {
            const file = this.files[0];
            if(file){
                fileNameAddDisplay.textContent = 'Fichier : ' + file.name;
                const reader = new FileReader();
                reader.onload = e => {
                    previewAdd.src = e.target.result;
                    previewAdd.classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            } else {
                fileNameAddDisplay.textContent = 'Aucun fichier sélectionné';
                previewAdd.classList.add('hidden');
            }
        });

        // --- Modale de Modification ---
        const editModal = document.getElementById('editEmployeModal');
        const closeEditBtn = document.getElementById('closeEditModalBtn');
        const editForm = document.getElementById('editForm');
        const photoEditInput = document.getElementById('photo_edit');
        const fileNameEditDisplay = document.getElementById('file-name-edit');
        const previewEdit = document.getElementById('preview-edit');

        closeEditBtn.addEventListener('click', () => closeModal(editModal));
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) closeModal(editModal);
        });

        // Ouvrir modale modification
        document.querySelectorAll('.openEditModalBtn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                
                // Remplissage des champs
                document.getElementById('edit_id').value = id;
                document.getElementById('edit_nom').value = this.dataset.nom;
                document.getElementById('edit_prenom').value = this.dataset.prenom;
                document.getElementById('edit_phone').value = this.dataset.phone;
                document.getElementById('edit_email').value = this.dataset.email;
                document.getElementById('edit_ville').value = this.dataset.ville;
                document.getElementById('edit_quartier').value = this.dataset.quartier;

                // Mise à jour de l'action du formulaire (URL)
                editForm.action = `/employes/${id}`;
                
                // Réinitialisation des champs optionnels/photo
                document.getElementById('edit_password').value = '';
                document.getElementById('edit_password_confirmation').value = '';
                fileNameEditDisplay.textContent = 'Laisser vide pour conserver la photo actuelle';
                previewEdit.classList.add('hidden'); // On cache l'aperçu par défaut

                // Si l'employé a une photo, on affiche l'aperçu actuel (il faudra l'injecter via Blade ou AJAX si non disponible)
                // Pour simplifier ici, on se contente de l'état "caché" par défaut. Si la photo est présente, vous pourriez 
                // avoir besoin d'un champ masqué `current_photo` ou d'une logique AJAX pour la récupérer.

                openModal(editModal);
            });
        });
        
        // Photo preview Modification
        photoEditInput.addEventListener('change', function() {
            const file = this.files[0];
            if(file){
                fileNameEditDisplay.textContent = 'Fichier : ' + file.name;
                const reader = new FileReader();
                reader.onload = e => {
                    previewEdit.src = e.target.result;
                    previewEdit.classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            } else {
                fileNameEditDisplay.textContent = 'Laisser vide pour conserver la photo actuelle';
                previewEdit.classList.add('hidden');
            }
        });
    });
</script>
@endpush

@endsection