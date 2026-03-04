@extends('layouts.app')

@section('title', 'Véhicules')

@push('styles')
<style>
/* ============================================================
   DATATABLES — OVERRIDE COMPLET (même design system que users)
   ============================================================ */

.dataTables_wrapper {
    font-family: var(--font-body, system-ui, sans-serif);
    font-size: 0.82rem;
    color: var(--color-text);
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-secondary-text);
    font-size: 0.78rem;
}

/* Masqué : on utilise notre champ de recherche custom */
.dataTables_wrapper .dataTables_filter { display: none !important; }

.dataTables_wrapper .dataTables_length select {
    background-color: var(--color-input-bg) !important;
    border: 1px solid var(--color-input-border) !important;
    color: var(--color-text) !important;
    border-radius: 0.4rem;
    padding: 0.3rem 0.5rem;
    font-size: 0.78rem;
    cursor: pointer;
    transition: border-color 0.2s;
    appearance: auto;
}

.dataTables_wrapper .dataTables_length select:focus {
    outline: none;
    border-color: var(--color-primary) !important;
    box-shadow: 0 0 0 3px rgba(245, 130, 32, 0.2);
}

.dataTables_wrapper .dataTables_info {
    color: var(--color-secondary-text);
    font-size: 0.75rem;
    padding-top: 0.5rem;
}

/* Pagination */
.dataTables_wrapper .dataTables_paginate {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding-top: 0.5rem;
    justify-content: flex-end;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid var(--color-border-subtle) !important;
    background: var(--color-card) !important;
    color: var(--color-text) !important;
    font-size: 0.78rem;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
    box-shadow: none !important;
    background-image: none !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--color-sidebar-active-bg) !important;
    border-color: var(--color-primary) !important;
    color: var(--color-primary) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current,
.dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: var(--color-primary) !important;
    border-color: var(--color-primary) !important;
    color: #fff !important;
    font-weight: 700;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
.dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
    opacity: 0.35;
    cursor: not-allowed;
    pointer-events: none;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.previous,
.dataTables_wrapper .dataTables_paginate .paginate_button.next {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.65rem;
    letter-spacing: 0.03em;
    padding: 0 0.75rem;
}

/* En-têtes */
table.dataTable thead th,
table.dataTable thead td {
    background-color: var(--color-border-subtle) !important;
    color: var(--color-text) !important;
    border-bottom: 2px solid var(--color-primary) !important;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    padding: 0.65rem 1rem !important;
    white-space: nowrap;
    cursor: pointer;
    user-select: none;
}

table.dataTable thead th.sorting::after,
table.dataTable thead th.sorting_asc::after,
table.dataTable thead th.sorting_desc::after {
    opacity: 0.6;
    color: var(--color-primary) !important;
}

table.dataTable thead th.sorting_asc::after,
table.dataTable thead th.sorting_desc::after {
    opacity: 1;
    color: var(--color-primary) !important;
}

table.dataTable thead tr th:active,
table.dataTable thead tr td:active {
    outline: none;
    background-color: var(--color-border-subtle) !important;
}

/* Lignes */
table.dataTable tbody tr {
    background-color: var(--color-card) !important;
    border-bottom: 1px solid var(--color-border-subtle) !important;
    transition: background-color 0.15s;
}

table.dataTable tbody tr:hover {
    background-color: var(--color-sidebar-active-bg) !important;
}

table.dataTable.stripe tbody tr.odd,
table.dataTable.display tbody tr.odd {
    background-color: var(--color-card) !important;
}

table.dataTable.stripe tbody tr.even,
table.dataTable.display tbody tr.even {
    background-color: var(--color-bg) !important;
}

table.dataTable tbody td {
    padding: 0.6rem 1rem !important;
    color: var(--color-text) !important;
    border: none !important;
    vertical-align: middle;
}

table.dataTable {
    border-collapse: collapse !important;
    margin: 0 !important;
    width: 100% !important;
    border: none !important;
}

.dataTables_wrapper .dataTables_processing {
    background: var(--color-card);
    color: var(--color-primary);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.5rem;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.75rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

/* ============================================================
   DESIGN PAGE — VÉHICULES
   ============================================================ */

/* Toolbar */
.table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.table-toolbar-left {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Compteur */
.vehicles-count-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.25rem 0.65rem;
    border-radius: 9999px;
    background: var(--color-sidebar-active-bg);
    border: 1px solid rgba(245, 130, 32, 0.25);
    color: var(--color-primary);
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.65rem;
    font-weight: 700;
}

/* Boutons actions tableau */
.tbl-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 0.4rem;
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
    font-size: 0.78rem;
}

