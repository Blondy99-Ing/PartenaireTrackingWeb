@extends('layouts.app')

@section('title', 'Contrats Lease')

@push('styles')
<style>
/* ============================================================
   LEASE CONTRACTS — Fleetra
   Héritage total du design system app.blade.php
   Cohérence : dashboard / users / lease
   ============================================================ */

/* ── KPI sticky (même pattern que lease.blade) ── */
.lc-kpi-bar {
    position: sticky;
    top: var(--navbar-h, 64px);
    z-index: var(--z-kpi, 9);
    background: var(--color-bg);
    padding: .45rem 0 .4rem;
    box-shadow: 0 4px 18px rgba(0,0,0,.07);
}
.dark-mode .lc-kpi-bar { box-shadow: 0 6px 24px rgba(0,0,0,.4); }

.lc-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: .45rem;
}
@media (max-width: 1280px) { .lc-kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 767px)  { .lc-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

.lckpi {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    padding: .42rem .62rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .4rem;
    transition: transform .15s, box-shadow .15s, border-color .15s;
    overflow: hidden;
}
.lckpi:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
    border-color: var(--color-primary-border);
}
.lckpi-left { min-width: 0; flex: 1; }
.lckpi-label {
    font-family: var(--font-display);
    font-size: .59rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}
.lckpi-value {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 1.12rem;
    line-height: 1.1;
    color: var(--color-primary);
    margin: .07rem 0 0;
    white-space: nowrap;
}
.lckpi-value.neutral { color: var(--color-text); }
.lckpi-value.success { color: var(--color-success); }
.lckpi-value.danger  { color: var(--color-error); }
.lckpi-value.warning { color: var(--color-warning); }
.lckpi-value.info    { color: var(--color-info); }

.lckpi-icon {
    width: 34px; height: 34px;
    border-radius: var(--r-md);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: .78rem;
}
.ico-orange { background: var(--color-primary-light);  color: var(--color-primary); }
.ico-green  { background: var(--color-success-bg);     color: var(--color-success); }
.ico-red    { background: var(--color-error-bg);       color: var(--color-error); }
.ico-blue   { background: var(--color-info-bg);        color: var(--color-info); }
.ico-amber  { background: var(--color-warning-bg);     color: var(--color-warning); }
.ico-grey   { background: rgba(107,114,128,.1);        color: #6b7280; }

/* ── Progress bar dans le tableau ── */
.contract-progress-wrap {
    display: flex;
    align-items: center;
    gap: .45rem;
    min-width: 110px;
}
.contract-progress-bar {
    flex: 1;
    height: 5px;
    background: var(--color-border-subtle);
    border-radius: 999px;
    overflow: hidden;
}
.contract-progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width .4s ease;
}
.contract-progress-pct {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: .62rem;
    white-space: nowrap;
    flex-shrink: 0;
}

/* ── Badges statut contrat ── */
.contract-badge {
    display: inline-flex;
    align-items: center;
    gap: .28rem;
    padding: .22rem .55rem;
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 700;
    letter-spacing: .03em;
    white-space: nowrap;
}
.cb-actif    { background: var(--color-success-bg); color: var(--color-success); border: 1px solid rgba(22,163,74,.22); }
.cb-retard   { background: var(--color-error-bg);   color: var(--color-error);   border: 1px solid rgba(220,38,38,.22); }
.cb-termine  { background: var(--color-info-bg);    color: var(--color-info);    border: 1px solid rgba(37,99,235,.22); }
.cb-suspendu { background: var(--color-warning-bg); color: var(--color-warning); border: 1px solid rgba(217,119,6,.22); }

/* ── Freq badge ── */
.freq-badge {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    padding: .2rem .5rem;
    border-radius: var(--r-sm);
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 700;
    background: var(--color-primary-light);
    color: var(--color-primary);
    border: 1px solid var(--color-primary-border);
}

/* ── Ref badge ── */
.ref-code {
    font-family: var(--font-mono, monospace);
    font-size: .72rem;
    font-weight: 700;
    color: var(--color-text);
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-sm);
    padding: .18rem .5rem;
    white-space: nowrap;
    display: inline-block;
}

/* ── Table scroll ── */
.lc-table-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.lc-table-scroll {
    overflow-x: auto;
    overflow-y: auto;
    max-height: calc(100vh - var(--navbar-h, 64px) - var(--lc-kpi-h, 90px) - 210px);
    min-height: 360px;
}
.lc-table-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
.lc-table-scroll::-webkit-scrollbar-thumb { background: var(--color-border-subtle); border-radius: 999px; }
#contractsTable { min-width: 1100px; }
#contractsTable thead { position: sticky; top: 0; z-index: 2; }
#contractsTable thead th { background: var(--color-bg-subtle) !important; white-space: nowrap; }
.dark-mode #contractsTable thead th { background: #161b22 !important; }

/* ── Actions tableau ── */
.tbl-action {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    border-radius: var(--r-sm);
    border: 1px solid var(--color-border-subtle);
    background: transparent; cursor: pointer; font-size: .72rem;
    transition: background .12s, color .12s, border-color .12s;
    flex-shrink: 0;
}
.tbl-action.edit    { color: var(--color-warning);  border-color: rgba(217,119,6,.3); }
.tbl-action.edit:hover    { background: var(--color-warning-bg); border-color: var(--color-warning); }
.tbl-action.view    { color: var(--color-info);     border-color: rgba(37,99,235,.25); }
.tbl-action.view:hover    { background: var(--color-info-bg);    border-color: var(--color-info); }
.tbl-action.suspend { color: var(--color-warning);  border-color: rgba(217,119,6,.3); }
.tbl-action.suspend:hover { background: var(--color-warning-bg); border-color: var(--color-warning); }
.tbl-action.delete  { color: var(--color-error);    border-color: rgba(220,38,38,.3); }
.tbl-action.delete:hover  { background: var(--color-error-bg);   border-color: var(--color-error); }

/* ── Footer tableau ── */
.lc-table-footer {
    display: flex; align-items: center; justify-content: space-between;
    gap: .75rem; padding: .62rem 1rem;
    border-top: 1px solid var(--color-border-subtle); flex-wrap: wrap;
}
.lc-table-info { font-family: var(--font-display); font-size: .67rem; color: var(--color-secondary-text); display: flex; align-items: center; gap: .4rem; }

.lc-pagination { display: flex; align-items: center; gap: .2rem; }
.pg-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 28px; height: 28px; padding: 0 .4rem;
    border-radius: var(--r-sm);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card); color: var(--color-text);
    font-family: var(--font-body); font-size: .72rem; cursor: pointer;
    transition: background .12s, border-color .12s, color .12s;
}
.pg-btn:hover { background: var(--color-primary-light); border-color: var(--color-primary); color: var(--color-primary); }
.pg-btn.active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; font-weight: 700; }
.pg-btn.disabled { opacity: .3; pointer-events: none; }

.perpage-select { display: inline-flex; align-items: center; gap: .35rem; font-family: var(--font-display); font-size: .65rem; color: var(--color-secondary-text); }
.perpage-select select { border: 1px solid var(--color-input-border); background: var(--color-input-bg); color: var(--color-text); border-radius: var(--r-sm); padding: .2rem .4rem; font-size: .68rem; font-family: var(--font-display); outline: none; cursor: pointer; width: auto; appearance: auto; }

