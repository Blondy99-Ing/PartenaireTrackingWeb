@extends('layouts.app')

@section('title', 'Paramétrage coupure lease')

@php
    $rows = collect($rows ?? $vehicles ?? []);
    $totalContracts = $rows->count();
    $activeRules = $rows->sum(fn ($row) => (int) ($row['enabled_contract_rules_count'] ?? 0));
    $missingTimes = $rows->sum(fn ($row) => (int) ($row['missing_time_contract_rules_count'] ?? 0));
    $totalRuleLines = $rows->sum(fn ($row) => count($row['contract_rules'] ?? []));
@endphp

@push('styles')
<style>
    .lco-page{display:flex;flex-direction:column;gap:1rem}.lco-card{background:var(--color-card,#fff);border:1px solid var(--color-border-subtle,#e5e7eb);border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06);overflow:hidden}.lco-head{display:flex;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid var(--color-border-subtle,#e5e7eb)}.lco-head h2{margin:0;font-size:1rem;font-weight:900;color:var(--color-text,#111827);display:flex;gap:.5rem;align-items:center}.lco-head h2 i{color:var(--color-primary,#f58220)}.lco-head p{margin:.25rem 0 0;font-size:.78rem;color:var(--color-secondary-text,#6b7280);line-height:1.55}.lco-alert{padding:.85rem 1rem;border-radius:16px;border:1px solid transparent;font-size:.78rem;font-weight:800}.lco-alert.success{color:#15803d;background:rgba(22,163,74,.08);border-color:rgba(22,163,74,.22)}.lco-alert.error{color:#b91c1c;background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.22)}.lco-alert.warn{color:#b45309;background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.25)}.lco-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;padding:1rem}@media(max-width:900px){.lco-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.lco-kpis{grid-template-columns:1fr}}.lco-kpi{border:1px solid var(--color-border-subtle,#e5e7eb);border-radius:16px;padding:.85rem;background:rgba(148,163,184,.05);display:flex;justify-content:space-between;align-items:center}.lco-kpi span{display:block;color:var(--color-secondary-text,#6b7280);font-size:.62rem;font-weight:900;letter-spacing:.07em;text-transform:uppercase}.lco-kpi strong{display:block;margin-top:.15rem;color:var(--color-primary,#f58220);font-size:1.35rem;font-weight:900}.lco-kpi i{width:36px;height:36px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:rgba(245,130,32,.12);color:var(--color-primary,#f58220)}.lco-toolbar{padding:.9rem 1rem;border-bottom:1px solid var(--color-border-subtle,#e5e7eb);display:flex;flex-direction:column;gap:.75rem}.lco-toolbar-top{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap}.lco-search{position:relative;flex:1;min-width:260px}.lco-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:.75rem;color:#6b7280}.lco-input{width:100%;border:1px solid var(--color-border-subtle,#e5e7eb);border-radius:12px;padding:.62rem .75rem;background:var(--color-card,#fff);color:var(--color-text,#111827);font-size:.76rem;outline:none}.lco-search .lco-input{padding-left:2.1rem}.lco-input:focus{border-color:var(--color-primary,#f58220);box-shadow:0 0 0 3px rgba(245,130,32,.12)}.lco-btn{border:1px solid var(--color-border-subtle,#e5e7eb);background:var(--color-card,#fff);color:var(--color-text,#111827);border-radius:12px;padding:.62rem .82rem;font-size:.72rem;font-weight:900;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:.16s ease;text-decoration:none}.lco-btn:hover{border-color:var(--color-primary,#f58220);color:var(--color-primary,#f58220);transform:translateY(-1px)}.lco-btn.primary{background:var(--color-primary,#f58220);border-color:var(--color-primary,#f58220);color:#fff}.lco-btn.soft{background:rgba(245,130,32,.09);border-color:rgba(245,130,32,.22);color:var(--color-primary,#f58220)}.lco-selection{display:none;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;padding:.75rem 1rem;background:rgba(245,130,32,.08);border-top:1px solid rgba(245,130,32,.2)}.lco-selection.show{display:flex}.lco-table-wrap{overflow-x:auto}.lco-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1120px}.lco-table th{position:sticky;top:0;background:rgba(248,250,252,.96);z-index:1;text-align:left;font-size:.62rem;text-transform:uppercase;letter-spacing:.07em;color:#64748b;padding:.75rem;border-bottom:1px solid #e5e7eb}.lco-table td{padding:.85rem;border-bottom:1px solid #eef2f7;vertical-align:top}.lco-row.hidden{display:none}.lco-row.selected{background:rgba(245,130,32,.045)}.contract-title{font-weight:900;color:#111827;display:flex;gap:.45rem;align-items:center}.contract-meta{margin-top:.25rem;font-size:.72rem;color:#6b7280;line-height:1.45}.tag{display:inline-flex;align-items:center;gap:.25rem;border-radius:999px;padding:.2rem .48rem;font-size:.62rem;font-weight:900}.tag.ok{background:rgba(22,163,74,.10);color:#15803d}.tag.warn{background:rgba(245,158,11,.12);color:#b45309}.tag.off{background:rgba(100,116,139,.12);color:#64748b}.tag.sub{background:rgba(59,130,246,.10);color:#1d4ed8}.rule-grid{display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:.7rem}@media(max-width:980px){.rule-grid{grid-template-columns:1fr}}.rule-card{border:1px solid #e5e7eb;border-radius:16px;padding:.75rem;background:rgba(248,250,252,.65);transition:.15s}.rule-card.enabled{border-color:rgba(22,163,74,.35);background:rgba(22,163,74,.045)}.rule-card .rule-top{display:flex;justify-content:space-between;gap:.5rem}.rule-name{font-weight:900;color:#111827}.rule-sub{font-size:.68rem;color:#64748b;margin-top:.16rem}.switchline{display:flex;align-items:center;gap:.4rem;font-size:.72rem;font-weight:900}.switchline input,.lco-row-check{width:16px;height:16px;accent-color:var(--color-primary,#f58220)}.rule-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.45rem;margin-top:.65rem}.mini-field label{display:block;font-size:.58rem;text-transform:uppercase;letter-spacing:.06em;font-weight:900;color:#64748b;margin-bottom:.2rem}.mini-input{width:100%;border:1px solid #e5e7eb;border-radius:10px;padding:.48rem .55rem;font-size:.72rem;background:#fff}.mini-check{display:flex;align-items:center;gap:.35rem;margin-top:.45rem;font-size:.66rem;font-weight:800;color:#475569}.empty{padding:2rem;text-align:center;color:#64748b;font-weight:800}.lco-footer{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:1rem;border-top:1px solid #e5e7eb}.lco-footer p{margin:0;font-size:.76rem;color:#64748b;line-height:1.45}.lco-actions{display:flex;gap:.5rem;flex-wrap:wrap}.dirty .contract-title:after{content:'Modifié';font-size:.55rem;border-radius:999px;padding:.15rem .38rem;background:rgba(245,158,11,.12);color:#b45309}
</style>
@endpush

@section('content')
<div class="lco-page">
    @if(session('success'))
        <div class="lco-alert success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="lco-alert error"><i class="fas fa-triangle-exclamation"></i> {{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="lco-alert error">
            <strong>Veuillez corriger le formulaire :</strong>
            <ul style="margin:.5rem 0 0 1rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach(($pageWarnings ?? []) as $warning)
        <div class="lco-alert warn"><i class="fas fa-circle-info"></i> {{ $warning }}</div>
    @endforeach

    <section class="lco-card">
        <div class="lco-head">
            <div>
                <h2><i class="fas fa-shield-halved"></i> Règles de coupure par contrat réel</h2>
                <p>
                    Une coupure automatique n’est possible que si le contrat spécifique ou l’un de ses sous-contrats réellement associés possède une règle active.
                    Le paramétrage en masse est autorisé, mais il ne modifie que les contrats/sous-contrats visibles et sélectionnés.
                </p>
            </div>
        </div>

        <div class="lco-kpis">
            <div class="lco-kpi"><div><span>Contrats principaux</span><strong id="kpiContracts">{{ $totalContracts }}</strong></div><i class="fas fa-file-contract"></i></div>
            <div class="lco-kpi"><div><span>Lignes réelles</span><strong>{{ $totalRuleLines }}</strong></div><i class="fas fa-link"></i></div>
            <div class="lco-kpi"><div><span>Règles actives</span><strong id="kpiActiveRules">{{ $activeRules }}</strong></div><i class="fas fa-power-off"></i></div>
            <div class="lco-kpi"><div><span>Actives sans heure</span><strong id="kpiMissingTime">{{ $missingTimes }}</strong></div><i class="fas fa-clock"></i></div>
        </div>
    </section>

    <section class="lco-card">
        <div class="lco-head">
            <div>
                <h2><i class="fas fa-layer-group"></i> Paramétrage en masse</h2>
                <p>Les actions ci-dessous s’appliquent aux lignes visibles ou sélectionnées. Elles n’ajoutent jamais de sous-contrat absent du contrat.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('lease.cutoff-rules.store') }}" id="cutoffRulesForm">
            @csrf

            <div class="lco-toolbar">
                <div class="lco-toolbar-top">
                    <div class="lco-search">
                        <i class="fas fa-search"></i>
                        <input class="lco-input" type="search" id="contractSearch" placeholder="Rechercher contrat, véhicule, chauffeur, type...">
                    </div>
                    <input type="time" class="lco-input" id="bulkTime" style="max-width:150px" value="12:00">
                    <button type="button" class="lco-btn soft" id="applyTimeVisibleBtn"><i class="fas fa-clock"></i> Heure aux visibles</button>
                    <button type="button" class="lco-btn soft" id="enableVisibleBtn"><i class="fas fa-toggle-on"></i> Activer visibles</button>
                    <button type="button" class="lco-btn" id="disableVisibleBtn"><i class="fas fa-toggle-off"></i> Désactiver visibles</button>
                </div>
            </div>

            <div class="lco-selection" id="selectionBar">
                <strong><span id="selectedCount">0</span> contrat(s) sélectionné(s)</strong>
                <div class="lco-actions">
                    <input type="time" class="lco-input" id="selectionTime" style="max-width:150px" value="12:00">
                    <button type="button" class="lco-btn soft" id="selApplyTimeBtn"><i class="fas fa-clock"></i> Appliquer heure</button>
                    <button type="button" class="lco-btn soft" id="selEnableBtn"><i class="fas fa-toggle-on"></i> Activer</button>
                    <button type="button" class="lco-btn" id="selDisableBtn"><i class="fas fa-toggle-off"></i> Désactiver</button>
                    <button type="button" class="lco-btn" id="clearSelectionBtn"><i class="fas fa-xmark"></i> Vider</button>
                </div>
            </div>

            <div class="lco-table-wrap">
                <table class="lco-table">
                    <thead>
                        <tr>
                            <th style="width:45px"><input type="checkbox" id="checkAll" class="lco-row-check"></th>
                            <th>Contrat / Véhicule</th>
                            <th>Chauffeur</th>
                            <th>Statut</th>
                            <th>Contrat et sous-contrats réellement associés</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $rowIndex => $row)
                            @php
                                $searchText = strtolower(trim(($row['immatriculation'] ?? '').' '.($row['driver_name'] ?? '').' '.($row['main_type_label'] ?? '').' '.collect($row['contract_rules'] ?? [])->pluck('type_contrat_label')->implode(' ')));
                            @endphp
                            <tr class="lco-row" data-search="{{ $searchText }}" data-enabled-count="{{ $row['enabled_contract_rules_count'] ?? 0 }}" data-missing-time="{{ $row['missing_time_contract_rules_count'] ?? 0 }}">
                                <td><input type="checkbox" class="lco-row-check"></td>
                                <td>
                                    <input type="hidden" name="rules[{{ $rowIndex }}][main_contract_link_id]" value="{{ $row['main_contract_link_id'] }}">
                                    <input type="hidden" name="rules[{{ $rowIndex }}][vehicle_id]" value="{{ $row['vehicle_id'] }}">
                                    <div class="contract-title"><i class="fas fa-motorcycle"></i> {{ $row['immatriculation'] ?: 'Véhicule sans immatriculation' }}</div>
                                    <div class="contract-meta">
                                        {{ trim(($row['marque'] ?? '').' '.($row['model'] ?? '')) ?: 'Modèle non renseigné' }}<br>
                                        Contrat principal #{{ $row['main_source_contract_id'] }} — {{ $row['main_type_label'] }}<br>
                                        GPS : {{ $row['mac_id_gps'] ?: 'mac_id_gps manquant' }}
                                    </div>
                                </td>
                                <td><div class="contract-meta">{{ $row['driver_name'] ?: 'Chauffeur non résolu' }}</div></td>
                                <td class="row-status"></td>
                                <td>
                                    <div class="rule-grid">
                                        @foreach(($row['contract_rules'] ?? []) as $ruleIndex => $rule)
                                            <div class="rule-card {{ !empty($rule['is_enabled']) ? 'enabled' : '' }}">
                                                <div class="rule-top">
                                                    <div>
                                                        <div class="rule-name">
                                                            @if(($rule['contract_kind'] ?? 'MAIN') === 'SUB')
                                                                <span class="tag sub">Sous-contrat</span>
                                                            @else
                                                                <span class="tag ok">Principal</span>
                                                            @endif
                                                            {{ $rule['type_contrat_label'] }}
                                                        </div>
                                                        <div class="rule-sub">
                                                            Contrat #{{ $rule['source_contract_id'] }}
                                                            @if(!empty($rule['source_parent_contract_id']))
                                                                — parent #{{ $rule['source_parent_contract_id'] }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <label class="switchline">
                                                        <input type="checkbox" class="rule-enabled" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][is_enabled]" value="1" @checked($rule['is_enabled'])>
                                                        Activer
                                                    </label>
                                                </div>

                                                <input type="hidden" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][contract_link_id]" value="{{ $rule['contract_link_id'] }}">
                                                <input type="hidden" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][timezone]" value="{{ $rule['timezone'] ?? 'Africa/Douala' }}">

                                                <div class="rule-fields">
                                                    <div class="mini-field">
                                                        <label>Heure</label>
                                                        <input type="time" class="mini-input rule-time" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][cutoff_time]" value="{{ $rule['cutoff_time'] ?: '12:00' }}">
                                                    </div>
                                                    <div class="mini-field">
                                                        <label>Grâce</label>
                                                        <input type="number" class="mini-input" min="0" max="365" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][grace_days]" value="{{ $rule['grace_days'] ?? 0 }}">
                                                    </div>
                                                    <div class="mini-field">
                                                        <label>Sécurité</label>
                                                        <label class="mini-check"><input type="checkbox" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][only_when_stopped]" value="1" @checked($rule['only_when_stopped'] ?? true)> Arrêt seul</label>
                                                    </div>
                                                    <div class="mini-field">
                                                        <label>Notification</label>
                                                        <label class="mini-check"><input type="checkbox" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][notify_before_cutoff]" value="1" @checked($rule['notify_before_cutoff'] ?? false)> Notifier</label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty"><i class="fas fa-file-contract"></i><br>Aucun contrat lié trouvé. Synchronisez d’abord les contrats Recouvrement avec les véhicules Tracking.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="lco-footer">
                <p><i class="fas fa-info-circle"></i> Pas de règle active sur le contrat/sous-contrat réel = aucune planification de coupure.</p>
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
    const search = document.getElementById('contractSearch');
    const checkAll = document.getElementById('checkAll');
    const selectionBar = document.getElementById('selectionBar');
    const selectedCount = document.getElementById('selectedCount');

    const visibleRows = () => rows.filter(row => !row.classList.contains('hidden'));
    const selectedRows = () => rows.filter(row => row.querySelector('.lco-row-check')?.checked);

    function refreshRow(row) {
        const cards = Array.from(row.querySelectorAll('.rule-card'));
        const enabled = cards.filter(card => card.querySelector('.rule-enabled')?.checked).length;
        const missing = cards.filter(card => card.querySelector('.rule-enabled')?.checked && !card.querySelector('.rule-time')?.value).length;
        row.dataset.enabledCount = String(enabled);
        row.dataset.missingTime = String(missing);

        cards.forEach(card => card.classList.toggle('enabled', !!card.querySelector('.rule-enabled')?.checked));

        const status = row.querySelector('.row-status');
        if (status) {
            if (enabled > 0 && missing === 0) status.innerHTML = '<span class="tag ok">Règle active</span>';
            else if (enabled > 0 && missing > 0) status.innerHTML = '<span class="tag warn">Heure manquante</span>';
            else status.innerHTML = '<span class="tag off">Aucune règle active</span>';
        }

        row.classList.add('dirty');
        refreshKpis();
    }

    function refreshKpis() {
        const activeRules = rows.reduce((sum, row) => sum + Number(row.dataset.enabledCount || 0), 0);
        const missing = rows.reduce((sum, row) => sum + Number(row.dataset.missingTime || 0), 0);
        document.getElementById('kpiActiveRules').textContent = activeRules;
        document.getElementById('kpiMissingTime').textContent = missing;
    }

    function applyFilters() {
        const q = (search?.value || '').trim().toLowerCase();
        rows.forEach(row => {
            const ok = !q || (row.dataset.search || '').includes(q);
            row.classList.toggle('hidden', !ok);
        });
        refreshSelectionBar();
    }

    function refreshSelectionBar() {
        const selected = selectedRows();
        selectionBar?.classList.toggle('show', selected.length > 0);
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

    function setRulesEnabled(row, enabled) {
        row.querySelectorAll('.rule-enabled').forEach(input => input.checked = enabled);
    }

    function setTimes(row, time) {
        if (!time) return;
        row.querySelectorAll('.rule-time').forEach(input => input.value = time);
    }

    search?.addEventListener('input', applyFilters);
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

    document.getElementById('enableVisibleBtn')?.addEventListener('click', () => mutateRows(visibleRows(), row => setRulesEnabled(row, true)));
    document.getElementById('disableVisibleBtn')?.addEventListener('click', () => mutateRows(visibleRows(), row => setRulesEnabled(row, false)));
    document.getElementById('applyTimeVisibleBtn')?.addEventListener('click', () => {
        const time = document.getElementById('bulkTime')?.value;
        if (!time) return alert('Choisissez une heure à appliquer.');
        mutateRows(visibleRows(), row => setTimes(row, time));
    });

    document.getElementById('selEnableBtn')?.addEventListener('click', () => mutateRows(selectedRows(), row => setRulesEnabled(row, true)));
    document.getElementById('selDisableBtn')?.addEventListener('click', () => mutateRows(selectedRows(), row => setRulesEnabled(row, false)));
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
    rows.forEach(row => row.classList.remove('dirty'));
    applyFilters();
});
</script>
@endpush