.tbl-action.locate {
    color: var(--color-primary);
    border-color: rgba(245, 130, 32, 0.3);
}
.tbl-action.locate:hover {
    background-color: rgba(245, 130, 32, 0.1);
    border-color: var(--color-primary);
}

.tbl-action.assign {
    color: #3b82f6;
    border-color: rgba(59, 130, 246, 0.3);
}
.tbl-action.assign:hover {
    background-color: rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
}

/* Pastille couleur véhicule */
.color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 2px solid var(--color-border-subtle);
    display: inline-block;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.15);
    transition: border-color 0.2s, transform 0.15s;
    cursor: default;
    vertical-align: middle;
    flex-shrink: 0;
}

.color-swatch:hover {
    border-color: var(--color-primary);
    transform: scale(1.2);
}

/* Immatriculation stylée */
.immat-cell {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.immat-badge {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--color-text);
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.35rem;
    padding: 3px 8px;
    white-space: nowrap;
}

/* ============================================================
   MODALE AFFECTATION
   ============================================================ */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.65);
    z-index: 9000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(2px);
}

.modal-panel {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 1rem;
    width: 100%;
    max-width: 52rem;
    max-height: 90vh;
    overflow-y: auto;
    padding: 1.5rem;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    transform: translateY(8px);
    opacity: 0;
    transition: transform 0.2s ease, opacity 0.2s ease;
}

.modal-panel.open {
    transform: translateY(0);
    opacity: 1;
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    color: var(--color-secondary-text);
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s, background-color 0.2s;
    line-height: 1;
}

.modal-close:hover {
    color: #ef4444;
    background-color: rgba(239, 68, 68, 0.1);
}

.modal-title {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0 0 0.5rem;
    padding-right: 2rem;
}

.modal-ctx {
    font-size: 0.78rem;
    color: var(--color-secondary-text);
    margin-bottom: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
</style>
@endpush

@section('content')

<div class="space-y-4">

    {{-- Flash messages --}}
    @if(session('success'))
    <div style="padding:0.75rem 1rem;border-radius:0.5rem;background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);color:#16a34a;font-size:0.82rem;display:flex;align-items:center;gap:0.5rem;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div style="padding:0.75rem 1rem;border-radius:0.5rem;background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.3);color:#ef4444;font-size:0.82rem;display:flex;align-items:center;gap:0.5rem;">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
    </div>
    @endif

    @if($errors->any())
    <div style="padding:0.75rem 1rem;border-radius:0.5rem;background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.3);color:#ef4444;font-size:0.82rem;">
        <ul style="margin:0;padding-left:1.25rem;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- ============================================================
         TABLEAU VÉHICULES
         ============================================================ --}}
    <div class="ui-card" style="padding:1.25rem;">

        {{-- Toolbar --}}
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <h2 class="font-orbitron" style="font-size:0.9rem;font-weight:700;color:var(--color-text);margin:0;">
                    Flotte de véhicules
                </h2>
                <span class="vehicles-count-badge">
                    <i class="fas fa-car" style="font-size:0.6rem;"></i>
                    {{ count($voitures ?? []) }} véhicule(s)
                </span>
            </div>

            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                {{-- Recherche custom --}}
                <div style="position:relative;">
                    <i class="fas fa-search" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:0.75rem;pointer-events:none;"></i>
                    <input id="vehiclesSearchInput"
                           type="text"
                           class="ui-input-style"
                           style="padding-left:2rem;min-width:200px;font-size:0.78rem;"
                           placeholder="Immat, marque, modèle...">
                </div>

                {{-- Lien Associations --}}
                <a href="{{ route('partner.affectations.index') }}" class="btn-secondary" style="white-space:nowrap;font-size:0.78rem;">
                    <i class="fas fa-link"></i> Associations
                </a>
            </div>
        </div>

        {{-- Tableau --}}
        <div class="ui-table-container">
            <table id="vehiclesTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Immatriculation</th>
                        <th>Marque</th>
                        <th>Modèle</th>
                        <th>Couleur</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($voitures ?? [] as $voiture)
                    @php
                        $label = trim(($voiture->immatriculation ?? '') . ' – ' . ($voiture->marque ?? '') . ' ' . ($voiture->model ?? ''));
                    @endphp
                    <tr>
                        {{-- Immatriculation --}}
                        <td>
                            <div class="immat-cell">
                                <span class="immat-badge">{{ $voiture->immatriculation }}</span>
                            </div>
                        </td>

                        {{-- Marque --}}
                        <td>
                            <span style="font-weight:600;color:var(--color-text);">{{ $voiture->marque ?? '—' }}</span>
                        </td>

                        {{-- Modèle --}}
                        <td style="color:var(--color-secondary-text);">{{ $voiture->model ?? '—' }}</td>

                        {{-- Couleur --}}
                        <td>
                            <div style="display:flex;align-items:center;gap:0.5rem;">
                                <span class="color-swatch"
                                      style="background-color:{{ $voiture->couleur ?? '#e5e7eb' }};"
                                      title="{{ $voiture->couleur ?? 'N/A' }}"></span>
                            </div>
                        </td>

                        {{-- Actions --}}
                        <td>
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.3rem;">
                                {{-- Localiser --}}
                                <button
                                    type="button"
                                    class="tbl-action locate"
                                    onclick="goToProfile({{ auth()->id() }}, {{ $voiture->id }})"
                                    title="Localiser {{ $voiture->immatriculation }}">
                                    <i class="fas fa-map-marker-alt"></i>
                                </button>

                                {{-- Associer chauffeur --}}
                                <button
                                    type="button"
                                    class="tbl-action assign js-open-affect-from-vehicle"
                                    data-voiture-id="{{ $voiture->id }}"
                                    data-voiture-label="{{ e($label) }}"
                                    title="Associer un chauffeur à {{ $voiture->immatriculation }}">
                                    <i class="fas fa-user-tag"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer info --}}
        <div style="margin-top:0.75rem;font-size:0.7rem;color:var(--color-secondary-text);display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:0.65rem;"></i>
            Cliquez sur <i class="fas fa-map-marker-alt" style="color:var(--color-primary);margin:0 2px;"></i> pour localiser,
            sur <i class="fas fa-user-tag" style="color:#3b82f6;margin:0 2px;"></i> pour associer un chauffeur.
        </div>
    </div>

