@extends('layouts.app')

@section('title', 'Historique des coupures lease')

@push('styles')
<style>
/* ══════════════════════════════════════════════
   PAGE LAYOUT
══════════════════════════════════════════════ */
.ch-page {
    display: flex;
    flex-direction: column;
    gap: var(--dash-gap);
}

/* ══════════════════════════════════════════════
   HEADER CARD
══════════════════════════════════════════════ */
.ch-header {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    padding: var(--sp-lg) var(--sp-xl);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: var(--sp-lg);
    box-shadow: var(--shadow-sm);
}

.ch-header-left h1 {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: 800;
    color: var(--color-text);
    margin: 0 0 .25rem;
    letter-spacing: var(--ls-tight);
}

.ch-header-left p {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    margin: 0;
    max-width: 920px;
    line-height: 1.55;
}

/* ══════════════════════════════════════════════
   KPI STRIP
══════════════════════════════════════════════ */
.ch-kpi-strip {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: .6rem;
}

@media (max-width: 1400px) {
    .ch-kpi-strip { grid-template-columns: repeat(4, 1fr); }
}
@media (max-width: 900px) {
    .ch-kpi-strip { grid-template-columns: repeat(2, 1fr); }
}

.ch-kpi {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    padding: .9rem 1rem;
    box-shadow: var(--shadow-xs);
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s, transform .1s, background .15s;
    text-decoration: none;
    display: block;
}

.ch-kpi:hover {
    border-color: var(--color-primary-border);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}

.ch-kpi.active-filter {
    border-color: var(--color-primary);
    background: var(--color-primary-light);
    box-shadow: 0 0 0 2px var(--color-primary-border);
}

.ch-kpi-label {
    font-family: var(--font-display);
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: var(--ls-wider);
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: .35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ch-kpi-val {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--color-text);
    line-height: 1;
    letter-spacing: var(--ls-tight);
}

.ch-kpi-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    margin-right: .35rem;
    vertical-align: middle;
    margin-bottom: 2px;
}

/* ══════════════════════════════════════════════
   FILTER CARD
══════════════════════════════════════════════ */
.ch-filters-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    padding: 1rem 1.1rem;
    box-shadow: var(--shadow-xs);
}

.ch-filter-form {
    display: flex;
    flex-direction: column;
    gap: .85rem;
}

.ch-filter-main {
    display: grid;
    grid-template-columns: minmax(320px, 1.8fr) minmax(190px, .75fr) minmax(220px, .85fr) auto;
    gap: .75rem;
    align-items: center;
}

.ch-search-wrap {
    position: relative;
}

.ch-search-icon {
    position: absolute;
    left: .9rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: .8rem;
    pointer-events: none;
}

.ch-select,
.ch-input,
.ch-date {
    height: 42px;
    border: 1px solid var(--color-input-border);
    border-radius: 14px;
    background: var(--color-input-bg);
    color: var(--color-text);
    font-family: var(--font-body);
    font-size: .85rem;
    transition: border-color .15s, box-shadow .15s, background .15s;
    appearance: auto;
}

.ch-select:focus,
.ch-input:focus,
.ch-date:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: var(--focus-ring);
}

.ch-input-search {
    width: 100%;
    padding: 0 .95rem 0 2.5rem;
    font-size: .9rem;
    font-weight: 500;
}

.ch-select {
    padding: 0 .85rem;
}

.ch-filter-actions {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    justify-content: flex-end;
}

.ch-filter-sub {
    display: none;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    padding-top: .15rem;
}

.ch-filter-sub.visible {
    display: flex;
}

.ch-filter-sub-label {
    font-family: var(--font-display);
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: var(--ls-wide);
    text-transform: uppercase;
    color: var(--color-text-muted);
    white-space: nowrap;
}

.ch-date-group {
    display: inline-flex;
    align-items: center;
    gap: .65rem;
    flex-wrap: wrap;
}

.ch-date-helper {
    font-size: .75rem;
    color: var(--color-text-muted);
}

.ch-period-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    padding-top: .2rem;
    border-top: 1px solid var(--color-border-subtle);
}

