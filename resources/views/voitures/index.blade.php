@extends('layouts.app')

@section('title', 'Suivi des Véhicules')

@section('content')
<div class="space-y-4 p-0 md:p-4">

    {{-- Messages d'erreur ou succès --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
            <strong class="font-bold">Erreurs de validation:</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Liste des véhicules --}}
    <div class="ui-card mt-2">
        <div class="flex justify-end mb-4">
            <a href="{{ route('partner.affectations.index') }}" class="btn-secondary">
                <i class="fas fa-link mr-2"></i> Voir les associations
            </a>
        </div>
        <div class="flex items-center justify-between mb-6 gap-3 flex-wrap">
            <h2 class="text-xl font-bold font-orbitron">Liste des Véhicules</h2>

            <div class="relative w-full sm:w-72">
                <input id="vehiclesSearchInput" type="text" class="ui-input-style pl-10" placeholder="Rechercher (immat, marque, modèle...)">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-secondary">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </div>


        <div class="ui-table-container shadow-md">
            <table id="example" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Modèle</th>
                        <th>Marque</th>
                        <th>Couleur</th>
                        <th>GPS</th>
                        <th>Photo</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                        @php
                            $label = trim(($voiture->immatriculation ?? '').' - '.($voiture->marque ?? '').' '.($voiture->model ?? ''));
                        @endphp
                        <tr>
                            <td>{{ $voiture->immatriculation }}</td>
                            <td>{{ $voiture->model }}</td>
                            <td>{{ $voiture->marque }}</td>
                            <td>
                                <div class="w-8 h-8 rounded" style="background-color: {{ $voiture->couleur }}"></div>
                            </td>
                            <td>{{ $voiture->mac_id_gps }}</td>
                            <td>
                                @if($voiture->photo)
                                    <img
                                        src="{{ asset('storage/' . $voiture->photo) }}"
                                        alt="Photo"
                                        class="h-10 w-10 object-cover rounded"
                                        onerror="this.style.display='none';"
                                    >
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                {{-- Localiser --}}
                                <button
                                    type="button"
                                    class="text-primary hover:text-orange-600 transition p-2"
                                    onclick="goToProfile({{ auth()->id() }}, {{ $voiture->id }})"
                                    title="Localiser le véhicule"
                                >
                                    <i class="fas fa-map-marker-alt text-xl"></i>
                                </button>

                                {{-- Associer chauffeur (SANS onclick json) --}}
                                <button
                                    type="button"
                                    class="text-blue-500 hover:text-blue-700 transition p-2 js-open-affect-from-vehicle"
                                    data-voiture-id="{{ $voiture->id }}"
                                    data-voiture-label="{{ e($label) }}"
                                    title="Associer un chauffeur"
                                >
                                    <i class="fas fa-user-tag text-xl"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>
    </div>

</div>


