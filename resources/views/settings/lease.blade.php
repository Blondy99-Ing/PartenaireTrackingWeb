@extends('layouts.app')

@section('title', 'Configuration partenaire')

@php
    $partner = $partner ?? auth()->user();
@endphp

@push('styles')
<style>
.partner-settings-page {
    padding: .65rem 1rem 1rem;
    background: var(--color-bg, #f5f7fb);
    color: var(--color-text, #111827);
    min-height: calc(100vh - var(--navbar-h, 64px));
}

.partner-settings-page .settings-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .8rem;
    flex-wrap: wrap;
    margin-bottom: .7rem;
}

.partner-settings-page .settings-title h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: .45rem;
    font-family: var(--font-display, system-ui);
    font-size: 1.15rem;
    font-weight: 850;
    color: var(--color-text, #111827);
}

.partner-settings-page .settings-title h1 i {
    color: var(--color-primary, #f58220);
}

.partner-settings-page .settings-title p {
    margin: .18rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}

.partner-settings-page .partner-chip {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border-radius: 999px;
    background: rgba(245,130,32,.12);
    color: var(--color-primary, #f58220);
    font-size: .7rem;
    font-weight: 800;
    padding: .42rem .75rem;
    white-space: nowrap;
}

.partner-settings-page .ui-card {
    background: var(--color-card, #fff);
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: 1rem;
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.06));
}

.partner-settings-page .settings-layout {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: .75rem;
}

.partner-settings-page .settings-sidebar {
    padding: .65rem;
    position: sticky;
    top: calc(var(--navbar-h, 64px) + .75rem);
    align-self: start;
}

.partner-settings-page .settings-nav-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: .55rem;
    border: 0;
    background: transparent;
    color: var(--color-secondary-text, #6b7280);
    border-radius: .75rem;
    padding: .62rem .7rem;
    font-size: .73rem;
    font-weight: 800;
    text-align: left;
    cursor: pointer;
    transition: .16s ease;
}

.partner-settings-page .settings-nav-btn i {
    width: 18px;
    color: var(--color-secondary-text, #6b7280);
}

.partner-settings-page .settings-nav-btn:hover,
.partner-settings-page .settings-nav-btn.active {
    background: rgba(245,130,32,.12);
    color: var(--color-primary, #f58220);
}

.partner-settings-page .settings-nav-btn:hover i,
.partner-settings-page .settings-nav-btn.active i {
    color: var(--color-primary, #f58220);
}

.partner-settings-page .settings-content {
    padding: .85rem;
}

.partner-settings-page .settings-section {
    display: none;
}

.partner-settings-page .settings-section.active {
    display: block;
}

.partner-settings-page .section-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .8rem;
    padding-bottom: .7rem;
    border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);
}

.partner-settings-page .section-head h2 {
    margin: 0;
    font-family: var(--font-display, system-ui);
    font-size: .98rem;
    font-weight: 850;
    color: var(--color-text, #111827);
}

.partner-settings-page .section-head p {
    margin: .15rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .7rem;
    line-height: 1.5;
}

.partner-settings-page .form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .75rem;
}

.partner-settings-page .form-grid.three {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.partner-settings-page .field.full {
    grid-column: 1 / -1;
}

.partner-settings-page .field label {
    display: block;
    margin-bottom: .32rem;
    font-family: var(--font-display, system-ui);
    font-weight: 800;
    font-size: .68rem;
    color: var(--color-text, #111827);
}

.partner-settings-page .form-control,
.partner-settings-page .form-select,
.partner-settings-page textarea {
    width: 100%;
    min-height: 38px;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .75rem;
    background: var(--color-input-bg, #fff);
    color: var(--color-text, #111827);
    outline: none;
    padding: .48rem .65rem;
    font-size: .73rem;
}

.partner-settings-page textarea {
    resize: vertical;
    min-height: 78px;
}

.partner-settings-page .form-control:focus,
.partner-settings-page .form-select:focus,
.partner-settings-page textarea:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.14);
}

.partner-settings-page .btn-orange,
.partner-settings-page .btn-soft,
.partner-settings-page .btn-danger-soft {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    border-radius: .75rem;
    border: 1px solid transparent;
    padding: .5rem .8rem;
    font-size: .7rem;
    font-weight: 850;
    cursor: pointer;
    transition: .16s ease;
    white-space: nowrap;
}

.partner-settings-page .btn-orange {
    background: var(--color-primary, #f58220);
    color: #fff;
    box-shadow: 0 8px 18px rgba(245,130,32,.22);
}

.partner-settings-page .btn-orange:hover {
    background: var(--color-primary-hover, #e07318);
}

.partner-settings-page .btn-soft {
    background: var(--color-bg-subtle, #f3f4f6);
    border-color: var(--color-border-subtle, #e5e7eb);
    color: var(--color-text, #111827);
}

.partner-settings-page .btn-danger-soft {
    background: rgba(220,38,38,.08);
    color: var(--color-error, #dc2626);
    border-color: rgba(220,38,38,.18);
}

.partner-settings-page .mini-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .55rem;
    margin-bottom: .75rem;
}

.partner-settings-page .mini-kpi {
    padding: .75rem;
    border-radius: .9rem;
    background: var(--color-card, #fff);
    border: 1px solid var(--color-border-subtle, #e5e7eb);
}

.partner-settings-page .mini-kpi span {
    display: block;
    color: var(--color-secondary-text, #6b7280);
    font-size: .66rem;
    font-weight: 800;
}

.partner-settings-page .mini-kpi strong {
    display: block;
    margin-top: .18rem;
    font-family: var(--font-display, system-ui);
    font-size: 1rem;
    font-weight: 900;
    color: var(--color-text, #111827);
}

.partner-settings-page .data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .9rem;
    font-size: .72rem;
}

.partner-settings-page .data-table th,
.partner-settings-page .data-table td {
    padding: .62rem .65rem;
    border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);
    color: var(--color-text, #111827);
    vertical-align: middle;
}

.partner-settings-page .data-table th {
    background: var(--color-bg-subtle, #f3f4f6);
    font-family: var(--font-display, system-ui);
    font-size: .67rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--color-secondary-text, #6b7280);
}

.partner-settings-page .data-table tr:last-child td {
    border-bottom: 0;
}

.partner-settings-page .status-pill {
    display: inline-flex;
    align-items: center;
    gap: .28rem;
    padding: .25rem .55rem;
    border-radius: 999px;
    font-size: .65rem;
    font-weight: 900;
}

.partner-settings-page .status-pill.ok {
    color: #15803d;
    background: rgba(22,163,74,.10);
}

.partner-settings-page .status-pill.off {
    color: #6b7280;
    background: rgba(107,114,128,.12);
}

.partner-settings-page .warning-box {
    display: flex;
    gap: .55rem;
    align-items: flex-start;
    border: 1px solid rgba(245,158,11,.25);
    background: rgba(245,158,11,.08);
    color: #92400e;
    border-radius: .9rem;
    padding: .65rem .75rem;
    margin-bottom: .75rem;
    font-size: .7rem;
    font-weight: 700;
    line-height: 1.55;
}

.partner-settings-page .map-placeholder {
    height: 280px;
    border-radius: 1rem;
    border: 1px dashed var(--color-border, #cbd5e1);
    background:
        radial-gradient(circle at 20% 20%, rgba(245,130,32,.18), transparent 26%),
        linear-gradient(135deg, rgba(148,163,184,.10), rgba(148,163,184,.04));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-secondary-text, #6b7280);
    font-size: .75rem;
    font-weight: 800;
    margin-top: .75rem;
}

.partner-settings-page .days-list {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
}

.partner-settings-page .day-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .42rem .65rem;
    border-radius: 999px;
    background: var(--color-bg-subtle, #f3f4f6);
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    font-size: .68rem;
    font-weight: 800;
}

.partner-settings-page input[type="checkbox"] {
    accent-color: var(--color-primary, #f58220);
}

@media (max-width: 980px) {
    .partner-settings-page .settings-layout {
        grid-template-columns: 1fr;
    }

    .partner-settings-page .settings-sidebar {
        position: static;
    }

    .partner-settings-page .settings-sidebar nav {
        display: grid;
        grid-template-columns: repeat(2, minmax(0,1fr));
        gap: .35rem;
    }

    .partner-settings-page .mini-kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .partner-settings-page {
        padding: .55rem;
    }

    .partner-settings-page .form-grid,
    .partner-settings-page .form-grid.three {
        grid-template-columns: 1fr;
    }

    .partner-settings-page .settings-sidebar nav {
        grid-template-columns: 1fr;
    }

    .partner-settings-page .section-head {
        flex-direction: column;
    }

    .partner-settings-page .mini-kpis {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@section('content')
<div class="partner-settings-page">
    <div class="settings-top">
        <div class="settings-title">
            <h1><i class="fas fa-sliders-h"></i> Configuration </h1>
            <p>Centre de paramétrage du profil, des contrats Lease, des règles GPS et des horaires.</p>
        </div>

        <div class="partner-chip">
            <i class="fas fa-building"></i>
            {{ $partner->name ?? $partner->full_name ?? 'Partenaire connecté' }}
        </div>
    </div>

    <div class="mini-kpis">
        <div class="mini-kpi"><span>Contrats actifs</span><strong>24</strong></div>
        <div class="mini-kpi"><span>Règles coupure</span><strong>18</strong></div>
        <div class="mini-kpi"><span>Zones GPS</span><strong>03</strong></div>
        <div class="mini-kpi"><span>Timezone</span><strong>Africa/Douala</strong></div>
    </div>

    <div class="settings-layout">
        <aside class="settings-sidebar ui-card">
            <nav aria-label="Navigation paramètres partenaire">
                <button class="settings-nav-btn active" type="button" data-target="profile"><i class="fas fa-user-tie"></i> Profil partenaire</button>
                <button class="settings-nav-btn" type="button" data-target="security"><i class="fas fa-lock"></i> Mot de passe</button>
                <button class="settings-nav-btn" type="button" data-target="contracts"><i class="fas fa-file-contract"></i> Types de contrats</button>
                <button class="settings-nav-btn" type="button" data-target="cutoff"><i class="fas fa-power-off"></i> Règles de coupure</button>
                <button class="settings-nav-btn" type="button" data-target="geofence"><i class="fas fa-draw-polygon"></i> Geofence</button>
               {{-- <button class="settings-nav-btn" type="button" data-target="safezone"><i class="fas fa-shield-alt"></i> Safe Zone</button> --}}
                <button class="settings-nav-btn" type="button" data-target="timezone"><i class="fas fa-clock"></i> Timezone</button>
            </nav>
        </aside>


        



        <div class="settings-section active" id="section-profile">
    <div class="section-head">
        <div>
            <h2>Profil du partenaire</h2>
           
        </div>

        
    </div>

    <div class="form-grid">
        <div class="field">
            <label>Nom / raison sociale</label>
            <input
                class="form-control"
                type="text"
                value="{{ $partner->name ?? $partner->full_name ?? trim(($partner->prenom ?? '') . ' ' . ($partner->nom ?? '')) ?: 'Non renseigné' }}"
                readonly
            >
        </div>

        <div class="field">
            <label>Email</label>
            <input
                class="form-control"
                type="email"
                value="{{ $partner->email ?? 'Non renseigné' }}"
                readonly
            >
        </div>

        <div class="field">
            <label>Téléphone</label>
            <input
                class="form-control"
                type="text"
                value="{{ $partner->phone ?? $partner->telephone ?? 'Non renseigné' }}"
                readonly
            >
        </div>

        

       

        

        
    </div>
</div>



            
            

            <div class="settings-section" id="section-security">
    <form method="POST" action="{{ route('settings.security.password.update') }}">
        @csrf
        @method('PUT')

        <div class="section-head">
            <div>
                <h2>Sécurité du compte</h2>
                <p>
                    Modification du mot de passe .
                    
                </p>
            </div>

            <button class="btn-orange" type="submit">
                <i class="fas fa-key"></i>
                Modifier
            </button>
        </div>

        @if ($errors->updatePassword->any())
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    @foreach ($errors->updatePassword->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="form-grid three">
            <div class="field">
                <label>Mot de passe actuel</label>
                <input
                    class="form-control"
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <div class="field">
                <label>Nouveau mot de passe</label>
                <input
                    class="form-control"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    required
                >
            </div>

            <div class="field">
                <label>Confirmation</label>
                <input
                    class="form-control"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required
                >
            </div>
        </div>
    </form>
</div>

<div class="settings-section" id="section-contracts">
    <div class="section-head">
        <div>
            <h2>Types de contrats</h2>
            <p>
                Créez les contrats principaux et les sous-contrats utilisés pour la creation des contrats chauffeurs.
                
            </p>
        </div>
    </div>

   <form method="POST" action="{{ route('settings.lease.contract-types.store') }}" style="margin-bottom: 1.25rem;">
        @csrf

        <div class="form-grid three" style="margin-bottom:.75rem;">
            <div class="field">
                <label>Nom du type</label>
                <input
                    class="form-control"
                    type="text"
                    name="libelle"
                    value="{{ old('libelle') }}"
                    placeholder="Moto, Batterie, Téléphone..."
                    required
                >
            </div>

            <div class="field">
                <label>Code</label>
                <input
                    class="form-control"
                    type="text"
                    name="code"
                    value="{{ old('code') }}"
                    placeholder="MOTO, BATTERIE, TELEPHONE"
                >
            </div>

            <div class="field">
                <label>Catégorie</label>
                <select class="form-select" name="est_principal">
                    <option value="1" @selected(old('est_principal') === '1')>
                        Contrat principal
                    </option>
                    <option value="0" @selected(old('est_principal', '0') === '0')>
                        Sous-contrat
                    </option>
                </select>
            </div>

            
        </div>

        <button class="btn-orange" type="submit">
            <i class="fas fa-plus"></i>
            Créer le type de contrat
        </button>
    </form>

    @php
        $mainContractTypes = collect($contractTypes ?? [])->filter(fn ($type) => !empty($type['is_main']))->values();
        $subContractTypes = collect($contractTypes ?? [])->filter(fn ($type) => empty($type['is_main']))->values();
    @endphp

    <div class="mini-kpis" style="margin-bottom:1rem;">
        <div class="mini-kpi">
            <span>Contrats principaux</span>
            <strong>{{ $mainContractTypes->count() }}</strong>
        </div>

        <div class="mini-kpi">
            <span>Sous-contrats</span>
            <strong>{{ $subContractTypes->count() }}</strong>
        </div>

        <div class="mini-kpi">
            <span>Total types</span>
            <strong>{{ collect($contractTypes ?? [])->count() }}</strong>
        </div>
    </div>

    <h3 style="font-size:1rem;font-weight:800;margin:1rem 0 .75rem;">
        <i class="fas fa-file-contract"></i>
        Contrats principaux
    </h3>

    <div style="overflow:auto; margin-bottom:1.5rem;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Catégorie</th>
                    <th>Statut</th>
                </tr>
            </thead>

            <tbody>
                @forelse($mainContractTypes as $type)
                    <tr>
                        <td>{{ $type['label'] ?? '-' }}</td>
                        <td>{{ $type['code'] ?? '-' }}</td>
                        <td>{{ $type['description'] ?? 'Contrat principal' }}</td>
                        <td>Principal</td>
                        <td>
                            <span class="status-pill ok">
                                <i class="fas fa-check-circle"></i>
                                Disponible
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:#64748b;">
                            Aucun contrat principal disponible.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h3 style="font-size:1rem;font-weight:800;margin:1rem 0 .75rem;">
        <i class="fas fa-layer-group"></i>
        Sous-contrats
    </h3>

    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Sous-contrat</th>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Catégorie</th>
                    <th>Statut</th>
                </tr>
            </thead>

            <tbody>
                @forelse($subContractTypes as $type)
                    <tr>
                        <td>{{ $type['label'] ?? '-' }}</td>
                        <td>{{ $type['code'] ?? '-' }}</td>
                        <td>{{ $type['description'] ?? 'Sous-contrat Lease' }}</td>
                        <td>Sous-contrat</td>
                        <td>
                            <span class="status-pill ok">
                                <i class="fas fa-check-circle"></i>
                                Disponible
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:#64748b;">
                            Aucun sous-contrat disponible.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="settings-section" id="section-cutoff">
    <form method="POST" action="{{ route('settings.lease.cutoff-default-rules.update') }}">
        @csrf
        @method('PUT')

        <div class="section-head">
            <div>
                <h2>Règles de coupure par défaut</h2>
                <p>
                    Définissez les règles par défaut par type de contrat.
                    Ces règles seront appliquées automatiquement aux contrats créés,
                    puis personnalisables contrat par contrat.
                </p>
            </div>

            <button class="btn-orange" type="submit">
                <i class="fas fa-save"></i>
                Sauvegarder les règles
            </button>
        </div>

        <div class="warning-box">
            <i class="fas fa-info-circle"></i>
            <div>
                Une règle par défaut ne coupe pas directement un véhicule.
                Elle sert de modèle pour les futurs contrats liés à ce type.
                Les jours cochés représentent les jours où le paiement est attendu.
            </div>
        </div>

        <div style="overflow:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type de contrat</th>
                        <th>Catégorie</th>
                        <th>Actif</th>
                        <th>Heure coupure</th>
                        <th>Jours actifs</th>
                        <th>Grâce</th>
                        <th>À l'arrêt</th>
                        <th>Notifier</th>
                    </tr>
                </thead>

                <tbody>
                    @php
                        $days = [
                            'monday' => 'Lun',
                            'tuesday' => 'Mar',
                            'wednesday' => 'Mer',
                            'thursday' => 'Jeu',
                            'friday' => 'Ven',
                            'saturday' => 'Sam',
                            'sunday' => 'Dim',
                        ];
                    @endphp

                    @forelse($contractTypes ?? [] as $index => $type)
                        @php
                            $typeId = $type['id'] ?? null;
                            $typeLabel = $type['label'] ?? $type['libelle'] ?? '-';
                            $typeCode = $type['code'] ?? null;
                            $isMain = !empty($type['is_main']);

                            $rule = $defaultRules[$typeId] ?? null;

                            $selectedDays = old(
                                "rules.$index.active_days",
                                $rule?->active_days ?? ['monday','tuesday','wednesday','thursday','friday','saturday']
                            );
                        @endphp

                        <tr>
                            <td>
                                <strong>{{ $typeLabel }}</strong>

                                <input type="hidden" name="rules[{{ $index }}][type_contrat_id]" value="{{ $typeId }}">
                                <input type="hidden" name="rules[{{ $index }}][type_contrat_label]" value="{{ $typeLabel }}">
                                <input type="hidden" name="rules[{{ $index }}][type_contrat_code]" value="{{ $typeCode }}">
                            </td>

                            <td>
                                {{ $isMain ? 'Principal' : 'Sous-contrat' }}
                            </td>

                            <td>
                                <input type="hidden" name="rules[{{ $index }}][is_enabled]" value="0">
                                <input
                                    type="checkbox"
                                    name="rules[{{ $index }}][is_enabled]"
                                    value="1"
                                    @checked(old("rules.$index.is_enabled", $rule?->is_enabled ?? false))
                                >
                            </td>

                            <td>
                                <input
                                    class="form-control"
                                    type="time"
                                    name="rules[{{ $index }}][cutoff_time]"
                                    value="{{ old("rules.$index.cutoff_time", $rule?->cutoff_time ? substr($rule->cutoff_time, 0, 5) : '') }}"
                                >
                            </td>

                            <td>
                                <div class="days-list">
                                    @foreach($days as $dayValue => $dayLabel)
                                        <label class="day-pill">
                                            <input
                                                type="checkbox"
                                                name="rules[{{ $index }}][active_days][]"
                                                value="{{ $dayValue }}"
                                                @checked(in_array($dayValue, $selectedDays ?? [], true))
                                            >
                                            {{ $dayLabel }}
                                        </label>
                                    @endforeach
                                </div>
                            </td>

                            <td>
                                <input
                                    class="form-control"
                                    type="number"
                                    min="0"
                                    max="365"
                                    name="rules[{{ $index }}][grace_days]"
                                    value="{{ old("rules.$index.grace_days", $rule?->grace_days ?? 0) }}"
                                >
                            </td>

                            <td>
                                <input type="hidden" name="rules[{{ $index }}][only_when_stopped]" value="0">
                                <input
                                    type="checkbox"
                                    name="rules[{{ $index }}][only_when_stopped]"
                                    value="1"
                                    @checked(old("rules.$index.only_when_stopped", $rule?->only_when_stopped ?? true))
                                >
                            </td>

                            <td>
                                <input type="hidden" name="rules[{{ $index }}][notify_before_cutoff]" value="0">
                                <input
                                    type="checkbox"
                                    name="rules[{{ $index }}][notify_before_cutoff]"
                                    value="1"
                                    @checked(old("rules.$index.notify_before_cutoff", $rule?->notify_before_cutoff ?? false))
                                >
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;color:#64748b;">
                                Aucun type de contrat disponible. Créez d’abord les types de contrats.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
</div>

           <div class="settings-section" id="section-geofence">
    <div class="section-head">
        <div>
            <h2>Geofences partenaires</h2>
            <p>
                Créez des geofences personnalisés, puis appliquez-les à un ou plusieurs véhicules.
               
            </p>
        </div>
    </div>

    <form method="POST"
          action="{{ route('settings.geofences.store') }}"
          onsubmit="return prepareGeofenceSubmit()"
          style="margin-bottom: 1.25rem;">
        @csrf

        <div class="form-grid three">
            <div class="field">
                <label>Nom du geofence</label>
                <input class="form-control" type="text" name="name" required placeholder="Ex : Zone Promote">
            </div>

            <div class="field">
                <label>Code optionnel</label>
                <input class="form-control" type="text" name="code" placeholder="Ex : PROMOTE">
            </div>

            <div class="field">
                <label>Points dessinés</label>
                <input class="form-control" type="text" id="geofencePointCount" value="0 point" readonly>
            </div>

            <input type="hidden" name="zone" id="geofenceZoneInput">
        </div>

        <div id="partnerGeofenceMap"
             style="height:430px;border-radius:1rem;border:1px solid var(--color-border-subtle,#e5e7eb);overflow:hidden;margin-top:.75rem;">
        </div>

        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem;">
            <button class="btn-soft" type="button" onclick="closeGeofencePolygon()">
                <i class="fas fa-link"></i>
                Fermer le contour
            </button>

            <button class="btn-soft" type="button" onclick="resetGeofencePolygon()">
                <i class="fas fa-undo"></i>
                Réinitialiser
            </button>

            <button class="btn-orange" type="submit">
                <i class="fas fa-save"></i>
                Enregistrer le geofence
            </button>
        </div>
    </form>

    <div style="overflow:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Geofence</th>
                    <th>Code</th>
                    <th>Appliquer aux véhicules</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                @forelse($geofences ?? [] as $geofence)
                    <tr>
                        <td>
                            <strong>{{ $geofence->name }}</strong>
                            <div style="font-size:.65rem;color:#64748b;">
                                ID : {{ $geofence->id }}
                            </div>
                        </td>

                        <td>{{ $geofence->code ?: '-' }}</td>

                        <td>
                            <form method="POST" action="{{ route('settings.geofences.assign', $geofence) }}">
                                @csrf

                                <label style="display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem;">
                                    <input type="checkbox" name="all_vehicles" value="1" onchange="toggleGeofenceAllVehicles(this)">
                                    Tous les véhicules
                                </label>

                                <div style="max-height:125px;overflow:auto;border:1px solid var(--color-border-subtle,#e5e7eb);border-radius:.75rem;padding:.45rem;">
                                    @forelse($vehicles ?? [] as $vehicle)
                                        <label style="display:flex;align-items:center;gap:.4rem;font-size:.68rem;padding:.2rem 0;">
                                            <input type="checkbox" name="vehicle_ids[]" value="{{ $vehicle->id }}">
                                            {{ $vehicle->immatriculation }}
                                            — {{ $vehicle->marque }} {{ $vehicle->model }}
                                        </label>
                                    @empty
                                        <span style="font-size:.68rem;color:#64748b;">
                                            Aucun véhicule disponible.
                                        </span>
                                    @endforelse
                                </div>

                                <button class="btn-orange" type="submit" style="margin-top:.45rem;">
                                    <i class="fas fa-check"></i>
                                    Appliquer
                                </button>
                            </form>
                        </td>

                        <td>
                            <button type="button"
                                    class="btn-soft"
                                    onclick='previewGeofence(@json($geofence->zone_array))'>
                                <i class="fas fa-eye"></i>
                                Voir
                            </button>

                            <form method="POST"
                                  action="{{ route('settings.geofences.destroy', $geofence) }}"
                                  style="display:inline-block;margin-left:.3rem;">
                                @csrf
                                @method('DELETE')

                                <button class="btn-danger-soft"
                                        type="submit"
                                        onclick="return confirm('Supprimer ce geofence ?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:#64748b;">
                            Aucun geofence créé.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

            <div class="settings-section" id="section-safezone">
                <div class="section-head">
                    <div>
                        <h2>Safe Zone</h2>
                        <p>Zone sécurisée de stationnement ou dépôt avec plages horaires.</p>
                    </div>
                    <button class="btn-orange" type="button"><i class="fas fa-shield-alt"></i> Enregistrer</button>
                </div>

                <div class="form-grid three">
                    <div class="field">
                        <label>Nom</label>
                        <input class="form-control" type="text" value="Dépôt partenaire">
                    </div>

                    <div class="field">
                        <label>Latitude</label>
                        <input class="form-control" type="text" value="3.8500">
                    </div>

                    <div class="field">
                        <label>Longitude</label>
                        <input class="form-control" type="text" value="11.5100">
                    </div>

                    <div class="field">
                        <label>Rayon en mètres</label>
                        <input class="form-control" type="number" value="150">
                    </div>

                    <div class="field">
                        <label>Heure début</label>
                        <input class="form-control" type="time" value="20:00">
                    </div>

                    <div class="field">
                        <label>Heure fin</label>
                        <input class="form-control" type="time" value="06:00">
                    </div>
                </div>

                <div class="map-placeholder">
                    <i class="fas fa-shield-alt"></i>&nbsp; Carte Safe Zone à connecter ici
                </div>
            </div>



<div class="settings-section" id="section-timezone">
    <div class="section-head">
        <div>
            <h2>Time Zone véhicule</h2>
            <p>
                Définissez simplement une heure de début et une heure de fin.
                Si les deux champs sont vides ou si vous cochez “Désactiver”, la plage horaire du véhicule sera supprimée.
            </p>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.timezone.update') }}">
        @csrf
        @method('PUT')

        <div class="form-grid three">
            <div class="field">
                <label>Heure de début</label>
                <input
                    class="form-control timezone-field"
                    type="time"
                    name="time_zone_start"
                    value="{{ old('time_zone_start') }}"
                >
            </div>

            <div class="field">
                <label>Heure de fin</label>
                <input
                    class="form-control timezone-field"
                    type="time"
                    name="time_zone_end"
                    value="{{ old('time_zone_end') }}"
                >
            </div>

            <div class="field">
                <label>Désactivation</label>
                <label style="display:flex;align-items:center;gap:.5rem;margin-top:.55rem;">
                    <input
                        type="checkbox"
                        name="disable_timezone"
                        value="1"
                        onchange="toggleTimeZoneDisable(this)"
                    >
                    Désactiver la Time Zone
                </label>
            </div>
        </div>

        <div class="warning-box" style="margin-top:1rem;">
            <i class="fas fa-info-circle"></i>
            <div>
                Cette configuration s’applique tous les jours.
                Il n’y a pas de jours actifs dans cette version.
                Exemple : 06:00 → 22:00 ou 22:00 → 06:00.
            </div>
        </div>

        <div style="margin-top:1rem;">
            <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:.6rem;">
                <input
                    type="checkbox"
                    name="all_vehicles"
                    value="1"
                    onchange="toggleTimeZoneAllVehicles(this)"
                >
                Appliquer à tous les véhicules
            </label>

            <div style="max-height:260px;overflow:auto;border:1px solid var(--color-border-subtle,#e5e7eb);border-radius:.9rem;padding:.65rem;background:#fff;">
                @forelse($timeZoneVehicles ?? [] as $vehicle)
                    @php
                        $tzStart = $vehicle->time_zone_start ? substr($vehicle->time_zone_start, 0, 5) : null;
                        $tzEnd = $vehicle->time_zone_end ? substr($vehicle->time_zone_end, 0, 5) : null;
                        $tzEnabled = $tzStart && $tzEnd;
                    @endphp

                    <label style="display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px solid #f1f5f9;">
                        <span style="display:flex;align-items:center;gap:.5rem;">
                            <input type="checkbox" name="vehicle_ids[]" value="{{ $vehicle->id }}" class="timezone-vehicle-checkbox">

                            <span>
                                <strong>{{ $vehicle->immatriculation }}</strong>
                                <span style="font-size:.75rem;color:#64748b;">
                                    — {{ $vehicle->marque }} {{ $vehicle->model }}
                                </span>
                            </span>
                        </span>

                        <span style="font-size:.72rem;color:{{ $tzEnabled ? '#15803d' : '#64748b' }};">
                            @if($tzEnabled)
                                Active : {{ $tzStart }} → {{ $tzEnd }}
                            @else
                                Désactivée
                            @endif
                        </span>
                    </label>
                @empty
                    <span style="font-size:.8rem;color:#64748b;">
                        Aucun véhicule disponible.
                    </span>
                @endforelse
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
            <button class="btn-orange" type="submit">
                <i class="fas fa-save"></i>
                Enregistrer la Time Zone
            </button>
        </div>
    </form>
</div>



        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.partner-settings-page .settings-nav-btn');
    const sections = document.querySelectorAll('.partner-settings-page .settings-section');
    const storageKey = 'partner_settings_active_section';

    function activateSection(target, persist = true) {
        if (!target || !document.getElementById('section-' + target)) {
            target = 'profile';
        }

        buttons.forEach((item) => item.classList.toggle('active', item.dataset.target === target));
        sections.forEach((item) => item.classList.toggle('active', item.id === 'section-' + target));

        if (persist) {
            localStorage.setItem(storageKey, target);
            const url = new URL(window.location.href);
            url.searchParams.set('section', target);
            window.history.replaceState({}, '', url.toString());
        }
    }

   const urlSection =
    new URLSearchParams(window.location.search).get('section')
    || window.location.hash.replace('#section-', '');

    const initialSection = urlSection || localStorage.getItem(storageKey) || 'profile';
    activateSection(initialSection, false);

    buttons.forEach((button) => {
        button.addEventListener('click', () => activateSection(button.dataset.target));
    });

    document.querySelectorAll('.partner-settings-page form').forEach((form) => {
        form.addEventListener('submit', () => {
            const active = document.querySelector('.partner-settings-page .settings-section.active');
            const target = active?.id?.replace('section-', '') || 'profile';
            localStorage.setItem(storageKey, target);

            if (!form.action.includes('section=')) {
                const url = new URL(form.action || window.location.href, window.location.origin);
                url.searchParams.set('section', target);
                form.action = url.toString();
            }
        });
    });
});
</script>


<script>
function toggleTimeZoneAllVehicles(checkbox) {
    const form = checkbox.closest('form');
    const vehicleInputs = form.querySelectorAll('.timezone-vehicle-checkbox');

    vehicleInputs.forEach(input => {
        input.disabled = checkbox.checked;

        if (checkbox.checked) {
            input.checked = false;
        }
    });
}

function toggleTimeZoneDisable(checkbox) {
    const form = checkbox.closest('form');
    const fields = form.querySelectorAll('.timezone-field');

    fields.forEach(input => {
        input.disabled = checkbox.checked;

        if (checkbox.checked) {
            input.value = '';
        }
    });
}
</script>



<script>
let partnerGeofenceMap = null;
let geofencePoints = [];
let geofenceMarkers = [];
let geofencePolygon = null;
let geofencePreviewPolygon = null;

function initPartnerGeofenceMap() {
    const mapElement = document.getElementById('partnerGeofenceMap');

    if (!mapElement) {
        return;
    }

    partnerGeofenceMap = new google.maps.Map(mapElement, {
        center: { lat: 3.89106, lng: 11.50057 },
        zoom: 15,
        mapTypeId: 'roadmap',
        streetViewControl: false,
        fullscreenControl: true,
    });

    partnerGeofenceMap.addListener('click', function (event) {
        const point = {
            lat: event.latLng.lat(),
            lng: event.latLng.lng()
        };

        geofencePoints.push(point);

        const marker = new google.maps.Marker({
            position: point,
            map: partnerGeofenceMap,
            label: String(geofencePoints.length)
        });

        geofenceMarkers.push(marker);
        redrawGeofencePolygon();
        updateGeofencePointCount();
    });
}

function redrawGeofencePolygon() {
    if (geofencePolygon) {
        geofencePolygon.setMap(null);
    }

    if (geofencePoints.length < 2) {
        return;
    }

    geofencePolygon = new google.maps.Polygon({
        paths: geofencePoints,
        strokeOpacity: 0.95,
        strokeWeight: 2,
        fillOpacity: 0.18,
        map: partnerGeofenceMap,
    });
}

function closeGeofencePolygon() {
    if (geofencePoints.length < 3) {
        alert('Ajoutez au moins 3 points.');
        return;
    }

    redrawGeofencePolygon();
}

function resetGeofencePolygon() {
    geofencePoints = [];

    geofenceMarkers.forEach(marker => marker.setMap(null));
    geofenceMarkers = [];

    if (geofencePolygon) {
        geofencePolygon.setMap(null);
        geofencePolygon = null;
    }

    if (geofencePreviewPolygon) {
        geofencePreviewPolygon.setMap(null);
        geofencePreviewPolygon = null;
    }

    updateGeofencePointCount();
}

function updateGeofencePointCount() {
    const input = document.getElementById('geofencePointCount');
    const count = geofencePoints.length;

    if (input) {
        input.value = count + (count > 1 ? ' points' : ' point');
    }
}

function prepareGeofenceSubmit() {
    if (geofencePoints.length < 3) {
        alert('Le geofence doit contenir au moins 3 points.');
        return false;
    }

    document.getElementById('geofenceZoneInput').value = JSON.stringify(geofencePoints);

    return true;
}

function previewGeofence(zone) {
    if (!partnerGeofenceMap) {
        return;
    }

    if (!Array.isArray(zone) || zone.length < 3) {
        alert('Zone invalide.');
        return;
    }

    if (geofencePreviewPolygon) {
        geofencePreviewPolygon.setMap(null);
    }

    geofencePreviewPolygon = new google.maps.Polygon({
        paths: zone,
        strokeOpacity: 0.95,
        strokeWeight: 2,
        fillOpacity: 0.18,
        map: partnerGeofenceMap,
    });

    const bounds = new google.maps.LatLngBounds();

    zone.forEach(point => {
        bounds.extend(new google.maps.LatLng(point.lat, point.lng));
    });

    partnerGeofenceMap.fitBounds(bounds);
}

function toggleGeofenceAllVehicles(checkbox) {
    const form = checkbox.closest('form');
    const vehicleInputs = form.querySelectorAll('input[name="vehicle_ids[]"]');

    vehicleInputs.forEach(input => {
        input.disabled = checkbox.checked;

        if (checkbox.checked) {
            input.checked = false;
        }
    });
}
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initPartnerGeofenceMap"
    async
    defer>
</script>
@endpush