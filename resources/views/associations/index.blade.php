@extends('layouts.app')

@section('title', 'Associations Chauffeur ↔ Véhicule')

@push('styles')
<style>
/* ============================================================
   DATATABLES — OVERRIDE COMPLET (cohérent avec le design system)
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

.dataTables_wrapper .dataTables_length select {
    background-color: var(--color-input-bg) !important;
    border: 1px solid var(--color-input-border) !important;
    color: var(--color-text) !important;
    border-radius: 0.4rem;
    padding: 0.3rem 0.5rem;
    font-size: 0.78rem;
    cursor: pointer;
    appearance: auto;
    transition: border-color 0.2s;
}
.dataTables_wrapper .dataTables_length select:focus {
    outline: none;
    border-color: var(--color-primary) !important;
    box-shadow: 0 0 0 3px rgba(245,130,32,0.2);
}

/* Barre de recherche native DataTables — on la remplace par la nôtre */
.dataTables_wrapper .dataTables_filter input {
    background-color: var(--color-input-bg) !important;
    border: 1px solid var(--color-input-border) !important;
    color: var(--color-text) !important;
    border-radius: 0.4rem;
    padding: 0.3rem 0.6rem;
    font-size: 0.78rem;
    transition: border-color 0.2s;
}
.dataTables_wrapper .dataTables_filter input:focus {
    outline: none;
    border-color: var(--color-primary) !important;
    box-shadow: 0 0 0 3px rgba(245,130,32,0.2);
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
table.dataTable thead th.sorting_desc::after { color: var(--color-primary) !important; }
table.dataTable thead th.sorting_asc::after,
table.dataTable thead th.sorting_desc::after { opacity: 1; color: var(--color-primary) !important; }
table.dataTable thead tr th:active { outline: none; background-color: var(--color-border-subtle) !important; }

/* Lignes */
table.dataTable tbody tr {
    background-color: var(--color-card) !important;
    border-bottom: 1px solid var(--color-border-subtle) !important;
    transition: background-color 0.15s;
}
table.dataTable tbody tr:hover { background-color: var(--color-sidebar-active-bg) !important; }
table.dataTable.stripe tbody tr.odd  { background-color: var(--color-card) !important; }
table.dataTable.stripe tbody tr.even { background-color: var(--color-bg) !important; }
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

/* ============================================================
   PAGE — ASSOCIATIONS
   ============================================================ */

/* Onglets de navigation */
.nav-tabs { display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap; }

.nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.9rem;
    border-radius: 0.5rem;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-decoration: none;
    transition: background-color 0.2s, color 0.2s, border-color 0.2s;
    border: 1px solid transparent;
    color: var(--color-secondary-text);
}
.nav-tab:hover {
    color: var(--color-primary);
    background-color: var(--color-sidebar-active-bg);
    border-color: var(--color-border-subtle);
}
.nav-tab.active {
    color: var(--color-primary);
    background-color: var(--color-sidebar-active-bg);
    border-color: var(--color-primary);
}

/* Toolbar */
.table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.table-toolbar-left { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

/* Badge compteur */
.count-badge {
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

/* Immatriculation badge */
.immat-badge {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--color-text);
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: 0.35rem;
    padding: 2px 7px;
    white-space: nowrap;
    display: inline-block;
}

/* GPS tag */
.gps-tag {
    font-family: monospace;
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    background: var(--color-border-subtle);
    border-radius: 0.3rem;
    padding: 2px 6px;
}

/* Bouton désaffecter */
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
.tbl-action.unlink {
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}
.tbl-action.unlink:hover {
    background-color: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
}

/* Date badge */
.date-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    white-space: nowrap;
}

/* Note truncate */
.note-cell {
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.72rem;
    color: var(--color-secondary-text);
    font-style: italic;
}

/* ============================================================
   MODALE CONFIRM DÉSAFFECTATION (centrée, stylée)
   ============================================================ */
.confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(3px);
}

.confirm-panel {
    background-color: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 1rem;
    width: 100%;
    max-width: 400px;
    padding: 1.75rem;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: translateY(12px) scale(0.97);
    opacity: 0;
    transition: transform 0.22s ease, opacity 0.22s ease;
}
.confirm-panel.open {
    transform: translateY(0) scale(1);
    opacity: 1;
}

/* Icône danger */
.confirm-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.12);
    border: 2px solid rgba(239, 68, 68, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: #ef4444;
    font-size: 1.3rem;
}

.confirm-title {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--color-text);
    text-align: center;
    margin: 0 0 0.5rem;
}

