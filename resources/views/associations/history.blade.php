@extends('layouts.app')

@section('title', 'Historique des associations')

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

/* Masquer la recherche native — on utilise notre input custom */
.dataTables_wrapper .dataTables_filter { display: none !important; }

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

.dataTables_wrapper .dataTables_info {
    color: var(--color-secondary-text);
    font-size: 0.75rem;
    padding-top: 0.5rem;
}

/* Pagination — masquée ici car on utilise la pagination Laravel */
.dataTables_wrapper .dataTables_paginate { display: none !important; }

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
   PAGE — HISTORIQUE
   ============================================================ */

/* Onglets navigation */
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

/* Compteur */
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

/* Chip date */
.date-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.68rem;
    color: var(--color-secondary-text);
    white-space: nowrap;
}

/* Badge durée */
.duration-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 9999px;
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
}
.duration-badge.ongoing {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.3);
    color: #16a34a;
}
.duration-badge.ended {
    background: var(--color-border-subtle);
    border: 1px solid var(--color-border-subtle);
    color: var(--color-secondary-text);
}

/* Note tronquée */
.note-cell {
    max-width: 140px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.72rem;
    color: var(--color-secondary-text);
    font-style: italic;
}

/* Pagination Laravel — override pour correspondre au design */
.pagination {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
    list-style: none;
    padding: 0;
    margin: 0;
}
.pagination li > a,
.pagination li > span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.5rem;
    border-radius: 0.4rem;
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    color: var(--color-text);
    font-size: 0.78rem;
    font-weight: 500;
    text-decoration: none;
    transition: background-color 0.15s, color 0.15s, border-color 0.15s;
}
.pagination li > a:hover {
    background: var(--color-sidebar-active-bg);
    border-color: var(--color-primary);
    color: var(--color-primary);
}
.pagination li.active > span,
.pagination li > span[aria-current="page"] {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
    font-weight: 700;
}
.pagination li.disabled > span {
    opacity: 0.35;
    cursor: not-allowed;
}
/* Boutons Précédent/Suivant */
.pagination li:first-child > a,
.pagination li:first-child > span,
.pagination li:last-child > a,
.pagination li:last-child > span {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.62rem;
    padding: 0 0.75rem;
    letter-spacing: 0.03em;
}