/* ── Toolbar recherche ── */
.lc-toolbar { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .65rem; }
.lc-search-wrap { position: relative; flex: 1; min-width: 180px; max-width: 280px; }
.lc-search-wrap i { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); font-size: .7rem; color: var(--color-secondary-text); pointer-events: none; }
.lc-search-wrap input { width: 100%; border: 1px solid var(--color-input-border); background: var(--color-input-bg); color: var(--color-text); border-radius: var(--r-pill); padding: .32rem .6rem .32rem 2rem; font-size: .78rem; font-family: var(--font-body); outline: none; transition: border-color .15s; }
.lc-search-wrap input:focus { border-color: var(--color-primary); }

/* Filtre statut pill */
.filter-pill-wrap { position: relative; }
.filter-pill-btn {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .32rem .65rem;
    border-radius: var(--r-pill);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card); color: var(--color-text);
    font-family: var(--font-display); font-size: .65rem; font-weight: 700; letter-spacing: .02em;
    cursor: pointer; transition: border-color .15s, background .15s, color .15s;
    white-space: nowrap;
}
.filter-pill-btn:hover, .filter-pill-btn.active { border-color: var(--color-primary); background: var(--color-primary-light); color: var(--color-primary); }
.filter-pill-btn .fchev { font-size: .5rem; color: var(--color-secondary-text); transition: transform .2s; }
.filter-pill-btn.open .fchev { transform: rotate(180deg); }

.filter-dropdown-menu {
    position: absolute; top: calc(100% + 6px); left: 0; min-width: 180px;
    background: var(--color-card); border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg); box-shadow: var(--shadow-lg);
    z-index: var(--z-dropdown);
    opacity: 0; visibility: hidden; transform: translateY(-4px);
    transition: opacity .16s, transform .16s, visibility 0s .16s;
    padding: .35rem 0;
}
.filter-dropdown-menu.open { opacity: 1; visibility: visible; transform: translateY(0); transition: opacity .16s, transform .16s, visibility 0s; }
.fdrop-item {
    display: flex; align-items: center; gap: .5rem;
    padding: .42rem .85rem;
    font-family: var(--font-display); font-weight: 600; font-size: .72rem;
    color: var(--color-text); cursor: pointer; transition: background .1s, color .1s; white-space: nowrap;
}
.fdrop-item:hover { background: var(--color-sidebar-active); color: var(--color-primary); }
.fdrop-item.selected { background: var(--color-primary-light); color: var(--color-primary); }
.fdrop-item .fdot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.fdrop-item .fcheck { margin-left: auto; font-size: .6rem; color: var(--color-primary); opacity: 0; }
.fdrop-item.selected .fcheck { opacity: 1; }
.fdrop-label { padding: .3rem .85rem .1rem; font-family: var(--font-display); font-size: .55rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--color-secondary-text); opacity: .7; }

.toolbar-sep { width: 1px; height: 20px; background: var(--color-border-subtle); flex-shrink: 0; }

/* ── MODAL — Grande modale contrat ── */
.fl-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.6);
    z-index: var(--z-modal);
    display: none; align-items: center; justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(3px);
}
.fl-modal-overlay.open { display: flex; }

.fl-modal-panel {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    width: 100%; max-width: 680px;
    max-height: 92vh; overflow-y: auto;
    position: relative;
    box-shadow: var(--shadow-xl);
    transform: translateY(14px) scale(.98);
    opacity: 0;
    transition: transform .22s ease, opacity .22s ease;
}
.fl-modal-panel.visible { transform: translateY(0) scale(1); opacity: 1; }
.fl-modal-panel.sm { max-width: 420px; }

.fl-modal-header {
    display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem;
    padding: 1.2rem 1.25rem .85rem;
    border-bottom: 1px solid var(--color-border-subtle);
    position: sticky; top: 0; background: var(--color-card); z-index: 2;
}
.fl-modal-title { font-family: var(--font-display); font-size: .92rem; font-weight: 800; color: var(--color-text); margin: 0; letter-spacing: -.005em; }
.fl-modal-subtitle { font-family: var(--font-body); font-size: .72rem; color: var(--color-secondary-text); margin: .2rem 0 0; }
.fl-modal-close {
    width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
    border-radius: 50%; border: 1px solid var(--color-border-subtle);
    background: transparent; color: var(--color-secondary-text); font-size: 1rem;
    cursor: pointer; flex-shrink: 0; transition: background .12s, color .12s; line-height: 1;
}
.fl-modal-close:hover { background: var(--color-error-bg); color: var(--color-error); border-color: rgba(220,38,38,.3); }

.fl-modal-body { padding: 1rem 1.25rem; }
.fl-modal-footer {
    padding: .75rem 1.25rem 1.25rem;
    display: flex; gap: .5rem; justify-content: flex-end;
    border-top: 1px solid var(--color-border-subtle);
    position: sticky; bottom: 0; background: var(--color-card); z-index: 2;
}

/* Sections modale */
.modal-section {
    margin-bottom: 1rem;
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    overflow: hidden;
}
.modal-section-header {
    display: flex; align-items: center; gap: .5rem;
    padding: .55rem .85rem;
    background: var(--color-bg-subtle);
    border-bottom: 1px solid var(--color-border-subtle);
    font-family: var(--font-display); font-size: .65rem; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase; color: var(--color-secondary-text);
}
.modal-section-header i { color: var(--color-primary); font-size: .68rem; }
.modal-section-body { padding: .75rem .85rem; }

/* Grid form */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .6rem; }
@media (max-width: 560px) { .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; } }

.fl-form-group { margin-bottom: 0; }
.fl-form-label {
    display: block; font-family: var(--font-display); font-size: .61rem; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase; color: var(--color-secondary-text);
    margin-bottom: .3rem;
}
.fl-form-label span.opt { text-transform: none; font-weight: 400; font-family: var(--font-body); font-size: .62rem; }

/* Read-only recap */
.recap-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .45rem; }
.recap-item {
    background: var(--color-bg);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-md);
    padding: .45rem .6rem;
}
.dark-mode .recap-item { background: rgba(255,255,255,.03); }
.recap-item .rk { font-family: var(--font-display); font-size: .57rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--color-secondary-text); margin: 0; }
.recap-item .rv { font-family: var(--font-display); font-weight: 800; font-size: .88rem; color: var(--color-text); margin: .06rem 0 0; }
.recap-item .rv.primary { color: var(--color-primary); }
.recap-item .rv.success { color: var(--color-success); }
.recap-item .rv.error   { color: var(--color-error); }

/* Toggle switch option */
.option-row {
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding: .5rem 0;
    border-bottom: 1px solid var(--color-border-subtle);
}
.option-row:last-child { border-bottom: none; padding-bottom: 0; }
.option-label { font-family: var(--font-body); font-size: .8rem; color: var(--color-text); }
.option-label small { display: block; font-size: .68rem; color: var(--color-secondary-text); margin-top: 1px; }
.opt-toggle {
    position: relative; width: 38px; height: 20px;
    border-radius: 10px; background: var(--color-input-border);
    cursor: pointer; flex-shrink: 0; transition: background .25s;
}
.opt-toggle::after {
    content: ''; position: absolute; top: 2px; left: 2px;
    width: 16px; height: 16px; border-radius: 50%;
    background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3);
    transition: transform .25s;
}
.opt-toggle.on { background: var(--color-primary); }
.opt-toggle.on::after { transform: translateX(18px); }