.confirm-desc {
    font-size: 0.8rem;
    color: var(--color-secondary-text);
    text-align: center;
    margin: 0 0 0.25rem;
    line-height: 1.5;
}

.confirm-vehicle {
    text-align: center;
    margin: 0.75rem 0 1.25rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
}

.confirm-actions {
    display: flex;
    gap: 0.5rem;
}

.confirm-actions button {
    flex: 1;
    padding: 0.55rem 1rem;
    border-radius: 0.5rem;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    cursor: pointer;
    transition: background-color 0.15s, transform 0.1s, opacity 0.15s;
    border: none;
}

.btn-confirm-cancel {
    background: var(--color-border-subtle);
    color: var(--color-text);
}
.btn-confirm-cancel:hover { opacity: 0.8; }

.btn-confirm-danger {
    background: #ef4444;
    color: #fff;
}
.btn-confirm-danger:hover { background: #b91c1c; }
.btn-confirm-danger:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.btn-confirm-danger .spinner {
    display: none;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-right: 6px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.btn-confirm-danger.loading .spinner { display: inline-block; }
.btn-confirm-danger.loading .btn-label { opacity: 0.7; }
</style>
@endpush

@section('content')

<div class="space-y-5">

    {{-- ============================================================
         NAVIGATION ONGLETS
         ============================================================ --}}
    <div style="border-bottom:1px solid var(--color-border-subtle);padding-bottom:0.75rem;">
        <nav class="nav-tabs">
            <a href="{{ route('users.index') }}" class="nav-tab">
                <i class="fas fa-users"></i> Chauffeurs
            </a>
            <a href="{{ route('partner.affectations.index') }}"
               class="nav-tab {{ request()->routeIs('partner.affectations.index') ? 'active' : '' }}">
                <i class="fas fa-link"></i> Associations
            </a>
            <a href="{{ route('partner.affectations.history') }}"
               class="nav-tab {{ request()->routeIs('partner.affectations.history') ? 'active' : '' }}">
                <i class="fas fa-clock-rotate-left"></i> Historique
            </a>
        </nav>
    </div>

    {{-- Flash --}}
    @if(session('status'))
    <div style="padding:0.75rem 1rem;border-radius:0.5rem;background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);color:#16a34a;font-size:0.82rem;display:flex;align-items:center;gap:0.5rem;">
        <i class="fas fa-check-circle"></i> {{ session('status') }}
    </div>
    @endif

    {{-- ============================================================
         TABLEAU ASSOCIATIONS
         ============================================================ --}}
    <div class="ui-card" style="padding:1.25rem;">

        {{-- Toolbar --}}
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <h2 class="font-orbitron" style="font-size:0.9rem;font-weight:700;color:var(--color-text);margin:0;">
                    Associations actives
                </h2>
                <span class="count-badge">
                    <i class="fas fa-link" style="font-size:0.6rem;"></i>
                    {{ count($items ?? []) }} association(s)
                </span>
            </div>

            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                {{-- Recherche intégrée à DataTables (on garde la native ici car multi-colonnes) --}}
                <div style="position:relative;">
                    <i class="fas fa-search" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:0.75rem;pointer-events:none;"></i>
                    <input id="assocSearchInput"
                           type="text"
                           class="ui-input-style"
                           style="padding-left:2rem;min-width:200px;font-size:0.78rem;"
                           placeholder="Rechercher...">
                </div>

                <a href="{{ route('tracking.vehicles') }}" class="btn-secondary" style="font-size:0.78rem;white-space:nowrap;">
                    <i class="fas fa-car"></i> Véhicules
                </a>
                <a href="{{ route('users.index') }}" class="btn-secondary" style="font-size:0.78rem;white-space:nowrap;">
                    <i class="fas fa-users"></i> Chauffeurs
                </a>
            </div>
        </div>

        {{-- Table --}}
        <div class="ui-table-container">
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
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach(($items ?? []) as $row)
                <tr>
                    {{-- Véhicule --}}
                    <td>
                        <div>
                            <span class="immat-badge">{{ $row->voiture?->immatriculation ?? '—' }}</span>
                        </div>
                        <div style="font-size:0.68rem;color:var(--color-secondary-text);margin-top:3px;">
                            {{ $row->voiture?->marque }} {{ $row->voiture?->model }}
                        </div>
                    </td>

                    {{-- Chauffeur --}}
                    <td>
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <div style="width:28px;height:28px;border-radius:50%;background:var(--color-sidebar-active-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user" style="font-size:0.65rem;color:var(--color-primary);"></i>
                            </div>
                            <span style="font-weight:600;font-size:0.82rem;color:var(--color-text);">
                                {{ $row->chauffeur?->prenom }} {{ $row->chauffeur?->nom }}
                            </span>
                        </div>
                    </td>

                    {{-- Téléphone --}}
                    <td>
                        <span style="font-size:0.78rem;color:var(--color-secondary-text);white-space:nowrap;">
                            <i class="fas fa-phone" style="font-size:0.6rem;color:var(--color-primary);margin-right:4px;"></i>
                            {{ $row->chauffeur?->phone ?? '—' }}
                        </span>
                    </td>

                    {{-- GPS --}}
                    <td>
                        @if($row->voiture?->mac_id_gps)
                            <span class="gps-tag">{{ $row->voiture->mac_id_gps }}</span>
                        @else
                            <span style="color:var(--color-secondary-text);font-size:0.72rem;">—</span>
                        @endif
                    </td>

                    {{-- Date --}}
                    <td>
                        <span class="date-chip">
                            <i class="fas fa-calendar-alt" style="font-size:0.6rem;color:var(--color-primary);"></i>
                            {{ optional($row->assigned_at)->format('d/m/Y') ?? '—' }}
                        </span>
                        <div style="font-size:0.65rem;color:var(--color-secondary-text);margin-top:1px;padding-left:14px;">
                            {{ optional($row->assigned_at)->format('H:i') ?? '' }}
                        </div>
                    </td>

                    {{-- Par --}}
                    <td style="font-size:0.72rem;color:var(--color-secondary-text);">
                        {{ $row->assigner?->prenom }} {{ $row->assigner?->nom }}
                    </td>

                    {{-- Note --}}
                    <td>
                        <div class="note-cell" title="{{ $row->note }}">
                            {{ $row->note ?? '—' }}
                        </div>
                    </td>

                    {{-- Actions --}}
                    <td>
                        <div style="display:flex;justify-content:flex-end;">
                            <button
                                type="button"
                                class="tbl-action unlink js-unassign"
                                data-voiture-id="{{ $row->voiture_id }}"
                                data-chauffeur-id="{{ $row->chauffeur_id }}"
                                data-immat="{{ $row->voiture?->immatriculation }}"
                                data-chauffeur="{{ $row->chauffeur?->prenom }} {{ $row->chauffeur?->nom }}"
                                data-marque="{{ $row->voiture?->marque }} {{ $row->voiture?->model }}"
                                title="Désaffecter cette association">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div style="margin-top:0.75rem;font-size:0.7rem;color:var(--color-secondary-text);display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:0.65rem;"></i>
            Cliquez sur <i class="fas fa-unlink" style="color:#ef4444;margin:0 2px;"></i> pour désaffecter une association.
        </div>
    </div>

</div>

{{-- ============================================================
     MODALE CONFIRMATION DÉSAFFECTATION
     ============================================================ --}}
<div id="confirmModal" class="confirm-overlay" style="display:none;" aria-modal="true" role="alertdialog" aria-labelledby="confirmTitle">
    <div id="confirmPanel" class="confirm-panel">

        {{-- Icône danger --}}
        <div class="confirm-icon">
            <i class="fas fa-unlink"></i>
        </div>

        {{-- Titre --}}
        <h2 class="confirm-title" id="confirmTitle">Désaffecter l'association</h2>
        <p class="confirm-desc">Vous êtes sur le point de supprimer l'association entre :</p>

        {{-- Infos dynamiques --}}
        <div class="confirm-vehicle">
            <div style="display:flex;align-items:center;gap:0.4rem;">
                <span class="immat-badge" id="confirm-immat">—</span>
                <span style="font-size:0.72rem;color:var(--color-secondary-text);" id="confirm-marque"></span>
            </div>
            <div style="display:flex;align-items:center;gap:0.4rem;margin-top:4px;">
                <i class="fas fa-arrows-left-right" style="color:var(--color-primary);font-size:0.75rem;"></i>
                <span style="font-size:0.82rem;font-weight:600;color:var(--color-text);" id="confirm-chauffeur">—</span>
            </div>
        </div>

        <p class="confirm-desc" style="color:#ef4444;font-size:0.72rem;">
            Cette action est irréversible et sera enregistrée dans l'historique.
        </p>

        {{-- Boutons --}}
        <div class="confirm-actions" style="margin-top:1.25rem;">
            <button type="button" class="btn-confirm-cancel" id="confirmCancel">
                Annuler
            </button>
            <button type="button" class="btn-confirm-danger" id="confirmOk">
                <span class="spinner"></span>
                <span class="btn-label"><i class="fas fa-unlink" style="margin-right:5px;"></i>Désaffecter</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ============================================================
       DATATABLES INIT
       ============================================================ */
    let dt = null;
    if (window.jQuery && $.fn.DataTable) {
        dt = $('#assocTable').DataTable({
            pageLength: 25,
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
                zeroRecords:  'Aucune association trouvée',
                emptyTable:   'Aucune association active',
                paginate: { first:'«', previous:'‹', next:'›', last:'»' }
            }
        });

        /* Recherche custom branchée sur DT */
        const searchInput = document.getElementById('assocSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                dt.search(this.value).draw();
            });
        }
    }

    /* ============================================================
       MODALE CONFIRM DÉSAFFECTATION
       ============================================================ */
    const modal       = document.getElementById('confirmModal');
    const panel       = document.getElementById('confirmPanel');
    const cancelBtn   = document.getElementById('confirmCancel');
    const okBtn       = document.getElementById('confirmOk');
    const immatEl     = document.getElementById('confirm-immat');
    const marqueEl    = document.getElementById('confirm-marque');
    const chauffeurEl = document.getElementById('confirm-chauffeur');

    const CSRF    = document.querySelector('meta[name="csrf-token"]')?.content;
    const URL_UNASSIGN = @json(route('partner.affectations.unassign'));

    let pendingVoitureId   = null;
    let pendingChauffeurId = null;

    function openConfirm(voitureId, chauffeurId, immat, marque, chauffeur) {
        pendingVoitureId   = voitureId;
        pendingChauffeurId = chauffeurId;

        immatEl.textContent     = immat    || '—';
        marqueEl.textContent    = marque   || '';
        chauffeurEl.textContent = chauffeur|| '—';

        /* Reset bouton au cas où */
        okBtn.classList.remove('loading');
        okBtn.disabled = false;

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.add('open')));
    }

    function closeConfirm() {
        panel.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => {
            modal.style.display = 'none';
            pendingVoitureId   = null;
            pendingChauffeurId = null;
        }, 220);
    }

    cancelBtn?.addEventListener('click', closeConfirm);
    modal.addEventListener('click', e => { if (e.target === modal) closeConfirm(); });

    /* Fermer avec Escape */
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeConfirm();
    });

    /* Confirmation → appel API */
    okBtn?.addEventListener('click', async function () {
        if (!pendingVoitureId || !pendingChauffeurId) return;

        /* État loading */
        okBtn.classList.add('loading');
        okBtn.disabled = true;

        try {
            const res = await fetch(URL_UNASSIGN, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF
                },
                body: JSON.stringify({
                    voiture_id:   pendingVoitureId,
                    chauffeur_id: pendingChauffeurId,
                    note: 'Désaffectation manuelle'
                })
            });

            const json = await res.json().catch(() => null);

            if (!res.ok || !json?.ok) {
                okBtn.classList.remove('loading');
                okBtn.disabled = false;
                showInlineError(json?.message || 'Erreur lors de la désaffectation.');
                return;
            }

            /* Succès → fermer et recharger */
            closeConfirm();
            showSuccessToast(json.message || 'Association désaffectée avec succès.');
            setTimeout(() => window.location.reload(), 800);

        } catch (err) {
            okBtn.classList.remove('loading');
            okBtn.disabled = false;
            showInlineError('Erreur réseau. Veuillez réessayer.');
        }
    });

    /* ============================================================
       OUVERTURE DEPUIS LE BOUTON TABLEAU (event delegation)
       ============================================================ */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-unassign');
        if (!btn) return;

        openConfirm(
            parseInt(btn.dataset.voitureId,   10),
            parseInt(btn.dataset.chauffeurId, 10),
            btn.dataset.immat      || '—',
            btn.dataset.marque     || '',
            btn.dataset.chauffeur  || '—'
        );
    });

    /* ============================================================
       FEEDBACK : erreur inline dans la modale
       ============================================================ */
    function showInlineError(msg) {
        let el = document.getElementById('confirm-error');
        if (!el) {
            el = document.createElement('p');
            el.id = 'confirm-error';
            el.style.cssText = 'font-size:0.75rem;color:#ef4444;text-align:center;margin-top:0.5rem;';
            document.querySelector('.confirm-actions').insertAdjacentElement('beforebegin', el);
        }
        el.textContent = msg;
    }

    /* ============================================================
       TOAST SUCCÈS (réutilise window.showToastMsg du layout)
       ============================================================ */
    function showSuccessToast(msg) {
        if (window.showToastMsg) {
            window.showToastMsg('Désaffectation', msg, 'success');
        }
    }

});
</script>
@endpush

@endsection