/* Info pagination */
.pagination-info {
    font-size: 0.75rem;
    color: var(--color-secondary-text);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
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

    @if($errors->any())
    <div style="padding:0.75rem 1rem;border-radius:0.5rem;background:rgba(239,68,68,0.10);border:1px solid rgba(239,68,68,0.3);color:#ef4444;font-size:0.82rem;">
        <ul style="margin:0;padding-left:1.25rem;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- ============================================================
         TABLEAU HISTORIQUE
         ============================================================ --}}
    <div class="ui-card" style="padding:1.25rem;">

        {{-- Toolbar --}}
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <h2 class="font-orbitron" style="font-size:0.9rem;font-weight:700;color:var(--color-text);margin:0;">
                    Historique des associations
                </h2>
                <span class="count-badge">
                    <i class="fas fa-clock-rotate-left" style="font-size:0.6rem;"></i>
                    {{ $items->total() ?? 0 }} entrée(s)
                </span>
            </div>

            {{-- Recherche (filtre DataTables sur la page courante) --}}
            <div style="position:relative;">
                <i class="fas fa-search" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:0.75rem;pointer-events:none;"></i>
                <input id="historySearchInput"
                       type="text"
                       class="ui-input-style"
                       style="padding-left:2rem;min-width:200px;font-size:0.78rem;"
                       placeholder="Filtrer cette page...">
            </div>
        </div>

        {{-- Tableau --}}
        <div class="ui-table-container">
            <table id="historyTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Véhicule</th>
                        <th>Chauffeur</th>
                        <th>Téléphone</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Durée</th>
                        <th>Par</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                @foreach(($items ?? []) as $row)
                @php
                    $start    = $row->started_at ? \Carbon\Carbon::parse($row->started_at) : null;
                    $end      = $row->ended_at   ? \Carbon\Carbon::parse($row->ended_at)   : null;
                    $ongoing  = $start && !$end;
                    $duration = null;
                    if ($start && $end)  { $duration = $start->diffForHumans($end, true); }
                    elseif ($ongoing)    { $duration = 'En cours'; }
                @endphp
                <tr>
                    {{-- Véhicule --}}
                    <td>
                        <span class="immat-badge">{{ $row->voiture?->immatriculation ?? '—' }}</span>
                        <div style="font-size:0.68rem;color:var(--color-secondary-text);margin-top:3px;">
                            {{ $row->voiture?->marque }} {{ $row->voiture?->model }}
                        </div>
                    </td>

                    {{-- Chauffeur --}}
                    <td>
                        <div style="display:flex;align-items:center;gap:0.4rem;">
                            <div style="width:26px;height:26px;border-radius:50%;background:var(--color-sidebar-active-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user" style="font-size:0.6rem;color:var(--color-primary);"></i>
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

                    {{-- Début --}}
                    <td>
                        @if($start)
                        <span class="date-chip">
                            <i class="fas fa-play" style="font-size:0.55rem;color:#22c55e;"></i>
                            {{ $start->format('d/m/Y') }}
                        </span>
                        <div style="font-size:0.65rem;color:var(--color-secondary-text);margin-top:1px;padding-left:14px;">
                            {{ $start->format('H:i') }}
                        </div>
                        @else
                        <span style="color:var(--color-secondary-text);font-size:0.72rem;">—</span>
                        @endif
                    </td>

                    {{-- Fin --}}
                    <td>
                        @if($end)
                        <span class="date-chip">
                            <i class="fas fa-stop" style="font-size:0.55rem;color:#ef4444;"></i>
                            {{ $end->format('d/m/Y') }}
                        </span>
                        <div style="font-size:0.65rem;color:var(--color-secondary-text);margin-top:1px;padding-left:14px;">
                            {{ $end->format('H:i') }}
                        </div>
                        @elseif($ongoing)
                        <span class="date-chip">
                            <i class="fas fa-circle" style="font-size:0.5rem;color:#22c55e;animation:pulse 2s infinite;"></i>
                            Toujours active
                        </span>
                        @else
                        <span style="color:var(--color-secondary-text);font-size:0.72rem;">—</span>
                        @endif
                    </td>

                    {{-- Durée --}}
                    <td>
                        @if($duration)
                        <span class="duration-badge {{ $ongoing ? 'ongoing' : 'ended' }}">
                            <i class="fas fa-{{ $ongoing ? 'spinner fa-spin' : 'hourglass-end' }}" style="font-size:0.55rem;"></i>
                            {{ $duration }}
                        </span>
                        @else
                        <span style="color:var(--color-secondary-text);font-size:0.72rem;">—</span>
                        @endif
                    </td>

                    {{-- Par --}}
                    <td style="font-size:0.72rem;color:var(--color-secondary-text);">
                        @if($row->assigner)
                        <span style="display:flex;align-items:center;gap:4px;">
                            <i class="fas fa-user-shield" style="font-size:0.6rem;color:var(--color-primary);"></i>
                            {{ $row->assigner->prenom }} {{ $row->assigner->nom }}
                        </span>
                        @else
                        —
                        @endif
                    </td>

                    {{-- Note --}}
                    <td>
                        <div class="note-cell" title="{{ $row->note }}">
                            {{ $row->note ?? '—' }}
                        </div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination Laravel + info --}}
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;margin-top:1rem;">

            {{-- Info --}}
            <p class="pagination-info">
                <i class="fas fa-list" style="color:var(--color-primary);font-size:0.65rem;"></i>
                Page {{ $items->currentPage() }} / {{ $items->lastPage() }}
                &nbsp;·&nbsp;
                {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} sur {{ $items->total() }}
            </p>

            {{-- Liens --}}
            {{ $items->links() }}
        </div>

        {{-- Footer --}}
        <div style="margin-top:0.5rem;font-size:0.7rem;color:var(--color-secondary-text);display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:0.65rem;"></i>
            Le filtre s'applique sur la page courante. Utilisez la pagination pour naviguer entre les pages.
        </div>
    </div>

</div>

@push('scripts')
<style>
/* Pulse animation pour "En cours" */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ============================================================
       DATATABLES — paging: false car on utilise la pagination Laravel
       Le tri et la recherche (sur la page courante) restent actifs.
       ============================================================ */
    let dt = null;
    if (window.jQuery && $.fn.DataTable) {
        dt = $('#historyTable').DataTable({
            pageLength: 25,
            ordering: true,
            searching: true,
            info: true,
            paging: false, /* Pagination gérée par Laravel */
            dom: '<"flex items-center justify-between flex-wrap gap-2 mb-3"l>t<"mt-2"i>',
            language: {
                processing:   'Chargement...',
                search:       'Rechercher :',
                lengthMenu:   'Afficher _MENU_ lignes',
                info:         '_START_–_END_ sur _TOTAL_ (page courante)',
                infoEmpty:    '0 entrée',
                infoFiltered: '(filtré parmi _MAX_)',
                loadingRecords: 'Chargement...',
                zeroRecords:  'Aucun résultat sur cette page',
                emptyTable:   'Aucun historique disponible',
            }
        });

        /* Champ de recherche custom → DataTables */
        const searchInput = document.getElementById('historySearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                dt.search(this.value).draw();
            });
        }
    }
});
</script>
@endpush

@endsection