/* Confirmation modale */
.fl-confirm-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto .7rem; font-size: 1.2rem; }
.fl-confirm-icon.danger  { background: var(--color-error-bg);   color: var(--color-error); }
.fl-confirm-icon.warning { background: var(--color-warning-bg); color: var(--color-warning); }
.fl-confirm-title { font-family: var(--font-display); font-size: .88rem; font-weight: 800; text-align: center; color: var(--color-text); margin: 0 0 .4rem; }
.fl-confirm-msg   { font-family: var(--font-body); font-size: .77rem; text-align: center; color: var(--color-secondary-text); line-height: 1.55; margin: 0; }
.fl-confirm-detail { background: var(--color-bg-subtle); border: 1px solid var(--color-border-subtle); border-radius: var(--r-md); padding: .5rem .75rem; margin-top: .65rem; font-family: var(--font-display); font-size: .7rem; font-weight: 700; text-align: center; color: var(--color-text); }

/* Buttons */
.btn-danger {
    display: inline-flex; align-items: center; gap: var(--sp-xs);
    background: var(--color-error); color: #fff; padding: .45rem 1rem;
    border-radius: var(--r-md); font-family: var(--font-display); font-weight: 700; font-size: .82rem;
    border: none; cursor: pointer; min-height: 36px; white-space: nowrap;
    transition: background .15s, transform .1s;
}
.btn-danger:hover { background: #b91c1c; transform: translateY(-1px); }

/* Amount cells */
.amount-cell { font-family: var(--font-display); font-weight: 700; font-size: .78rem; white-space: nowrap; }
.amount-cell.muted { color: var(--color-secondary-text); }
.amount-cell.good  { color: var(--color-success); }
.amount-cell.bad   { color: var(--color-error); }

/* Échéance dépassée */
.echeance-late { color: var(--color-error); font-weight: 700; }
.echeance-ok   { color: var(--color-text); }
.echeance-soon { color: var(--color-warning); font-weight: 600; }

/* Vide */
.lc-empty { text-align: center; padding: 3rem 1rem; color: var(--color-secondary-text); font-family: var(--font-display); font-size: .82rem; }
.lc-empty i { font-size: 2rem; color: var(--color-border); display: block; margin-bottom: .6rem; }

/* Chip compteur */
.count-chip {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .25rem .65rem; border-radius: var(--r-pill);
    background: var(--color-sidebar-active); border: 1px solid rgba(245,130,32,.25);
    color: var(--color-primary); font-family: var(--font-display); font-size: .63rem; font-weight: 700;
}
</style>
@endpush

@section('content')

@if(!empty($pageError))
    <div class="alert alert-danger" style="margin-bottom:.75rem;">
        {{ $pageError }}
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:.75rem;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger" style="margin-bottom:.75rem;">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:.75rem;">
        <ul style="margin:0;padding-left:1.25rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


@php
$contracts = $contracts ?? [];
$chauffeurs_list = $chauffeurs_list ?? [];
$vehicules_list = $vehicules_list ?? [];
@endphp

{{-- ═══════════════════════════════════════════════
     KPI STICKY BAR
═══════════════════════════════════════════════ --}}
<div class="lc-kpi-bar" id="lcKpiBar">
    <div class="lc-kpi-grid">

        <div class="lckpi">
            <div class="lckpi-left">
                <p class="lckpi-label">Contrats actifs</p>
                <p class="lckpi-value" id="kActive">—</p>
            </div>
            <div class="lckpi-icon ico-green"><i class="fas fa-file-contract"></i></div>
        </div>

        <div class="lckpi">
            <div class="lckpi-left">
                <p class="lckpi-label">Montant engagé</p>
                <p class="lckpi-value neutral" id="kEngaged">—</p>
            </div>
            <div class="lckpi-icon ico-grey"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>

        <div class="lckpi">
            <div class="lckpi-left">
                <p class="lckpi-label">Total collecté</p>
                <p class="lckpi-value success" id="kCollected">—</p>
            </div>
            <div class="lckpi-icon ico-green"><i class="fas fa-coins"></i></div>
        </div>

        <div class="lckpi">
            <div class="lckpi-left">
                <p class="lckpi-label">Restant dû</p>
                <p class="lckpi-value warning" id="kRemaining">—</p>
            </div>
            <div class="lckpi-icon ico-amber"><i class="fas fa-hourglass-half"></i></div>
        </div>

        <div class="lckpi">
            <div class="lckpi-left">
                <p class="lckpi-label">En retard</p>
                <p class="lckpi-value danger" id="kLate">—</p>
            </div>
            <div class="lckpi-icon ico-red"><i class="fas fa-exclamation-triangle"></i></div>
        </div>

        <div class="lckpi">
            <div class="lckpi-left">
                <p class="lckpi-label">Terminés</p>
                <p class="lckpi-value info" id="kDone">—</p>
            </div>
            <div class="lckpi-icon ico-blue"><i class="fas fa-check-double"></i></div>
        </div>

    </div>
</div>

{{-- ═══════════════════════════════════════════════
     CONTENU PRINCIPAL
═══════════════════════════════════════════════ --}}
<div style="padding-top:.75rem;">

    {{-- Header --}}
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem;">
        <div>
            <h1 style="font-family:var(--font-display);font-size:1.05rem;font-weight:800;color:var(--color-text);margin:0;display:flex;align-items:center;gap:.5rem;">
                <i class="fas fa-file-signature" style="color:var(--color-primary);font-size:.9rem;"></i>
                Contrats Lease
            </h1>
           
        </div>
        <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
            <button class="btn-secondary" onclick="window.exportContractsCsv()" title="Exporter">
                <i class="fas fa-download"></i> Export
            </button>
            <button class="btn-primary" id="btnNewContract" onclick="window.openContractModal()">
                <i class="fas fa-plus"></i> Nouveau contrat
            </button>
        </div>
    </div>

    {{-- Onglets navigation (cohérence avec users.blade) --}}
    <div style="border-bottom:1px solid var(--color-border-subtle);padding-bottom:.7rem;margin-bottom:.8rem;">
        <nav style="display:flex;align-items:center;gap:.25rem;flex-wrap:wrap;">
            <a href="#" class="nav-tab active" style="display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .85rem;border-radius:.5rem;font-family:var(--font-display);font-size:.67rem;font-weight:600;letter-spacing:.02em;text-decoration:none;color:var(--color-primary);background:var(--color-primary-light);border:1px solid var(--color-primary-border);">
                <i class="fas fa-file-contract"></i> Contrats
            </a>
            <a href="#" style="display:inline-flex;align-items:center;gap:.4rem;padding:.42rem .85rem;border-radius:.5rem;font-family:var(--font-display);font-size:.67rem;font-weight:600;letter-spacing:.02em;text-decoration:none;color:var(--color-secondary-text);border:1px solid transparent;transition:all .15s;" onmouseover="this.style.color='var(--color-primary)';this.style.background='var(--color-primary-light)';this.style.borderColor='var(--color-border-subtle)';" onmouseout="this.style.color='var(--color-secondary-text)';this.style.background='transparent';this.style.borderColor='transparent';">
                <i class="fas fa-money-bill-wave"></i> Paiements
            </a>
            
        </nav>
    </div>

    {{-- ── TOOLBAR ── --}}
    <div class="lc-toolbar">

        <div class="lc-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="contractSearch" placeholder="Réf, chauffeur, véhicule…" autocomplete="off">
        </div>

        <div class="toolbar-sep"></div>

        {{-- Filtre statut --}}
        <div class="filter-pill-wrap" id="fw-statut">
            <button class="filter-pill-btn" id="fpb-statut" onclick="lcToggleDrop('statut')">
                <i class="fas fa-tag" style="font-size:.6rem;"></i>
                Statut
                <i class="fas fa-chevron-down fchev"></i>
            </button>
            <div class="filter-dropdown-menu" id="fdrop-statut">
                <div class="fdrop-label">Filtrer par statut</div>
                <div class="fdrop-item selected" data-val="all"      onclick="lcSetFilter('statut','all',this)">
                    <span class="fdot" style="background:var(--color-border)"></span>Tous<i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-val="actif"    onclick="lcSetFilter('statut','actif',this)">
                    <span class="fdot" style="background:var(--color-success)"></span>Actifs<i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-val="retard"   onclick="lcSetFilter('statut','retard',this)">
                    <span class="fdot" style="background:var(--color-error)"></span>En retard<i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-val="termine"  onclick="lcSetFilter('statut','termine',this)">
                    <span class="fdot" style="background:var(--color-info)"></span>Terminés<i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-val="suspendu" onclick="lcSetFilter('statut','suspendu',this)">
                    <span class="fdot" style="background:var(--color-warning)"></span>Suspendus<i class="fas fa-check fcheck"></i>
                </div>
            </div>
        </div>

        {{-- Filtre fréquence --}}
        <div class="filter-pill-wrap" id="fw-freq">
            <button class="filter-pill-btn" id="fpb-freq" onclick="lcToggleDrop('freq')">
                <i class="fas fa-sync-alt" style="font-size:.6rem;"></i>
                Fréquence
                <i class="fas fa-chevron-down fchev"></i>
            </button>
            <div class="filter-dropdown-menu" id="fdrop-freq">
                <div class="fdrop-label">Fréquence paiement</div>
                <div class="fdrop-item selected" data-val="all"           onclick="lcSetFilter('freq','all',this)">Toutes<i class="fas fa-check fcheck"></i></div>
                <div class="fdrop-item"           data-val="journalier"    onclick="lcSetFilter('freq','journalier',this)">Journalier<i class="fas fa-check fcheck"></i></div>
                <div class="fdrop-item"           data-val="hebdomadaire"  onclick="lcSetFilter('freq','hebdomadaire',this)">Hebdomadaire<i class="fas fa-check fcheck"></i></div>
                <div class="fdrop-item"           data-val="mensuel"       onclick="lcSetFilter('freq','mensuel',this)">Mensuel<i class="fas fa-check fcheck"></i></div>
            </div>
        </div>

        <div class="toolbar-sep"></div>

        <span class="count-chip" id="contractsCount">
            <i class="fas fa-file-contract" style="font-size:.58rem;"></i>
            <span id="contractsCountVal">10</span> contrat(s)
        </span>

    </div>

    {{-- ── TABLEAU ── --}}
    <div class="lc-table-card">
        <div class="lc-table-scroll">
            <table class="ui-table" id="contractsTable">
                <thead>
                    <tr>
                        <th style="cursor:pointer;" onclick="lcSort('ref')">
                            Référence <i class="fas fa-sort" style="font-size:.5rem;opacity:.4;"></i>
                        </th>
                        <th style="cursor:pointer;" onclick="lcSort('chauffeur')">
                            Chauffeur <i class="fas fa-sort" style="font-size:.5rem;opacity:.4;"></i>
                        </th>
                        <th>Véhicule</th>
                        <th style="text-align:right;">Montant total</th>
                        <th>Progression</th>
                        <th style="text-align:right;">Total payé</th>
                        <th style="text-align:right;">Restant</th>
                        <th>Versement</th>
                        <th>Fréquence</th>
                        <th>Prochaine échéance</th>
                        <th>Statut</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="contractsTableBody">
                    {{-- Rempli par JS --}}
                </tbody>
            </table>
        </div>

        <div class="lc-table-footer">
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                <div class="lc-table-info">
                    <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:.62rem;"></i>
                    <span id="lcTableInfo">—</span>
                </div>
                <div class="perpage-select">
                    Afficher
                    <select id="lcPerPage" onchange="lcSetPerPage(this.value)">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    lignes
                </div>
            </div>
            <div class="lc-pagination" id="lcPagination"></div>
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════════
     MODALE CRÉER / ÉDITER CONTRAT
═══════════════════════════════════════════════ --}}
<div id="modalContract" class="fl-modal-overlay" aria-modal="true" role="dialog">
    <div class="fl-modal-panel" id="modalContractPanel">

        {{-- Header sticky --}}
        <div class="fl-modal-header">
            <div>
                <h2 class="fl-modal-title" id="contractModalTitle">
                    <i class="fas fa-file-signature" style="color:var(--color-primary);font-size:.82rem;margin-right:.35rem;"></i>
                    Nouveau contrat
                </h2>
                <p class="fl-modal-subtitle" id="contractModalSub">Définissez les conditions du contrat de lease</p>
            </div>
            <button type="button" class="fl-modal-close" onclick="lcCloseModal('modalContract')">&times;</button>
        </div>

        <form id="contractForm" method="POST" action="{{ route('lease.contrat.store') }}">
            @csrf

            <div class="fl-modal-body">

                {{-- ── Section Affectation ── --}}
                <div class="modal-section">
                    <div class="modal-section-header">
                        <i class="fas fa-link"></i>
                        Affectation
                    </div>
                    <div class="modal-section-body">
                        <div class="form-grid-2" style="margin-bottom:.6rem;">
                            <div class="fl-form-group">
                                <label class="fl-form-label">Chauffeur <span style="color:var(--color-error);">*</span></label>
                                <select id="ctChauffeur" name="chauffeur" class="ui-input-style" style="appearance:auto;" required>
                                    <option value="">Sélectionner un chauffeur…</option>
                                    @foreach(($chauffeurs_list ?? []) as $ch)
                                        @php
                                            $chId = is_array($ch) ? ($ch['id'] ?? null) : ($ch->id ?? null);
                                            $chLabel = is_array($ch)
                                                ? ($ch['label'] ?? $ch['nom_complet'] ?? $ch['email'] ?? ('Chauffeur #' . $chId))
                                                : ($ch->label ?? $ch->nom_complet ?? $ch->email ?? ('Chauffeur #' . $chId));
                                        @endphp
                                        @if($chId)
                                            <option value="{{ $chId }}" @selected(old('chauffeur') == $chId)>{{ $chLabel }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @if(empty($chauffeurs_list))
                                    <small style="color:var(--color-error);font-size:.68rem;">Aucun chauffeur recouvrement trouvé.</small>
                                @endif
                            </div>
                            <div class="fl-form-group">
                                <label class="fl-form-label">Véhicule <span style="color:var(--color-error);">*</span></label>
                                <select id="ctVehicule" name="immatriculation" class="ui-input-style" style="appearance:auto;" required>
                                    <option value="">Sélectionner un véhicule…</option>
                                    @foreach(($vehicules_list ?? []) as $veh)
                                        @php
                                            $vehId = is_array($veh) ? ($veh['id'] ?? null) : ($veh->id ?? null);
                                            $immat = is_array($veh) ? ($veh['immatriculation'] ?? '') : ($veh->immatriculation ?? '');
                                            $vehLabel = is_array($veh) ? ($veh['label'] ?? $immat) : ($veh->label ?? $immat);
                                        @endphp
                                        @if($vehId && $immat)
                                            <option value="{{ $immat }}" data-vehicle-id="{{ $vehId }}" @selected(old('immatriculation') == $immat)>{{ $vehLabel }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <input type="hidden" id="ctVehicleId" name="vehicle_id" value="{{ old('vehicle_id') }}">
                                @if(empty($vehicules_list))
                                    <small style="color:var(--color-error);font-size:.68rem;">Aucun véhicule local trouvé.</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Section Durée ── --}}
                <div class="modal-section">
                    <div class="modal-section-header">
                        <i class="fas fa-calendar-alt"></i>
                        Durée du contrat
                    </div>
                    <div class="modal-section-body">
                        <div class="form-grid-3">
                            <div class="fl-form-group">
                                <label class="fl-form-label">Date de début <span style="color:var(--color-error);">*</span></label>
                                <input type="date" id="ctDateDebut" name="date_debut" class="ui-input-style" value="{{ old('date_debut') }}" required>
                            </div>
                            <div class="fl-form-group">
                                <label class="fl-form-label">Date de fin prévue <span style="color:var(--color-error);">*</span></label>
                                <input type="date" id="ctDateFin" name="date_fin" class="ui-input-style" value="{{ old('date_fin') }}" required>
                            </div>
                            <div class="fl-form-group">
                                <label class="fl-form-label">Prochaine échéance <span style="color:var(--color-error);">*</span></label>
                                <input type="date" id="ctPremEcheance" name="prochaine_echeance" class="ui-input-style" value="{{ old('prochaine_echeance') }}" required>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Section Financier ── --}}
                <div class="modal-section">
                    <div class="modal-section-header">
                        <i class="fas fa-coins"></i>
                        Conditions financières
                    </div>
                    <div class="modal-section-body">
                        <div class="form-grid-3" style="margin-bottom:.6rem;">
                            <div class="fl-form-group">
                                <label class="fl-form-label">Montant total contrat (XAF) <span style="color:var(--color-error);">*</span></label>
                                <input type="number" id="ctMontantTotal" name="montant_total" class="ui-input-style" placeholder="1500000" min="1" step="1000" value="{{ old('montant_total') }}" oninput="lcCalcContractPreview()" required>
                            </div>
                            <div class="fl-form-group">
                                <label class="fl-form-label">Versement régulier (XAF) <span style="color:var(--color-error);">*</span></label>
                                <input type="number" id="ctVersement" name="montant_par_paiement" class="ui-input-style" placeholder="25000" min="1" step="500" value="{{ old('montant_par_paiement', '25000') }}" oninput="lcCalcContractPreview()" required>
                            </div>
                            <div class="fl-form-group">
                                <label class="fl-form-label">Fréquence <span style="color:var(--color-error);">*</span></label>
                                <select id="ctFrequence" name="frequence" class="ui-input-style" style="appearance:auto;" onchange="lcCalcContractPreview()" required>
                                    <option value="">—</option>
                                    <option value="JOURNALIER" @selected(old('frequence') === 'JOURNALIER')>JOURNALIER</option>
                                    <option value="HEBDOMADAIRE" @selected(old('frequence', 'HEBDOMADAIRE') === 'HEBDOMADAIRE')>HEBDOMADAIRE</option>
                                    <option value="MENSUEL" @selected(old('frequence') === 'MENSUEL')>MENSUEL</option>
                                </select>
                            </div>
                        </div>

                        <div id="ctCalcPreview" style="width:100%;background:var(--color-primary-light);border:1px solid var(--color-primary-border);border-radius:var(--r-md);padding:.45rem .65rem;font-family:var(--font-display);font-size:.7rem;color:var(--color-primary);font-weight:700;display:none;">
                            <i class="fas fa-calculator" style="margin-right:.3rem;"></i>
                            <span id="ctCalcText">—</span>
                        </div>
                    </div>
                </div>

                {{-- ── Section Options ── --}}
                <div class="modal-section">
                    <div class="modal-section-header">
                        <i class="fas fa-sliders-h"></i>
                        Options & configuration
                    </div>
                    <div class="modal-section-body">
                        <div class="option-row">
                            <div class="option-label">
                                Coupure automatique
                                <small>Créer ou mettre à jour la règle de coupure locale pour ce véhicule</small>
                            </div>

                            <label style="display:flex;align-items:center;gap:.55rem;cursor:pointer;">
                                <input type="checkbox" id="ctCoupureAuto" name="coupure_auto" value="1" @checked(old('coupure_auto'))>
                                <span style="font-size:.75rem;color:var(--color-secondary-text);">Activer</span>
                            </label>
                        </div>

                        <div id="ctCutoffTimeWrap" style="margin-top:.65rem;display:none;">
                            <label class="fl-form-label" style="display:block;margin-bottom:.3rem;">Heure de coupure</label>
                            <input type="time" id="ctCutoffTime" name="cutoff_time" class="ui-input-style" value="{{ old('cutoff_time', '12:00') }}" style="max-width:180px;">
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer sticky --}}
            <div class="fl-modal-footer">
                <button type="button" class="btn-secondary" onclick="lcCloseModal('modalContract')">Annuler</button>
                <button type="button" class="btn-primary" id="ctSubmitBtn" onclick="lcSubmitContract()">
                    <i class="fas fa-plus"></i> Créer le contrat
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════
     MODALE CONFIRMATION SUPPRESSION
