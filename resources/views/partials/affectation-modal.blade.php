{{-- resources/views/partials/affectation-modal.blade.php --}}
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

<script>
(function(){
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
        mode: null, // 'from_user' => choisir voiture, 'from_vehicle' => choisir chauffeur
        chauffeurId: null,
        voitureId: null,
        timer: null,
    };

    function openModal() {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        setTimeout(()=> panel.classList.remove('scale-95','opacity-0'), 10);
    }
    function closeModal() {
        panel.classList.add('scale-95','opacity-0');
        document.body.style.overflow = '';
        setTimeout(()=> modal.classList.add('hidden'), 150);
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

    function setHeaders(cols) {
        headRow.innerHTML = cols.map(c => `<th>${c}</th>`).join('');
    }

    async function loadList() {
        const q = (searchEl.value || '').trim();
        const url = state.mode === 'from_user'
            ? `${URL_VEHICLES}?q=${encodeURIComponent(q)}`
            : `${URL_DRIVERS}?q=${encodeURIComponent(q)}`;

        bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-secondary py-6">Chargement...</td></tr>`;

        const res = await fetch(url, { headers: { 'Accept':'application/json' }});
        const json = await res.json();

        if (!json.ok) {
            bodyEl.innerHTML = `<tr><td colspan="10" class="text-center text-red-500 py-6">Erreur chargement</td></tr>`;
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
            // choisir une voiture
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
                        <button class="btn-primary text-sm js-pick" data-voiture-id="${v.id}">
                            Associer
                        </button>
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
            // choisir un chauffeur
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
                        <button class="btn-primary text-sm js-pick" data-chauffeur-id="${u.id}">
                            Associer
                        </button>
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
        // mode from_user => chauffeurId connu, on choisit voiture
        // mode from_vehicle => voitureId connu, on choisit chauffeur
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

            if (confirm(msg)) {
                return doAssign(true);
            }
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

    // helpers
    function escapeHtml(str){
        return String(str ?? '')
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'",'&#039;');
    }

    // API globale pour ouvrir la modale depuis les pages
    window.openAffectModalFromUser = function(chauffeurId, chauffeurLabel){
        state.mode = 'from_user';
        state.chauffeurId = chauffeurId;
        state.voitureId = null;

        titleEl.textContent = 'Associer un véhicule';
        ctxEl.textContent = `Chauffeur: ${chauffeurLabel}`;

        setHeaders(['Immatriculation','Véhicule','GPS','Statut','']);
        openModal();
        loadList();
    }

    window.openAffectModalFromVehicle = function(voitureId, voitureLabel){
        state.mode = 'from_vehicle';
        state.voitureId = voitureId;
        state.chauffeurId = null;

        titleEl.textContent = 'Associer un chauffeur';
        ctxEl.textContent = `Véhicule: ${voitureLabel}`;

        setHeaders(['Chauffeur','Téléphone','Email','Statut','']);
        openModal();
        loadList();
    }

    // events
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    if (searchEl) {
        searchEl.addEventListener('input', () => debounceLoad(loadList));
    }
})();
</script>
