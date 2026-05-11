@extends('layouts.app')

@section('title', 'Paramétrage coupure lease')

@php
    $vehicles = collect($vehicles ?? []);
    $contractTypes = collect($contractTypes ?? []);

    $totalVehicles = $vehicles->count();
    $enabledVehicles = $vehicles->where('is_enabled', true)->count();
    $activeTypeRules = $vehicles->sum(fn ($vehicle) => (int) ($vehicle['enabled_type_rules_count'] ?? 0));
    $missingTimeVehicles = $vehicles->filter(fn ($vehicle) => !empty($vehicle['is_enabled']) && empty($vehicle['cutoff_time']))->count();
@endphp

@push('styles')
<style>
    .lco-page { display:flex; flex-direction:column; gap:1rem; }
    .lco-card { background:var(--color-card,#fff); border:1px solid var(--color-border-subtle,#e5e7eb); border-radius:18px; box-shadow:var(--shadow-sm,0 8px 24px rgba(15,23,42,.06)); overflow:hidden; }
    .lco-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; padding:1rem; border-bottom:1px solid var(--color-border-subtle,#e5e7eb); }
    .lco-head h2 { margin:0; font-size:1rem; font-weight:900; color:var(--color-text,#111827); display:flex; align-items:center; gap:.5rem; }
    .lco-head h2 i { color:var(--color-primary,#f58220); }
    .lco-head p { margin:.25rem 0 0; font-size:.76rem; color:var(--color-secondary-text,#6b7280); line-height:1.45; max-width:880px; }
    .lco-actions { display:flex; gap:.5rem; flex-wrap:wrap; justify-content:flex-end; }
    .lco-btn { border:1px solid var(--color-border-subtle,#e5e7eb); background:var(--color-card,#fff); color:var(--color-text,#111827); border-radius:12px; padding:.62rem .82rem; font-size:.72rem; font-weight:900; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:.16s ease; text-decoration:none; }
    .lco-btn:hover { border-color:var(--color-primary,#f58220); color:var(--color-primary,#f58220); transform:translateY(-1px); }
    .lco-btn.primary { background:var(--color-primary,#f58220); border-color:var(--color-primary,#f58220); color:#fff; }
    .lco-btn.soft { background:rgba(245,130,32,.09); border-color:rgba(245,130,32,.22); color:var(--color-primary,#f58220); }
    .lco-btn.danger { color:#dc2626; border-color:rgba(220,38,38,.25); }

    .lco-alert { padding:.85rem 1rem; border-radius:16px; border:1px solid transparent; font-size:.78rem; font-weight:800; }
    .lco-alert.success { color:#15803d; background:rgba(22,163,74,.08); border-color:rgba(22,163,74,.22); }
    .lco-alert.error { color:#b91c1c; background:rgba(220,38,38,.08); border-color:rgba(220,38,38,.22); }
    .lco-alert.warn { color:#b45309; background:rgba(245,158,11,.08); border-color:rgba(245,158,11,.25); }

    .lco-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; padding:1rem; }
    @media(max-width:900px){ .lco-kpis{grid-template-columns:repeat(2,minmax(0,1fr));} }
    @media(max-width:560px){ .lco-kpis{grid-template-columns:1fr;} }
    .lco-kpi { border:1px solid var(--color-border-subtle,#e5e7eb); border-radius:16px; padding:.85rem; background:rgba(148,163,184,.05); display:flex; justify-content:space-between; gap:.6rem; align-items:center; }
    .lco-kpi span { display:block; color:var(--color-secondary-text,#6b7280); font-size:.62rem; font-weight:900; letter-spacing:.07em; text-transform:uppercase; }
    .lco-kpi strong { display:block; margin-top:.15rem; color:var(--color-primary,#f58220); font-size:1.35rem; font-weight:900; }
    .lco-kpi i { width:36px; height:36px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:rgba(245,130,32,.12); color:var(--color-primary,#f58220); }

    .lco-create { padding:1rem; }
    .lco-form-grid { display:grid; grid-template-columns:1.2fr .7fr .7fr .7fr auto; gap:.65rem; align-items:end; }
    @media(max-width:980px){ .lco-form-grid{grid-template-columns:1fr 1fr;} }
    @media(max-width:620px){ .lco-form-grid{grid-template-columns:1fr;} }
    .lco-field { display:flex; flex-direction:column; gap:.3rem; }
    .lco-field label { font-size:.62rem; font-weight:900; text-transform:uppercase; letter-spacing:.06em; color:var(--color-secondary-text,#6b7280); }
    .lco-input,.lco-select { width:100%; border:1px solid var(--color-border-subtle,#e5e7eb); border-radius:12px; padding:.62rem .75rem; background:var(--color-card,#fff); color:var(--color-text,#111827); font-size:.76rem; outline:none; }
    .lco-input:focus,.lco-select:focus { border-color:var(--color-primary,#f58220); box-shadow:0 0 0 3px rgba(245,130,32,.12); }
    .lco-checkline { display:flex; align-items:center; gap:.45rem; font-size:.75rem; color:var(--color-text,#111827); font-weight:800; padding:.58rem .2rem; }
    .lco-checkline input { width:16px; height:16px; accent-color:var(--color-primary,#f58220); }

    .lco-toolbar { padding:.85rem 1rem; border-bottom:1px solid var(--color-border-subtle,#e5e7eb); display:flex; flex-direction:column; gap:.7rem; }
    .lco-toolbar-top { display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; }
    .lco-search { position:relative; flex:1; min-width:240px; }
    .lco-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--color-secondary-text,#6b7280); font-size:.75rem; }
    .lco-search input { padding-left:2.1rem; }
    .lco-filter-pills { display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; }
    .lco-pill { border:1px solid var(--color-border-subtle,#e5e7eb); border-radius:999px; padding:.32rem .65rem; font-size:.62rem; font-weight:900; color:var(--color-secondary-text,#6b7280); cursor:pointer; background:transparent; }
    .lco-pill.active { border-color:var(--color-primary,#f58220); background:rgba(245,130,32,.09); color:var(--color-primary,#f58220); }
    .lco-meta { margin-left:auto; color:var(--color-secondary-text,#6b7280); font-size:.68rem; font-weight:900; }

    .lco-selection { display:none; padding:.75rem 1rem; border-bottom:1px solid rgba(245,130,32,.25); background:rgba(245,130,32,.08); gap:.5rem; align-items:center; flex-wrap:wrap; }
    .lco-selection.show { display:flex; }
    .lco-selection strong { color:var(--color-primary,#f58220); font-size:.72rem; }

    .lco-table-wrap { overflow:auto; max-height:calc(100vh - 360px); min-height:240px; }
    .lco-table { width:100%; min-width:1180px; border-collapse:separate; border-spacing:0; }
    .lco-table th { position:sticky; top:0; z-index:2; background:var(--color-card,#fff); border-bottom:1px solid var(--color-border-subtle,#e5e7eb); padding:.7rem .75rem; text-align:left; font-size:.58rem; font-weight:900; letter-spacing:.08em; text-transform:uppercase; color:var(--color-secondary-text,#6b7280); }
    .lco-table td { padding:.75rem; border-bottom:1px solid var(--color-border-subtle,#e5e7eb); vertical-align:top; color:var(--color-text,#111827); font-size:.74rem; }
    .lco-row:hover { background:rgba(148,163,184,.05); }
    .lco-row.selected { background:rgba(245,130,32,.08); }
    .lco-row.dirty { outline:2px solid rgba(245,158,11,.16); outline-offset:-2px; }
    .lco-row.hidden { display:none; }
    .lco-row-check,.lco-check-all { width:17px; height:17px; accent-color:var(--color-primary,#f58220); cursor:pointer; }

    .veh-title { font-size:.82rem; font-weight:900; margin:0; color:var(--color-text,#111827); }
    .veh-sub { margin:.18rem 0 0; font-size:.66rem; color:var(--color-secondary-text,#6b7280); }
    .veh-gps { margin:.18rem 0 0; font-family:monospace; font-size:.65rem; color:var(--color-secondary-text,#6b7280); }

    .tag { display:inline-flex; align-items:center; gap:.28rem; border-radius:999px; padding:.24rem .55rem; font-size:.58rem; font-weight:900; border:1px solid transparent; white-space:nowrap; }
    .tag.ok { background:rgba(22,163,74,.1); color:#15803d; border-color:rgba(22,163,74,.22); }
    .tag.off { background:rgba(107,114,128,.1); color:#4b5563; border-color:rgba(107,114,128,.2); }
    .tag.warn { background:rgba(245,158,11,.12); color:#b45309; border-color:rgba(245,158,11,.22); }

    .switch { position:relative; width:44px; height:24px; display:inline-block; }
    .switch input { opacity:0; width:0; height:0; }
    .slider { position:absolute; inset:0; border-radius:999px; background:#cbd5e1; transition:.16s; cursor:pointer; }
    .slider:before { content:""; position:absolute; width:16px; height:16px; top:4px; left:4px; border-radius:999px; background:#fff; transition:.16s; box-shadow:0 2px 6px rgba(0,0,0,.2); }
    .switch input:checked + .slider { background:#16a34a; }
    .switch input:checked + .slider:before { transform:translateX(20px); }

    .type-grid { display:grid; grid-template-columns:repeat(2,minmax(190px,1fr)); gap:.55rem; }
    @media(max-width:1300px){ .type-grid{grid-template-columns:1fr;} }
    .type-card { border:1px solid var(--color-border-subtle,#e5e7eb); border-radius:14px; padding:.65rem; background:rgba(148,163,184,.04); display:flex; flex-direction:column; gap:.55rem; }
    .type-card.enabled { border-color:rgba(22,163,74,.28); background:rgba(22,163,74,.05); }
    .type-card-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
    .type-name { font-weight:900; font-size:.75rem; display:flex; align-items:center; gap:.35rem; }
    .type-name small { font-size:.55rem; color:var(--color-secondary-text,#6b7280); font-weight:900; border:1px solid var(--color-border-subtle,#e5e7eb); border-radius:999px; padding:.1rem .35rem; }
    .type-options { display:grid; grid-template-columns:88px 84px; gap:.45rem; align-items:end; }
    .mini-field { display:flex; flex-direction:column; gap:.18rem; }
    .mini-field label { font-size:.52rem; font-weight:900; color:var(--color-secondary-text,#6b7280); text-transform:uppercase; }
    .mini-input { border:1px solid var(--color-border-subtle,#e5e7eb); border-radius:9px; padding:.38rem .45rem; font-size:.68rem; background:var(--color-card,#fff); color:var(--color-text,#111827); }

    .lco-footer { padding:.85rem 1rem; border-top:1px solid var(--color-border-subtle,#e5e7eb); display:flex; justify-content:space-between; gap:1rem; align-items:center; flex-wrap:wrap; }
    .lco-footer p { margin:0; font-size:.68rem; color:var(--color-secondary-text,#6b7280); }
    .empty { padding:2.5rem 1rem; text-align:center; color:var(--color-secondary-text,#6b7280); font-weight:900; }
</style>
@endpush

@section('content')
<div class="lco-page">
    @if(session('success'))
        <div class="lco-alert success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="lco-alert error"><i class="fas fa-exclamation-triangle"></i> {{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="lco-alert error">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Veuillez corriger les champs suivants :</strong>
                <ul style="margin:.4rem 0 0 1rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if(!empty($pageWarnings))
        @foreach($pageWarnings as $warning)
            <div class="lco-alert warn"><i class="fas fa-info-circle"></i> {{ $warning }}</div>
        @endforeach
    @endif

    <section class="lco-card">
        <div class="lco-head">
            <div>
                <h2><i class="fas fa-bolt"></i> Paramétrage coupure contrats & sous-contrats</h2>
                <p>
                    La coupure est décidée côté Tracking par véhicule et par type de contrat recouvrement.
                    Si un sous-contrat impayé a son type activé pour le véhicule du contrat parent, ce véhicule sera planifié à la coupure à l’heure indiquée.
                    Si le type est désactivé, il ne déclenche pas la coupure.
                </p>
            </div>
            <div class="lco-actions">
                <button type="button" class="lco-btn soft" id="enableVisibleBtn"><i class="fas fa-toggle-on"></i> Activer visibles</button>
                <button type="button" class="lco-btn" id="disableVisibleBtn"><i class="fas fa-toggle-off"></i> Désactiver visibles</button>
            </div>
        </div>

        <div class="lco-kpis">
            <div class="lco-kpi"><div><span>Véhicules</span><strong>{{ $totalVehicles }}</strong></div><i class="fas fa-car"></i></div>
            <div class="lco-kpi"><div><span>Règles véhicule actives</span><strong id="kpiEnabledVehicles">{{ $enabledVehicles }}</strong></div><i class="fas fa-bolt"></i></div>
            <div class="lco-kpi"><div><span>Types actifs</span><strong id="kpiActiveTypes">{{ $activeTypeRules }}</strong></div><i class="fas fa-tags"></i></div>
            <div class="lco-kpi"><div><span>Actifs sans heure</span><strong id="kpiMissingTime">{{ $missingTimeVehicles }}</strong></div><i class="fas fa-clock"></i></div>
        </div>
    </section>

    <section class="lco-card">
        <div class="lco-head">
            <div>
                <h2><i class="fas fa-plus-circle"></i> Créer un type de contrat / sous-contrat</h2>
                <p>
                    Le type est créé dans recouvrement via <strong>POST /api/v1/type-contrats/</strong>.
                    Tracking l’ajoute ensuite à la matrice de coupure. Par sécurité, un nouveau type est désactivé par défaut.
                </p>
            </div>
        </div>

        <div class="lco-create">
            <form method="POST" action="{{ route('lease.cutoff-rules.type-contrats.store') }}">
                @csrf
                <div class="lco-form-grid">
                    <div class="lco-field">
                        <label>Libellé</label>
                        <input class="lco-input" name="libelle" placeholder="Ex : Batterie, Casque, Assurance" value="{{ old('libelle') }}" required>
                    </div>
                    <div class="lco-field">
                        <label>Code</label>
                        <input class="lco-input" name="code" placeholder="Ex : BAT" value="{{ old('code') }}">
                    </div>
                    <label class="lco-checkline">
                        <input type="checkbox" name="est_principal" value="1" @checked(old('est_principal'))>
                        Type principal
                    </label>
                    <label class="lco-checkline" title="Déconseillé sauf si vous voulez que ce nouveau type coupe immédiatement sur tous les véhicules.">
                        <input type="checkbox" name="enable_by_default" value="1" @checked(old('enable_by_default'))>
                        Activer par défaut
                    </label>
                    <button class="lco-btn primary" type="submit"><i class="fas fa-save"></i> Créer</button>
                </div>
            </form>
        </div>
    </section>

    <section class="lco-card">
        <form method="POST" action="{{ route('lease.cutoff-rules.store') }}" id="cutoffRulesForm">
            @csrf

            <div class="lco-toolbar">
                <div class="lco-toolbar-top">
                    <div class="lco-search">
                        <i class="fas fa-search"></i>
                        <input class="lco-input" id="vehicleSearch" placeholder="Rechercher véhicule, GPS, marque, type de contrat...">
                    </div>

                    <input type="time" class="lco-input" id="bulkTime" style="width:130px" title="Heure à appliquer">
                    <button type="button" class="lco-btn soft" id="applyTimeVisibleBtn"><i class="fas fa-clock"></i> Heure visibles</button>
                    <button type="button" class="lco-btn soft" id="enableAllTypesVisibleBtn"><i class="fas fa-check-double"></i> Tous types visibles ON</button>
                    <button type="button" class="lco-btn" id="disableAllTypesVisibleBtn"><i class="fas fa-ban"></i> Tous types visibles OFF</button>
                </div>

                <div class="lco-filter-pills" id="quickFilters">
                    <button type="button" class="lco-pill active" data-filter="all">Tous</button>
                    <button type="button" class="lco-pill" data-filter="enabled">Véhicules actifs</button>
                    <button type="button" class="lco-pill" data-filter="disabled">Véhicules inactifs</button>
                    <button type="button" class="lco-pill" data-filter="has-type-enabled">Au moins un type actif</button>
                    <button type="button" class="lco-pill" data-filter="missing-time">Actifs sans heure</button>
                    <span class="lco-meta"><span id="visibleCount">{{ $totalVehicles }}</span> véhicule(s) visible(s)</span>
                </div>
            </div>

            <div class="lco-selection" id="selectionBar">
                <strong><i class="fas fa-check-square"></i> <span id="selectedCount">0</span> sélectionné(s)</strong>
                <button type="button" class="lco-btn soft" id="selEnableVehicleBtn">Activer véhicule</button>
                <button type="button" class="lco-btn" id="selDisableVehicleBtn">Désactiver véhicule</button>
                <button type="button" class="lco-btn soft" id="selEnableAllTypesBtn">Tous types ON</button>
                <button type="button" class="lco-btn" id="selDisableAllTypesBtn">Tous types OFF</button>
                <input type="time" class="lco-input" id="selectionTime" style="width:130px">
                <button type="button" class="lco-btn soft" id="selApplyTimeBtn">Appliquer heure</button>
                <button type="button" class="lco-btn danger" id="clearSelectionBtn">Annuler sélection</button>
            </div>

            <div class="lco-table-wrap">
                <table class="lco-table">
                    <thead>
                        <tr>
                            <th style="width:34px"><input type="checkbox" class="lco-check-all" id="checkAll"></th>
                            <th style="min-width:220px">Véhicule</th>
                            <th style="width:130px">Règle véhicule</th>
                            <th style="width:120px">Heure</th>
                            <th style="width:100px">Grâce</th>
                            <th style="min-width:440px">Types de contrats / sous-contrats</th>
                        </tr>
                    </thead>
                    <tbody id="cutoffTableBody">
                        @forelse($vehicles as $vehicleIndex => $vehicle)
                            @php
                                $enabled = !empty($vehicle['is_enabled']);
                                $timeMissing = $enabled && empty($vehicle['cutoff_time']);
                                $typeRules = collect($vehicle['contract_type_rules'] ?? []);
                                $enabledTypeCount = $typeRules->where('is_enabled', true)->count();
                                $searchText = strtolower(trim(implode(' ', [
                                    $vehicle['immatriculation'] ?? '',
                                    $vehicle['marque'] ?? '',
                                    $vehicle['model'] ?? '',
                                    $vehicle['mac_id_gps'] ?? '',
                                    $typeRules->pluck('type_contrat_label')->implode(' '),
                                ])));
                            @endphp
                            <tr class="lco-row"
                                data-search="{{ $searchText }}"
                                data-enabled="{{ $enabled ? '1' : '0' }}"
                                data-missing-time="{{ $timeMissing ? '1' : '0' }}"
                                data-enabled-type-count="{{ $enabledTypeCount }}">
                                <td>
                                    <input type="checkbox" class="lco-row-check" aria-label="Sélectionner {{ $vehicle['immatriculation'] ?? '' }}">
                                </td>

                                <td>
                                    <p class="veh-title">{{ $vehicle['immatriculation'] ?? '—' }}</p>
                                    <p class="veh-sub">{{ trim(($vehicle['marque'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?: '—' }}</p>
                                    <p class="veh-gps">GPS : {{ $vehicle['mac_id_gps'] ?? '—' }}</p>
                                    <input type="hidden" name="rules[{{ $vehicleIndex }}][vehicle_id]" value="{{ $vehicle['vehicle_id'] }}">
                                    <input type="hidden" name="rules[{{ $vehicleIndex }}][timezone]" value="{{ $vehicle['timezone'] ?? 'Africa/Douala' }}">
                                </td>

                                <td>
                                    <label class="switch">
                                        <input type="checkbox" class="vehicle-enabled" name="rules[{{ $vehicleIndex }}][is_enabled]" value="1" @checked($enabled)>
                                        <span class="slider"></span>
                                    </label>
                                    <div style="margin-top:.45rem" class="row-status">
                                        @if($enabled && !$timeMissing)
                                            <span class="tag ok">Active</span>
                                        @elseif($enabled && $timeMissing)
                                            <span class="tag warn">Sans heure</span>
                                        @else
                                            <span class="tag off">Inactive</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <input type="time" class="mini-input vehicle-time" name="rules[{{ $vehicleIndex }}][cutoff_time]" value="{{ $vehicle['cutoff_time'] ?? '' }}" step="60">
                                </td>

                                <td>
                                    <input type="number" class="mini-input vehicle-grace" name="rules[{{ $vehicleIndex }}][grace_days]" value="{{ $vehicle['grace_days'] ?? 0 }}" min="0" max="365">
                                    <label class="lco-checkline" style="padding:.25rem 0 0; font-size:.62rem;">
                                        <input type="checkbox" name="rules[{{ $vehicleIndex }}][only_when_stopped]" value="1" @checked($vehicle['only_when_stopped'] ?? true)>
                                        À l’arrêt
                                    </label>
                                </td>

                                <td>
                                    @if($contractTypes->isEmpty())
                                        <div class="empty" style="padding:1rem">Aucun type disponible.</div>
                                    @else
                                        <div class="type-grid">
                                            @foreach($typeRules as $typeIndex => $rule)
                                                @php
                                                    $typeEnabled = !empty($rule['is_enabled']);
                                                    $isMain = !empty($rule['is_main']);
                                                @endphp
                                                <div class="type-card {{ $typeEnabled ? 'enabled' : '' }}">
                                                    <div class="type-card-head">
                                                        <div class="type-name">
                                                            {{ $rule['type_contrat_label'] ?? 'Type' }}
                                                            <small>{{ $isMain ? 'principal' : 'sous-contrat' }}</small>
                                                        </div>
                                                        <label class="switch">
                                                            <input type="checkbox" class="type-enabled" name="rules[{{ $vehicleIndex }}][contract_types][{{ $typeIndex }}][is_enabled]" value="1" @checked($typeEnabled)>
                                                            <span class="slider"></span>
                                                        </label>
                                                    </div>

                                                    <input type="hidden" name="rules[{{ $vehicleIndex }}][contract_types][{{ $typeIndex }}][type_contrat_id]" value="{{ $rule['type_contrat_id'] }}">
                                                    <input type="hidden" name="rules[{{ $vehicleIndex }}][contract_types][{{ $typeIndex }}][type_contrat_label]" value="{{ $rule['type_contrat_label'] }}">
                                                    <input type="hidden" name="rules[{{ $vehicleIndex }}][contract_types][{{ $typeIndex }}][only_when_stopped]" value="{{ !empty($rule['only_when_stopped']) ? '1' : '0' }}">
                                                    <input type="hidden" name="rules[{{ $vehicleIndex }}][contract_types][{{ $typeIndex }}][notify_before_cutoff]" value="{{ !empty($rule['notify_before_cutoff']) ? '1' : '0' }}">

                                                    <div class="type-options">
                                                        <div class="mini-field">
                                                            <label>Heure</label>
                                                            <input type="time" class="mini-input type-time" name="rules[{{ $vehicleIndex }}][contract_types][{{ $typeIndex }}][cutoff_time]" value="{{ $rule['cutoff_time'] ?? $vehicle['cutoff_time'] ?? '' }}" step="60">
                                                        </div>
                                                        <div class="mini-field">
                                                            <label>Grâce</label>
                                                            <input type="number" class="mini-input type-grace" name="rules[{{ $vehicleIndex }}][contract_types][{{ $typeIndex }}][grace_days]" value="{{ $rule['grace_days'] ?? $vehicle['grace_days'] ?? 0 }}" min="0" max="365">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6"><div class="empty"><i class="fas fa-car"></i><br>Aucun véhicule trouvé pour ce partenaire.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="lco-footer">
                <p><i class="fas fa-info-circle"></i> Une coupure n’est possible que si la règle véhicule est active, que le type du contrat impayé est actif, et qu’une heure de coupure est définie.</p>
                <div class="lco-actions">
                    <button type="reset" class="lco-btn"><i class="fas fa-rotate-left"></i> Réinitialiser</button>
                    <button type="submit" class="lco-btn primary"><i class="fas fa-save"></i> Enregistrer les règles</button>
                </div>
            </div>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('.lco-row'));
    const search = document.getElementById('vehicleSearch');
    const visibleCount = document.getElementById('visibleCount');
    const checkAll = document.getElementById('checkAll');
    const selectionBar = document.getElementById('selectionBar');
    const selectedCount = document.getElementById('selectedCount');
    let activeFilter = 'all';

    const visibleRows = () => rows.filter(row => !row.classList.contains('hidden'));
    const selectedRows = () => rows.filter(row => row.querySelector('.lco-row-check')?.checked);

    function refreshRow(row) {
        const vehicleEnabled = row.querySelector('.vehicle-enabled')?.checked || false;
        const vehicleTime = row.querySelector('.vehicle-time')?.value || '';
        const activeTypes = Array.from(row.querySelectorAll('.type-enabled')).filter(input => input.checked).length;
        const missingTime = vehicleEnabled && !vehicleTime;
        const status = row.querySelector('.row-status');

        row.dataset.enabled = vehicleEnabled ? '1' : '0';
        row.dataset.missingTime = missingTime ? '1' : '0';
        row.dataset.enabledTypeCount = String(activeTypes);

        row.querySelectorAll('.type-card').forEach(card => {
            const cb = card.querySelector('.type-enabled');
            card.classList.toggle('enabled', !!cb?.checked);
        });

        if (status) {
            if (vehicleEnabled && !missingTime) status.innerHTML = '<span class="tag ok">Active</span>';
            else if (vehicleEnabled && missingTime) status.innerHTML = '<span class="tag warn">Sans heure</span>';
            else status.innerHTML = '<span class="tag off">Inactive</span>';
        }

        row.classList.add('dirty');
        refreshKpis();
    }

    function refreshKpis() {
        const enabledVehicles = rows.filter(r => r.dataset.enabled === '1').length;
        const missingTime = rows.filter(r => r.dataset.missingTime === '1').length;
        const activeTypes = rows.reduce((sum, r) => sum + Number(r.dataset.enabledTypeCount || 0), 0);

        const ev = document.getElementById('kpiEnabledVehicles');
        const mt = document.getElementById('kpiMissingTime');
        const at = document.getElementById('kpiActiveTypes');

        if (ev) ev.textContent = enabledVehicles;
        if (mt) mt.textContent = missingTime;
        if (at) at.textContent = activeTypes;
    }

    function applyFilters() {
        const q = (search?.value || '').trim().toLowerCase();

        rows.forEach(row => {
            const matchesSearch = !q || (row.dataset.search || '').includes(q);
            const enabled = row.dataset.enabled === '1';
            const missingTime = row.dataset.missingTime === '1';
            const hasTypeEnabled = Number(row.dataset.enabledTypeCount || 0) > 0;

            let ok = matchesSearch;
            if (activeFilter === 'enabled') ok = ok && enabled;
            if (activeFilter === 'disabled') ok = ok && !enabled;
            if (activeFilter === 'missing-time') ok = ok && missingTime;
            if (activeFilter === 'has-type-enabled') ok = ok && hasTypeEnabled;

            row.classList.toggle('hidden', !ok);
        });

        if (visibleCount) visibleCount.textContent = visibleRows().length;
        refreshSelectionBar();
    }

    function refreshSelectionBar() {
        const selected = selectedRows();
        if (selectionBar) selectionBar.classList.toggle('show', selected.length > 0);
        if (selectedCount) selectedCount.textContent = selected.length;

        const vis = visibleRows();
        const visSelected = vis.filter(row => row.querySelector('.lco-row-check')?.checked).length;

        if (checkAll) {
            checkAll.checked = vis.length > 0 && visSelected === vis.length;
            checkAll.indeterminate = visSelected > 0 && visSelected < vis.length;
        }

        rows.forEach(row => row.classList.toggle('selected', !!row.querySelector('.lco-row-check')?.checked));
    }

    function mutateRows(targetRows, callback) {
        targetRows.forEach(row => {
            callback(row);
            refreshRow(row);
        });
        applyFilters();
    }

    function setVehicleEnabled(row, enabled) {
        const cb = row.querySelector('.vehicle-enabled');
        if (cb) cb.checked = enabled;
    }

    function setAllTypes(row, enabled) {
        row.querySelectorAll('.type-enabled').forEach(input => input.checked = enabled);
    }

    function setTimes(row, time) {
        if (!time) return;
        const vehicleTime = row.querySelector('.vehicle-time');
        if (vehicleTime) vehicleTime.value = time;
        row.querySelectorAll('.type-time').forEach(input => input.value = time);
    }

    search?.addEventListener('input', applyFilters);

    document.querySelectorAll('#quickFilters .lco-pill').forEach(button => {
        button.addEventListener('click', () => {
            document.querySelectorAll('#quickFilters .lco-pill').forEach(item => item.classList.remove('active'));
            button.classList.add('active');
            activeFilter = button.dataset.filter || 'all';
            applyFilters();
        });
    });

    checkAll?.addEventListener('change', () => {
        visibleRows().forEach(row => {
            const cb = row.querySelector('.lco-row-check');
            if (cb) cb.checked = checkAll.checked;
        });
        refreshSelectionBar();
    });

    rows.forEach(row => {
        row.querySelector('.lco-row-check')?.addEventListener('change', refreshSelectionBar);
        row.querySelectorAll('input').forEach(input => {
            if (!input.classList.contains('lco-row-check')) {
                input.addEventListener('change', () => { refreshRow(row); applyFilters(); });
                input.addEventListener('input', () => { refreshRow(row); applyFilters(); });
            }
        });
    });

    document.getElementById('enableVisibleBtn')?.addEventListener('click', () => mutateRows(visibleRows(), row => setVehicleEnabled(row, true)));
    document.getElementById('disableVisibleBtn')?.addEventListener('click', () => mutateRows(visibleRows(), row => setVehicleEnabled(row, false)));
    document.getElementById('enableAllTypesVisibleBtn')?.addEventListener('click', () => mutateRows(visibleRows(), row => setAllTypes(row, true)));
    document.getElementById('disableAllTypesVisibleBtn')?.addEventListener('click', () => mutateRows(visibleRows(), row => setAllTypes(row, false)));
    document.getElementById('applyTimeVisibleBtn')?.addEventListener('click', () => {
        const time = document.getElementById('bulkTime')?.value;
        if (!time) return alert('Choisissez une heure à appliquer.');
        mutateRows(visibleRows(), row => setTimes(row, time));
    });

    document.getElementById('selEnableVehicleBtn')?.addEventListener('click', () => mutateRows(selectedRows(), row => setVehicleEnabled(row, true)));
    document.getElementById('selDisableVehicleBtn')?.addEventListener('click', () => mutateRows(selectedRows(), row => setVehicleEnabled(row, false)));
    document.getElementById('selEnableAllTypesBtn')?.addEventListener('click', () => mutateRows(selectedRows(), row => setAllTypes(row, true)));
    document.getElementById('selDisableAllTypesBtn')?.addEventListener('click', () => mutateRows(selectedRows(), row => setAllTypes(row, false)));
    document.getElementById('selApplyTimeBtn')?.addEventListener('click', () => {
        const time = document.getElementById('selectionTime')?.value;
        if (!time) return alert('Choisissez une heure à appliquer.');
        mutateRows(selectedRows(), row => setTimes(row, time));
    });
    document.getElementById('clearSelectionBtn')?.addEventListener('click', () => {
        rows.forEach(row => {
            const cb = row.querySelector('.lco-row-check');
            if (cb) cb.checked = false;
        });
        refreshSelectionBar();
    });

    rows.forEach(refreshRow);
    applyFilters();
});
</script>
@endpush