═══════════════════════════════════════════════ --}}
<div id="modalDelete" class="fl-modal-overlay" aria-modal="true" role="dialog">
    <div class="fl-modal-panel sm" id="modalDeletePanel">
        <div class="fl-modal-header">
            <h2 class="fl-modal-title">Supprimer le contrat</h2>
            <button class="fl-modal-close" onclick="lcCloseModal('modalDelete')">&times;</button>
        </div>
        <div class="fl-modal-body" style="text-align:center;padding-top:1.2rem;">
            <div class="fl-confirm-icon danger"><i class="fas fa-trash-alt"></i></div>
            <p class="fl-confirm-title">Confirmer la suppression ?</p>
            <p class="fl-confirm-msg">Cette action est irréversible. Tout l'historique de paiement associé sera conservé mais le contrat sera définitivement supprimé.</p>
            <div class="fl-confirm-detail" id="deleteContractDetail">—</div>
        </div>
        <div class="fl-modal-footer">
            <button class="btn-secondary" onclick="lcCloseModal('modalDelete')">Annuler</button>
            <button class="btn-danger" onclick="lcConfirmDelete()"><i class="fas fa-trash-alt"></i> Supprimer</button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════
     MODALE CHANGEMENT STATUT
═══════════════════════════════════════════════ --}}
<div id="modalStatus" class="fl-modal-overlay" aria-modal="true" role="dialog">
    <div class="fl-modal-panel sm" id="modalStatusPanel">
        <div class="fl-modal-header">
            <h2 class="fl-modal-title">Changer le statut</h2>
            <button class="fl-modal-close" onclick="lcCloseModal('modalStatus')">&times;</button>
        </div>
        <div class="fl-modal-body">
            <p style="font-family:var(--font-body);font-size:.8rem;color:var(--color-secondary-text);margin:0 0 .85rem;">
                Sélectionnez le nouveau statut pour le contrat :
            </p>
            <div class="fl-confirm-detail" id="statusContractDetail" style="margin-bottom:.85rem;">—</div>
            <div class="fl-form-group">
                <label class="fl-form-label">Nouveau statut</label>
                <select id="newStatusSelect" class="ui-input-style" style="appearance:auto;">
                    <option value="actif">✅ Actif</option>
                    <option value="suspendu">⏸ Suspendu</option>
                    <option value="termine">✔️ Terminé</option>
                    <option value="retard">⚠️ En retard</option>
                </select>
            </div>
            <div class="fl-form-group" style="margin-top:.5rem;">
                <label class="fl-form-label">Raison <span class="opt">(optionnel)</span></label>
                <input type="text" id="statusReason" class="ui-input-style" placeholder="Motif du changement…">
            </div>
        </div>
        <div class="fl-modal-footer">
            <button class="btn-secondary" onclick="lcCloseModal('modalStatus')">Annuler</button>
            <button class="btn-primary" onclick="lcConfirmStatus()"><i class="fas fa-check"></i> Appliquer</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    /* ── Données statiques (PHP → JS) ── */
    const RAW = @json($contracts ?? []);
    let filtered = [...RAW];
    let currentPage = 1;
    let perPage = 10;
    let sortKey = 'ref';
    let sortDir = 'asc';
    let filters = { statut: 'all', freq: 'all' };
    let searchQ  = '';
    let pendingId = null;
    let editMode  = false;
    let editId    = null;
    let autoRefIdx = 1; // pour générer des refs auto

    /* ── Helpers ── */
    const fmt   = n  => Number(n || 0).toLocaleString('fr-FR') + ' F';
    const fmtK  = n  => (n >= 1000000 ? (n/1000000).toFixed(2) + ' M' : n >= 1000 ? (n/1000).toFixed(0) + ' k' : String(n)) + ' F';
    const esc   = s  => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    const pct   = (p, t) => t > 0 ? Math.min(100, Math.round(p / t * 100)) : 0;

    const STATUS_MAP = {
        actif:    { cls: 'cb-actif',    icon: 'fa-circle',             label: 'Actif' },
        retard:   { cls: 'cb-retard',   icon: 'fa-exclamation-circle', label: 'En retard' },
        termine:  { cls: 'cb-termine',  icon: 'fa-check-circle',       label: 'Terminé' },
        suspendu: { cls: 'cb-suspendu', icon: 'fa-pause-circle',       label: 'Suspendu' },
    };
    const FREQ_MAP = {
        journalier:   { icon: 'fa-sun',      label: 'Journalier' },
        hebdomadaire: { icon: 'fa-calendar-week', label: 'Hebdo' },
        mensuel:      { icon: 'fa-calendar', label: 'Mensuel' },
    };
    const PROGRESS_COLORS = {
        actif: '#16a34a', retard: '#dc2626', termine: '#2563eb', suspendu: '#d97706',
    };

    /* ════════════════════════
       KPI
    ════════════════════════ */
    function renderKPIs() {
        const data = filtered;
        const actifs    = data.filter(c => c.statut === 'actif').length;
        const retards   = data.filter(c => c.statut === 'retard').length;
        const termines  = data.filter(c => c.statut === 'termine').length;
        const engaged   = data.reduce((s, c) => s + (c.montant_total  || 0), 0);
        const collected = data.reduce((s, c) => s + (c.total_paye     || 0), 0);
        const remaining = data.filter(c => c.statut !== 'termine').reduce((s, c) => s + Math.max(0, (c.montant_total || 0) - (c.total_paye || 0)), 0);

        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('kActive',    actifs);
        set('kEngaged',   fmtK(engaged));
        set('kCollected', fmtK(collected));
        set('kRemaining', fmtK(remaining));
        set('kLate',      retards);
        set('kDone',      termines);

        const cntEl = document.getElementById('contractsCountVal');
        if (cntEl) cntEl.textContent = data.length;
    }

    /* ════════════════════════
       FILTRAGE
    ════════════════════════ */
    function applyFilters() {
        let data = [...RAW];

        if (filters.statut !== 'all') data = data.filter(c => c.statut === filters.statut);
        if (filters.freq   !== 'all') data = data.filter(c => c.frequence === filters.freq);

        if (searchQ.trim()) {
            const q = searchQ.toLowerCase();
            data = data.filter(c =>
                (c.ref       || '').toLowerCase().includes(q) ||
                (c.chauffeur || '').toLowerCase().includes(q) ||
                (c.vehicule  || '').toLowerCase().includes(q) ||
                (c.partenaire|| '').toLowerCase().includes(q)
            );
        }

        data.sort((a, b) => {
            let va = a[sortKey] ?? '', vb = b[sortKey] ?? '';
            if (typeof va === 'number') return sortDir === 'asc' ? va - vb : vb - va;
            return sortDir === 'asc' ? String(va).localeCompare(String(vb)) : String(vb).localeCompare(String(va));
        });

        filtered = data;
        currentPage = 1;
        renderTable();
        renderKPIs();
        renderPagination();
    }

    /* ════════════════════════
       TABLEAU
    ════════════════════════ */
    function renderTable() {
        const tbody = document.getElementById('contractsTableBody');
        if (!tbody) return;

        const start = (currentPage - 1) * perPage;
        const end   = start + perPage;
        const page  = filtered.slice(start, end);

        const info = document.getElementById('lcTableInfo');
        if (info) info.textContent = `${filtered.length} contrat${filtered.length !== 1 ? 's' : ''} (${start+1}–${Math.min(end, filtered.length)})`;

        if (!page.length) {
            tbody.innerHTML = `<tr><td colspan="12"><div class="lc-empty"><i class="fas fa-file-contract"></i>Aucun contrat ne correspond aux filtres.</div></td></tr>`;
            return;
        }

        const today = new Date().toISOString().slice(0,10);

        tbody.innerHTML = page.map(c => {
            const progress  = pct(c.total_paye, c.montant_total);
            const restant   = Math.max(0, (c.montant_total || 0) - (c.total_paye || 0));
            const sm = STATUS_MAP[c.statut] || STATUS_MAP.actif;
            const fm = FREQ_MAP[c.frequence] || { icon:'fa-question', label: c.frequence };
            const progColor = PROGRESS_COLORS[c.statut] || '#16a34a';

            let echClass = 'echeance-ok';
            let echText  = c.prochaine_echeance || '—';
            if (c.prochaine_echeance) {
                if (c.prochaine_echeance < today)         echClass = 'echeance-late';
                else if (c.prochaine_echeance === today)  echClass = 'echeance-soon';
            }
            if (c.statut === 'termine') echText = '✔ Soldé';

            return `
<tr data-id="${c.id}">
    <td><span class="ref-code">${esc(c.ref)}</span></td>
    <td style="white-space:nowrap;">
        <div style="font-weight:600;font-size:.8rem;">${esc(c.chauffeur)}</div>
        <div style="font-size:.68rem;color:var(--color-secondary-text);margin-top:1px;font-family:var(--font-mono,monospace);">${esc(c.phone_ch)}</div>
    </td>
    <td style="white-space:nowrap;">
        <div style="font-family:var(--font-mono,monospace);font-weight:700;font-size:.72rem;">${esc(c.vehicule)}</div>
        <div style="font-size:.65rem;color:var(--color-secondary-text);margin-top:1px;">${esc(c.marque)}</div>
    </td>
    <td style="text-align:right;"><span class="amount-cell">${fmt(c.montant_total)}</span></td>
    <td>
        <div class="contract-progress-wrap">
            <div class="contract-progress-bar">
                <div class="contract-progress-fill" style="width:${progress}%;background:${progColor};"></div>
            </div>
            <span class="contract-progress-pct" style="color:${progColor};">${progress}%</span>
        </div>
    </td>
    <td style="text-align:right;"><span class="amount-cell good">${fmt(c.total_paye)}</span></td>
    <td style="text-align:right;"><span class="amount-cell bad">${restant > 0 ? fmt(restant) : '<span style="color:var(--color-success)">Soldé</span>'}</span></td>
    <td><span style="font-family:var(--font-display);font-weight:700;font-size:.75rem;white-space:nowrap;">${fmt(c.versement)}</span></td>
    <td><span class="freq-badge"><i class="fas ${fm.icon}" style="font-size:.55rem;"></i> ${esc(fm.label)}</span></td>
    <td><span class="${echClass}" style="font-size:.75rem;font-family:var(--font-body);">${esc(echText)}</span></td>
    <td><span class="contract-badge ${sm.cls}"><i class="fas ${sm.icon}"></i> ${sm.label}</span></td>
    <td>
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:.2rem;">
            <button class="tbl-action view"    onclick="lcOpenEdit(${c.id})"      title="Voir / éditer le contrat"><i class="fas fa-pen"></i></button>
            <button class="tbl-action suspend" onclick="lcOpenStatus(${c.id})"    title="Changer le statut"><i class="fas fa-exchange-alt"></i></button>
            <button class="tbl-action delete"  onclick="lcOpenDelete(${c.id})"    title="Supprimer"><i class="fas fa-trash"></i></button>
        </div>
    </td>
</tr>`;
        }).join('');
    }

    /* ════════════════════════
       PAGINATION
    ════════════════════════ */
    function renderPagination() {
        const total = Math.ceil(filtered.length / perPage) || 1;
        const pag   = document.getElementById('lcPagination');
        if (!pag) return;

        let html = `<button class="pg-btn${currentPage===1?' disabled':''}" onclick="lcGoPage(${currentPage-1})">‹</button>`;
        for (let i = 1; i <= total; i++) {
            if (total > 7 && i !== 1 && i !== total && Math.abs(i - currentPage) > 2) {
                if (i === 2 || i === total-1) html += `<span style="font-size:.7rem;padding:0 .15rem;color:var(--color-secondary-text);">…</span>`;
                continue;
            }
            html += `<button class="pg-btn${i===currentPage?' active':''}" onclick="lcGoPage(${i})">${i}</button>`;
        }
        html += `<button class="pg-btn${currentPage===total?' disabled':''}" onclick="lcGoPage(${currentPage+1})">›</button>`;
        pag.innerHTML = html;
    }

    window.lcGoPage = n => {
        const total = Math.ceil(filtered.length / perPage) || 1;
        if (n < 1 || n > total) return;
        currentPage = n;
        renderTable();
        renderPagination();
        document.querySelector('.lc-table-scroll')?.scrollTo({ top:0, behavior:'smooth' });
    };

    window.lcSetPerPage = v => { perPage = parseInt(v,10); currentPage=1; renderTable(); renderPagination(); };

    /* ════════════════════════
       TRI
    ════════════════════════ */
    window.lcSort = key => {
        if (sortKey === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        else { sortKey = key; sortDir = 'asc'; }
        applyFilters();
    };

    /* ════════════════════════
       FILTRES DROPDOWN
    ════════════════════════ */
    window.lcToggleDrop = name => {
        const menu = document.getElementById('fdrop-' + name);
        const btn  = document.getElementById('fpb-' + name);
        if (!menu || !btn) return;
        const isOpen = menu.classList.contains('open');
        document.querySelectorAll('.filter-dropdown-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.filter-pill-btn.open').forEach(b => b.classList.remove('open'));
        if (!isOpen) { menu.classList.add('open'); btn.classList.add('open'); }
    };
    document.addEventListener('click', e => {
        if (!e.target.closest('.filter-pill-wrap')) {
            document.querySelectorAll('.filter-dropdown-menu.open').forEach(m => m.classList.remove('open'));
            document.querySelectorAll('.filter-pill-btn.open').forEach(b => b.classList.remove('open'));
        }
    });
    window.lcSetFilter = (name, val, el) => {
        filters[name] = val;
        const menu = document.getElementById('fdrop-' + name);
        menu?.querySelectorAll('.fdrop-item').forEach(i => i.classList.toggle('selected', i.dataset.val === val));
        document.getElementById('fpb-' + name)?.classList.toggle('active', val !== 'all');
        applyFilters();
    };

    /* Recherche */
    document.getElementById('contractSearch')?.addEventListener('input', function () {
        searchQ = this.value;
        applyFilters();
    });

    /* ════════════════════════
       MODALES
    ════════════════════════ */
    window.lcOpenModal = id => {
        const ov = document.getElementById(id);
        const pn = ov?.querySelector('.fl-modal-panel');
        if (!ov) return;
        ov.classList.add('open');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => requestAnimationFrame(() => pn?.classList.add('visible')));
    };
    window.lcCloseModal = id => {
        const ov = document.getElementById(id);
        const pn = ov?.querySelector('.fl-modal-panel');
        pn?.classList.remove('visible');
        document.body.style.overflow = '';
        setTimeout(() => ov?.classList.remove('open'), 220);
    };
    ['modalContract','modalDelete','modalStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', function(e) {
            if (e.target === this) window.lcCloseModal(id);
        });
    });

    /* ── Modale contrat : CRÉER ── */
    window.openContractModal = () => {
        editMode = false;
        editId = null;

        const today = new Date().toISOString().slice(0,10);
        const setVal = (id, v) => {
            const el = document.getElementById(id);
            if (el) el.value = v;
        };

        setVal('ctChauffeur', '');
        setVal('ctVehicule', '');
        setVal('ctVehicleId', '');
        setVal('ctDateDebut', today);
        setVal('ctDateFin', '');
        setVal('ctMontantTotal', '');
        setVal('ctVersement', '25000');
        setVal('ctFrequence', 'HEBDOMADAIRE');
        setVal('ctPremEcheance', today);
        setVal('ctCutoffTime', '12:00');

        const cutoffCheckbox = document.getElementById('ctCoupureAuto');
        if (cutoffCheckbox) cutoffCheckbox.checked = false;

        const cutoffWrap = document.getElementById('ctCutoffTimeWrap');
        if (cutoffWrap) cutoffWrap.style.display = 'none';

        const title = document.getElementById('contractModalTitle');
        if (title) {
            title.innerHTML = '<i class="fas fa-file-signature" style="color:var(--color-primary);font-size:.82rem;margin-right:.35rem;"></i>Nouveau contrat';
        }

        const sub = document.getElementById('contractModalSub');
        if (sub) sub.textContent = 'Définissez les conditions du contrat de lease';

        const btn = document.getElementById('ctSubmitBtn');
        if (btn) btn.innerHTML = '<i class="fas fa-plus"></i> Créer le contrat';

        const preview = document.getElementById('ctCalcPreview');
        if (preview) preview.style.display = 'none';

        window.lcOpenModal('modalContract');
    };

    /* ── Modale contrat : ÉDITER ──
       L’API recouvrement fournie ici ne précise pas encore l’endpoint update.
       On garde donc l’action edit non destructive : ouverture en lecture/pré-remplissage basique. */
    window.lcOpenEdit = id => {
        const c = RAW.find(x => x.id === id);
        if (!c) return;

        editMode = true;
        editId = id;

        const setVal = (elId, v) => {
            const el = document.getElementById(elId);
            if (el) el.value = v || '';
        };

        setVal('ctChauffeur', c.chauffeur || c.chauffeur_id || '');
        setVal('ctVehicule', c.immatriculation || c.vehicule || '');
        setVal('ctDateDebut', c.date_debut || '');
        setVal('ctDateFin', c.date_fin || c.date_fin_prevue || '');
        setVal('ctMontantTotal', parseInt(c.montant_total || 0, 10) || '');
        setVal('ctVersement', parseInt(c.montant_par_paiement || c.versement || 0, 10) || '');
        setVal('ctFrequence', (c.frequence || '').toString().toUpperCase());
        setVal('ctPremEcheance', c.prochaine_echeance || '');

        const vehicleSelect = document.getElementById('ctVehicule');
        const vehicleIdInput = document.getElementById('ctVehicleId');
        if (vehicleSelect && vehicleIdInput) {
            const option = vehicleSelect.options[vehicleSelect.selectedIndex];
            vehicleIdInput.value = option?.dataset?.vehicleId || '';
        }

        const cutoffCheckbox = document.getElementById('ctCoupureAuto');
        if (cutoffCheckbox) cutoffCheckbox.checked = false;

        const cutoffWrap = document.getElementById('ctCutoffTimeWrap');
        if (cutoffWrap) cutoffWrap.style.display = 'none';

        const title = document.getElementById('contractModalTitle');
        if (title) {
            title.innerHTML = '<i class="fas fa-eye" style="color:var(--color-primary);font-size:.82rem;margin-right:.35rem;"></i>Détail du contrat';
        }

        const sub = document.getElementById('contractModalSub');
        if (sub) sub.textContent = `Contrat #${c.id || ''}`;

        const btn = document.getElementById('ctSubmitBtn');
        if (btn) btn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';

        lcCalcContractPreview();
        window.lcOpenModal('modalContract');
    };

    /* Calcul aperçu durée */
    window.lcCalcContractPreview = () => {
        const total = parseInt(document.getElementById('ctMontantTotal')?.value || 0, 10);
        const vers = parseInt(document.getElementById('ctVersement')?.value || 0, 10);
        const freq = document.getElementById('ctFrequence')?.value;
        const preview = document.getElementById('ctCalcPreview');
        const text = document.getElementById('ctCalcText');

        if (!preview || !text) return;

        if (total > 0 && vers > 0 && freq) {
            const nb = Math.ceil(total / vers);

            const freqLabel = {
                JOURNALIER: 'jour(s)',
                HEBDOMADAIRE: 'semaine(s)',
                MENSUEL: 'mois'
            }[freq] || 'échéance(s)';

            text.textContent = `≈ ${nb} ${freqLabel} pour solder ${Number(total).toLocaleString('fr-FR')} F`;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    };

    /* Ancienne génération de référence : conservée en no-op pour ne pas casser les handlers existants. */
    window.lcUpdateContractRef = () => {};

    /* Submit (simulation) */
    window.lcSubmitContract = () => {
        const form = document.getElementById('contractForm');

        const chId = document.getElementById('ctChauffeur')?.value;
        const vehImmat = document.getElementById('ctVehicule')?.value;
        const vehId = document.getElementById('ctVehicleId')?.value;
        const total = parseInt(document.getElementById('ctMontantTotal')?.value || 0, 10);
        const vers = parseInt(document.getElementById('ctVersement')?.value || 0, 10);
        const freq = document.getElementById('ctFrequence')?.value;
        const dateDebut = document.getElementById('ctDateDebut')?.value;
        const dateFin = document.getElementById('ctDateFin')?.value;
        const echeance = document.getElementById('ctPremEcheance')?.value;

        if (!form) return;
        if (!chId) { alert('Sélectionnez un chauffeur.'); return; }
        if (!vehImmat || !vehId) { alert('Sélectionnez un véhicule.'); return; }
        if (total <= 0) { alert('Le montant total doit être > 0.'); return; }
        if (vers <= 0) { alert('Le versement doit être > 0.'); return; }
        if (!freq) { alert('Choisissez une fréquence.'); return; }
        if (!dateDebut) { alert('La date de début est obligatoire.'); return; }
        if (!dateFin) { alert('La date de fin est obligatoire.'); return; }
        if (!echeance) { alert('La prochaine échéance est obligatoire.'); return; }

        form.submit();
    };

    /* ── Modale suppression ── */
    window.lcOpenDelete = id => {
        const c = RAW.find(x => x.id === id);
        if (!c) return;
        pendingId = id;
        const det = document.getElementById('deleteContractDetail');
        if (det) det.textContent = `${c.ref} — ${c.chauffeur} — ${c.vehicule}`;
        window.lcOpenModal('modalDelete');
    };
    window.lcConfirmDelete = () => {
        const c = RAW.find(x => x.id === pendingId);
        const idx = RAW.indexOf(c);
        if (idx > -1) RAW.splice(idx, 1);
        window.lcCloseModal('modalDelete');
        if (window.showToast) window.showToast('Contrat supprimé', `Contrat ${c?.ref || ''} supprimé`, 'error');
        applyFilters();
    };

    /* ── Modale changement statut ── */
    window.lcOpenStatus = id => {
        const c = RAW.find(x => x.id === id);
        if (!c) return;
        pendingId = id;
        const det = document.getElementById('statusContractDetail');
        if (det) det.textContent = `${c.ref} — ${c.chauffeur} — ${STATUS_MAP[c.statut]?.label || c.statut}`;
        const sel = document.getElementById('newStatusSelect');
        if (sel) sel.value = c.statut;
        const reason = document.getElementById('statusReason');
        if (reason) reason.value = '';
        window.lcOpenModal('modalStatus');
    };
    window.lcConfirmStatus = () => {
        const newStatus = document.getElementById('newStatusSelect')?.value;
        const c = RAW.find(x => x.id === pendingId);
        if (c && newStatus) c.statut = newStatus;
        window.lcCloseModal('modalStatus');
        if (window.showToast) window.showToast('Statut mis à jour', `${c?.ref || ''} → ${STATUS_MAP[newStatus]?.label || newStatus}`, 'success');
        applyFilters();
    };

    /* ── Export CSV ── */
    window.exportContractsCsv = () => {
        const headers = ['Référence','Chauffeur','Véhicule','Montant total','Total payé','Restant','Versement','Fréquence','Statut','Date début','Date fin','Prochaine échéance'];
        const rows = filtered.map(c => [
            c.ref, c.chauffeur, c.vehicule, c.montant_total, c.total_paye,
            Math.max(0, c.montant_total - c.total_paye), c.versement, c.frequence,
            c.statut, c.date_debut, c.date_fin_prevue, c.prochaine_echeance || ''
        ]);
        const csv = [headers, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
        const a   = document.createElement('a');
        a.href     = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
        a.download = `contrats_lease_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
    };

    /* ── KPI bar height ── */
    function measureKpiBar() {
        const bar = document.getElementById('lcKpiBar');
        if (bar) document.documentElement.style.setProperty('--lc-kpi-h', Math.round(bar.getBoundingClientRect().height) + 'px');
    }

    /* ── INIT ── */
    const boot = () => {
        const vehicleSelect = document.getElementById('ctVehicule');
        const vehicleIdInput = document.getElementById('ctVehicleId');

        if (vehicleSelect && vehicleIdInput) {
            vehicleSelect.addEventListener('change', function () {
                const option = this.options[this.selectedIndex];
                vehicleIdInput.value = option?.dataset?.vehicleId || '';
            });

            const option = vehicleSelect.options[vehicleSelect.selectedIndex];
            vehicleIdInput.value = option?.dataset?.vehicleId || vehicleIdInput.value || '';
        }

        const cutoffCheckbox = document.getElementById('ctCoupureAuto');
        const cutoffWrap = document.getElementById('ctCutoffTimeWrap');

        function syncCutoffVisibility() {
            if (!cutoffCheckbox || !cutoffWrap) return;
            cutoffWrap.style.display = cutoffCheckbox.checked ? 'block' : 'none';
        }

        cutoffCheckbox?.addEventListener('change', syncCutoffVisibility);
        syncCutoffVisibility();

        @if($errors->any())
            window.lcOpenModal('modalContract');
            window.lcCalcContractPreview();
        @endif

        applyFilters();
        measureKpiBar();
        if (window.ResizeObserver) {
            const bar = document.getElementById('lcKpiBar');
            if (bar) new ResizeObserver(() => measureKpiBar()).observe(bar);
        }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();

})();
</script>
@endpush