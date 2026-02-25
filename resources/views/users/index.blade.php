@extends('layouts.app')

@section('title', 'Utilisateurs secondaires')

@push('styles')
<style>
/* ============================================================
   DATATABLES — OVERRIDE COMPLET
   Notre design system prévaut sur les styles natifs de DataTables.
   Stratégie : on réécrit TOUTES les classes .dataTables_* avec
   nos variables CSS et notre typographie Orbitron/system-font.
   ============================================================ */

/* ---- Wrapper global DataTables ---- */
.dataTables_wrapper {
    font-family: var(--font-body, system-ui, sans-serif);
    font-size: 0.82rem;
    color: var(--color-text);
}

/* ---- Controls row (length + filter) ---- */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--color-secondary-text);
    font-size: 0.78rem;
}

.dataTables_wrapper .dataTables_filter {
    justify-content: flex-end;
}

/* Masqué car on utilise notre propre input de recherche (#usersSearchInput) */
.dataTables_wrapper .dataTables_filter {
    display: none !important;
}

/* Select "Afficher X éléments" */
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

/* ---- Info bas de tableau ---- */
.dataTables_wrapper .dataTables_info {
    color: var(--color-secondary-text);
    font-size: 0.75rem;
    padding-top: 0.5rem;
}

/* ---- Pagination ---- */
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
    /* Supprimer le fond gris natif de DataTables */
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

/* Boutons Précédent / Suivant */
.dataTables_wrapper .dataTables_paginate .paginate_button.previous,
.dataTables_wrapper .dataTables_paginate .paginate_button.next {
    font-family: var(--font-display, 'Orbitron', sans-serif);
    font-size: 0.65rem;
    letter-spacing: 0.03em;
    padding: 0 0.75rem;
}

/* ---- Footer row (info + paginate côte à côte) ---- */
.dataTables_wrapper .dataTables_wrapper div.row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}

/* ---- Tri des colonnes (flèches) ---- */
table.dataTable thead th,
table.dataTable thead td {
    /* Écrase le background natif DataTables */
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

/* Flèche de tri : on la colore avec notre orange */
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

/* Supprimer les pseudo-éléments natifs DataTables (fond gris au clic) */
table.dataTable thead tr th:active,
table.dataTable thead tr td:active {
    outline: none;
    background-color: var(--color-border-subtle) !important;
}

/* ---- Lignes du corps ---- */
table.dataTable tbody tr {
    background-color: var(--color-card) !important;
    border-bottom: 1px solid var(--color-border-subtle) !important;
    transition: background-color 0.15s;
}

table.dataTable tbody tr:hover {
    background-color: var(--color-sidebar-active-bg) !important;
}

/* Lignes paires (stripes DataTables) — on neutralise le gris natif */
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

/* ---- Supprimer la bordure de table native DataTables ---- */
table.dataTable {
    border-collapse: collapse !important;
    margin: 0 !important;
    width: 100% !important;
    border: none !important;
}

/* ---- Traitement (processing overlay) ---- */
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
   LAYOUT PAGE — USERS
   ============================================================ */

/* Onglets de navigation */
.nav-tabs {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-wrap: wrap;
}

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

/* Badge de rôle */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 9999px;
    font-size: 0.62rem;
    font-weight: 700;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background-color: rgba(245, 130, 32, 0.12);
    color: var(--color-primary);
    border: 1px solid rgba(245, 130, 32, 0.3);
}

/* Action buttons dans le tableau */
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

.tbl-action.edit {
    color: #d97706;
    border-color: rgba(217, 119, 6, 0.3);
}
.tbl-action.edit:hover {
    background-color: rgba(217, 119, 6, 0.1);
    border-color: #d97706;
}

.tbl-action.assign {
    color: #3b82f6;
    border-color: rgba(59, 130, 246, 0.3);
}
.tbl-action.assign:hover {
    background-color: rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
}

.tbl-action.delete {
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}
.tbl-action.delete:hover {
    background-color: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
}