</div>

{{-- ============================================================
     MODALE ASSOCIATION CHAUFFEUR
     ============================================================ --}}
<div id="affectModal" class="modal-overlay" style="display:none;" aria-modal="true" role="dialog">
    <div id="affectPanel" class="modal-panel">

        <button type="button" id="affectClose" class="modal-close" aria-label="Fermer">&times;</button>

        <h2 id="affectTitle" class="modal-title">Associer un chauffeur</h2>

        <div id="affectContext" class="modal-ctx">
            <i class="fas fa-car" style="color:var(--color-primary);"></i>
        </div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">
            <input id="affectSearch" type="text" class="ui-input-style"
                   style="flex:1;min-width:160px;" placeholder="Recherche intelligente...">
            <input id="affectNote" type="text" class="ui-input-style"
                   style="flex:1;min-width:160px;" placeholder="Note (optionnel)">
        </div>

        <div class="ui-table-container">
            <table class="ui-table w-full">
                <thead><tr id="affectHeadRow"></tr></thead>
                <tbody id="affectBody"></tbody>
            </table>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-top:1rem;">
            <button type="button" id="affectCancel" class="btn-secondary">Annuler</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {

    /* ============================================================
       DATATABLES INIT
       ============================================================ */
    let dt = null;
    if (window.jQuery && $.fn.DataTable) {
        dt = $('#vehiclesTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            searching: true,
            info: true,
            dom: '<"flex items-center justify-between flex-wrap gap-2 mb-3"l>' +
                 't' +
                 '<"flex items-center justify-between flex-wrap gap-2 mt-3"ip>',
            language: {
                processing:   'Chargement...',
                search:       'Rechercher :',
                lengthMenu:   'Afficher _MENU_ lignes',
                info:         '_START_–_END_ sur _TOTAL_',
                infoEmpty:    '0–0 sur 0',
                infoFiltered: '(filtré parmi _MAX_)',
                loadingRecords: 'Chargement...',
                zeroRecords:  'Aucun véhicule trouvé',
                emptyTable:   'Aucun véhicule enregistré',
                paginate: { first:'«', previous:'‹', next:'›', last:'»' }
            }
        });

        const searchInput = document.getElementById('vehiclesSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                dt.search(this.value).draw();
            });
        }
    }

    /* ============================================================
       HELPERS
       ============================================================ */
    function esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, m =>
            ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
    }

    function openOverlay(el, panel) {
        el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => panel?.classList.add('open')));
    }

    function closeOverlay(el, panel, onClosed) {
        panel?.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => { el.style.display = 'none'; onClosed?.(); }, 200);
    }

    /* ============================================================
       MODALE AFFECTATION
       ============================================================ */
    const modal    = document.getElementById('affectModal');
    const panel    = document.getElementById('affectPanel');
    const closeBtn = document.getElementById('affectClose');
    const cancelBtn= document.getElementById('affectCancel');
    const titleEl  = document.getElementById('affectTitle');
    const ctxEl    = document.getElementById('affectContext');
    const headRow  = document.getElementById('affectHeadRow');
    const bodyEl   = document.getElementById('affectBody');
    const searchEl = document.getElementById('affectSearch');
    const noteEl   = document.getElementById('affectNote');

    if (!modal || !panel) return;

    const CSRF         = document.querySelector('meta[name="csrf-token"]')?.content;
    const URL_VEHICLES = @json(route('partner.affectations.vehicles'));
    const URL_DRIVERS  = @json(route('partner.affectations.drivers'));
    const URL_ASSIGN   = @json(route('partner.affectations.assign'));

    let state = { mode: null, chauffeurId: null, voitureId: null, timer: null };

    function openModal() { openOverlay(modal, panel); }

    function closeModal() {
        closeOverlay(modal, panel, () => {
            bodyEl.innerHTML = '';
            headRow.innerHTML = '';
            searchEl.value = '';
            noteEl.value = '';
            state = { mode: null, chauffeurId: null, voitureId: null, timer: null };
        });
    }

    async function loadList() {
        const q = (searchEl.value || '').trim();
        const url = state.mode === 'from_user'
            ? `${URL_VEHICLES}?q=${encodeURIComponent(q)}`
            : `${URL_DRIVERS}?q=${encodeURIComponent(q)}`;

        bodyEl.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--color-secondary-text);font-size:0.8rem;">
            <i class="fas fa-spinner fa-spin" style="color:var(--color-primary);margin-right:6px;"></i>Chargement...</td></tr>`;

        let json;
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            json = await res.json();
        } catch {
            bodyEl.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#ef4444;font-size:0.8rem;">Erreur réseau</td></tr>`;
            return;
        }

        if (!json.ok) {
            bodyEl.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:#ef4444;font-size:0.8rem;">${esc(json.message || 'Erreur')}</td></tr>`;
            return;
        }

        renderRows(json.items || []);
    }

    function renderRows(items) {
        if (!items.length) {
            bodyEl.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--color-secondary-text);font-size:0.8rem;">Aucun résultat</td></tr>`;
            return;
        }

        if (state.mode === 'from_user') {
            /* Depuis un chauffeur : choisir un véhicule */
            bodyEl.innerHTML = items.map(v => {
                const cur = v.current_driver
                    ? `<span style="color:#f97316;">Affecté : ${esc(v.current_driver.prenom)} ${esc(v.current_driver.nom)}</span>`
                    : `<span style="color:#22c55e;">Disponible</span>`;
                return `<tr>
                    <td><span class="immat-badge">${esc(v.immatriculation)}</span></td>
                    <td>${esc((v.marque || '') + ' ' + (v.model || ''))}</td>
                    <td style="color:var(--color-secondary-text);font-size:0.72rem;font-family:monospace;">${esc(v.mac_id_gps || '—')}</td>
                    <td style="font-size:0.72rem;">${cur}</td>
                    <td style="text-align:right;">
                        <button type="button" class="btn-primary js-pick" data-voiture-id="${v.id}" style="font-size:0.7rem;padding:0.3rem 0.75rem;">
                            <i class="fas fa-link"></i> Associer
                        </button>
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
            /* Depuis un véhicule : choisir un chauffeur */
            bodyEl.innerHTML = items.map(u => {
                const cur = u.current_vehicle
                    ? `<span style="color:#f97316;">Affecté : ${esc(u.current_vehicle.immatriculation)}</span>`
                    : `<span style="color:#22c55e;">Disponible</span>`;
                return `<tr>
                    <td><strong style="color:var(--color-text);">${esc((u.prenom || '') + ' ' + (u.nom || ''))}</strong></td>
                    <td style="color:var(--color-secondary-text);">
                        <i class="fas fa-phone" style="font-size:0.6rem;color:var(--color-primary);margin-right:4px;"></i>${esc(u.phone || '—')}
                    </td>
                    <td style="color:var(--color-secondary-text);font-size:0.72rem;">${esc(u.email || '—')}</td>
                    <td style="font-size:0.72rem;">${cur}</td>
                    <td style="text-align:right;">
                        <button type="button" class="btn-primary js-pick" data-chauffeur-id="${u.id}" style="font-size:0.7rem;padding:0.3rem 0.75rem;">
                            <i class="fas fa-link"></i> Associer
                        </button>
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
            voiture_id:   state.voitureId,
            note:         noteEl.value || null,
            force:        !!force
        };

        const res = await fetch(URL_ASSIGN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(payload)
        });

        if (res.status === 409) {
            const j = await res.json();
            let msg = '';
            if (j.type === 'conflict_vehicle') {
                msg = `Ce véhicule est déjà associé à ${j.existing?.prenom ?? ''} ${j.existing?.nom ?? ''} (${j.existing?.phone ?? ''}).\n\nForcer la réaffectation ?`;
            } else if (j.type === 'conflict_driver') {
                msg = `Ce chauffeur est déjà associé au véhicule ${j.existing?.immatriculation ?? ''} (${j.existing?.marque ?? ''} ${j.existing?.model ?? ''}).\n\nForcer la réaffectation ?`;
            } else {
                msg = `Conflit détecté. Forcer la réaffectation ?`;
            }
            if (confirm(msg)) doAssign(true);
            return;
        }

        const json = await res.json();
        if (!json.ok) { alert(json.message || 'Erreur affectation'); return; }
        alert(json.message || 'Affectation réussie !');
        window.location.reload();
    }

    /* ---- Ouverture depuis véhicule ---- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-open-affect-from-vehicle');
        if (!btn) return;

        state.mode       = 'from_vehicle';
        state.voitureId  = parseInt(btn.dataset.voitureId, 10);
        state.chauffeurId= null;

        titleEl.textContent  = 'Associer un chauffeur';
        ctxEl.innerHTML = `<i class="fas fa-car" style="color:var(--color-primary);"></i> ${esc(btn.dataset.voitureLabel || '')}`;

        headRow.innerHTML = ['Chauffeur', 'Téléphone', 'Email', 'Statut', '']
            .map(c => `<th>${c}</th>`).join('');

        openModal();
        loadList();
    });

    closeBtn?.addEventListener('click',  closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    searchEl?.addEventListener('input', () => {
        clearTimeout(state.timer);
        state.timer = setTimeout(loadList, 280);
    });

    /* ---- APIs globales (pour usage depuis d'autres scripts) ---- */
    window.openAffectModalFromVehicle = function (voitureId, voitureLabel) {
        state.mode        = 'from_vehicle';
        state.voitureId   = voitureId;
        state.chauffeurId = null;
        titleEl.textContent = 'Associer un chauffeur';
        ctxEl.innerHTML = `<i class="fas fa-car" style="color:var(--color-primary);"></i> ${esc(voitureLabel || '')}`;
        headRow.innerHTML = ['Chauffeur','Téléphone','Email','Statut',''].map(c => `<th>${c}</th>`).join('');
        openModal();
        loadList();
    };

    window.openAffectModalFromUser = function (chauffeurId, chauffeurLabel) {
        state.mode        = 'from_user';
        state.chauffeurId = chauffeurId;
        state.voitureId   = null;
        titleEl.textContent = 'Associer un véhicule';
        ctxEl.innerHTML = `<i class="fas fa-user" style="color:var(--color-primary);"></i> ${esc(chauffeurLabel || '')}`;
        headRow.innerHTML = ['Immatriculation','Véhicule','GPS','Statut',''].map(c => `<th>${c}</th>`).join('');
        openModal();
        loadList();
    };

    console.log('[AffectModal:vehicles] ready ✅');
})();

/* Localisation */
function goToProfile(userId, vehicleId) {
    if (!userId || !vehicleId) return;
    window.location.href = `/users/${userId}/profile?vehicle_id=${vehicleId}`;
}
</script>
@endpush

@endsection