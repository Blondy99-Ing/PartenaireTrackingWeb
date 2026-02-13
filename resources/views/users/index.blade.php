@extends('layouts.app')

@section('title', 'Utilisateurs secondaires')

@section('content')
@php
    $disk = config('media.disk', 'public');

    // URLs JS
    $storeUrl = route('users.store');
    $baseUrl  = url('users'); // /users/{id}

    // (Optionnel) page associations (si tu ne l'as pas encore, remplace par '#')
    $assocIndexUrl = route('partner.affectations.index', [], false) ?? '#';
@endphp

<div class="space-y-6 p-4 md:p-8">

    {{-- Navigation (SANS onglet Employés) --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b pb-4"
         style="border-color: var(--color-border-subtle);">
        <div class="flex mt-4 sm:mt-0 space-x-4">
            <a href="{{ route('users.index') }}"
               class="py-2 px-4 rounded-lg font-semibold text-primary border-b-2 border-primary transition-colors">
                <i class="fas fa-users mr-2"></i> Chauffeurs
            </a>

            <a href="{{ route('partner.affectations.index') }}"
               class="py-2 px-4 rounded-lg font-semibold text-secondary hover:text-primary transition-colors">
                <i class="fas fa-link mr-2"></i> Associations
            </a>

            <a href="{{ route('partner.affectations.history') }}"
               class="py-2 px-4 rounded-lg font-semibold text-secondary hover:text-primary transition-colors">
                <i class="fas fa-clock-rotate-left mr-2"></i> Historique
            </a>
        </div>
    </div>

    {{-- Flash status --}}
    @if(session('status'))
        <div class="p-3 rounded-lg bg-green-100 text-green-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Erreurs validation --}}
    @if($errors->any())
        <div class="p-3 rounded-lg bg-red-100 text-red-800">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="ui-card">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <h2 class="text-xl font-bold font-orbitron" style="color: var(--color-text);">
                Liste des Utilisateurs secondaires
            </h2>

            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                {{-- Recherche --}}
                <div class="relative">
                    <input id="usersSearchInput" type="text"
                           class="ui-input-style pl-10"
                           placeholder="Rechercher (nom, phone, email, ville...)">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-secondary">
                        <i class="fas fa-search"></i>
                    </span>
                </div>

                <button type="button" id="openAddModalBtn" class="btn-primary text-sm">
                    <i class="fas fa-plus mr-2"></i> Ajouter un chauffeur
                </button>
            </div>
        </div>

        <div class="ui-table-container shadow-md">
            <table id="usersTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Rôle</th>
                        <th>Nom & Prénom</th>
                        <th>Téléphone</th>
                        <th>Ville</th>
                        <th>Quartier</th>
                        <th>Email</th>
                        
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach(($users ?? []) as $user)
                        @php
                            $photoUrl = $user->photo
                                ? \Illuminate\Support\Facades\Storage::disk($disk)->url($user->photo)
                                : null;

                            $thumbUrl = $photoUrl ?: 'https://placehold.co/40x40/F58220/ffffff?text=NP';
                            $fullUrl  = $photoUrl ?: 'https://placehold.co/600x600/F58220/ffffff?text=NP';
                            $fullName = trim(($user->prenom ?? '').' '.($user->nom ?? ''));

                            $editPayload = [
                                'id' => $user->id,
                                'nom' => $user->nom,
                                'prenom' => $user->prenom,
                                'phone' => $user->phone,
                                'email' => $user->email,
                                'ville' => $user->ville,
                                'quartier' => $user->quartier,
                                'photo_url' => $photoUrl,
                            ];
                        @endphp

                        <tr class="hover:bg-hover-subtle transition-colors">
                            <td>Chauffeur</td>
                            <td style="color: var(--color-text);">{{ $user->nom }} {{ $user->prenom }}</td>
                            <td class="text-secondary">{{ $user->phone }}</td>
                            <td>{{ $user->ville }}</td>
                            <td>{{ $user->quartier }}</td>
                            <td>{{ $user->email }}</td>

                        

                            <td class="space-x-2 whitespace-nowrap">
                                {{-- Modifier --}}
                                <button type="button"
                                        class="text-yellow-500 hover:text-yellow-700 dark:text-yellow-400 dark:hover:text-yellow-200 p-2 btn-edit"
                                        data-user-id="{{ $user->id }}"
                                        title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>

                                {{-- Associer véhicule (✅ sans onclick) --}}
                                <button type="button"
                                        class="text-blue-500 hover:text-blue-700 p-2 js-open-affect-from-user"
                                        data-user-id="{{ $user->id }}"
                                        data-user-label='@json($user->prenom." ".$user->nom." (".$user->phone.")")'
                                        title="Associer un véhicule">
                                    <i class="fas fa-car"></i>
                                </button>

                                {{-- JSON payload edit --}}
                                <script type="application/json" id="user-json-{{ $user->id }}">
                                    @json($editPayload)
                                </script>

                                {{-- Supprimer --}}
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST"
                                      class="inline-block"
                                      onsubmit="return confirm('Confirmer la suppression de {{ $user->prenom }} {{ $user->nom }} ?')">
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

        <div class="mt-4 text-xs text-secondary">
            Recherche + pagination automatique via DataTables.
        </div>
    </div>

</div>

{{-- ================= MODALE AJOUT / EDIT ================= --}}
<div id="userModal"
     class="fixed inset-0 bg-black bg-opacity-75 hidden z-[9999] flex items-center justify-center transition-opacity duration-300">
    <div id="userModalPanel"
         class="bg-card rounded-2xl w-full max-w-2xl p-6 relative shadow-lg transform transition-transform duration-300 scale-95 opacity-0 ui-card">

        <button id="closeModalBtn"
                type="button"
                class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold transition-colors">
            &times;
        </button>

        <h2 id="modalTitle" class="text-xl font-bold font-orbitron mb-6" style="color: var(--color-text);">
            Ajouter un chauffeur
        </h2>

        <form id="userForm"
              action="{{ $storeUrl }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-4">
            @csrf

            <div id="methodSpoofContainer"></div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="nom" class="block text-sm font-medium text-secondary">Nom</label>
                    <input type="text" id="nom" name="nom" class="ui-input-style mt-1" required>
                </div>
                <div>
                    <label for="prenom" class="block text-sm font-medium text-secondary">Prénom</label>
                    <input type="text" id="prenom" name="prenom" class="ui-input-style mt-1" required>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="phone" class="block text-sm font-medium text-secondary">Téléphone</label>
                    <input type="tel" id="phone" name="phone" class="ui-input-style mt-1" required
                           placeholder="696..., 0696..., +237..., 237..., 00237...">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-secondary">Email (optionnel)</label>
                    <input type="email" id="email" name="email" class="ui-input-style mt-1">
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

            {{-- Photo --}}
            <div class="space-y-2">
                <label class="block text-sm font-medium text-secondary">Photo</label>

                <label for="photo" class="btn-secondary w-full text-center cursor-pointer transition-colors text-base">
                    Choisir un fichier
                </label>

                <input type="file" class="hidden" id="photo" name="photo" accept="image/*">
                <div id="file-name" class="text-xs text-secondary italic">Aucun fichier sélectionné</div>

                <img id="preview" src="" alt="Aperçu"
                     class="mt-2 h-24 w-24 object-cover rounded-full hidden border border-border-subtle">
            </div>

            {{-- Password --}}
            <div id="passwordFields" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-secondary">
                        Mot de passe
                        <span id="pwdHint" class="text-xs text-secondary font-normal"></span>
                    </label>
                    <input type="password" id="password" name="password" class="ui-input-style mt-1">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-secondary">Confirmer</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="ui-input-style mt-1">
                </div>
            </div>

            <button type="submit" class="btn-primary w-full mt-6" id="submitBtn">
                <i class="fas fa-user-plus mr-2"></i> Ajouter
            </button>
        </form>
    </div>
</div>

{{-- ================= MODALE PHOTO (VIEW) ================= --}}
<div id="imageModal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-black bg-opacity-75">
    <div class="relative bg-white rounded-lg shadow-2xl max-w-4xl max-h-[90vh] overflow-hidden">
        <button id="closeImageModalBtn"
                type="button"
                class="absolute top-4 right-4 text-3xl font-bold text-white hover:text-primary transition-colors bg-gray-900 rounded-full h-10 w-10 flex items-center justify-center">
            &times;
        </button>

        <div class="px-4 pt-4">
            <div id="imageModalTitle" class="text-sm font-semibold text-secondary"></div>
        </div>

        <img id="modalImage" src="" alt="Image"
             class="w-full h-auto object-contain max-h-[85vh] p-4">
    </div>
</div>

{{-- ================= MODALE ASSOCIATION (AFFECTATION) ================= --}}
<div id="affectModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-[99999] items-center justify-center">
    <div id="affectPanel"
         class="bg-card rounded-2xl w-full max-w-3xl p-6 relative shadow-lg transform transition duration-200 scale-95 opacity-0 ui-card">

        <button type="button" id="affectClose"
                class="absolute top-4 right-4 text-secondary hover:text-red-500 text-xl font-bold">&times;</button>

        <h2 id="affectTitle" class="text-xl font-bold font-orbitron mb-4" style="color: var(--color-text);">
            Associer
        </h2>

        <div class="mb-3 text-sm text-secondary" id="affectContext"></div>

        <div class="flex flex-col sm:flex-row gap-2 mb-3">
            <input id="affectSearch" type="text" class="ui-input-style" placeholder="Recherche intelligente...">
            <input id="affectNote" type="text" class="ui-input-style" placeholder="Note (optionnel)">
        </div>

        <div class="ui-table-container shadow-md">
            <table class="ui-table w-full">
                <thead>
                <tr id="affectHeadRow"></tr>
                </thead>
                <tbody id="affectBody"></tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-end gap-2">
            <button type="button" id="affectCancel" class="btn-secondary">Annuler</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // =====================
    // DataTables (sans JSON FR externe => pas de CORS)
    // =====================
    let dt = null;
    if (window.jQuery && $.fn.DataTable) {
        dt = $('#usersTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            searching: true,
            info: true,
            dom: 'lrtip',
            language: {
                processing: "Traitement en cours...",
                search: "Rechercher:",
                lengthMenu: "Afficher _MENU_ éléments",
                info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
                infoEmpty: "Affichage de 0 à 0 sur 0 éléments",
                infoFiltered: "(filtré de _MAX_ éléments au total)",
                loadingRecords: "Chargement...",
                zeroRecords: "Aucun élément à afficher",
                emptyTable: "Aucune donnée disponible",
                paginate: { first: "Premier", previous: "Précédent", next: "Suivant", last: "Dernier" }
            }
        });

        const searchInput = document.getElementById('usersSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                dt.search(this.value).draw();
            });
        }
    }

    // =====================
    // Modal add/edit user
    // =====================
    const modal = document.getElementById('userModal');
    const panel = document.getElementById('userModalPanel');

    const openAddBtn = document.getElementById('openAddModalBtn');
    const closeBtn = document.getElementById('closeModalBtn');

    const modalTitle = document.getElementById('modalTitle');
    const userForm = document.getElementById('userForm');
    const submitBtn = document.getElementById('submitBtn');
    const methodSpoofContainer = document.getElementById('methodSpoofContainer');

    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirmation');
    const pwdHint = document.getElementById('pwdHint');

    const photoInput = document.getElementById('photo');
    const fileNameDisplay = document.getElementById('file-name');
    const preview = document.getElementById('preview');

    const STORE_URL = @json($storeUrl);
    const BASE_URL  = @json($baseUrl);

    function openUserModal() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => panel.classList.remove('scale-95', 'opacity-0'), 10);
    }

    function resetToAdd() {
        userForm.reset();
        userForm.action = STORE_URL;
        methodSpoofContainer.innerHTML = '';

        passwordInput.required = true;
        passwordConfirmInput.required = true;
        passwordInput.setAttribute('minlength', '8');
        passwordConfirmInput.setAttribute('minlength', '8');
        if (pwdHint) pwdHint.textContent = '';

        preview.src = '';
        preview.classList.add('hidden');
        fileNameDisplay.textContent = 'Aucun fichier sélectionné';

        modalTitle.textContent = 'Ajouter un utilisateur';
        submitBtn.innerHTML = '<i class="fas fa-user-plus mr-2"></i> Ajouter';
    }

    function closeUserModal() {
        panel.classList.add('scale-95', 'opacity-0');
        document.body.style.overflow = '';
        setTimeout(() => modal.classList.add('hidden'), 200);
        resetToAdd();
    }

    modal.addEventListener('click', (e) => { if (e.target === modal) closeUserModal(); });
    closeBtn?.addEventListener('click', closeUserModal);
    openAddBtn?.addEventListener('click', () => { resetToAdd(); openUserModal(); });

    photoInput?.addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (file) {
            fileNameDisplay.textContent = 'Fichier : ' + file.name;
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            fileNameDisplay.textContent = 'Aucun fichier sélectionné';
            preview.classList.add('hidden');
            preview.src = '';
        }
    });

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            const userId = btn.getAttribute('data-user-id');
            const jsonEl = document.getElementById('user-json-' + userId);
            if (!jsonEl) return;

            let user = null;
            try { user = JSON.parse(jsonEl.textContent); }
            catch (e) { console.error('Invalid JSON payload', userId, e); return; }

            modalTitle.textContent = "Modifier l'utilisateur";
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Mettre à jour';

            userForm.action = `${BASE_URL}/${user.id}`;
            methodSpoofContainer.innerHTML = '<input type="hidden" name="_method" value="PUT">';

            document.getElementById('nom').value = user.nom || '';
            document.getElementById('prenom').value = user.prenom || '';
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('email').value = user.email || '';
            document.getElementById('ville').value = user.ville || '';
            document.getElementById('quartier').value = user.quartier || '';

            passwordInput.required = false;
            passwordConfirmInput.required = false;
            passwordInput.removeAttribute('minlength');
            passwordConfirmInput.removeAttribute('minlength');
            passwordInput.value = '';
            passwordConfirmInput.value = '';
            if (pwdHint) pwdHint.textContent = ' (laisser vide pour ne pas modifier)';

            if (user.photo_url) {
                preview.src = user.photo_url;
                preview.classList.remove('hidden');
                fileNameDisplay.textContent = 'Laisser vide pour conserver la photo actuelle';
            } else {
                preview.src = '';
                preview.classList.add('hidden');
                fileNameDisplay.textContent = 'Laisser vide pour conserver la photo actuelle';
            }

            openUserModal();
        });
    });

    // =====================
    // Modal photo view
    // =====================
    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const imageModalTitle = document.getElementById('imageModalTitle');
    const closeImageModalBtn = document.getElementById('closeImageModalBtn');

    function openImageModal(url, title) {
        if (!url) return;
        modalImage.src = url;
        imageModalTitle.textContent = title ? `Photo : ${title}` : 'Photo';
        imageModal.classList.remove('hidden');
        imageModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        imageModal.classList.add('hidden');
        imageModal.classList.remove('flex');
        modalImage.src = '';
        document.body.style.overflow = '';
    }

    closeImageModalBtn?.addEventListener('click', closeImageModal);
    imageModal?.addEventListener('click', (e) => { if (e.target === imageModal) closeImageModal(); });
    document.addEventListener('click', function (e) {
        const img = e.target.closest('.js-user-photo');
        if (!img) return;
        openImageModal(img.getAttribute('data-full-url'), img.getAttribute('data-title'));
    });

});
</script>