/* Avatar dans le tableau */
.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--color-border-subtle);
    cursor: pointer;
    transition: border-color 0.2s;
    flex-shrink: 0;
}

.user-avatar:hover {
    border-color: var(--color-primary);
}

/* Cellule nom+avatar */
.user-name-cell {
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

/* Barre de recherche custom (au-dessus du tableau) */
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

/* Compteur total */
.users-count-badge {
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

/* ============================================================
   MODALES — override pour hériter du design system
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
    max-width: 42rem;
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
    margin: 0 0 1.25rem;
    padding-right: 2rem;
}

.form-label {
    display: block;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--color-secondary-text);
    margin-bottom: 0.3rem;
    font-family: var(--font-display, 'Orbitron', sans-serif);
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

/* Séparateur dans la modale */
.modal-divider {
    border: none;
    border-top: 1px solid var(--color-border-subtle);
    margin: 1rem 0;
}

/* Photo upload zone */
.photo-upload-zone {
    border: 2px dashed var(--color-border-subtle);
    border-radius: 0.75rem;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.2s, background-color 0.2s;
}

.photo-upload-zone:hover {
    border-color: var(--color-primary);
    background-color: var(--color-sidebar-active-bg);
}
</style>
@endpush

@section('content')
@php
    $disk = config('media.disk', 'public');
    $storeUrl      = route('users.store');
    $baseUrl       = url('users');
    $assocIndexUrl = route('partner.affectations.index', [], false) ?? '#';
@endphp

<div class="space-y-5">

    {{-- ============================================================
         HEADER : titre + onglets de navigation
         ============================================================ --}}
    <div style="display:flex;flex-direction:column;gap:1rem;">

        {{-- Onglets --}}
        <div style="border-bottom:1px solid var(--color-border-subtle);padding-bottom:0.75rem;">
            <nav class="nav-tabs">
                <a href="{{ route('users.index') }}" class="nav-tab active">
                    <i class="fas fa-users"></i> Chauffeurs
                </a>
                <a href="{{ route('partner.affectations.index') }}" class="nav-tab">
                    <i class="fas fa-link"></i> Associations
                </a>
                <a href="{{ route('partner.affectations.history') }}" class="nav-tab">
                    <i class="fas fa-clock-rotate-left"></i> Historique
                </a>
            </nav>
        </div>

        {{-- Flash messages --}}
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

    </div>

    {{-- ============================================================
         TABLEAU PRINCIPAL
         ============================================================ --}}
    <div class="ui-card" style="padding:1.25rem;">

        {{-- Toolbar --}}
        <div class="table-toolbar">
            <div class="table-toolbar-left">
                <h2 class="font-orbitron" style="font-size:0.9rem;font-weight:700;color:var(--color-text);margin:0;">
                    Chauffeurs
                </h2>
                <span class="users-count-badge">
                    <i class="fas fa-users" style="font-size:0.6rem;"></i>
                    {{ count($users ?? []) }} enregistré(s)
                </span>
            </div>

            <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                {{-- Recherche custom (pilote DataTables via JS) --}}
                <div style="position:relative;">
                    <i class="fas fa-search" style="position:absolute;left:0.6rem;top:50%;transform:translateY(-50%);color:var(--color-secondary-text);font-size:0.75rem;pointer-events:none;"></i>
                    <input id="usersSearchInput"
                           type="text"
                           class="ui-input-style"
                           style="padding-left:2rem;min-width:200px;font-size:0.78rem;"
                           placeholder="Rechercher...">
                </div>

                <button type="button" id="openAddModalBtn" class="btn-primary" style="white-space:nowrap;">
                    <i class="fas fa-user-plus"></i> Nouveau chauffeur
                </button>
            </div>
        </div>

        {{-- Tableau --}}
        <div class="ui-table-container">
            <table id="usersTable" class="ui-table w-full">
                <thead>
                    <tr>
                        <th>Rôle</th>
                        <th>Chauffeur</th>
                        <th>Téléphone</th>
                        <th>Ville</th>
                        <th>Quartier</th>
                        <th>Email</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($users ?? []) as $user)
                    @php
                        $photoUrl = $user->photo
                            ? \Illuminate\Support\Facades\Storage::disk($disk)->url($user->photo)
                            : null;
                        $thumbUrl = $photoUrl ?: 'https://placehold.co/40x40/F58220/ffffff?text=' . strtoupper(substr($user->prenom ?? 'U', 0, 1));
                        $fullUrl  = $photoUrl ?: 'https://placehold.co/600x600/F58220/ffffff?text=' . strtoupper(substr($user->prenom ?? 'U', 0, 1));
                        $fullName = trim(($user->prenom ?? '') . ' ' . ($user->nom ?? ''));
                        $editPayload = [
                            'id'        => $user->id,
                            'nom'       => $user->nom,
                            'prenom'    => $user->prenom,
                            'phone'     => $user->phone,
                            'email'     => $user->email,
                            'ville'     => $user->ville,
                            'quartier'  => $user->quartier,
                            'photo_url' => $photoUrl,
                        ];
                    @endphp
                    <tr>
                        <td>
                            <span class="role-badge">
                                <i class="fas fa-steering-wheel" style="font-size:0.55rem;"></i>
                                Chauffeur
                            </span>
                        </td>
                        <td>
                            <div class="user-name-cell">
                                <img src="{{ $thumbUrl }}"
                                     alt="{{ $fullName }}"
                                     class="user-avatar js-user-photo"
                                     data-full-url="{{ $fullUrl }}"
                                     data-title="{{ $fullName }}"
                                     loading="lazy">
                                <div>
                                    <p style="font-weight:600;font-size:0.82rem;color:var(--color-text);margin:0;white-space:nowrap;">
                                        {{ $user->prenom }} {{ $user->nom }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td style="color:var(--color-secondary-text);white-space:nowrap;">
                            <i class="fas fa-phone" style="font-size:0.65rem;margin-right:4px;color:var(--color-primary);"></i>
                            {{ $user->phone }}
                        </td>
                        <td>{{ $user->ville ?? '—' }}</td>
                        <td style="color:var(--color-secondary-text);">{{ $user->quartier ?? '—' }}</td>
                        <td style="color:var(--color-secondary-text);font-size:0.78rem;">
                            {{ $user->email ?? '—' }}
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.3rem;">
                                {{-- Modifier --}}
                                <button type="button"
                                        class="tbl-action edit btn-edit"
                                        data-user-id="{{ $user->id }}"
                                        title="Modifier {{ $fullName }}">
                                    <i class="fas fa-pen"></i>
                                </button>

                                {{-- Associer véhicule --}}
                                <button type="button"
                                        class="tbl-action assign js-open-affect-from-user"
                                        data-user-id="{{ $user->id }}"
                                        data-user-label="{{ $user->prenom }} {{ $user->nom }} ({{ $user->phone }})"
                                        title="Associer un véhicule">
                                    <i class="fas fa-car"></i>
                                </button>

                                {{-- Supprimer --}}
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="tbl-action delete"
                                            title="Supprimer {{ $fullName }}"
                                            onclick="return confirm('Supprimer {{ addslashes($fullName) }} ?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>

                                {{-- JSON payload (caché) --}}
                                <script type="application/json" id="user-json-{{ $user->id }}">@json($editPayload)</script>
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
            Cliquez sur une photo pour l'agrandir. Utilisez la recherche pour filtrer les résultats.
        </div>
    </div>

</div>

{{-- ============================================================
     MODALE AJOUT / MODIFICATION
     ============================================================ --}}
<div id="userModal" class="modal-overlay" style="display:none;" aria-modal="true" role="dialog">
    <div id="userModalPanel" class="modal-panel">

        <button id="closeModalBtn" class="modal-close" aria-label="Fermer">&times;</button>

        <h2 id="modalTitle" class="modal-title">Ajouter un chauffeur</h2>

        <form id="userForm" action="{{ $storeUrl }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div id="methodSpoofContainer"></div>

            {{-- Nom + Prénom --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                <div>
                    <label for="nom" class="form-label">Nom</label>
                    <input type="text" id="nom" name="nom" class="ui-input-style" required autocomplete="family-name">
                </div>
                <div>
                    <label for="prenom" class="form-label">Prénom</label>
                    <input type="text" id="prenom" name="prenom" class="ui-input-style" required autocomplete="given-name">
                </div>
            </div>

            {{-- Téléphone + Email --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                <div>
                    <label for="phone" class="form-label">Téléphone</label>
                    <input type="tel" id="phone" name="phone" class="ui-input-style" required
                           placeholder="696..., +237...">
                </div>
                <div>
                    <label for="email" class="form-label">Email <span style="font-weight:400;text-transform:none;font-family:var(--font-body);">(optionnel)</span></label>
                    <input type="email" id="email" name="email" class="ui-input-style" autocomplete="email">
                </div>
            </div>

            {{-- Ville + Quartier --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
                <div>
                    <label for="ville" class="form-label">Ville</label>
                    <input type="text" id="ville" name="ville" class="ui-input-style">
                </div>
                <div>
                    <label for="quartier" class="form-label">Quartier</label>
                    <input type="text" id="quartier" name="quartier" class="ui-input-style">
                </div>
            </div>

            <hr class="modal-divider">

            {{-- Photo --}}
            <div style="margin-bottom:0.75rem;">
                <label class="form-label">Photo du chauffeur</label>
                <div class="photo-upload-zone" onclick="document.getElementById('photo').click();">
                    <i class="fas fa-camera" style="font-size:1.25rem;color:var(--color-primary);margin-bottom:0.4rem;display:block;"></i>
                    <p style="font-size:0.75rem;color:var(--color-secondary-text);margin:0;">Cliquer pour choisir une photo</p>
                    <p id="file-name" style="font-size:0.7rem;color:var(--color-primary);margin-top:0.3rem;font-style:italic;">Aucun fichier sélectionné</p>
                </div>
                <input type="file" id="photo" name="photo" accept="image/*" style="display:none;">

                {{-- Preview --}}
                <div id="previewWrapper" style="display:none;margin-top:0.75rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
                    <img id="preview" src="" alt="Aperçu"
                         style="width:72px;height:72px;object-fit:cover;border-radius:50%;border:3px solid var(--color-primary);">
                    <div>
                        <p style="font-size:0.72rem;color:var(--color-secondary-text);margin:0;">Aperçu de la photo</p>
                        <button type="button" id="removePhotoBtn"
                                style="font-size:0.7rem;color:#ef4444;background:none;border:none;cursor:pointer;padding:0;margin-top:2px;">
                            <i class="fas fa-times"></i> Supprimer
                        </button>
                    </div>
                </div>
            </div>

            <hr class="modal-divider">

            {{-- Mot de passe --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;" id="passwordFields">
                <div>
                    <label for="password" class="form-label">
                        Mot de passe
                        <span id="pwdHint" style="text-transform:none;font-family:var(--font-body);font-weight:400;font-size:0.68rem;color:var(--color-secondary-text);"></span>
                    </label>
                    <input type="password" id="password" name="password" class="ui-input-style" autocomplete="new-password">
                </div>
                <div>
                    <label for="password_confirmation" class="form-label">Confirmation</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="ui-input-style" autocomplete="new-password">
                </div>
            </div>

            <button type="submit" id="submitBtn" class="btn-primary" style="width:100%;">
                <i class="fas fa-user-plus"></i> Ajouter le chauffeur
            </button>
        </form>
    </div>
</div>

{{-- ============================================================
     MODALE PHOTO (agrandissement)
     ============================================================ --}}
<div id="imageModal" class="modal-overlay" style="display:none;" aria-modal="true" role="dialog">
    <div style="position:relative;background:var(--color-card);border-radius:0.75rem;overflow:hidden;max-width:560px;width:100%;max-height:90vh;box-shadow:0 20px 60px rgba(0,0,0,0.4);">
        <button id="closeImageModalBtn" class="modal-close" aria-label="Fermer">&times;</button>
        <div id="imageModalTitle" style="padding:0.75rem 1rem 0;font-size:0.75rem;font-weight:600;color:var(--color-secondary-text);font-family:var(--font-display,'Orbitron',sans-serif);"></div>
        <img id="modalImage" src="" alt="Photo" style="width:100%;height:auto;object-fit:contain;max-height:80vh;padding:0.75rem;">
    </div>
</div>

{{-- ============================================================
     MODALE ASSOCIATION (Affectation)
     ============================================================ --}}
<div id="affectModal" class="modal-overlay" style="display:none;" aria-modal="true" role="dialog">
    <div id="affectPanel" class="modal-panel" style="max-width:52rem;">

        <button type="button" id="affectClose" class="modal-close">&times;</button>

        <h2 id="affectTitle" class="modal-title">Associer un véhicule</h2>

        <div id="affectContext" style="font-size:0.78rem;color:var(--color-secondary-text);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem;">
            <i class="fas fa-user" style="color:var(--color-primary);"></i>
        </div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">
            <input id="affectSearch" type="text" class="ui-input-style" style="flex:1;min-width:160px;" placeholder="Recherche intelligente...">
            <input id="affectNote" type="text" class="ui-input-style" style="flex:1;min-width:160px;" placeholder="Note (optionnel)">
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
document.addEventListener('DOMContentLoaded', function () {

    /* ============================================================
       DATATABLES INIT — notre dom custom pour contrôle total
       ============================================================ */
    let dt = null;
    if (window.jQuery && $.fn.DataTable) {
        dt = $('#usersTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            searching: true,
            info: true,
            /* On cache la barre de recherche native et on utilise #usersSearchInput
               On positionne length + info + paginate proprement */
            dom: '<"flex items-center justify-between flex-wrap gap-2 mb-3"l>' +
                 't' +
                 '<"flex items-center justify-between flex-wrap gap-2 mt-3"ip>',
            language: {
                processing:   "Chargement...",
                search:       "Rechercher :",
                lengthMenu:   "Afficher _MENU_ lignes",
                info:         "_START_–_END_ sur _TOTAL_",
                infoEmpty:    "0–0 sur 0",
                infoFiltered: "(filtré parmi _MAX_)",
                loadingRecords: "Chargement...",
                zeroRecords:  "Aucun chauffeur trouvé",
                emptyTable:   "Aucun chauffeur enregistré",
                paginate: {
                    first:    "«",
                    previous: "‹",
                    next:     "›",
                    last:     "»"
                }
            }
        });

        /* Brancher notre champ de recherche custom sur DataTables */
        const searchInput = document.getElementById('usersSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                dt.search(this.value).draw();
            });
        }
    }

    /* ============================================================
       HELPERS MODALES
       ============================================================ */
    function openOverlay(overlayEl, panelEl) {
        overlayEl.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => panelEl?.classList.add('open')));
    }

    function closeOverlay(overlayEl, panelEl, onClosed) {
        panelEl?.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => {
            overlayEl.style.display = 'none';
            onClosed?.();
        }, 200);
    }

    /* ============================================================
       MODALE ADD / EDIT USER
       ============================================================ */
    const userModal   = document.getElementById('userModal');
    const userPanel   = document.getElementById('userModalPanel');
    const openAddBtn  = document.getElementById('openAddModalBtn');
    const closeBtn    = document.getElementById('closeModalBtn');
    const modalTitle  = document.getElementById('modalTitle');
    const userForm    = document.getElementById('userForm');
    const submitBtn   = document.getElementById('submitBtn');
    const methodSpoofContainer = document.getElementById('methodSpoofContainer');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirmation');
    const pwdHint     = document.getElementById('pwdHint');
    const photoInput  = document.getElementById('photo');
    const fileNameDisplay = document.getElementById('file-name');
    const previewWrapper  = document.getElementById('previewWrapper');
    const preview         = document.getElementById('preview');
    const removePhotoBtn  = document.getElementById('removePhotoBtn');

    const STORE_URL = @json($storeUrl);
    const BASE_URL  = @json($baseUrl);

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
        previewWrapper.style.display = 'none';
        fileNameDisplay.textContent = 'Aucun fichier sélectionné';

        modalTitle.textContent = 'Ajouter un chauffeur';
        submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Ajouter le chauffeur';
    }

    function openUserModal()  { openOverlay(userModal, userPanel); }
    function closeUserModal() { closeOverlay(userModal, userPanel, resetToAdd); }

    userModal.addEventListener('click', e => { if (e.target === userModal) closeUserModal(); });
    closeBtn?.addEventListener('click', closeUserModal);
    openAddBtn?.addEventListener('click', () => { resetToAdd(); openUserModal(); });

    photoInput?.addEventListener('change', function () {
        const file = this.files?.[0];
        if (file) {
            fileNameDisplay.textContent = file.name;
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                previewWrapper.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        } else {
            fileNameDisplay.textContent = 'Aucun fichier sélectionné';
            preview.src = '';
            previewWrapper.style.display = 'none';
        }
    });

    removePhotoBtn?.addEventListener('click', () => {
        photoInput.value = '';
        preview.src = '';
        previewWrapper.style.display = 'none';
        fileNameDisplay.textContent = 'Aucun fichier sélectionné';
    });

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            const userId = btn.getAttribute('data-user-id');
            const jsonEl = document.getElementById('user-json-' + userId);
            if (!jsonEl) return;
            let user;
            try { user = JSON.parse(jsonEl.textContent); } catch { return; }

            modalTitle.textContent = 'Modifier le chauffeur';
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer les modifications';

            userForm.action = `${BASE_URL}/${user.id}`;
            methodSpoofContainer.innerHTML = '<input type="hidden" name="_method" value="PUT">';

            document.getElementById('nom').value      = user.nom || '';
            document.getElementById('prenom').value   = user.prenom || '';
            document.getElementById('phone').value    = user.phone || '';
            document.getElementById('email').value    = user.email || '';
            document.getElementById('ville').value    = user.ville || '';
            document.getElementById('quartier').value = user.quartier || '';

            passwordInput.required = false;
            passwordConfirmInput.required = false;
            passwordInput.removeAttribute('minlength');
            passwordConfirmInput.removeAttribute('minlength');
            passwordInput.value = '';
            passwordConfirmInput.value = '';
            if (pwdHint) pwdHint.textContent = ' – laisser vide pour conserver';

            if (user.photo_url) {
                preview.src = user.photo_url;
                previewWrapper.style.display = 'flex';
                fileNameDisplay.textContent = 'Laisser vide pour conserver la photo';
            } else {
                preview.src = '';
                previewWrapper.style.display = 'none';
                fileNameDisplay.textContent = 'Aucun fichier sélectionné';
            }

            openUserModal();
        });
    });

    /* ============================================================
       MODALE PHOTO (agrandissement)
       ============================================================ */
    const imageModal   = document.getElementById('imageModal');
    const modalImage   = document.getElementById('modalImage');
    const imageModalTitle = document.getElementById('imageModalTitle');
    const closeImageBtn   = document.getElementById('closeImageModalBtn');

    function openImageModal(url, title) {
        if (!url) return;
        modalImage.src = url;
        imageModalTitle.textContent = title ? `Photo : ${title}` : 'Photo';
        imageModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        imageModal.style.display = 'none';
        modalImage.src = '';
        document.body.style.overflow = '';
    }

    closeImageBtn?.addEventListener('click', closeImageModal);
    imageModal?.addEventListener('click', e => { if (e.target === imageModal) closeImageModal(); });

    document.addEventListener('click', function (e) {
        const img = e.target.closest('.js-user-photo');
        if (img) openImageModal(img.dataset.fullUrl, img.dataset.title);
    });

});
</script>

