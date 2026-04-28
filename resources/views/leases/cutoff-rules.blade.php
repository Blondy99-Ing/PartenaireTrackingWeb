@extends('layouts.app')

@section('title', 'Paramétrage coupure lease')

@push('styles')
<style>
/* ============================================================
   LEASE CUTOFF RULES — aligné design system Fleetra
   ============================================================ */

.lco-page {
    display: flex;
    flex-direction: column;
    gap: .85rem;
}

/* ── Flash messages ──────────────────────────────────────── */
.flash-success,
.flash-error {
    padding: .75rem .95rem;
    border-radius: var(--r-xl, 12px);
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .68rem;
    display: flex;
    align-items: center;
    gap: .55rem;
}
.flash-success {
    background: var(--color-success-bg, rgba(22,163,74,.1));
    color: var(--color-success, #15803d);
    border: 1px solid rgba(22,163,74,.2);
}
.flash-error {
    background: var(--color-error-bg, rgba(220,38,38,.1));
    color: var(--color-error, #b91c1c);
    border: 1px solid rgba(220,38,38,.2);
}

/* ── Header card ─────────────────────────────────────────── */
.lco-header-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg, 10px);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.lco-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: .85rem .95rem;
    border-bottom: 1px solid var(--color-border-subtle);
}
.lco-head h2 {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .9rem;
    color: var(--color-text);
    display: flex;
    align-items: center;
    gap: .45rem;
}
.lco-head h2 i { color: var(--color-primary); font-size: .8rem; }
.lco-head p {
    margin: .2rem 0 0;
    font-family: var(--font-body);
    font-size: .72rem;
    color: var(--color-secondary-text, #8b949e);
    max-width: 680px;
}

/* ── KPI strip ───────────────────────────────────────────── */
.lco-kpi-strip {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .65rem;
    padding: .85rem;
}
@media(max-width:640px) { .lco-kpi-strip { grid-template-columns: 1fr; } }

.lco-kpi {
    border: 1px solid var(--color-border-subtle);
    border-radius: 14px;
    background: rgba(0,0,0,.03);
    padding: .7rem .8rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .6rem;
}
.dark-mode .lco-kpi { background: rgba(255,255,255,.03); }
.lco-kpi .lbl {
    margin: 0;
    font-family: var(--font-display);
    font-size: .58rem; font-weight: 900;
    letter-spacing: .08em; text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e);
}
.lco-kpi .val {
    margin: .12rem 0 0;
    font-family: var(--font-display);
    font-size: 1.35rem; font-weight: 900;
    color: var(--color-primary);
}
.lco-kpi .ico {
    width: 36px; height: 36px;
    border-radius: 10px;
    background: var(--color-primary-light);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.lco-kpi .ico i { color: var(--color-primary); font-size: .8rem; }

/* ── Form card ───────────────────────────────────────────── */
.lco-form-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg, 10px);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

/* ── Toolbar ─────────────────────────────────────────────── */
.lco-toolbar {
    display: flex;
    flex-direction: column;
    gap: .55rem;
    padding: .75rem .85rem;
    border-bottom: 1px solid var(--color-border-subtle);
}
.lco-toolbar-top {
    display: flex;
    align-items: center;
    gap: .65rem;
    flex-wrap: wrap;
}