{{-- ================= MODALE AFFECTATION (inline) ================= --}}
<div id="affectModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-[99999] flex items-center justify-center">
    <div id="affectPanel" class="bg-card rounded-2xl w-full max-w-3xl p-6 relative shadow-lg transform transition duration-200 scale-95 opacity-0 ui-card">

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
(function() {
    // ========== DataTables + Search externe ==========
    let dt = null;
    if (window.jQuery && $.fn.DataTable) {
        dt = $('#example').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            searching: true,
            info: true,
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" },
            dom: 'lrtip'
        });

        const searchInput = document.getElementById('vehiclesSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                dt.search(this.value).draw();
            });
        }
    }

    // ========== Helpers ==========
    function escapeHtml(str){
        return String(str ?? '')
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'",'&#039;');
    }

    // ========== Affectation Modal ==========
    const modal = document.getElementById('affectModal');
    const panel = document.getElementById('affectPanel');

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

    let state = {
        mode: null, // 'from_user' ou 'from_vehicle'
        chauffeurId: null,
        voitureId: null,
        timer: null,
    };

    function setHeaders(cols) {
        headRow.innerHTML = cols.map(c => `<th>${c}</th>`).join('');
    }

    function openModal() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(() => panel.classList.remove('scale-95','opacity-0'), 10);
    }

    function closeModal() {
        panel.classList.add('scale-95','opacity-0');
        document.body.style.overflow = '';
        setTimeout(() => modal.classList.add('hidden'), 150);

        bodyEl.innerHTML = '';
        headRow.innerHTML = '';
        searchEl.value = '';
        noteEl.value = '';

        state = { mode:null, chauffeurId:null, voitureId:null, timer:null };
    }

    function debounceLoad(fn) {
        clearTimeout(state.timer);
        state.timer = setTimeout(fn, 250);
    }

    async function loadList() {
        const q = (searchEl.value || '').trim();
        const url = state.mode === 'from_user'
            ? `${URL_VEHICLES}?q=${encodeURIComponent(q)}`
            : `${URL_DRIVERS}?q=${encodeURIComponent(q)}`;

        bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-secondary py-6">Chargement...</td></tr>`;

        try {
            const res = await fetch(url, { headers: { 'Accept':'application/json' } });
            const json = await res.json();

            if (!json.ok) {
                bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-red-500 py-6">Erreur chargement</td></tr>`;
                return;
            }
            renderRows(json.items || []);
        } catch (e) {
            console.error(e);
            bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-red-500 py-6">Erreur réseau</td></tr>`;
        }
    }

    function renderRows(items) {
        if (!items.length) {
            bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-secondary py-6">Aucun résultat</td></tr>`;
            return;
        }

        if (state.mode === 'from_user') {
            // Choisir une voiture pour un chauffeur
            bodyEl.innerHTML = items.map(v => {
                const cur = v.current_driver
                    ? `Déjà affecté à: ${v.current_driver.prenom} ${v.current_driver.nom} (${v.current_driver.phone})`
                    : 'Non affecté';

                return `
                <tr class="hover:bg-hover-subtle transition-colors">
                    <td>${escapeHtml(v.immatriculation || '')}</td>
                    <td>${escapeHtml((v.marque||'') + ' ' + (v.model||''))}</td>
                    <td class="text-secondary">${escapeHtml(v.mac_id_gps || '')}</td>
                    <td class="text-xs">${escapeHtml(cur)}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-primary text-sm js-pick" data-voiture-id="${v.id}">Associer</button>
                    </td>
                </tr>`;
            }).join('');

            bodyEl.querySelectorAll('.js-pick').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.voitureId = parseInt(btn.getAttribute('data-voiture-id'), 10);
                    doAssign(false);
                });
            });

        } else {
            // Choisir un chauffeur pour une voiture
            bodyEl.innerHTML = items.map(u => {
                const cur = u.current_vehicle
                    ? `Déjà affecté à: ${u.current_vehicle.immatriculation} (${u.current_vehicle.marque} ${u.current_vehicle.model})`
                    : 'Non affecté';

                return `
                <tr class="hover:bg-hover-subtle transition-colors">
                    <td>${escapeHtml((u.prenom||'') + ' ' + (u.nom||''))}</td>
                    <td class="text-secondary">${escapeHtml(u.phone || '')}</td>
                    <td>${escapeHtml(u.email || '')}</td>
                    <td class="text-xs">${escapeHtml(cur)}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn-primary text-sm js-pick" data-chauffeur-id="${u.id}">Associer</button>
                    </td>
                </tr>`;
            }).join('');

            bodyEl.querySelectorAll('.js-pick').forEach(btn => {
                btn.addEventListener('click', () => {
                    state.chauffeurId = parseInt(btn.getAttribute('data-chauffeur-id'), 10);
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
            let msg = '';

            if (j.type === 'conflict_vehicle') {
                msg = `Ce véhicule est déjà associé à ${j.existing?.prenom ?? ''} ${j.existing?.nom ?? ''} (${j.existing?.phone ?? ''}).\n\nVoulez-vous le désassocier et le réassocier au chauffeur sélectionné ?`;
            } else if (j.type === 'conflict_driver') {
                msg = `Ce chauffeur est déjà associé au véhicule ${j.existing?.immatriculation ?? ''} (${j.existing?.marque ?? ''} ${j.existing?.model ?? ''}).\n\nVoulez-vous le désassocier et le réassocier au véhicule sélectionné ?`;
            } else {
                msg = `Conflit détecté. Voulez-vous forcer la réaffectation ?`;
            }

            if (confirm(msg)) return doAssign(true);
            return;
        }

        const json = await res.json();
        if (!json.ok) {
            alert(json.message || 'Erreur affectation');
            return;
        }

        alert(json.message || 'Affectation OK');
        window.location.reload();
    }

    // ========== API globale ==========
    window.openAffectModalFromUser = function(chauffeurId, chauffeurLabel) {
        state.mode = 'from_user';
        state.chauffeurId = chauffeurId;
        state.voitureId = null;

        titleEl.textContent = 'Associer un véhicule';
        ctxEl.textContent = `Chauffeur: ${chauffeurLabel}`;

        setHeaders(['Immatriculation','Véhicule','GPS','Statut','']);
        openModal();
        loadList();
    };

    window.openAffectModalFromVehicle = function(voitureId, voitureLabel) {
        state.mode = 'from_vehicle';
        state.voitureId = voitureId;
        state.chauffeurId = null;

        titleEl.textContent = 'Associer un chauffeur';
        ctxEl.textContent = `Véhicule: ${voitureLabel}`;

        setHeaders(['Chauffeur','Téléphone','Email','Statut','']);
        openModal();
        loadList();
    };

    // ========== Events modale ==========
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    if (searchEl) searchEl.addEventListener('input', () => debounceLoad(loadList));

    // ========== IMPORTANT : ouverture depuis bouton (sans onclick json) ==========
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-open-affect-from-vehicle');
        if (!btn) return;

        const id = parseInt(btn.getAttribute('data-voiture-id'), 10);
        const label = btn.getAttribute('data-voiture-label') || '';

        if (Number.isFinite(id)) {
            window.openAffectModalFromVehicle(id, label);
        }
    });

    console.log('[AffectModal] ready');
})();
</script>

<script>
function goToProfile(userId, vehicleId) {
    if (!userId || !vehicleId) return;
    window.location.href = `/users/${userId}/profile?vehicle_id=${vehicleId}`;
}
</script>
@endpush
@endsection
