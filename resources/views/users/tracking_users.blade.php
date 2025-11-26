@extends('layouts.app')

@section('title', 'Suivi des Utilisateurs')

@section('content')
<div class="space-y-8 p-4 md:p-8">

    {{-- Titre Principal --}}

    {{-- Bande de navigation secondaire (Dark Mode Ready) --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
        style="border-color: var(--color-border-subtle);">
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('tracking.users') }}"
                class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                <i class="fas fa-users mr-2"></i> Utilisateurs
            </a>
            <a href="{{ route('employes.index') }}"
                class="py-2 px-4 rounded-lg font-semibold text-secondary hover:text-primary transition-colors">
                <i class="fas fa-user-tie mr-2"></i> Employés
            </a>
        </div>
    </div>

    {{-- Messages (Dark Mode Ready) --}}
    @if(session('success'))
    <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4 ui-card">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4 ui-card">
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
            <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">Liste des Utilisateurs</h2>
            <button type="button" id="openAddModalBtn" class="btn-primary text-sm">
                <i class="fas fa-plus mr-2"></i> Ajouter un Utilisateur
            </button>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="usersTable" class="ui-table w-full">
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
                    @foreach($users ?? [] as $user)
                    <tr class="hover:bg-hover-subtle transition-colors">
                        <td style="color: var(--color-text);">{{ $user->nom }} {{ $user->prenom }}</td>
                        <td class="text-secondary">{{ $user->phone }}</td>
                        <td>{{ $user->ville }}</td>
                        <td>{{ $user->quartier }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <img src="{{ $user->photo ? asset('storage/' . $user->photo) : 'https://placehold.co/40x40/F58220/ffffff?text=NP' }}"
                                alt="Photo" class="h-10 w-10 object-cover rounded-full border border-border-subtle">
                        </td>
                        <td class="space-x-2">
                            {{-- Voir (Dark Mode Ready) --}}
                            <a href="#" class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200 p-2"
                                title="Voir les détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            {{-- Modifier (Dark Mode Ready) --}}
                            <button class="text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-200 p-2 btn-edit" data-user="{{ json_encode($user) }}" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            {{-- Supprimer (Dark Mode Ready) --}}
                            <form action="{{ route('tracking.users.destroy', $user->id) }}" method="POST"
                                class="inline-block" onsubmit="return confirm('Confirmer la suppression de {{ $user->prenom }} {{ $user->nom }} ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 p-2" title="Supprimer">
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

<div id="userModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-[9999] flex items-center justify-center transition-opacity duration-300">
    <div
        class="bg-card rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card">
        
        <button id="closeModalBtn"
            class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">&times;</button>
        
        <h2 id="modalTitle" class="text-xl font-bold font-orbitron mb-6" style="color: var(--color-text);">Ajouter un Utilisateur</h2>

        <form id="userForm" method="POST" enctype="multipart/form-data" class="space-y-4">
            {{-- Le token CSRF sera géré par JavaScript lors de l'édition si la méthode change en PUT --}}
            @csrf 
            <input type="hidden" id="userId" name="user_id">
            <input type="hidden" name="_method" id="formMethod" value="POST"> {{-- Simplification pour la gestion PUT --}}

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="nom" name="nom"
                        class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="prenom" name="prenom"
                        class="ui-input-style mt-1" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="phone" name="phone"
                        class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-secondary">Email</label>
                    <input type="email" id="email" name="email"
                        class="ui-input-style mt-1" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ville" class="block text-sm font-medium text-secondary">Ville</label>
                    <input type="text" id="ville" name="ville"
                        class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="quartier" class="block text-sm font-medium text-secondary">Quartier</label>
                    <input type="text" id="quartier" name="quartier"
                        class="ui-input-style mt-1">
                </div>
            </div>

            <div class="space-y-2">
                <label for="photo" class="block text-sm font-medium text-secondary">Photo</label>
                <label for="photo"
                    class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                    Choisir un fichier
                </label>
                <input type="file" class="hidden" id="photo" name="photo" accept="image/*">
                <div id="file-name" class="text-xs text-secondary italic">Aucun fichier sélectionné
                </div>
                <img id="preview" src="#" alt="Aperçu" class="mt-2 h-24 w-24 object-cover rounded-full hidden border border-border-subtle">
            </div>

            <div id="passwordFields" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-secondary">Mot de passe</label>
                    <input type="password" id="password" name="password"
                        class="ui-input-style mt-1">
                </div>
                <div>
                    <label for="password_confirmation"
                        class="block text-sm font-medium text-secondary">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        class="ui-input-style mt-1">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6" id="submitBtn">
                <i class="fas fa-user-plus mr-2"></i> Ajouter
            </button>
        </form>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {

    // --- DataTables ---
    // Assurez-vous d'avoir bien les fichiers CSS/JS de DataTables inclus dans `layouts.app`
    if ($.fn.DataTable) {
        $('#usersTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json"
            }
        });
    }


    // --- Modal centrée ---
    const modal = document.getElementById('userModal');
    const openAddBtn = document.getElementById('openAddModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');
    const modalTitle = document.getElementById('modalTitle');
    const userForm = document.getElementById('userForm');
    const submitBtn = document.getElementById('submitBtn');
    const userIdInput = document.getElementById('userId');
    const passwordFields = document.getElementById('passwordFields');
    const formMethod = document.getElementById('formMethod');

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
        setTimeout(() => modal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
    }

    function closeModal() {
        modal.firstElementChild.classList.add('scale-95', 'opacity-0');
        document.body.style.overflow = '';
        setTimeout(() => {
             modal.classList.add('hidden');
             modal.classList.remove('opacity-100');
        }, 200);
        
        userForm.reset();
        document.getElementById('preview').classList.add('hidden');
        document.getElementById('file-name').textContent = 'Aucun fichier sélectionné';
        passwordFields.style.display = 'grid'; // Afficher par défaut pour l'ajout
        submitBtn.innerHTML = '<i class="fas fa-user-plus mr-2"></i> Ajouter';
        modalTitle.textContent = 'Ajouter un Utilisateur';
        userForm.action = "{{ route('tracking.users.store') }}";
        formMethod.value = 'POST';
        userIdInput.value = '';
    }
    
    // Fermeture par clic sur l'arrière-plan
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });


    openAddBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);

    // --- Photo preview ---
    const photoInput = document.getElementById('photo');
    const fileNameDisplay = document.getElementById('file-name');
    const preview = document.getElementById('preview');

    photoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            fileNameDisplay.textContent = 'Fichier : ' + file.name;
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        } else {
            fileNameDisplay.textContent = 'Aucun fichier sélectionné';
            preview.classList.add('hidden');
        }
    });

    // --- Edition ---
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', () => {
            const user = JSON.parse(button.getAttribute('data-user'));
            
            // Mise à jour de la modale pour l'édition
            modalTitle.textContent = 'Modifier l\'Utilisateur';
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Mettre à jour';
            userForm.action = `/users/${user.id}`; // Route d'action de mise à jour (PUT)
            formMethod.value = 'PUT'; // Changement de la méthode pour Laravel
            
            userIdInput.value = user.id;
            document.getElementById('nom').value = user.nom;
            document.getElementById('prenom').value = user.prenom;
            document.getElementById('phone').value = user.phone;
            document.getElementById('email').value = user.email;
            document.getElementById('ville').value = user.ville;
            document.getElementById('quartier').value = user.quartier;
            
            // Les champs de mot de passe ne sont pas obligatoires lors de la modification
            passwordFields.style.display = 'grid'; // Les afficher, mais sans l'attribut `required` sur les inputs

            if (user.photo) {
                preview.src = `/storage/${user.photo}`;
                preview.classList.remove('hidden');
            } else {
                preview.src = '#';
                preview.classList.add('hidden');
            }
            fileNameDisplay.textContent = 'Laisser vide pour conserver la photo actuelle';

            openModal();
        });
    });
});
</script>

@endsection