.swrap {
    position: relative;
    flex: 1;
    min-width: 180px;
}
.swrap i {
    position: absolute; left: 10px; top: 50%;
    transform: translateY(-50%);
    font-size: .7rem;
    color: var(--color-secondary-text, #8b949e);
    pointer-events: none;
}
.swrap input {
    width: 100%;
    border: 1px solid var(--color-border-subtle);
    border-radius: 10px;
    padding: .5rem .6rem .5rem 2rem;
    font-size: .75rem;
    background: var(--color-card);
    color: var(--color-text);
    font-family: var(--font-body);
    outline: none;
}
.swrap input:focus { border-color: var(--color-primary); }
.sclear {
    position: absolute; right: 8px; top: 50%;
    transform: translateY(-50%);
    display: none;
    width: 18px; height: 18px;
    border-radius: 9999px;
    border: none;
    background: var(--color-border-subtle);
    color: var(--color-secondary-text, #8b949e);
    font-weight: 900; cursor: pointer;
    font-size: .75rem; line-height: 1;
}
.sclear.show { display: flex; align-items: center; justify-content: center; }

.lco-bulk-actions {
    display: flex; align-items: center;
    gap: .4rem; flex-wrap: wrap; flex-shrink: 0;
}

.lco-btn {
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    border-radius: 10px;
    padding: .48rem .7rem;
    font-family: var(--font-display);
    font-weight: 900; font-size: .62rem;
    cursor: pointer;
    color: var(--color-secondary-text, #8b949e);
    transition: .12s;
    white-space: nowrap;
    display: inline-flex; align-items: center; gap: .35rem;
}
.lco-btn:hover { border-color: var(--color-primary); color: var(--color-primary); }
.lco-btn.soft {
    background: var(--color-primary-light);
    color: var(--color-primary);
    border-color: var(--color-primary-border, rgba(245,130,32,.3));
}
.lco-btn.soft:hover { border-color: var(--color-primary); }
.lco-btn.primary {
    background: var(--color-primary); color: #fff;
    border-color: var(--color-primary);
}
.lco-btn.primary:hover { background: var(--color-primary-hover, #e07318); color: #fff; }
.lco-btn.danger:hover {
    border-color: var(--color-error, #dc2626);
    color: var(--color-error, #dc2626);
}

.lco-time-bulk {
    border: 1px solid var(--color-border-subtle);
    border-radius: 10px;
    background: var(--color-card);
    color: var(--color-text);
    padding: .46rem .6rem; outline: none;
    font-size: .72rem; font-family: var(--font-body);
    width: 108px;
}
.lco-time-bulk:focus { border-color: var(--color-primary); }

/* ── Filter bar ──────────────────────────────────────────── */
.lco-filterline {
    display: flex; align-items: center;
    justify-content: space-between;
    gap: .6rem; flex-wrap: wrap;
}
.lco-filters { display: flex; gap: .3rem; flex-wrap: wrap; }

.lco-f {
    border: 1px solid var(--color-border-subtle);
    border-radius: 9999px;
    padding: .22rem .55rem;
    font-family: var(--font-display);
    font-size: .58rem; font-weight: 800;
    color: var(--color-secondary-text, #8b949e);
    cursor: pointer; transition: .12s;
    background: transparent; user-select: none;
    display: inline-flex; align-items: center; gap: .25rem;
}
.lco-f:hover { border-color: var(--color-primary); color: var(--color-primary); }
.lco-f.active {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.lco-meta {
    font-size: .65rem;
    color: var(--color-secondary-text, #8b949e);
    font-family: var(--font-display);
    font-weight: 800; white-space: nowrap;
}

/* ── Selection action bar ────────────────────────────────── */
.lco-sel-bar {
    display: none;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
    padding: .6rem .85rem;
    background: var(--color-primary-light);
    border-bottom: 1px solid var(--color-primary-border, rgba(245,130,32,.25));
    animation: selBarIn .15s ease;
}
@keyframes selBarIn {
    from { opacity:0; transform:translateY(-4px); }
    to   { opacity:1; transform:translateY(0); }
}
.lco-sel-bar.show { display: flex; }

.sel-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    background: var(--color-primary);
    color: #fff;
    font-family: var(--font-display);
    font-weight: 900; font-size: .62rem;
    padding: .28rem .6rem;
    border-radius: 9999px;
    flex-shrink: 0;
}

.sel-label {
    font-family: var(--font-display);
    font-weight: 900; font-size: .65rem;
    color: var(--color-primary);
    flex-shrink: 0;
}

.sel-sep {
    width: 1px; height: 18px;
    background: var(--color-primary-border, rgba(245,130,32,.3));
    flex-shrink: 0;
}

.sel-time-wrap {
    display: flex; align-items: center; gap: .3rem;
}
.sel-time-wrap label {
    font-family: var(--font-display);
    font-weight: 900; font-size: .6rem;
    color: var(--color-primary);
    white-space: nowrap;
}
.lco-time-sel {
    border: 1px solid var(--color-primary-border, rgba(245,130,32,.3));
    border-radius: 9px;
    background: var(--color-card);
    color: var(--color-text);
    padding: .42rem .55rem; outline: none;
    font-size: .7rem; font-family: var(--font-body);
    width: 100px;
}
.lco-time-sel:focus { border-color: var(--color-primary); }

.sel-deselect {
    margin-left: auto;
    font-family: var(--font-display);
    font-weight: 900; font-size: .6rem;
    color: var(--color-primary);
    cursor: pointer; opacity: .7;
    background: none; border: none;
    display: inline-flex; align-items: center; gap: .25rem;
    padding: 0;
}
.sel-deselect:hover { opacity: 1; }

/* ── Table ───────────────────────────────────────────────── */
.lco-table-wrap {
    overflow: auto;
    max-height: calc(100vh - 350px);
    min-height: 200px;
}
.lco-table-wrap::-webkit-scrollbar { width: 6px; height: 6px; }
.lco-table-wrap::-webkit-scrollbar-thumb {
    background: var(--color-border-subtle);
    border-radius: 999px;
}

.lco-table {
    width: 100%;
    min-width: 820px;
    border-collapse: separate;
    border-spacing: 0;
}

.lco-table thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--color-card);
    border-bottom: 1px solid var(--color-border-subtle);
    padding: .65rem .75rem;
    text-align: left;
    font-family: var(--font-display);
    font-size: .58rem; font-weight: 900;
    letter-spacing: .08em; text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e);
    white-space: nowrap;
}
.lco-table thead th:first-child  { padding-left: .75rem; width: 36px; }
.lco-table thead th:nth-child(2) { width: 38px; text-align: center; }

.lco-table tbody td {
    padding: .72rem .75rem;
    border-bottom: 1px solid var(--color-border-subtle);
    vertical-align: middle;
    font-family: var(--font-body); font-size: .75rem;
    color: var(--color-text);
}
.lco-table tbody td:first-child  { padding-left: .75rem; }
.lco-table tbody td:nth-child(2) { text-align: center; }

.lco-row { transition: background .1s; }
.lco-row:last-child td { border-bottom: none; }
.lco-row:hover { background: rgba(128,128,128,.04); }
.lco-row.is-dirty { background: rgba(245,158,11,.07); }
.dark-mode .lco-row.is-dirty { background: rgba(245,158,11,.05); }
.lco-row.is-selected { background: var(--color-primary-light) !important; }
.lco-row.is-selected td {
    border-bottom-color: var(--color-primary-border, rgba(245,130,32,.2));
}

/* Checkboxes */
.check-all,
.row-check {
    width: 15px; height: 15px;
    border-radius: 4px;
    cursor: pointer;
    accent-color: var(--color-primary);
    flex-shrink: 0;
}

/* Vehicle cell */
.veh-cell { display: flex; flex-direction: column; gap: .1rem; }
.veh-cell .veh-title {
    font-family: var(--font-display);
    font-weight: 900; font-size: .78rem;
    color: var(--color-text); margin: 0;
}
.veh-cell .veh-sub {
    font-family: var(--font-body); font-size: .65rem;
    color: var(--color-secondary-text, #8b949e); margin: 0;
}

/* Tag / pill */
.tag {
    font-family: var(--font-display);
    font-weight: 800; font-size: .55rem;
    padding: .2rem .45rem; border-radius: 9999px;
    display: inline-flex; align-items: center; gap: .25rem;
}
.dot { width: 7px; height: 7px; border-radius: 9999px; flex-shrink: 0; }

/* Toggle switch */
.rule-switch {
    position: relative; width: 46px; height: 25px;
    display: inline-block; flex-shrink: 0;
}
.rule-switch input { opacity: 0; width: 0; height: 0; }
.rule-slider {
    position: absolute; inset: 0;
    border-radius: 9999px;
    background: var(--color-border, #9ca3af);
    transition: .2s; cursor: pointer;
}
.rule-slider::before {
    content: "";
    position: absolute;
    width: 17px; height: 17px;
    left: 4px; top: 4px;
    border-radius: 9999px;
    background: #fff; transition: .2s;
    box-shadow: 0 2px 6px rgba(0,0,0,.18);
}
.rule-switch input:checked + .rule-slider { background: var(--color-success, #16a34a); }
.rule-switch input:checked + .rule-slider::before { transform: translateX(21px); }

.lco-time-inline {
    border: 1px solid var(--color-border-subtle);
    border-radius: 9px;
    background: var(--color-card);
    color: var(--color-text);
    padding: .44rem .55rem; outline: none;
    font-size: .72rem; font-family: var(--font-body);
    width: 100px;
}
.lco-time-inline:focus { border-color: var(--color-primary); }

.lco-num {
    font-family: var(--font-mono, monospace);
    font-size: .6rem;
    color: var(--color-secondary-text, #8b949e);
}

/* ── Footer ──────────────────────────────────────────────── */
.lco-footer {
    display: flex; align-items: center;
    justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
    padding: .75rem .9rem;
    border-top: 1px solid var(--color-border-subtle);
}
.lco-footer .hint {
    margin: 0; font-family: var(--font-body);
    font-size: .68rem;
    color: var(--color-secondary-text, #8b949e);
}
.lco-footer .hint i { color: var(--color-warning, #d97706); margin-right: .25rem; }
.lco-footer-actions { display: flex; gap: .4rem; flex-wrap: wrap; }

/* ── Empty ───────────────────────────────────────────────── */
.lco-empty {
    padding: 2.5rem 1rem; text-align: center;
    color: var(--color-secondary-text, #8b949e);
    font-family: var(--font-display); font-weight: 800; font-size: .75rem;
}
.lco-empty i { font-size: 1.5rem; opacity: .4; }

/* ── Tip ─────────────────────────────────────────────────── */
.lco-tip {
    display: inline-flex; align-items: center; gap: .35rem;
    font-family: var(--font-body); font-size: .68rem;
    color: var(--color-secondary-text, #8b949e);
    background: rgba(37,99,235,.06);
    border: 1px solid rgba(37,99,235,.12);
    border-radius: 10px; padding: .45rem .7rem;
}
.dark-mode .lco-tip { background: rgba(37,99,235,.1); }
.lco-tip i { color: var(--color-info, #2563eb); flex-shrink: 0; }
</style>
@endpush

@section('content')
@php
    $vehicles = collect($vehicles ?? []);
    $totalVehicles = $vehicles->count();
    $enabledVehicles = $vehicles->where('is_enabled', true)->count();
    $missingTimeVehicles = $vehicles->filter(fn ($v) => !empty($v['is_enabled']) && empty($v['cutoff_time']))->count();
@endphp

<div class="lco-page">

 
    {{-- ── Header + KPIs ── --}}
    <div class="lco-header-card">
        <div class="lco-head">
            <div>
                <h2><i class="fas fa-bolt"></i> Paramétrage des coupures automatiques lease</h2>
                <p>Configurez les règles de coupure par véhicule. Cochez plusieurs véhicules pour leur appliquer les mêmes paramètres en une seule action.</p>
            </div>
            <div class="lco-tip" style="align-self:flex-start;flex-shrink:0">
                <i class="fas fa-info-circle"></i>
                <span>Cochez des lignes pour les actions groupées.</span>
            </div>
        </div>

        <div class="lco-kpi-strip">
            <div class="lco-kpi">
                <div>
                    <p class="lbl">Total véhicules</p>
                    <p class="val" id="kpiTotalVehicles">{{ $totalVehicles }}</p>
                </div>
                <div class="ico"><i class="fas fa-car"></i></div>
            </div>
            <div class="lco-kpi">
                <div>
                    <p class="lbl">Coupure active</p>
                    <p class="val" id="kpiEnabledVehicles">{{ $enabledVehicles }}</p>
                </div>
                <div class="ico" style="background:rgba(22,163,74,.12)">
                    <i class="fas fa-bolt" style="color:#16a34a"></i>
                </div>
            </div>
            <div class="lco-kpi">
                <div>
                    <p class="lbl">Actifs sans heure</p>
                    <p class="val" id="kpiMissingTime" style="{{ $missingTimeVehicles > 0 ? 'color:var(--color-warning,#d97706)' : '' }}">{{ $missingTimeVehicles }}</p>
                </div>
                <div class="ico" style="background:rgba(217,119,6,.1)">
                    <i class="fas fa-clock" style="color:#d97706"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Form card ── --}}
    <div class="lco-form-card">
        <form method="POST" action="{{ route('lease.cutoff-rules.store') }}">
            @csrf

            {{-- Toolbar : recherche + actions globales (visibles) --}}
            <div class="lco-toolbar">
                <div class="lco-toolbar-top">
                    <div class="swrap">
                        <i class="fas fa-search"></i>
                        <input id="vehicleSearch" placeholder="Immatriculation, marque, GPS…" autocomplete="off">
                        <button type="button" id="vehicleSearchClear" class="sclear" aria-label="Effacer">×</button>
                    </div>

                    <div class="lco-bulk-actions">
                        <button type="button" class="lco-btn soft" id="enableAllBtn">
                            <i class="fas fa-toggle-on"></i> Activer visibles
                        </button>
                        <button type="button" class="lco-btn" id="disableAllBtn">
                            <i class="fas fa-toggle-off"></i> Désactiver visibles
                        </button>
                        <input type="time" id="bulkTime" class="lco-time-bulk" step="60" title="Heure à appliquer aux visibles">
                        <button type="button" class="lco-btn soft" id="applyTimeAllBtn">
                            <i class="fas fa-clock"></i> Appliquer aux visibles
                        </button>
                        <button type="button" class="lco-btn danger" id="clearVisibleTimeBtn" title="Vider l'heure des visibles">
                            <i class="fas fa-eraser"></i>
                        </button>
                    </div>
                </div>

                <div class="lco-filterline">
                    <div class="lco-filters" id="quickFilters">
                        <span class="lco-f active" data-filter="all">Tous</span>
                        <span class="lco-f" data-filter="enabled">
                            <span class="dot" style="background:#16a34a"></span> Actifs
                        </span>
                        <span class="lco-f" data-filter="disabled">
                            <span class="dot" style="background:#6b7280"></span> Inactifs
                        </span>
                        <span class="lco-f" data-filter="missing-time">
                            <span class="dot" style="background:#d97706"></span> Actifs sans heure
                        </span>
                    </div>
                    <div class="lco-meta">
                        <span id="visibleCount">{{ $totalVehicles }}</span> véhicule(s) visible(s)
                    </div>
                </div>
            </div>

            {{-- Barre d'actions sur la sélection — apparaît dès qu'une ligne est cochée --}}
            <div class="lco-sel-bar" id="selBar">
                <span class="sel-badge">
                    <i class="fas fa-check-square"></i>
                    <span id="selCount">0</span> sélectionné(s)
                </span>

                <span class="sel-label">Appliquer aux sélectionnés :</span>

                <div class="sel-sep"></div>

                <button type="button" class="lco-btn soft" id="selEnableBtn">
                    <i class="fas fa-toggle-on"></i> Activer
                </button>
                <button type="button" class="lco-btn" id="selDisableBtn">
                    <i class="fas fa-toggle-off"></i> Désactiver
                </button>

                <div class="sel-sep"></div>

                <div class="sel-time-wrap">
                    <label for="selTime"><i class="fas fa-clock"></i> Heure</label>
                    <input type="time" id="selTime" class="lco-time-sel" step="60">
                </div>
                <button type="button" class="lco-btn soft" id="selApplyTimeBtn">
                    <i class="fas fa-check"></i> Appliquer
                </button>
                <button type="button" class="lco-btn danger" id="selClearTimeBtn" title="Vider l'heure des sélectionnés">
                    <i class="fas fa-eraser"></i>
                </button>

                <button type="button" class="sel-deselect" id="selDeselectBtn">
                    <i class="fas fa-times"></i> Désélectionner tout
                </button>
            </div>

            {{-- Table --}}
            <div class="lco-table-wrap">
                <table class="lco-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>
                                <input type="checkbox" class="check-all" id="checkAll" title="Tout cocher / décocher">
                            </th>
                            <th>Véhicule</th>
                            <th>GPS</th>
                            <th>État règle</th>
                            <th>Coupure auto</th>
                            <th>Heure</th>
                        </tr>
                    </thead>
                    <tbody id="cutoffTableBody">
                        @forelse($vehicles as $index => $vehicle)
                            @php
                                $enabled     = !empty($vehicle['is_enabled']);
                                $timeMissing = $enabled && empty($vehicle['cutoff_time']);
                            @endphp

                            <tr
                                class="lco-row"
                                data-search="{{ strtolower(trim(($vehicle['immatriculation'] ?? '') . ' ' . ($vehicle['marque'] ?? '') . ' ' . ($vehicle['model'] ?? '') . ' ' . ($vehicle['mac_id_gps'] ?? ''))) }}"
                                data-enabled="{{ $enabled ? '1' : '0' }}"
                                data-missing-time="{{ $timeMissing ? '1' : '0' }}"
                            >
                                <td><span class="lco-num">{{ $index + 1 }}</span></td>

                                <td>
                                    <input type="checkbox" class="row-check" aria-label="Sélectionner">
                                </td>

                                <td>
                                    <div class="veh-cell">
                                        <p class="veh-title">{{ $vehicle['immatriculation'] ?? '—' }}</p>
                                        <p class="veh-sub">{{ trim(($vehicle['marque'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?: '—' }}</p>
                                    </div>
                                    <input type="hidden" name="rules[{{ $index }}][vehicle_id]" value="{{ $vehicle['vehicle_id'] }}">
                                    <input type="hidden" name="rules[{{ $index }}][timezone]"   value="{{ $vehicle['timezone'] ?? '' }}" class="timezone-hidden">
                                </td>

                                <td>
                                    <span style="font-family:var(--font-mono,monospace);font-size:.68rem;color:var(--color-secondary-text,#8b949e)">
                                        {{ $vehicle['mac_id_gps'] ?? '—' }}
                                    </span>
                                </td>

                                <td class="rule-state-cell">
                                    @if($enabled && !$timeMissing)
                                        <span class="tag" style="background:rgba(22,163,74,.12);color:#16a34a">
                                            <span class="dot" style="background:#16a34a"></span>Active et complète
                                        </span>
                                    @elseif($enabled && $timeMissing)
                                        <span class="tag" style="background:rgba(217,119,6,.12);color:#d97706">
                                            <span class="dot" style="background:#d97706"></span>Active sans heure
                                        </span>
                                    @else
                                        <span class="tag" style="background:rgba(107,114,128,.12);color:#6b7280">
                                            <span class="dot" style="background:#6b7280"></span>Inactive
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    <label class="rule-switch">
                                        <input
                                            type="checkbox"
                                            name="rules[{{ $index }}][is_enabled]"
                                            value="1"
                                            class="enable-checkbox"
                                            {{ $enabled ? 'checked' : '' }}
                                        >
                                        <span class="rule-slider"></span>
                                    </label>
                                </td>

                                <td>
                                    <input
                                        type="time"
                                        name="rules[{{ $index }}][cutoff_time]"
                                        value="{{ $vehicle['cutoff_time'] ?? '' }}"
                                        class="lco-time-inline time-input"
                                        step="60"
                                    >
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="lco-empty">
                                        <i class="fas fa-car"></i>
                                        <div style="margin-top:.6rem">Aucun véhicule trouvé pour ce partenaire.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer --}}
            <div class="lco-footer">
                <p class="hint">
                    <i class="fas fa-exclamation-triangle"></i>
                    Vérifiez les véhicules <strong>actifs sans heure</strong> avant d'enregistrer.
                </p>
                <div class="lco-footer-actions">
                    <button type="reset" class="lco-btn">
                        <i class="fas fa-rotate-left"></i> Réinitialiser
                    </button>
                    <button type="submit" class="lco-btn primary">
                        <i class="fas fa-save"></i> Enregistrer les règles
                    </button>
                </div>
            </div>

        </form>
    </div>

</div>

<script>
(() => {
    'use strict';

    const rows            = Array.from(document.querySelectorAll('.lco-row'));
    const searchInput     = document.getElementById('vehicleSearch');
    const searchClear     = document.getElementById('vehicleSearchClear');
    const checkAll        = document.getElementById('checkAll');
    const selBar          = document.getElementById('selBar');
    const selCountEl      = document.getElementById('selCount');

    // Global (visibles)
    const enableAllBtn    = document.getElementById('enableAllBtn');
    const disableAllBtn   = document.getElementById('disableAllBtn');
    const applyTimeAllBtn = document.getElementById('applyTimeAllBtn');
    const clearTimeBtn    = document.getElementById('clearVisibleTimeBtn');
    const bulkTime        = document.getElementById('bulkTime');

    // Selection
    const selEnableBtn    = document.getElementById('selEnableBtn');
    const selDisableBtn   = document.getElementById('selDisableBtn');
    const selApplyTimeBtn = document.getElementById('selApplyTimeBtn');
    const selClearTimeBtn = document.getElementById('selClearTimeBtn');
    const selDeselectBtn  = document.getElementById('selDeselectBtn');
    const selTime         = document.getElementById('selTime');

    const visibleCount    = document.getElementById('visibleCount');

    let activeFilter = 'all';

    /* ── Helpers ─────────────────────────────────────────── */
    const getVisibleRows  = () => rows.filter(r => r.style.display !== 'none');
    const getSelectedRows = () => rows.filter(r => r.querySelector('.row-check')?.checked);
    const markDirty       = row => row.classList.add('is-dirty');

    const refreshRowState = row => {
        const cb   = row.querySelector('.enable-checkbox');
        const ti   = row.querySelector('.time-input');
        const cell = row.querySelector('.rule-state-cell');

        const enabled     = !!cb?.checked;
        const timeMissing = enabled && !ti?.value;

        row.dataset.enabled     = enabled     ? '1' : '0';
        row.dataset.missingTime = timeMissing ? '1' : '0';

        if (!cell) return;
        if (enabled && !timeMissing) {
            cell.innerHTML = `<span class="tag" style="background:rgba(22,163,74,.12);color:#16a34a"><span class="dot" style="background:#16a34a"></span>Active et complète</span>`;
        } else if (enabled && timeMissing) {
            cell.innerHTML = `<span class="tag" style="background:rgba(217,119,6,.12);color:#d97706"><span class="dot" style="background:#d97706"></span>Active sans heure</span>`;
        } else {
            cell.innerHTML = `<span class="tag" style="background:rgba(107,114,128,.12);color:#6b7280"><span class="dot" style="background:#6b7280"></span>Inactive</span>`;
        }
    };

    const updateKpis = () => {
        const enabled = rows.filter(r => r.dataset.enabled     === '1').length;
        const missing = rows.filter(r => r.dataset.missingTime === '1').length;
        const kE = document.getElementById('kpiEnabledVehicles');
        const kM = document.getElementById('kpiMissingTime');
        if (kE) kE.textContent = enabled;
        if (kM) {
            kM.textContent = missing;
            kM.style.color = missing > 0 ? 'var(--color-warning,#d97706)' : 'var(--color-primary)';
        }
    };

    /* ── Selection bar ──────────────────────────────────── */
    const updateSelBar = () => {
        const selected = getSelectedRows();
        const n = selected.length;

        selBar.classList.toggle('show', n > 0);
        if (selCountEl) selCountEl.textContent = n;

        // État indeterminate du check-all
        const visRows    = getVisibleRows();
        const visChecked = visRows.filter(r => r.querySelector('.row-check')?.checked).length;
        if (checkAll) {
            checkAll.indeterminate = visChecked > 0 && visChecked < visRows.length;
            checkAll.checked       = visRows.length > 0 && visChecked === visRows.length;
        }
    };

    const setRowSelected = (row, val) => {
        const cb = row.querySelector('.row-check');
        if (cb) cb.checked = val;
        row.classList.toggle('is-selected', val);
    };

    const deselectAll = () => {
        rows.forEach(r => setRowSelected(r, false));
        updateSelBar();
    };

    /* ── Filters ────────────────────────────────────────── */
    const applyFilters = () => {
        const q = (searchInput?.value || '').trim().toLowerCase();

        rows.forEach(row => {
            const text    = row.dataset.search  || '';
            const enabled = row.dataset.enabled === '1';
            const missing = row.dataset.missingTime === '1';

            let ok = true;
            if (q && !text.includes(q))                      ok = false;
            if (activeFilter === 'enabled'      && !enabled) ok = false;
            if (activeFilter === 'disabled'     &&  enabled) ok = false;
            if (activeFilter === 'missing-time' && !missing)  ok = false;

            row.style.display = ok ? '' : 'none';
        });

        if (visibleCount) visibleCount.textContent = getVisibleRows().length;
        searchClear?.classList.toggle('show', !!(searchInput?.value));
        updateSelBar();
    };

    /* ── Apply fn to a set of rows ──────────────────────── */
    const applyToRows = (targetRows, fn) => {
        targetRows.forEach(row => {
            fn(row);
            refreshRowState(row);
            markDirty(row);
        });
        updateKpis();
        applyFilters();
    };

    /* ── Search ─────────────────────────────────────────── */
    searchInput?.addEventListener('input', applyFilters);
    searchClear?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        applyFilters();
    });

    /* ── Quick filters ──────────────────────────────────── */
    document.querySelectorAll('#quickFilters .lco-f').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('#quickFilters .lco-f').forEach(x => x.classList.remove('active'));
            el.classList.add('active');
            activeFilter = el.dataset.filter || 'all';
            applyFilters();
        });
    });

    /* ── Check-all ──────────────────────────────────────── */
    checkAll?.addEventListener('change', () => {
        getVisibleRows().forEach(r => setRowSelected(r, checkAll.checked));
        updateSelBar();
    });

    /* ── Per-row checkbox ───────────────────────────────── */
    rows.forEach(row => {
        row.querySelector('.row-check')?.addEventListener('change', e => {
            row.classList.toggle('is-selected', e.target.checked);
            updateSelBar();
        });
    });

    /* ── GLOBAL bulk actions (tous les visibles) ─────────── */
    enableAllBtn?.addEventListener('click', () =>
        applyToRows(getVisibleRows(), row => { const cb = row.querySelector('.enable-checkbox'); if (cb) cb.checked = true; })
    );
    disableAllBtn?.addEventListener('click', () =>
        applyToRows(getVisibleRows(), row => { const cb = row.querySelector('.enable-checkbox'); if (cb) cb.checked = false; })
    );
    applyTimeAllBtn?.addEventListener('click', () => {
        if (!bulkTime?.value) { alert("Choisissez d'abord une heure dans le champ ci-dessus."); return; }
        applyToRows(getVisibleRows(), row => { const i = row.querySelector('.time-input'); if (i) i.value = bulkTime.value; });
    });
    clearTimeBtn?.addEventListener('click', () =>
        applyToRows(getVisibleRows(), row => { const i = row.querySelector('.time-input'); if (i) i.value = ''; })
    );

    /* ── SELECTION bulk actions ──────────────────────────── */
    selEnableBtn?.addEventListener('click', () =>
        applyToRows(getSelectedRows(), row => { const cb = row.querySelector('.enable-checkbox'); if (cb) cb.checked = true; })
    );
    selDisableBtn?.addEventListener('click', () =>
        applyToRows(getSelectedRows(), row => { const cb = row.querySelector('.enable-checkbox'); if (cb) cb.checked = false; })
    );
    selApplyTimeBtn?.addEventListener('click', () => {
        if (!selTime?.value) { alert("Choisissez d'abord une heure dans la barre de sélection."); return; }
        applyToRows(getSelectedRows(), row => { const i = row.querySelector('.time-input'); if (i) i.value = selTime.value; });
    });
    selClearTimeBtn?.addEventListener('click', () =>
        applyToRows(getSelectedRows(), row => { const i = row.querySelector('.time-input'); if (i) i.value = ''; })
    );
    selDeselectBtn?.addEventListener('click', deselectAll);

    /* ── Per-row input tracking ─────────────────────────── */
    rows.forEach(row => {
        row.querySelectorAll('input:not(.row-check)').forEach(input => {
            const handler = () => { refreshRowState(row); markDirty(row); updateKpis(); applyFilters(); };
            input.addEventListener('change', handler);
            input.addEventListener('input',  handler);
        });
    });

    /* ── Init ───────────────────────────────────────────── */
    applyFilters();
    updateKpis();
    updateSelBar();
})();
</script>
@endsection