.ch-pill {
    display: inline-flex;
    align-items: center;
    height: 34px;
    padding: 0 .875rem;
    border-radius: var(--r-pill);
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text);
    font-family: var(--font-display);
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .02em;
    text-decoration: none;
    cursor: pointer;
    transition: background .12s, border-color .12s, color .12s, transform .1s;
    white-space: nowrap;
}

.ch-pill:hover {
    background: var(--color-primary-light);
    border-color: var(--color-primary-border);
    color: var(--color-primary);
    transform: translateY(-1px);
}

.ch-pill.active {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
}

@media (max-width: 1180px) {
    .ch-filter-main {
        grid-template-columns: 1fr 1fr;
    }

    .ch-filter-actions {
        grid-column: 1 / -1;
        justify-content: flex-start;
    }
}

@media (max-width: 740px) {
    .ch-filter-main {
        grid-template-columns: 1fr;
    }

    .ch-filter-actions {
        justify-content: stretch;
        flex-wrap: wrap;
    }
}

/* ══════════════════════════════════════════════
   TABLE CARD
══════════════════════════════════════════════ */
.ch-table-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.ch-table-wrap {
    overflow-x: auto;
}

.ch-table {
    width: 100%;
    min-width: 1080px;
    border-collapse: collapse;
    font-family: var(--font-body);
    font-size: .82rem;
}

.ch-table thead th {
    background: var(--color-bg-subtle, var(--color-border-subtle));
    font-family: var(--font-display);
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: var(--ls-wider);
    text-transform: uppercase;
    color: var(--color-text-muted);
    padding: .7rem .9rem;
    border-bottom: 2px solid var(--color-primary);
    white-space: nowrap;
    text-align: left;
}

.ch-table tbody td {
    padding: .85rem .9rem;
    border-bottom: 1px solid var(--color-border-subtle);
    color: var(--color-text);
    vertical-align: top;
}

.ch-table tbody tr:last-child td {
    border-bottom: none;
}

.ch-table tbody tr:hover td {
    background: var(--color-sidebar-active);
}

.dark-mode .ch-table thead th {
    background: #161b22;
}

/* ── Statut badge ── */
.ch-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .34rem .7rem;
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .02em;
    white-space: nowrap;
    border: 1px solid transparent;
}