{{-- Affectation Modal --}}
<script>
(function () {
    const modal   = document.getElementById('affectModal');
    const panel   = document.getElementById('affectPanel');
    if (!modal || !panel) return;

    const closeBtn  = document.getElementById('affectClose');
    const cancelBtn = document.getElementById('affectCancel');
    const titleEl   = document.getElementById('affectTitle');
    const ctxEl     = document.getElementById('affectContext');
    const headRow   = document.getElementById('affectHeadRow');
    const bodyEl    = document.getElementById('affectBody');
    const searchEl  = document.getElementById('affectSearch');
    const noteEl    = document.getElementById('affectNote');

    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content;
    const URL_VEHICLES = @json(route('partner.affectations.vehicles'));
    const URL_DRIVERS  = @json(route('partner.affectations.drivers'));
    const URL_ASSIGN   = @json(route('partner.affectations.assign'));

    let state = { mode: null, chauffeurId: null, voitureId: null, timer: null };

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m =>
            ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m]));
    }

    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.add('open')));
    }

    function closeModal() {
        panel.classList.remove('open');
        document.body.style.overflow = '';
        setTimeout(() => {
            modal.style.display = 'none';
            bodyEl.innerHTML = '';
            headRow.innerHTML = '';
            searchEl.value = '';
            noteEl.value = '';
            state = { mode: null, chauffeurId: null, voitureId: null, timer: null };
        }, 200);
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
            bodyEl.innerHTML = items.map(v => {
                const cur = v.current_driver
                    ? `Déjà affecté : ${v.current_driver.prenom} ${v.current_driver.nom}`
                    : '<span style="color:#22c55e;">Disponible</span>';
                return `<tr>
                    <td><strong>${esc(v.immatriculation)}</strong></td>
                    <td>${esc((v.marque || '') + ' ' + (v.model || ''))}</td>
                    <td style="color:var(--color-secondary-text);font-size:0.72rem;">${esc(v.mac_id_gps || '—')}</td>
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
            bodyEl.innerHTML = items.map(u => {
                const cur = u.current_vehicle
                    ? `Déjà affecté : ${u.current_vehicle.immatriculation}`
                    : '<span style="color:#22c55e;">Disponible</span>';
                return `<tr>
                    <td><strong>${esc((u.prenom || '') + ' ' + (u.nom || ''))}</strong></td>
                    <td style="color:var(--color-secondary-text);">${esc(u.phone || '')}</td>
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
        const payload = { chauffeur_id: state.chauffeurId, voiture_id: state.voitureId, note: noteEl.value || null, force: !!force };
        const res = await fetch(URL_ASSIGN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify(payload)
        });

        if (res.status === 409) {
            const j = await res.json();
            if (confirm((j.message || 'Conflit') + '\n\nForcer la réaffectation ?')) doAssign(true);
            return;
        }

        const json = await res.json();
        if (!json.ok) { alert(json.message || 'Erreur'); return; }
        alert(json.message || 'Affectation réussie !');
        window.location.reload();
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-open-affect-from-user');
        if (!btn) return;

        state.mode = 'from_user';
        state.chauffeurId = parseInt(btn.dataset.userId, 10);
        state.voitureId = null;

        titleEl.textContent = 'Associer un véhicule';
        ctxEl.innerHTML = `<i class="fas fa-user" style="color:var(--color-primary);"></i> ${esc(btn.dataset.userLabel || '')}`;

        headRow.innerHTML = ['Immatriculation', 'Véhicule', 'GPS', 'Statut', '']
            .map(c => `<th>${c}</th>`).join('');

        openModal();
        loadList();
    });

    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    searchEl?.addEventListener('input', () => {
        clearTimeout(state.timer);
        state.timer = setTimeout(loadList, 280);
    });
})();
</script>
@endpush

@endsection