{{-- =======================
     Affectation Modal JS (robuste, sans onclick)
======================= --}}
<script>
(function(){
    const modal = document.getElementById('affectModal');
    const panel = document.getElementById('affectPanel');
    if (!modal || !panel) {
        console.error('[AffectModal] DOM manquant affectModal/affectPanel');
        return;
    }

    const closeBtn = document.getElementById('affectClose');
    const cancelBtn = document.getElementById('affectCancel');

    const titleEl = document.getElementById('affectTitle');
    const ctxEl = document.getElementById('affectContext');
    const headRow = document.getElementById('affectHeadRow');
    const bodyEl = document.getElementById('affectBody');
    const searchEl = document.getElementById('affectSearch');
    const noteEl = document.getElementById('affectNote');

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;

    const URL_VEHICLES = @json(route('partner.affectations.vehicles'));
    const URL_DRIVERS  = @json(route('partner.affectations.drivers'));
    const URL_ASSIGN   = @json(route('partner.affectations.assign'));

    let state = { mode:null, chauffeurId:null, voitureId:null, timer:null };

    function escapeHtml(str){
        return String(str ?? '')
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'",'&#039;');
    }

    function setHeaders(cols) {
        headRow.innerHTML = cols.map(c => `<th>${c}</th>`).join('');
    }

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        setTimeout(() => panel.classList.remove('scale-95','opacity-0'), 10);
    }

    function closeModal() {
        panel.classList.add('scale-95','opacity-0');
        document.body.style.overflow = '';
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 150);

        bodyEl.innerHTML = '';
        headRow.innerHTML = '';
        searchEl.value = '';
        noteEl.value = '';
        state = { mode:null, chauffeurId:null, voitureId:null, timer:null };
    }

    function debounce(fn){
        clearTimeout(state.timer);
        state.timer = setTimeout(fn, 250);
    }

    async function loadList() {
        const q = (searchEl.value || '').trim();
        const url = state.mode === 'from_user'
            ? `${URL_VEHICLES}?q=${encodeURIComponent(q)}`
            : `${URL_DRIVERS}?q=${encodeURIComponent(q)}`;

        bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-secondary py-6">Chargement...</td></tr>`;

        let res;
        try {
            res = await fetch(url, { headers: { 'Accept':'application/json' }});
        } catch (e) {
            bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-red-500 py-6">Erreur réseau</td></tr>`;
            return;
        }

        let json = null;
        try { json = await res.json(); }
        catch(e){
            bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-red-500 py-6">Réponse JSON invalide</td></tr>`;
            return;
        }

        if (!json.ok) {
            bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-red-500 py-6">${escapeHtml(json.message || 'Erreur chargement')}</td></tr>`;
            return;
        }

        renderRows(json.items || []);
    }

    function renderRows(items) {
        if (!items.length) {
            bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-secondary py-6">Aucun résultat</td></tr>`;
            return;
        }

        if (state.mode === 'from_user') {
            // Choisir une voiture
            bodyEl.innerHTML = items.map(v => {
                const cur = v.current_driver
                    ? `Déjà affecté à: ${v.current_driver.prenom} ${v.current_driver.nom} (${v.current_driver.phone})`
                    : 'Non affecté';

                return `
                <tr>
                    <td>${escapeHtml(v.immatriculation || '')}</td>
                    <td>${escapeHtml((v.marque||'') + ' ' + (v.model||''))}</td>
                    <td class="text-secondary">${escapeHtml(v.mac_id_gps || '')}</td>
                    <td class="text-xs">${escapeHtml(cur)}</td>
                    <td class="text-right whitespace-nowrap">
                        <button type="button" class="btn-primary text-sm js-pick" data-voiture-id="${v.id}">Associer</button>
                    </td>
                </tr>`;
            }).join('');

            bodyEl.querySelectorAll('.js-pick').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.voitureId = parseInt(btn.dataset.voitureId, 10);
                    doAssign(false);
                });
            });
        } else {
            // Choisir un chauffeur
            bodyEl.innerHTML = items.map(u => {
                const cur = u.current_vehicle
                    ? `Déjà affecté à: ${u.current_vehicle.immatriculation} (${u.current_vehicle.marque} ${u.current_vehicle.model})`
                    : 'Non affecté';

                return `
                <tr>
                    <td>${escapeHtml((u.prenom||'') + ' ' + (u.nom||''))}</td>
                    <td class="text-secondary">${escapeHtml(u.phone || '')}</td>
                    <td>${escapeHtml(u.email || '')}</td>
                    <td class="text-xs">${escapeHtml(cur)}</td>
                    <td class="text-right whitespace-nowrap">
                        <button type="button" class="btn-primary text-sm js-pick" data-chauffeur-id="${u.id}">Associer</button>
                    </td>
                </tr>`;
            }).join('');

            bodyEl.querySelectorAll('.js-pick').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.chauffeurId = parseInt(btn.dataset.chauffeurId, 10);
                    doAssign(false);
                });
            });
        }
    }

    async function doAssign(force) {
        if (!state.chauffeurId || !state.voitureId) return;

        const payload = {
            chauffeur_id: state.chauffeurId,
            voiture_id: state.voitureId,
            note: noteEl.value || null,
            force: !!force
        };

        const res = await fetch(URL_ASSIGN, {
            method: 'POST',
            headers: {
                'Content-Type':'application/json',
                'Accept':'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            body: JSON.stringify(payload)
        });

        if (res.status === 409) {
            const j = await res.json();
            if (confirm((j.message || 'Conflit détecté') + "\n\nForcer la réaffectation ?")) {
                return doAssign(true);
            }
            return;
        }

        const json = await res.json();
        if (!json.ok) return alert(json.message || 'Erreur affectation');

        alert(json.message || 'Affectation OK');
        window.location.reload();
    }

    // ✅ OUVERTURE via event listener (robuste même avec DataTables)
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.js-open-affect-from-user');
        if (!btn) return;

        state.mode = 'from_user';
        state.chauffeurId = parseInt(btn.dataset.userId, 10);
        state.voitureId = null;

        titleEl.textContent = 'Associer un véhicule';
        ctxEl.textContent = `Chauffeur: ${btn.dataset.userLabel || ''}`;
        setHeaders(['Immatriculation','Véhicule','GPS','Statut','']);

        openModal();
        loadList();
    });

    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    searchEl?.addEventListener('input', () => debounce(loadList));

    console.log('[AffectModal] ready ✅');
})();
</script>
@endpush

@endsection