.ch-badge-pending   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.ch-badge-waiting   { background: #fff7ed; color: #c2410c; border-color: #fdba74; }
.ch-badge-sent      { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
.ch-badge-cut       { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.ch-badge-cancelled { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
.ch-badge-failed    { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

.dark-mode .ch-badge-pending   { background: rgba(29,78,216,.2);  color: #93c5fd; border-color: rgba(147,197,253,.3); }
.dark-mode .ch-badge-waiting   { background: rgba(194,65,12,.2);  color: #fdba74; border-color: rgba(253,186,116,.3); }
.dark-mode .ch-badge-sent      { background: rgba(109,40,217,.2); color: #c4b5fd; border-color: rgba(196,181,253,.3); }
.dark-mode .ch-badge-cut       { background: rgba(4,120,87,.2);   color: #6ee7b7; border-color: rgba(110,231,183,.3); }
.dark-mode .ch-badge-cancelled { background: rgba(75,85,99,.2);   color: #9ca3af; border-color: rgba(156,163,175,.3); }
.dark-mode .ch-badge-failed    { background: rgba(185,28,28,.2);  color: #fca5a5; border-color: rgba(252,165,165,.3); }

/* ── Vehicle cell ── */
.ch-vehicle-main {
    font-weight: 700;
    color: var(--color-text);
    font-size: .88rem;
    font-family: var(--font-display);
}

.ch-vehicle-sub {
    font-size: .73rem;
    color: var(--color-text-muted);
    margin-top: .18rem;
}

/* ── Contract cell ── */
.ch-contract-line {
    font-size: .78rem;
    color: var(--color-text);
    line-height: 1.5;
}

.ch-contract-muted {
    color: var(--color-text-muted);
    font-size: .73rem;
}

/* ── Datetime cell ── */
.ch-dt-main {
    font-weight: 700;
    color: var(--color-text);
    font-size: .8rem;
    white-space: nowrap;
}

.ch-dt-sub {
    font-size: .7rem;
    color: var(--color-text-muted);
}

/* ── Timeline cell ── */
.ch-timeline {
    display: flex;
    flex-direction: column;
    gap: .35rem;
    min-width: 180px;
}

.ch-timeline-step {
    display: flex;
    align-items: center;
    gap: .45rem;
    font-size: .73rem;
}

.ch-timeline-icon {
    width: 17px;
    height: 17px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .55rem;
}

.ch-tl-done   { background: #ecfdf5; color: #047857; }
.ch-tl-empty  { background: var(--color-border-subtle); color: var(--color-text-muted); }

.dark-mode .ch-tl-done  { background: rgba(4,120,87,.2); color: #6ee7b7; }
.dark-mode .ch-tl-empty { background: rgba(255,255,255,.06); }

/* ── Reason cell ── */
.ch-reason-main {
    color: var(--color-text);
    font-size: .8rem;
    line-height: 1.5;
    max-width: 320px;
}

.ch-reason-note {
    margin-top: .5rem;
    padding: .5rem .65rem;
    border: 1px solid var(--color-border-subtle);
    border-radius: 12px;
    background: var(--color-bg-subtle, #f8fafc);
    color: var(--color-text-muted);
    font-size: .72rem;
    line-height: 1.45;
    max-width: 320px;
}

/* ── Detail toggle btn ── */
.ch-detail-btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .4rem .7rem;
    border-radius: 12px;
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-muted);
    font-family: var(--font-display);
    font-size: .68rem;
    font-weight: 700;
    cursor: pointer;
    transition: background .12s, border-color .12s, color .12s, transform .1s;
    white-space: nowrap;
}

.ch-detail-btn:hover,
.ch-detail-btn.open {
    background: var(--color-primary-light);
    border-color: var(--color-primary-border);
    color: var(--color-primary);
}

.ch-detail-btn:hover {
    transform: translateY(-1px);
}

.ch-detail-btn .ch-detail-arrow {
    transition: transform .2s;
    font-size: .55rem;
}

.ch-detail-btn.open .ch-detail-arrow {
    transform: rotate(180deg);
}

/* ── Expanded detail row ── */
.ch-detail-row td {
    background: var(--color-bg) !important;
    border-bottom: 2px solid var(--color-border-subtle) !important;
    padding: 0 !important;
}

.dark-mode .ch-detail-row td {
    background: rgba(255,255,255,.02) !important;
}

.ch-detail-inner {
    display: none;
    padding: 1.1rem 1.25rem 1.25rem;
    gap: 1rem;
    flex-wrap: wrap;
}

.ch-detail-inner.visible {
    display: flex;
}

.ch-detail-section {
    flex: 1;
    min-width: 240px;
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: 16px;
    padding: .95rem 1rem;
}

.ch-detail-section-title {
    font-family: var(--font-display);
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: var(--ls-wider);
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: .7rem;
    padding-bottom: .45rem;
    border-bottom: 1px solid var(--color-border-subtle);
}

.ch-detail-field {
    display: flex;
    gap: .55rem;
    font-size: .8rem;
    margin-bottom: .45rem;
    align-items: flex-start;
}

.ch-detail-field:last-child {
    margin-bottom: 0;
}

.ch-detail-field-key {
    color: var(--color-text-muted);
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
    min-width: 98px;
}

.ch-detail-field-val {
    color: var(--color-text);
    word-break: break-word;
    line-height: 1.45;
}

.ch-code-block {
    background: var(--color-bg-subtle, #f8fafc);
    border: 1px solid var(--color-border-subtle);
    border-radius: 12px;
    padding: .7rem .8rem;
    font-family: var(--font-mono);
    font-size: .72rem;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    max-width: 100%;
    max-height: 220px;
    overflow-y: auto;
    color: var(--color-text);
    line-height: 1.5;
}

.dark-mode .ch-code-block {
    background: rgba(0,0,0,.25);
}

/* ── Ignition badge ── */
.ch-ignition {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    font-size: .72rem;
    font-weight: 700;
    font-family: var(--font-display);
}

.ch-ign-on  { color: var(--color-success); }
.ch-ign-off { color: var(--color-text-muted); }

/* ── Empty state ── */
.ch-empty {
    padding: 2.7rem;
    text-align: center;
}

.ch-empty-icon {
    font-size: 2rem;
    color: var(--color-border);
    margin-bottom: .75rem;
}

.ch-empty-text {
    font-family: var(--font-display);
    font-size: .92rem;
    font-weight: 700;
    color: var(--color-text-muted);
}

.ch-empty-sub {
    font-size: .78rem;
    color: var(--color-text-muted);
    margin-top: .35rem;
}

/* ── Pagination wrapper ── */
.ch-pagination {
    padding: .95rem 1.25rem;
    border-top: 1px solid var(--color-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
}

.ch-pagination-info {
    font-size: .75rem;
    color: var(--color-text-muted);
    font-family: var(--font-display);
}
</style>
@endpush

@section('content')
@php
    $period       = $filters['period'] ?? '';
    $status       = $filters['status'] ?? '';
    $search       = $filters['search'] ?? '';
    $specificDate = $filters['specific_date'] ?? '';
    $dateFrom     = $filters['date_from'] ?? '';
    $dateTo       = $filters['date_to'] ?? '';
@endphp

<div class="ch-page">

    {{-- ── HEADER ──────────────────────────────────────────── --}}
    <div class="ch-header">
        <div class="ch-header-left">
            <h1>
                <i class="fa-solid fa-bolt" style="color:var(--color-primary);margin-right:.4rem;font-size:.9em;"></i>
                Historique des coupures automatiques lease
            </h1>
            <p>
                Suivi des coupures planifiées, commandes envoyées, confirmations effectives,
                attentes de sécurité, annulations après paiement et échecs finaux.
            </p>
        </div>
    </div>

    {{-- ── KPI STRIP ───────────────────────────────────────── --}}
    <div class="ch-kpi-strip">
        @php
            $kpis = [
                ['label' => 'Total global',     'val' => $summary['total_all'] ?? 0, 'dot' => '#6b7280', 'key' => ''],
                ['label' => 'Coupures conf.',   'val' => $summary['cut_off'] ?? 0, 'dot' => '#047857', 'key' => 'CUT_OFF'],
                ['label' => 'En attente',       'val' => ($summary['pending'] ?? 0) + ($summary['waiting_stop'] ?? 0), 'dot' => '#1d4ed8', 'key' => 'PENDING'],
                ['label' => 'Attente arrêt',    'val' => $summary['waiting_stop'] ?? 0, 'dot' => '#c2410c', 'key' => 'WAITING_STOP'],
                ['label' => 'Cmd. envoyée',     'val' => $summary['command_sent'] ?? 0, 'dot' => '#6d28d9', 'key' => 'COMMAND_SENT'],
                ['label' => 'Annulés / payés',  'val' => $summary['cancelled_paid'] ?? 0, 'dot' => '#4b5563', 'key' => 'CANCELLED_PAID'],
                ['label' => 'Échecs finaux',    'val' => $summary['failed'] ?? 0, 'dot' => '#b91c1c', 'key' => 'FAILED'],
            ];
        @endphp

        @foreach($kpis as $kpi)
            <a href="{{ route('lease.cutoff-history.index', array_merge(request()->except('status', 'page'), $kpi['key'] ? ['status' => $kpi['key']] : [])) }}"
               class="ch-kpi {{ $status === $kpi['key'] && $kpi['key'] ? 'active-filter' : '' }}">
                <div class="ch-kpi-label">
                    <span class="ch-kpi-dot" style="background:{{ $kpi['dot'] }};"></span>
                    {{ $kpi['label'] }}
                </div>
                <div class="ch-kpi-val">{{ $kpi['val'] }}</div>
            </a>
        @endforeach
    </div>

    {{-- ── FILTERS ──────────────────────────────────────────── --}}
    <div class="ch-filters-card">
        <form method="GET" action="{{ route('lease.cutoff-history.index') }}" class="ch-filter-form">

            <div class="ch-filter-main">
                {{-- Recherche visible --}}
                <div class="ch-search-wrap">
                    <i class="fas fa-search ch-search-icon"></i>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        class="ch-input ch-input-search"
                        placeholder="Rechercher immatriculation, MAC GPS, lease, chauffeur, motif…"
                    >
                </div>

                {{-- Statut --}}
                <select name="status" class="ch-select">
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                {{-- Période --}}
                <select name="period" class="ch-select" id="ch-period-select">
                    <option value="">Période libre</option>
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Aujourd’hui</option>
                    <option value="yesterday" {{ $period === 'yesterday' ? 'selected' : '' }}>Hier</option>
                    <option value="this_week" {{ $period === 'this_week' ? 'selected' : '' }}>Cette semaine</option>
                    <option value="this_month" {{ $period === 'this_month' ? 'selected' : '' }}>Ce mois</option>
                    <option value="this_year" {{ $period === 'this_year' ? 'selected' : '' }}>Cette année</option>
                    <option value="specific_date" {{ $period === 'specific_date' ? 'selected' : '' }}>Date spécifique</option>
                    <option value="range" {{ $period === 'range' ? 'selected' : '' }}>Plage de dates</option>
                </select>

                {{-- Actions --}}
                <div class="ch-filter-actions">
                    <select name="per_page" class="ch-select">
                        <option value="20"  {{ request('per_page', 20) == 20 ? 'selected' : '' }}>20 / page</option>
                        <option value="50"  {{ request('per_page') == 50 ? 'selected' : '' }}>50 / page</option>
                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 / page</option>
                    </select>

                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter" aria-hidden="true"></i>
                        Filtrer
                    </button>

                    <a href="{{ route('lease.cutoff-history.index') }}" class="btn-secondary">
                        <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        Réinitialiser
                    </a>
                </div>
            </div>

            {{-- Dates conditionnelles --}}
            <div class="ch-filter-sub {{ in_array($period, ['specific_date', 'range'], true) ? 'visible' : '' }}" id="ch-date-filter-box">
                <span class="ch-filter-sub-label">Filtre date</span>

                <div class="ch-date-group" id="ch-specific-date-group" style="{{ $period === 'specific_date' ? '' : 'display:none;' }}">
                    <input type="date" name="specific_date" value="{{ $specificDate }}" class="ch-date">
                    <span class="ch-date-helper">Sélectionnez une date précise.</span>
                </div>

                <div class="ch-date-group" id="ch-range-date-group" style="{{ $period === 'range' ? '' : 'display:none;' }}">
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="ch-date">
                    <span class="ch-date-helper">au</span>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="ch-date">
                </div>
            </div>

            {{-- Périodes rapides --}}
            <div class="ch-period-pills">
                @foreach([
                    'today'      => "Aujourd’hui",
                    'yesterday'  => 'Hier',
                    'this_week'  => 'Cette semaine',
                    'this_month' => 'Ce mois',
                    'this_year'  => 'Cette année',
                ] as $p => $label)
                    <a href="{{ route('lease.cutoff-history.index', array_merge(request()->except('period','specific_date','date_from','date_to','page'), ['period' => $p])) }}"
                       class="ch-pill {{ $period === $p ? 'active' : '' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

        </form>
    </div>

    {{-- ── TABLE ────────────────────────────────────────────── --}}
    <div class="ch-table-card">
        <div class="ch-table-wrap">
            <table class="ch-table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Véhicule</th>
                        <th>Contexte paiement</th>
                        <th>Statut</th>
                        <th>Planifié</th>
                        <th>Chronologie</th>
                        <th>Contrôle</th>
                        <th>Motif</th>
                        <th style="width:90px">Détail</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($histories as $index => $history)
                @php
                    $rowId = 'ch-row-' . $history->id;

                    $statusClass = match($history->status) {
                        'PENDING'        => 'ch-badge-pending',
                        'WAITING_STOP'   => 'ch-badge-waiting',
                        'COMMAND_SENT'   => 'ch-badge-sent',
                        'CUT_OFF'        => 'ch-badge-cut',
                        'CANCELLED_PAID' => 'ch-badge-cancelled',
                        'FAILED'         => 'ch-badge-failed',
                        default          => 'ch-badge-pending',
                    };

                    $statusLabel = match($history->status) {
                        'PENDING'        => 'En attente',
                        'WAITING_STOP'   => 'Attente arrêt',
                        'COMMAND_SENT'   => 'Cmd. envoyée',
                        'CUT_OFF'        => 'Coupure confirmée',
                        'CANCELLED_PAID' => 'Annulé / payé',
                        'FAILED'         => 'Échec final',
                        default          => $history->status ?? '—',
                    };

                    $statusIcon = match($history->status) {
                        'PENDING'        => 'fa-clock',
                        'WAITING_STOP'   => 'fa-hourglass-half',
                        'COMMAND_SENT'   => 'fa-paper-plane',
                        'CUT_OFF'        => 'fa-check',
                        'CANCELLED_PAID' => 'fa-ban',
                        'FAILED'         => 'fa-xmark',
                        default          => 'fa-circle',
                    };

                    $ignitionRaw = strtolower((string)($history->ignition_state ?? ''));
                    $ignOn = in_array($ignitionRaw, ['on', '1', 'true', 'running'], true);

                    $tl = [
                        ['label' => 'Détecté',  'val' => $history->detected_at,         'done' => (bool) $history->detected_at],
                        ['label' => 'Cmd.',     'val' => $history->cutoff_requested_at, 'done' => (bool) $history->cutoff_requested_at],
                        ['label' => 'Confirmé', 'val' => $history->cutoff_executed_at,  'done' => (bool) $history->cutoff_executed_at],
                    ];

                    $paymentSnapshot = is_array($history->payment_status_snapshot ?? null)
                        ? $history->payment_status_snapshot
                        : [];

                    $usefulPaymentFields = [
                        'chauffeur_nom_complet' => 'Chauffeur',
                        'date_echeance' => 'Échéance',
                        'reste_a_payer' => 'Reste à payer',
                        'montant_attendu' => 'Montant attendu',
                        'montant_paye' => 'Montant payé',
                        'statut' => 'Statut paiement',
                    ];
                @endphp

                {{-- MAIN ROW --}}
                <tr>
                    <td style="color:var(--color-text-muted);font-size:.72rem;">
                        {{ $histories->firstItem() + $index }}
                    </td>

                    {{-- Véhicule --}}
                    <td>
                        <div class="ch-vehicle-main">
                            {{ $history->vehicle->immatriculation ?? '—' }}
                        </div>

                        @if($history->vehicle->mac_id_gps ?? false)
                            <div class="ch-vehicle-sub">
                                <i class="fas fa-microchip" style="font-size:.65rem;"></i>
                                {{ $history->vehicle->mac_id_gps }}
                            </div>
                        @endif

                      
                    </td>

                    {{-- Contexte paiement --}}
                    <td>
                        @if(!empty($paymentSnapshot['chauffeur_nom_complet']))
                            <div class="ch-contract-line">
                                <i class="fas fa-user" style="font-size:.65rem;color:var(--color-text-muted);margin-right:.2rem;"></i>
                                {{ $paymentSnapshot['chauffeur_nom_complet'] }}
                            </div>
                        @endif

                        @if(!empty($paymentSnapshot['reste_a_payer']))
                            <div class="ch-contract-line">
                                <i class="fas fa-triangle-exclamation" style="font-size:.65rem;color:var(--color-warning);margin-right:.2rem;"></i>
                                {{ $paymentSnapshot['reste_a_payer'] }} restant
                            </div>
                        @endif

                        @if(!empty($paymentSnapshot['date_echeance']))
                            <div class="ch-contract-muted">
                                Échéance : {{ $paymentSnapshot['date_echeance'] }}
                            </div>
                        @endif

                        @if(empty($paymentSnapshot['chauffeur_nom_complet']) && empty($paymentSnapshot['reste_a_payer']) && empty($paymentSnapshot['date_echeance']))
                            <span style="color:var(--color-text-muted)">—</span>
                        @endif
                    </td>

                    {{-- Statut --}}
                    <td style="white-space:nowrap;">
                        <span class="ch-badge {{ $statusClass }}">
                            <i class="fas {{ $statusIcon }}" style="font-size:.6rem;"></i>
                            {{ $statusLabel }}
                        </span>
                    </td>

                    {{-- Planifié --}}
                    <td>
                        @if($history->scheduled_for)
                            <div class="ch-dt-main">{{ optional($history->scheduled_for)->format('d/m/Y') }}</div>
                            <div class="ch-dt-sub">{{ optional($history->scheduled_for)->format('H:i') }}</div>
                        @else
                            <span style="color:var(--color-text-muted)">—</span>
                        @endif
                    </td>

                    {{-- Chronologie --}}
                    <td>
                        <div class="ch-timeline">
                            @foreach($tl as $step)
                                <div class="ch-timeline-step">
                                    <span class="ch-timeline-icon {{ $step['done'] ? 'ch-tl-done' : 'ch-tl-empty' }}">
                                        <i class="fas {{ $step['done'] ? 'fa-check' : 'fa-minus' }}"></i>
                                    </span>
                                    <span style="color:{{ $step['done'] ? 'var(--color-text)' : 'var(--color-text-muted)' }};font-weight:{{ $step['done'] ? '600' : '400' }};">
                                        {{ $step['label'] }}
                                        @if($step['done'])
                                            <span style="color:var(--color-text-muted);font-weight:400;">
                                                — {{ optional($step['val'])->format('d/m H:i') }}
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </td>

                    {{-- Contrôle véhicule --}}
                    <td>
                        @if($history->speed_at_check !== null)
                            <div style="font-size:.79rem;font-weight:700;color:var(--color-text);">
                                <i class="fas fa-gauge" style="color:var(--color-text-muted);font-size:.65rem;"></i>
                                {{ $history->speed_at_check }} km/h
                            </div>
                        @endif

                        <div class="ch-ignition {{ $ignOn ? 'ch-ign-on' : 'ch-ign-off' }}" style="margin-top:.22rem;">
                            <i class="fas {{ $ignOn ? 'fa-circle-dot' : 'fa-circle' }}" style="font-size:.55rem;"></i>
                            {{ $ignOn ? 'Moteur ON' : 'Moteur OFF' }}
                        </div>
                    </td>

                    {{-- Motif --}}
                    <td>
                        <div class="ch-reason-main">
                            {{ $history->reason ?: 'Aucun motif renseigné.' }}
                        </div>

                       
                    </td>

                    {{-- Détail --}}
                    <td>
                        <button type="button"
                                class="ch-detail-btn"
                                onclick="chToggle('{{ $rowId }}')"
                                id="btn-{{ $rowId }}">
                            Détail
                            <i class="fas fa-chevron-down ch-detail-arrow"></i>
                        </button>
                    </td>
                </tr>

                {{-- DETAIL ROW --}}
                <tr class="ch-detail-row" id="{{ $rowId }}">
                    <td colspan="9">
                        <div class="ch-detail-inner" id="inner-{{ $rowId }}">

                            {{-- Suivi technique utile --}}
                            <div class="ch-detail-section">
                                <div class="ch-detail-section-title">
                                    <i class="fas fa-satellite-dish" style="margin-right:.3rem;"></i>
                                    Suivi technique
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">MAC GPS :</span>
                                    <span class="ch-detail-field-val">{{ $history->vehicle->mac_id_gps ?? '—' }}</span>
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Vitesse :</span>
                                    <span class="ch-detail-field-val">{{ $history->speed_at_check ?? '—' }}</span>
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Ignition :</span>
                                    <span class="ch-detail-field-val">{{ $history->ignition_state ?? '—' }}</span>
                                </div>
                            </div>

                            {{-- Paiement utile --}}
                            @if(!empty($paymentSnapshot))
                                <div class="ch-detail-section">
                                    <div class="ch-detail-section-title">
                                        <i class="fas fa-credit-card" style="margin-right:.3rem;"></i>
                                        Informations paiement utiles
                                    </div>

                                    @foreach($usefulPaymentFields as $key => $label)
                                        @if(!empty($paymentSnapshot[$key]))
                                            <div class="ch-detail-field">
                                                <span class="ch-detail-field-key">{{ $label }} :</span>
                                                <span class="ch-detail-field-val">{{ is_array($paymentSnapshot[$key]) ? json_encode($paymentSnapshot[$key], JSON_UNESCAPED_UNICODE) : $paymentSnapshot[$key] }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            {{-- Horodatage complet --}}
                            <div class="ch-detail-section">
                                <div class="ch-detail-section-title">
                                    <i class="fas fa-clock" style="margin-right:.3rem;"></i>
                                    Horodatage complet
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Planifié :</span>
                                    <span class="ch-detail-field-val">{{ optional($history->scheduled_for)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Détecté :</span>
                                    <span class="ch-detail-field-val">{{ optional($history->detected_at)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Commande :</span>
                                    <span class="ch-detail-field-val">{{ optional($history->cutoff_requested_at)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Confirmée :</span>
                                    <span class="ch-detail-field-val">{{ optional($history->cutoff_executed_at)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>
                            </div>

                            {{-- Notes / réponse provider --}}
                            @if(!empty($history->notes) || !empty($history->command_response))
                                <div class="ch-detail-section">
                                    <div class="ch-detail-section-title">
                                        <i class="fas fa-terminal" style="margin-right:.3rem;"></i>
                                        Diagnostic
                                    </div>

                                    @if(!empty($history->notes))
                                        <div class="ch-detail-field" style="display:block;">
                                            <div class="ch-detail-field-key" style="margin-bottom:.35rem;">Notes :</div>
                                            <div class="ch-detail-field-val">{{ $history->notes }}</div>
                                        </div>
                                    @endif
                                  

                                   
                                </div>
                            @endif

                        </div>
                    </td>
                </tr>

                @empty
                <tr>
                    <td colspan="9">
                        <div class="ch-empty">
                            <div class="ch-empty-icon"><i class="fas fa-inbox"></i></div>
                            <div class="ch-empty-text">Aucun historique trouvé</div>
                            <div class="ch-empty-sub">Modifiez les filtres pour afficher des résultats.</div>
                        </div>
                    </td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="ch-pagination">
            <span class="ch-pagination-info">
                {{ $histories->total() }} événement{{ $histories->total() > 1 ? 's' : '' }}
                — page {{ $histories->currentPage() }} / {{ $histories->lastPage() }}
            </span>
            {{ $histories->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function chToggle(rowId) {
    var inner = document.getElementById('inner-' + rowId);
    var btn   = document.getElementById('btn-' + rowId);
    if (!inner || !btn) return;

    var isOpen = inner.classList.contains('visible');
    inner.classList.toggle('visible', !isOpen);
    btn.classList.toggle('open', !isOpen);
}

function chHandleDateFilters() {
    var periodSelect = document.getElementById('ch-period-select');
    var wrapper = document.getElementById('ch-date-filter-box');
    var specific = document.getElementById('ch-specific-date-group');
    var range = document.getElementById('ch-range-date-group');

    if (!periodSelect || !wrapper || !specific || !range) return;

    var value = periodSelect.value;

    wrapper.classList.remove('visible');
    specific.style.display = 'none';
    range.style.display = 'none';

    if (value === 'specific_date') {
        wrapper.classList.add('visible');
        specific.style.display = 'inline-flex';
    } else if (value === 'range') {
        wrapper.classList.add('visible');
        range.style.display = 'inline-flex';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    chHandleDateFilters();

    var periodSelect = document.getElementById('ch-period-select');
    if (periodSelect) {
        periodSelect.addEventListener('change', chHandleDateFilters);
    }
});
</script>
@endpush