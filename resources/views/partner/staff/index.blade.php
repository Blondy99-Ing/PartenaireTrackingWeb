@extends('layouts.app')

@section('title', 'Gestion du Staff')

@section('content')

    <div class="space-y-8 p-4 md:p-8">

        {{-- Flash messages --}}
        @if(session('status'))
            <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-300 px-4 py-3 rounded mb-4 ui-card">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->has('general'))
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4 ui-card">
                {{ $errors->first('general') }}
            </div>
        @endif

        @if($errors->any() && !$errors->has('general'))
            <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-4 ui-card">
                <strong>Erreurs de validation :</strong>
                <ul class="list-disc list-inside mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Table card --}}
        <div class="ui-card">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                    <i class="fas fa-user-shield mr-2" style="color: var(--color-primary);"></i>
                    Membres du Staff
                </h2>
                <button type="button" id="openAddModalBtn" class="btn-primary text-sm">
                    <i class="fas fa-plus mr-2"></i> Ajouter un membre
                </button>
            </div>

            <div class="ui-table-container shadow-md">
                <table id="staffTable" class="ui-table w-full">
                    <thead>
                    <tr>
                        <th>Nom et Prénom</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Ville</th>
                        <th>Quartier</th>
                        <th>Permissions</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($staffMembers as $member)
                        <tr class="hover:bg-hover-subtle transition-colors">
                            <td style="color: var(--color-text);">
                                {{ $member->prenom }} {{ $member->nom }}
                            </td>
                            <td class="text-secondary">{{ $member->phone }}</td>
                            <td class="text-secondary">{{ $member->email ?? '—' }}</td>
                            <td>{{ $member->ville ?? '—' }}</td>
                            <td>{{ $member->quartier ?? '—' }}</td>
                            <td>
                                @forelse($member->permissions as $permission)
                                    <span class="inline-block text-[10px] px-2 py-0.5 mb-1 mr-1 rounded role-badge"
                                          style="background: var(--color-primary-light); color: var(--color-primary);"
                                          title="{{ $permission->description }}">
                                        {{ $permission->label }}
                                    </span>
                                @empty
                                    <span class="text-secondary text-xs">—</span>
                                @endforelse
                            </td>
                            <td>
                                <img src="{{ $member->photo ? asset('storage/' . $member->photo) : 'https://placehold.co/40x40/F58220/ffffff?text=ST' }}"
                                     alt="Photo"
                                     class="h-10 w-10 object-cover rounded-full border border-border-subtle">
                            </td>
                            <td class="space-x-2 whitespace-nowrap">
                                <button type="button"
                                        class="text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-200 p-2 openEditModalBtn"
                                        data-id="{{ $member->id }}"
                                        data-nom="{{ $member->nom }}"
                                        data-prenom="{{ $member->prenom }}"
                                        data-phone="{{ $member->phone }}"
                                        data-email="{{ $member->email }}"
                                        data-ville="{{ $member->ville }}"
                                        data-quartier="{{ $member->quartier }}"
                                        data-permissions="{{ $member->permissions->pluck('key')->implode(',') }}"
                                        title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <form action="{{ route('partner.staff.destroy', $member->id) }}"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Supprimer ce membre du staff ? Cette action est irréversible.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 p-2"
                                            title="Supprimer">
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

    {{-- ── ADD FLOW (step 1: détails → step 2: permissions) ─────────────────── --}}
    {{-- One form wraps both modals so all fields submit together. --}}
    <form id="addStaffForm" action="{{ route('partner.staff.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- STEP 1 — détails --}}
        <div id="addStaffModal"
             class="fixed inset-0 bg-black bg-opacity-75 hidden flex items-center justify-center z-[9999] transition-opacity duration-300">
            <div class="bg-card rounded-2xl w-full max-w-2xl relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card max-h-[90vh] flex flex-col overflow-hidden">

                <button type="button" id="closeAddModalBtn"
                        class="absolute top-4 right-4 z-10 text-secondary hover:text-red-500 text-xl font-bold transition-colors">&times;</button>

                <div class="shrink-0 px-6 pt-6 pb-4 border-b border-border-subtle">
                    <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                        Ajouter un membre du staff
                    </h2>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-secondary">Nom</label>
                            <input type="text" name="nom" required class="ui-input-style mt-1" value="{{ old('nom') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-secondary">Prénom</label>
                            <input type="text" name="prenom" required class="ui-input-style mt-1" value="{{ old('prenom') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-secondary">Téléphone</label>
                            <input type="tel" name="phone" required class="ui-input-style mt-1" value="{{ old('phone') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-secondary">Email</label>
                            <input type="email" name="email" class="ui-input-style mt-1" value="{{ old('email') }}">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-secondary">Ville</label>
                            <input type="text" name="ville" class="ui-input-style mt-1" value="{{ old('ville') }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-secondary">Quartier</label>
                            <input type="text" name="quartier" class="ui-input-style mt-1" value="{{ old('quartier') }}">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-secondary">Photo</label>
                        <label for="photo_add" class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                            Choisir un fichier
                        </label>
                        <input type="file" class="hidden" id="photo_add" name="photo" accept="image/*">
                        <div id="file-name-add" class="text-xs text-secondary italic">Aucun fichier sélectionné</div>
                        <img id="preview-add" src="#" alt="Aperçu"
                             class="mt-2 h-24 w-24 object-cover rounded-full hidden border border-border-subtle">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-secondary">Mot de passe</label>
                            <input type="password" name="password" required class="ui-input-style mt-1">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-secondary">Confirmer le mot de passe</label>
                            <input type="password" name="password_confirmation" required class="ui-input-style mt-1">
                        </div>
                    </div>

                </div>

                <div class="shrink-0 px-6 py-4 border-t border-border-subtle">
                    <button type="button" id="addContinueBtn" class="btn-primary w-full">
                        Continuer — choisir les permissions <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- STEP 2 — permissions --}}
        <div id="addPermsModal"
             class="fixed inset-0 bg-black bg-opacity-75 hidden flex items-center justify-center z-[10000] transition-opacity duration-300">
            <div class="bg-card rounded-2xl w-full max-w-2xl relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card max-h-[90vh] flex flex-col overflow-hidden">

                <button type="button" id="closeAddPermsBtn"
                        class="absolute top-4 right-4 z-10 text-secondary hover:text-red-500 text-xl font-bold transition-colors">&times;</button>

                <div class="shrink-0 px-6 pt-6 pb-4 border-b border-border-subtle">
                    <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                        Permissions du membre
                    </h2>
                    <p class="text-sm text-secondary mt-1">
                        Sélectionnez <strong>au moins une</strong> permission. Le membre ne pourra effectuer
                        que les actions autorisées.
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto px-6 py-4">
                    @include('partner.staff._permissions', ['idPrefix' => 'add', 'selected' => old('permissions', [])])

                    <div id="addPermsError" class="hidden text-red-500 text-sm mt-3">
                        Sélectionnez au moins une permission.
                    </div>
                </div>

                <div class="shrink-0 px-6 py-4 border-t border-border-subtle flex gap-3">
                    <button type="button" id="backAddPermsBtn" class="btn-secondary w-1/2">
                        <i class="fas fa-arrow-left mr-2"></i> Retour
                    </button>
                    <button type="submit" class="btn-primary w-1/2">
                        <i class="fas fa-user-plus mr-2"></i> Créer le membre
                    </button>
                </div>
            </div>
        </div>
    </form>

    {{-- ── EDIT MODAL ───────────────────────────────────────────────────────── --}}
    <div id="editStaffModal"
         class="fixed inset-0 bg-black bg-opacity-75 hidden flex items-center justify-center z-[9999] transition-opacity duration-300">
        <div class="bg-card rounded-2xl w-full max-w-2xl relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card max-h-[90vh] flex flex-col overflow-hidden">

            <button id="closeEditModalBtn"
                    class="absolute top-4 right-4 z-10 text-secondary hover:text-red-500 text-xl font-bold transition-colors">&times;</button>

            <div class="shrink-0 px-6 pt-6 pb-4 border-b border-border-subtle">
                <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                    Modifier le membre du staff
                </h2>
            </div>

            <form id="editStaffForm" method="POST" enctype="multipart/form-data" class="flex flex-col min-h-0 flex-1">
                @csrf
                @method('PUT')

                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-secondary">Nom</label>
                        <input type="text" id="edit_nom" name="nom" required class="ui-input-style mt-1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary">Prénom</label>
                        <input type="text" id="edit_prenom" name="prenom" required class="ui-input-style mt-1">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-secondary">Téléphone</label>
                        <input type="tel" id="edit_phone" name="phone" required class="ui-input-style mt-1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary">Email</label>
                        <input type="email" id="edit_email" name="email" class="ui-input-style mt-1">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-secondary">Ville</label>
                        <input type="text" id="edit_ville" name="ville" class="ui-input-style mt-1">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary">Quartier</label>
                        <input type="text" id="edit_quartier" name="quartier" class="ui-input-style mt-1">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-secondary">Nouvelle photo (optionnel)</label>
                    <label for="photo_edit" class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                        Choisir un fichier
                    </label>
                    <input type="file" class="hidden" id="photo_edit" name="photo" accept="image/*">
                    <div id="file-name-edit" class="text-xs text-secondary italic">Laisser vide pour conserver la photo actuelle</div>
                    <img id="preview-edit" src="#" alt="Aperçu"
                         class="mt-2 h-24 w-24 object-cover rounded-full hidden border border-border-subtle">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-secondary">Nouveau mot de passe</label>
                        <input type="password" id="edit_password" name="password"
                               class="ui-input-style mt-1" placeholder="Laisser vide si inchangé">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-secondary">Confirmer le mot de passe</label>
                        <input type="password" id="edit_password_confirmation" name="password_confirmation"
                               class="ui-input-style mt-1" placeholder="Laisser vide si inchangé">
                    </div>
                </div>

                <div class="pt-2 border-t border-border-subtle">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-secondary mb-1 mt-3">Permissions</h3>
                    <p class="text-xs text-secondary mb-3">Au moins une permission est requise.</p>
                    @include('partner.staff._permissions', ['idPrefix' => 'edit', 'selected' => []])
                    <div id="editPermsError" class="hidden text-red-500 text-sm mt-2">
                        Sélectionnez au moins une permission.
                    </div>
                </div>
                </div>

                <div class="shrink-0 px-6 py-4 border-t border-border-subtle">
                    <button type="submit" class="btn-primary w-full">
                        <i class="fas fa-save mr-2"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {

                // ── DataTables ──────────────────────────────────────────────────
                @if($staffMembers->count() > 0)
                if ($.fn.DataTable) {
                    $('#staffTable').DataTable({
                        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' },
                    });
                }
                @endif

                // ── Modal helpers ───────────────────────────────────────────────
                function openModal(modal) {
                    modal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                    setTimeout(() => modal.firstElementChild.classList.remove('scale-95', 'opacity-0'), 10);
                }

                function closeModal(modal) {
                    modal.firstElementChild.classList.add('scale-95', 'opacity-0');
                    document.body.style.overflow = '';
                    setTimeout(() => modal.classList.add('hidden'), 200);
                }

                function bindPhotoPreview(inputId, previewId, labelId, emptyLabel) {
                    document.getElementById(inputId).addEventListener('change', function () {
                        const file = this.files[0];
                        const label = document.getElementById(labelId);
                        const preview = document.getElementById(previewId);
                        if (file) {
                            label.textContent = 'Fichier : ' + file.name;
                            const reader = new FileReader();
                            reader.onload = e => {
                                preview.src = e.target.result;
                                preview.classList.remove('hidden');
                            };
                            reader.readAsDataURL(file);
                        } else {
                            label.textContent = emptyLabel;
                            preview.classList.add('hidden');
                        }
                    });
                }

                // ── Add flow (step 1 détails → step 2 permissions) ──────────────
                const addModal     = document.getElementById('addStaffModal');
                const addPermsModal = document.getElementById('addPermsModal');
                const addForm      = document.getElementById('addStaffForm');
                const addPermsError = document.getElementById('addPermsError');

                document.getElementById('openAddModalBtn').addEventListener('click', () => openModal(addModal));
                document.getElementById('closeAddModalBtn').addEventListener('click', () => closeModal(addModal));
                addModal.addEventListener('click', e => { if (e.target === addModal) closeModal(addModal); });

                // Step 1 → step 2 : validate the details before showing permissions
                document.getElementById('addContinueBtn').addEventListener('click', () => {
                    if (!addForm.reportValidity()) return;
                    closeModal(addModal);
                    openModal(addPermsModal);
                });

                document.getElementById('backAddPermsBtn').addEventListener('click', () => {
                    closeModal(addPermsModal);
                    openModal(addModal);
                });
                document.getElementById('closeAddPermsBtn').addEventListener('click', () => closeModal(addPermsModal));
                addPermsModal.addEventListener('click', e => { if (e.target === addPermsModal) closeModal(addPermsModal); });

                // Require at least one permission before submitting the add form
                addForm.addEventListener('submit', function (e) {
                    const checked = addForm.querySelectorAll('.add-perm-checkbox:checked').length;
                    if (checked === 0) {
                        e.preventDefault();
                        if (addModal.classList.contains('hidden')) {
                            addPermsError.classList.remove('hidden');
                        } else {
                            // user somehow submitted from step 1 — bring them to permissions
                            closeModal(addModal);
                            openModal(addPermsModal);
                            addPermsError.classList.remove('hidden');
                        }
                    }
                });

                bindPhotoPreview('photo_add', 'preview-add', 'file-name-add', 'Aucun fichier sélectionné');

                // ── Edit modal ──────────────────────────────────────────────────
                const editModal = document.getElementById('editStaffModal');
                const editForm  = document.getElementById('editStaffForm');
                const editPermsError = document.getElementById('editPermsError');

                document.getElementById('closeEditModalBtn').addEventListener('click', () => closeModal(editModal));
                editModal.addEventListener('click', e => { if (e.target === editModal) closeModal(editModal); });

                bindPhotoPreview('photo_edit', 'preview-edit', 'file-name-edit', 'Laisser vide pour conserver la photo actuelle');

                document.querySelectorAll('.openEditModalBtn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;

                        document.getElementById('edit_nom').value      = this.dataset.nom      ?? '';
                        document.getElementById('edit_prenom').value   = this.dataset.prenom   ?? '';
                        document.getElementById('edit_phone').value    = this.dataset.phone    ?? '';
                        document.getElementById('edit_email').value    = this.dataset.email    ?? '';
                        document.getElementById('edit_ville').value    = this.dataset.ville    ?? '';
                        document.getElementById('edit_quartier').value = this.dataset.quartier ?? '';

                        document.getElementById('edit_password').value              = '';
                        document.getElementById('edit_password_confirmation').value = '';

                        document.getElementById('file-name-edit').textContent = 'Laisser vide pour conserver la photo actuelle';
                        document.getElementById('preview-edit').classList.add('hidden');

                        // Pre-check the member's current permissions
                        const granted = (this.dataset.permissions || '').split(',').filter(Boolean);
                        editForm.querySelectorAll('.edit-perm-checkbox').forEach(cb => {
                            cb.checked = granted.includes(cb.value);
                        });
                        editPermsError.classList.add('hidden');

                        editForm.action = `/partner/staff/${id}`;

                        openModal(editModal);
                    });
                });

                // Require at least one permission before submitting the edit form
                editForm.addEventListener('submit', function (e) {
                    const checked = editForm.querySelectorAll('.edit-perm-checkbox:checked').length;
                    if (checked === 0) {
                        e.preventDefault();
                        editPermsError.classList.remove('hidden');
                    }
                });

                // ── Auto-open on validation error ───────────────────────────────
                @if($errors->any())
                openModal(addModal);
                @if($errors->has('permissions'))
                closeModal(addModal);
                openModal(addPermsModal);
                @endif
                @endif

            });
        </script>
    @endpush

@endsection
