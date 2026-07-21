@extends('layouts.app')

@section('title', 'Dashboard Recouvrement')

@php
    $dashboard = $dashboard ?? [];
    $filters = $filters ?? ($dashboard['filters'] ?? ['search' => '']);
    $kpis = $dashboard['kpis'] ?? [];
    $charts = $dashboard['charts'] ?? [];
    $tables = $dashboard['tables'] ?? [];
    $contractsSummary = $dashboard['contracts_summary'] ?? [];
    $cutoffSummary = $dashboard['cutoff_summary'] ?? [];
    $warnings = $dashboard['warnings'] ?? [];
    $period = $dashboard['period'] ?? [];
    $weekPeriod = $dashboard['week_period'] ?? [];
    $pageError = $pageError ?? null;
    $selectedPeriodKey = $filters['period'] ?? ($period['key'] ?? 'today');
    $selectedDate = $filters['date'] ?? ($period['date'] ?? $period['start_date'] ?? now()->toDateString());
    $selectedStartDate = $filters['start_date'] ?? ($period['start_date'] ?? now()->toDateString());
    $selectedEndDate = $filters['end_date'] ?? ($period['end_date'] ?? now()->toDateString());

    $money = fn ($value) => number_format((float) $value, 0, ',', ' ') . ' FCFA';

    $badgeClass = function (?string $type) {
        return match ($type) {
            'success' => 'success',
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            'primary' => 'primary',
            default => 'muted',
        };
    };

    $recoveryChart = $charts['recovery'] ?? [
        'labels' => [],
        'dates' => [],
        'expected' => [],
        'paid' => [],
        'remaining' => [],
        'rate' => [],
    ];

    $typeRecovery = $charts['type_recovery'] ?? [
        'items' => [],
        'total_expected' => 0,
        'total_paid' => 0,
    ];

    $overdueLedger = $dashboard['overdue_ledger'] ?? [
        'drivers' => [],
        'overdue_count' => 0,
        'up_to_date_count' => null,
        'total_due' => 0,
        'oldest_overdue_days' => 0,
    ];

    $paymentsSummary = $dashboard['payments_summary'] ?? [
        'total_amount' => 0,
        'payments_count' => 0,
        'drivers_count' => 0,
        'by_driver' => [],
    ];
@endphp

@push('styles')
<style>
.recouvrement-dashboard {
    padding: .65rem 1rem 1rem;
    background: var(--color-bg, #f5f7fb);
    color: var(--color-text, #111827);
    min-height: calc(100vh - var(--navbar-h, 64px));
}

.recouvrement-dashboard .dash-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
    margin: 0 0 .55rem;
}

.recouvrement-dashboard .dash-title h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: .45rem;
    font-family: var(--font-display, system-ui);
    font-size: 1.12rem;
    font-weight: 850;
    color: var(--color-text, #111827);
}

.recouvrement-dashboard .dash-title h1 i { color: var(--color-primary, #f58220); }
.recouvrement-dashboard .dash-title p {
    margin: .16rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}

.recouvrement-dashboard .partner-scope {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border-radius: 999px;
    background: rgba(245,130,32,.12);
    color: var(--color-primary, #f58220);
    font-size: .7rem;
    font-weight: 800;
    padding: .38rem .7rem;
}

.recouvrement-dashboard .dashboard-alert {
    display: flex;
    align-items: flex-start;
    gap: .55rem;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-card, #fff);
    border-radius: .9rem;
    padding: .65rem .75rem;
    margin-bottom: .55rem;
    font-size: .72rem;
    color: var(--color-secondary-text, #6b7280);
}
.recouvrement-dashboard .dashboard-alert.error { color: var(--color-error, #dc2626); }

.recouvrement-dashboard .search-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .7rem;
    padding: .55rem .7rem;
    margin-bottom: .6rem;
}
.recouvrement-dashboard .search-field {
    position: relative;
    flex: 1;
    min-width: 240px;
}
.recouvrement-dashboard .search-field i {
    position: absolute;
    left: .78rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}
.recouvrement-dashboard .search-input {
    width: 100%;
    height: 38px;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .75rem;
    background: var(--color-card, #fff);
    color: var(--color-text, #111827);
    padding: 0 .75rem 0 2rem;
    outline: none;
    font-size: .74rem;
}
.recouvrement-dashboard .search-input:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.14);
}
.recouvrement-dashboard .period-chip {
    white-space: nowrap;
    border-radius: 999px;
    background: var(--color-bg-subtle, rgba(148,163,184,.10));
    color: var(--color-secondary-text, #6b7280);
    font-size: .68rem;
    font-weight: 800;
    padding: .42rem .65rem;
}

.recouvrement-dashboard .period-filter-bar {
    display: flex;
    flex-direction: column;
    gap: .55rem;
    padding: .65rem .75rem;
    margin-bottom: .65rem;
}
.recouvrement-dashboard .period-filter-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .65rem;
    flex-wrap: wrap;
}
.recouvrement-dashboard .period-select-wrap {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex: 0 0 auto;
}
.recouvrement-dashboard .period-select {
    height: 38px;
    min-width: 180px;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .75rem;
    background: var(--color-card, #fff);
    color: var(--color-text, #111827);
    padding: 0 .7rem;
    outline: none;
    font-size: .72rem;
    font-weight: 850;
}
.recouvrement-dashboard .period-select:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.14);
}
.recouvrement-dashboard .date-filter-group {
    display: none;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    width: 100%;
}
.recouvrement-dashboard .date-filter-group.is-visible { display: flex; }
.recouvrement-dashboard .date-filter-group input[type="date"] {
    height: 34px;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .7rem;
    padding: 0 .55rem;
    font-size: .7rem;
    color: var(--color-text, #111827);
    background: var(--color-card, #fff);
}
.recouvrement-dashboard .date-filter-group .date-label {
    font-size: .64rem;
    font-weight: 850;
    color: var(--color-secondary-text, #6b7280);
}
.recouvrement-dashboard .filter-submit {
    height: 34px;
    border: none;
    border-radius: .7rem;
    padding: 0 .75rem;
    background: var(--color-primary, #f58220);
    color: #fff;
    font-size: .68rem;
    font-weight: 900;
    cursor: pointer;
}

.recouvrement-dashboard .ui-card {
    background: var(--color-card, #fff);
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: 1rem;
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.06));
}

.recouvrement-dashboard .lease-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .5rem;
    margin-bottom: .65rem;
}
.recouvrement-dashboard .lkpi {
    min-height: 82px;
    padding: .58rem .7rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .45rem;
    overflow: hidden;
}
.recouvrement-dashboard .lkpi-highlight {
    grid-column: span 2;
    min-height: auto;
    border-color: rgba(22,163,74,.35);
    background: linear-gradient(135deg, rgba(22,163,74,.07), var(--color-card, #fff) 65%);
}
.recouvrement-dashboard .lkpi-highlight .kpi-note { white-space: normal; }
@media (max-width: 640px) {
    .recouvrement-dashboard .lkpi-highlight { grid-column: span 1; }
}
.recouvrement-dashboard .lkpi-label {
    margin: 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    font-weight: 800;
    white-space: nowrap;
}
.recouvrement-dashboard .lkpi-value {
    margin: .1rem 0 0;
    font-family: var(--font-display, system-ui);
    font-size: 1.02rem;
    font-weight: 900;
    white-space: nowrap;
    color: var(--color-text, #111827);
}
.recouvrement-dashboard .lkpi-value.success { color: var(--color-success, #16a34a); }
.recouvrement-dashboard .lkpi-value.danger { color: var(--color-error, #dc2626); }
.recouvrement-dashboard .lkpi-value.warning { color: var(--color-warning, #d97706); }
.recouvrement-dashboard .kpi-note {
    margin-top: .22rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 700;
}
.recouvrement-dashboard .lkpi-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    border-radius: .75rem;
    flex-shrink: 0;
}
.recouvrement-dashboard .lkpi-icon.green { background: rgba(22,163,74,.12); color: #16a34a; }
.recouvrement-dashboard .lkpi-icon.red { background: rgba(220,38,38,.12); color: #dc2626; }
.recouvrement-dashboard .lkpi-icon.orange { background: rgba(245,130,32,.13); color: #f58220; }
.recouvrement-dashboard .lkpi-icon.blue { background: rgba(37,99,235,.12); color: #2563eb; }
.recouvrement-dashboard .lkpi-icon.grey { background: rgba(107,114,128,.12); color: #6b7280; }

.recouvrement-dashboard .page-grid,
.recouvrement-dashboard .analysis-grid,
.recouvrement-dashboard .table-grid,
.recouvrement-dashboard .ops-grid {
    display: grid;
    gap: .75rem;
}
.recouvrement-dashboard .analysis-grid { grid-template-columns: 1.45fr 1.05fr .8fr; }
.recouvrement-dashboard .chart-switch-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .45rem;
}
.recouvrement-dashboard .small-chart-title {
    display: flex;
    flex-direction: column;
    gap: .08rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .64rem;
    font-weight: 800;
}
.recouvrement-dashboard .small-chart-title strong {
    color: var(--color-text, #0f172a);
    font-size: .78rem;
}
.recouvrement-dashboard .chart-mode-toggle {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    background: var(--color-bg-subtle, #f8fafc);
    border: 1px solid var(--color-border-subtle, #e2e8f0);
    border-radius: 999px;
    padding: .2rem;
    flex-shrink: 0;
}
.recouvrement-dashboard .chart-mode-btn {
    border: 0;
    background: transparent;
    color: var(--color-secondary-text, #64748b);
    font-weight: 800;
    font-size: .66rem;
    padding: .38rem .62rem;
    border-radius: 999px;
    cursor: pointer;
    white-space: nowrap;
}
.recouvrement-dashboard .chart-mode-btn.active {
    background: var(--color-card, #ffffff);
    color: var(--color-primary, #f58220);
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(15,23,42,.08));
}
.recouvrement-dashboard .chart-wrap { height: 245px; position: relative; }
.recouvrement-dashboard .table-grid { grid-template-columns: 1.15fr 1fr; }
.recouvrement-dashboard .ops-grid { grid-template-columns: .85fr 1.15fr; }

.recouvrement-dashboard .card-pad { padding: .75rem; }
.recouvrement-dashboard .card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .65rem;
    margin-bottom: .65rem;
}
.recouvrement-dashboard .card-title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: .4rem;
    font-family: var(--font-display, system-ui);
    font-size: .88rem;
    font-weight: 850;
}
.recouvrement-dashboard .card-title i { color: var(--color-primary, #f58220); }

.recouvrement-dashboard .card-actions {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin-left: auto;
}
.recouvrement-dashboard .block-search-field {
    position: relative;
    width: min(260px, 34vw);
}
.recouvrement-dashboard .block-search-field i {
    position: absolute;
    left: .68rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-secondary-text, #6b7280);
    font-size: .68rem;
}
.recouvrement-dashboard .block-search-input {
    width: 100%;
    height: 32px;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .7rem;
    background: var(--color-card, #fff);
    color: var(--color-text, #111827);
    padding: 0 .65rem 0 1.85rem;
    outline: none;
    font-size: .7rem;
}
.recouvrement-dashboard .block-search-input:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.12);
}

.recouvrement-dashboard .card-subtitle {
    margin: .15rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .65rem;
}

.recouvrement-dashboard .type-progress-list {
    display: grid;
    gap: .55rem;
    max-height: 300px;
    overflow-y: auto;
    padding-right: .2rem;
}
.recouvrement-dashboard .type-progress-row {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .8rem;
    padding: .55rem;
}
.recouvrement-dashboard .type-progress-top {
    display: flex;
    justify-content: space-between;
    gap: .65rem;
    align-items: baseline;
    margin-bottom: .38rem;
}
.recouvrement-dashboard .type-name {
    min-width: 0;
    font-weight: 900;
    font-size: .72rem;
    color: var(--color-text, #111827);
}
.recouvrement-dashboard .type-kind {
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.recouvrement-dashboard .type-rate {
    font-weight: 900;
    color: var(--color-success, #16a34a);
    font-size: .74rem;
    white-space: nowrap;
}
.recouvrement-dashboard .progress-track {
    height: 9px;
    border-radius: 999px;
    background: rgba(148,163,184,.20);
    overflow: hidden;
}
.recouvrement-dashboard .progress-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--color-primary, #f58220), var(--color-success, #16a34a));
}
.recouvrement-dashboard .type-progress-bottom {
    display: flex;
    justify-content: space-between;
    gap: .65rem;
    margin-top: .35rem;
    font-size: .62rem;
    color: var(--color-secondary-text, #6b7280);
    font-weight: 700;
}

.recouvrement-dashboard .metric-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
}
.recouvrement-dashboard .metric-tile {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .85rem;
    padding: .62rem;
}
.recouvrement-dashboard .metric-tile span {
    display: block;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.recouvrement-dashboard .metric-tile strong {
    display: block;
    margin-top: .16rem;
    font-size: 1.08rem;
    font-weight: 900;
    color: var(--color-text, #111827);
}

.recouvrement-dashboard .table-scroll {
    overflow: auto;
    max-height: 420px;
}
.recouvrement-dashboard .table-scroll.payments { max-height: 430px; }
.recouvrement-dashboard .timeline-scroll { max-height: 420px; overflow-y: auto; padding-right: .2rem; }
.recouvrement-dashboard .dashboard-table {
    width: 100%;
    min-width: 660px;
    border-collapse: collapse;
}
.recouvrement-dashboard .dashboard-table th,
.recouvrement-dashboard .dashboard-table td {
    border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);
    padding: .55rem .45rem;
    text-align: left;
    white-space: nowrap;
}
.recouvrement-dashboard .dashboard-table th {
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 900;
}
.recouvrement-dashboard .dashboard-table td { font-size: .69rem; }
.recouvrement-dashboard .driver-name { font-weight: 900; }
.recouvrement-dashboard .small-muted { display: block; margin-top: .08rem; font-size: .58rem; color: var(--color-secondary-text, #6b7280); }
.recouvrement-dashboard .amount-success { color: var(--color-success, #16a34a); font-weight: 900; }
.recouvrement-dashboard .amount-danger { color: var(--color-error, #dc2626); font-weight: 900; }

.recouvrement-dashboard .dash-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: .22rem .48rem;
    font-size: .6rem;
    font-weight: 900;
    white-space: nowrap;
}
.recouvrement-dashboard .dash-badge.success { background: rgba(22,163,74,.12); color: #16a34a; }
.recouvrement-dashboard .dash-badge.danger { background: rgba(220,38,38,.12); color: #dc2626; }
.recouvrement-dashboard .dash-badge.warning { background: rgba(217,119,6,.14); color: #d97706; }
.recouvrement-dashboard .dash-badge.info { background: rgba(37,99,235,.12); color: #2563eb; }
.recouvrement-dashboard .dash-badge.primary { background: rgba(245,130,32,.13); color: #f58220; }
.recouvrement-dashboard .dash-badge.muted { background: rgba(107,114,128,.12); color: #6b7280; }

.recouvrement-dashboard .timeline {
    display: grid;
    gap: .5rem;
}
.recouvrement-dashboard .timeline-item {
    display: grid;
    grid-template-columns: 34px 1fr auto;
    gap: .55rem;
    align-items: center;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .8rem;
    padding: .55rem;
}
.recouvrement-dashboard .timeline-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: .75rem;
    background: rgba(245,130,32,.13);
    color: var(--color-primary, #f58220);
}
.recouvrement-dashboard .timeline-title {
    margin: 0;
    font-size: .72rem;
    font-weight: 900;
}
.recouvrement-dashboard .timeline-desc {
    margin: .1rem 0 0;
    font-size: .62rem;
    color: var(--color-secondary-text, #6b7280);
}
.recouvrement-dashboard .empty-state {
    border: 1px dashed var(--color-border-subtle, #e5e7eb);
    border-radius: .85rem;
    padding: .85rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .7rem;
    text-align: center;
}

.recouvrement-dashboard .ledger-card {
    border-left: 3px solid var(--color-primary, #f58220);
    margin-bottom: .75rem;
}
.recouvrement-dashboard .ledger-head-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .65rem;
    flex-wrap: wrap;
    margin-bottom: .7rem;
}
.recouvrement-dashboard .ledger-pill {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    white-space: nowrap;
    border-radius: 999px;
    background: rgba(245,130,32,.12);
    color: var(--color-primary, #f58220);
    font-size: .62rem;
    font-weight: 900;
    padding: .32rem .62rem;
}
.recouvrement-dashboard .ledger-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .5rem;
    margin-bottom: .7rem;
}
.recouvrement-dashboard .ledger-tile {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .85rem;
    padding: .62rem .7rem;
}
.recouvrement-dashboard .ledger-tile span {
    display: block;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.recouvrement-dashboard .ledger-tile strong {
    display: block;
    margin-top: .18rem;
    font-family: var(--font-display, system-ui);
    font-size: 1.08rem;
    font-weight: 900;
    color: var(--color-text, #111827);
}
.recouvrement-dashboard .ledger-tile.success strong { color: var(--color-success, #16a34a); }
.recouvrement-dashboard .ledger-tile.danger strong { color: var(--color-error, #dc2626); }
.recouvrement-dashboard .ledger-tile.warning strong { color: var(--color-warning, #d97706); }

.recouvrement-dashboard .payments-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .5rem;
    margin-bottom: .65rem;
}

@media (max-width: 900px) {
    .recouvrement-dashboard .ledger-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .recouvrement-dashboard .payments-summary { grid-template-columns: 1fr; }
}

@media (max-width: 1600px) {
    .recouvrement-dashboard .analysis-grid { grid-template-columns: 1fr; }
}
@media (max-width: 1100px) {
    .recouvrement-dashboard .table-grid,
    .recouvrement-dashboard .ops-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 760px) {
    .recouvrement-dashboard { padding: .55rem; }
    .recouvrement-dashboard .lease-kpi-grid { grid-template-columns: 1fr; }
    .recouvrement-dashboard .search-card { align-items: stretch; flex-direction: column; }
    .recouvrement-dashboard .metric-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
<div class="recouvrement-dashboard" data-recouvrement-dashboard>
    <div class="dash-top">
        <div class="dash-title">
            <h1><i class="fas fa-chart-line"></i> Dashboard Recouvrement</h1>
            <p>Vue par période, évolution du recouvrement et suivi des coupures moteur.</p>
        </div>
        <div class="partner-scope"><i class="fas fa-building"></i> Partenaire connecté</div>
    </div>

    @if($pageError)
        <div class="dashboard-alert error"><i class="fas fa-triangle-exclamation"></i><div>{{ $pageError }}</div></div>
    @endif

    @if(!empty($warnings))
        @foreach($warnings as $warning)
            <div class="dashboard-alert"><i class="fas fa-info-circle"></i><div>{{ $warning }}</div></div>
        @endforeach
    @endif

    <form id="leaseDashboardFilters" class="ui-card period-filter-bar" method="GET" action="{{ route('leases.dashboard') }}">
        <div class="period-filter-row">
            <div class="search-field">
                <i class="fas fa-search"></i>
                <input id="dashboardSearch" class="search-input" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Rechercher un chauffeur, une moto, un contrat, un paiement..." data-filter="search">
            </div>

            <div class="period-select-wrap">
                <select class="period-select" name="period" data-period-select aria-label="Filtrer le dashboard par période">
                    @foreach([
                        'today' => 'Aujourd’hui',
                        'yesterday' => 'Hier',
                        'week' => 'Cette semaine',
                        'month' => 'Ce mois',
                        'year' => 'Cette année',
                        'date' => 'Date spécifique',
                        'range' => 'Plage de dates',
                    ] as $key => $label)
                        <option value="{{ $key }}" @selected($selectedPeriodKey === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <span class="period-chip">{{ $period['label'] ?? 'Aujourd’hui' }} · {{ $period['start_date'] ?? now()->toDateString() }} @if(($period['end_date'] ?? null) && ($period['end_date'] ?? null) !== ($period['start_date'] ?? null)) → {{ $period['end_date'] }} @endif</span>
        </div>

        <div class="date-filter-group {{ $selectedPeriodKey === 'date' ? 'is-visible' : '' }}" data-date-filter="date">
            <span class="date-label">Date</span>
            <input type="date" name="date" value="{{ $selectedDate }}" data-period-date>
            <button type="submit" class="filter-submit">Appliquer</button>
        </div>

        <div class="date-filter-group {{ $selectedPeriodKey === 'range' ? 'is-visible' : '' }}" data-date-filter="range">
            <span class="date-label">Du</span>
            <input type="date" name="start_date" value="{{ $selectedStartDate }}" data-period-start>
            <span class="date-label">au</span>
            <input type="date" name="end_date" value="{{ $selectedEndDate }}" data-period-end>
            <button type="submit" class="filter-submit">Appliquer</button>
        </div>
    </form>

    <div class="lease-kpi-grid">
        <div class="ui-card lkpi lkpi-highlight">
            <div>
                <p class="lkpi-label">Encaissé aujourd’hui</p>
                <p class="lkpi-value success">{{ $money($kpis['today_cash_amount'] ?? 0) }}</p>
                <div class="kpi-note">Cash réel · {{ $kpis['today_cash_payments_count'] ?? 0 }} paiements · {{ $kpis['today_cash_drivers_count'] ?? 0 }} chauffeurs · fixe, indépendant du filtre ci-dessus</div>
            </div>
            <div class="lkpi-icon green"><i class="fas fa-sack-dollar"></i></div>
        </div>
        <div class="ui-card lkpi">
            <div><p class="lkpi-label">Taux recouvrement</p><p class="lkpi-value success">{{ $kpis['recovery_rate'] ?? 0 }} %</p><div class="kpi-note">{{ $kpis['drivers_paid'] ?? 0 }} payés / {{ $kpis['total_expected_drivers'] ?? 0 }} attendus</div></div>
            <div class="lkpi-icon green"><i class="fas fa-chart-pie"></i></div>
        </div>
        <div class="ui-card lkpi">
            <div><p class="lkpi-label">Montant attendu</p><p class="lkpi-value">{{ $money($kpis['expected_amount'] ?? 0) }}</p><div class="kpi-note">Dû sur les échéances de la période (date d’échéance)</div></div>
            <div class="lkpi-icon grey"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>
        <div class="ui-card lkpi">
            <div><p class="lkpi-label">Payé (échéances période)</p><p class="lkpi-value success">{{ $money($kpis['paid_amount'] ?? 0) }}</p><div class="kpi-note">Sur ces échéances précises, pas le cash du jour</div></div>
            <div class="lkpi-icon green"><i class="fas fa-coins"></i></div>
        </div>
        <div class="ui-card lkpi">
            <div><p class="lkpi-label">Reste à payer</p><p class="lkpi-value danger">{{ $money($kpis['remaining_amount'] ?? 0) }}</p><div class="kpi-note">{{ $kpis['drivers_unpaid'] ?? 0 }} chauffeurs concernés</div></div>
            <div class="lkpi-icon red"><i class="fas fa-exclamation-circle"></i></div>
        </div>
        <div class="ui-card lkpi">
            <div><p class="lkpi-label">Motos déjà utilisées</p><p class="lkpi-value warning">{{ $kpis['vehicles_used'] ?? 0 }}</p><div class="kpi-note">Avec contrat actif ou passé</div></div>
            <div class="lkpi-icon orange"><i class="fas fa-motorcycle"></i></div>
        </div>
        <div class="ui-card lkpi">
            <div><p class="lkpi-label">Motos jamais utilisées</p><p class="lkpi-value">{{ $kpis['vehicles_never_used'] ?? 0 }}</p><div class="kpi-note">Sur {{ $kpis['vehicles_count'] ?? 0 }} motos</div></div>
            <div class="lkpi-icon blue"><i class="fas fa-warehouse"></i></div>
        </div>
    </div>

    <div class="ui-card card-pad ledger-card">
        <div class="ledger-head-top">
            <div>
                <h2 class="card-title"><i class="fas fa-scale-balanced"></i> Ardoise globale par chauffeur</h2>
                <p class="card-subtitle">Impayés réels, toutes échéances confondues — indépendant de la période sélectionnée en haut de page.</p>
            </div>
            <span class="ledger-pill"><i class="fas fa-thumbtack"></i> Toujours à jour</span>
        </div>

        <div class="ledger-summary">
            <div class="ledger-tile success">
                <span>Chauffeurs à jour</span>
                <strong>{{ $overdueLedger['up_to_date_count'] ?? '—' }}</strong>
            </div>
            <div class="ledger-tile danger">
                <span>Chauffeurs en retard</span>
                <strong>{{ $overdueLedger['overdue_count'] ?? 0 }}</strong>
            </div>
            <div class="ledger-tile danger">
                <span>Dette totale cumulée</span>
                <strong>{{ $money($overdueLedger['total_due'] ?? 0) }}</strong>
            </div>
            <div class="ledger-tile warning">
                <span>Retard le plus ancien</span>
                <strong>{{ $overdueLedger['oldest_overdue_days'] ?? 0 }} j.</strong>
            </div>
        </div>

        <div class="card-head" style="margin-bottom:.5rem;">
            <div class="chart-mode-toggle" aria-label="Changer la vue de l’ardoise">
                <button type="button" class="chart-mode-btn active" data-ledger-view="driver">
                    <i class="fas fa-user"></i> Par chauffeur
                </button>
                <button type="button" class="chart-mode-btn" data-ledger-view="contract">
                    <i class="fas fa-file-contract"></i> Par contrat
                </button>
            </div>
            <div class="card-actions">
                <label class="block-search-field" aria-label="Rechercher dans l’ardoise globale">
                    <i class="fas fa-search"></i>
                    <input class="block-search-input" type="search" placeholder="Rechercher un chauffeur, une moto..." data-table-filter="#ledgerTable, #ledgerByContractTable">
                </label>
            </div>
        </div>

        <div class="table-scroll" style="max-height:340px;" data-ledger-panel="driver">
            <table class="dashboard-table" id="ledgerTable">
                <thead><tr><th>Chauffeur</th><th>Véhicule</th><th>Impayés</th><th>Montant dû</th><th>Type</th><th>Échéance la plus ancienne</th><th>Retard</th></tr></thead>
                <tbody>
                @forelse(($overdueLedger['drivers'] ?? []) as $row)
                    <tr data-search="{{ $row['search'] ?? '' }}">
                        <td><span class="driver-name">{{ $row['driver'] ?? '—' }}</span></td>
                        <td>{{ $row['vehicle'] ?? '—' }}</td>
                        <td class="amount-danger">{{ $row['unpaid_count'] ?? 0 }}</td>
                        <td class="amount-danger">{{ $money($row['amount_due'] ?? 0) }}</td>
                        <td>{{ $row['types'] ?? '—' }}</td>
                        <td>{{ $row['oldest_due_date'] ?? '—' }}</td>
                        <td><span class="dash-badge {{ $badgeClass($row['urgency']['badge'] ?? null) }}">{{ $row['urgency']['label'] ?? '—' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state">Aucun impayé en cours — tous les chauffeurs sont à jour.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-scroll" style="max-height:340px; display:none;" data-ledger-panel="contract">
            <table class="dashboard-table" id="ledgerByContractTable">
                <thead><tr><th>Chauffeur</th><th>Véhicule</th><th>Type de contrat</th><th>Montant dû</th><th>Échéance</th><th>Retard</th></tr></thead>
                <tbody>
                @forelse(($overdueLedger['contracts'] ?? []) as $row)
                    <tr data-search="{{ $row['search'] ?? '' }}">
                        <td><span class="driver-name">{{ $row['driver'] ?? '—' }}</span></td>
                        <td>{{ $row['vehicle'] ?? '—' }}</td>
                        <td>{{ $row['type'] ?? '—' }}</td>
                        <td class="amount-danger">{{ $money($row['amount_due'] ?? 0) }}</td>
                        <td>{{ $row['due_date'] ?? '—' }}</td>
                        <td><span class="dash-badge {{ $badgeClass($row['urgency']['badge'] ?? null) }}">{{ $row['urgency']['label'] ?? '—' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state">Aucun contrat impayé en cours.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="page-grid">
        <div class="analysis-grid">
            <div class="ui-card card-pad">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><i class="fas fa-chart-column"></i> Évolution du recouvrement</h2>
                        <p class="card-subtitle">Regroupement automatique selon la période sélectionnée.</p>
                    </div>
                </div>

                <div class="chart-switch-head">
                    <div class="small-chart-title">
                        <strong id="weeklyChartTitle">Montants {{ $recoveryChart['grouping_label'] ?? 'par jour' }}</strong>
                        <span id="weeklyChartSubtitle">Attendu · Collecté · Reste</span>
                    </div>

                    <div class="chart-mode-toggle" aria-label="Changer le type de graphique">
                        <button type="button" class="chart-mode-btn active" data-week-chart-mode="bar">
                            <i class="fas fa-chart-column"></i> Barres
                        </button>
                        <button type="button" class="chart-mode-btn" data-week-chart-mode="line">
                            <i class="fas fa-chart-line"></i> Courbe
                        </button>
                    </div>
                </div>

                <div class="chart-wrap"><canvas id="weeklyRecoveryChart"></canvas></div>
            </div>

            <div class="ui-card card-pad">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><i class="fas fa-ranking-star"></i> Collecte par type</h2>
                        <p class="card-subtitle">Contrat principal d’abord, puis sous-contrats.</p>
                    </div>
                </div>
                <div class="type-progress-list">
                    @forelse(($typeRecovery['items'] ?? []) as $item)
                        <div class="type-progress-row" data-search="{{ mb_strtolower(($item['label'] ?? '') . ' ' . ($item['kind'] ?? ''), 'UTF-8') }}">
                            <div class="type-progress-top">
                                <div>
                                    <div class="type-name">{{ $item['label'] ?? 'Contrat' }}</div>
                                    <div class="type-kind">{{ $item['kind'] ?? 'type' }}</div>
                                </div>
                                <div class="type-rate">{{ $item['rate'] ?? 0 }} %</div>
                            </div>
                            <div class="progress-track"><div class="progress-fill" style="width: {{ min(100, max(0, (int) ($item['rate'] ?? 0))) }}%;"></div></div>
                            <div class="type-progress-bottom">
                                <span>Collecté : {{ $money($item['paid'] ?? 0) }}</span>
                                <span>Attendu : {{ $money($item['expected'] ?? 0) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">Aucune collecte par type pour la période sélectionnée.</div>
                    @endforelse
                </div>
            </div>

            <div class="ui-card card-pad">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><i class="fas fa-shield-alt"></i> Sécurité coupure</h2>
                        <p class="card-subtitle">Actions sur la période sélectionnée · {{ $period['label'] ?? 'Aujourd’hui' }}.</p>
                    </div>
                </div>
                <div class="metric-grid">
                    <div class="metric-tile"><span>Queues planifiées</span><strong>{{ $cutoffSummary['planned'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Attente sécurité</span><strong>{{ $cutoffSummary['waiting_stop'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Commandes envoyées</span><strong>{{ $cutoffSummary['command_sent'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Coupures confirmées</span><strong>{{ $cutoffSummary['confirmed'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Pardons avant coupure</span><strong>{{ $cutoffSummary['forgiven_before_cut'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Relances après pardon</span><strong>{{ $cutoffSummary['reactivated'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Échecs</span><strong>{{ $cutoffSummary['failed'] ?? 0 }}</strong></div>
                </div>

                <p class="card-subtitle" style="margin-top:.75rem;">État actuel des règles configurées — indépendant de la période sélectionnée ci-dessus.</p>
                <div class="metric-grid" style="margin-top:.35rem;">
                    <div class="metric-tile"><span>Règles actives</span><strong>{{ $cutoffSummary['active_rules'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Règles inactives</span><strong>{{ $cutoffSummary['disabled_rules'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Sans règle</span><strong>{{ $cutoffSummary['contracts_without_rule'] ?? 0 }}</strong></div>
                </div>
            </div>
        </div>

        <div class="table-grid">
            <div class="ui-card card-pad">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><i class="fas fa-users"></i> Chauffeurs à suivre</h2>
                        <p class="card-subtitle">Basé sur la date d’échéance de la période sélectionnée — pas la date de paiement.</p>
                    </div>
                    <div class="card-actions">
                        <label class="block-search-field" aria-label="Rechercher dans les chauffeurs à suivre">
                            <i class="fas fa-search"></i>
                            <input class="block-search-input" type="search" placeholder="Rechercher..." data-table-filter="#driversTable">
                        </label>
                    </div>
                </div>
                <div class="table-scroll">
                    <table class="dashboard-table" id="driversTable">
                        <thead><tr><th>Chauffeur</th><th>Véhicule</th><th>Impayés</th><th>Montant dû</th><th>Type</th><th>Coupure</th><th>Statut</th></tr></thead>
                        <tbody>
                        @forelse(($tables['drivers_risk'] ?? []) as $row)
                            <tr data-search="{{ $row['search'] ?? '' }}">
                                <td><span class="driver-name">{{ $row['driver'] ?? '—' }}</span><span class="small-muted">{{ $row['last_info'] ?? '' }}</span></td>
                                <td>{{ $row['vehicle'] ?? '—' }}</td>
                                <td class="{{ ($row['unpaid_count'] ?? 0) > 0 ? 'amount-danger' : 'amount-success' }}">{{ $row['unpaid_count'] ?? 0 }}</td>
                                <td class="{{ ($row['amount_due'] ?? 0) > 0 ? 'amount-danger' : 'amount-success' }}">{{ $money($row['amount_due'] ?? 0) }}</td>
                                <td>{{ $row['types'] ?? '—' }}</td>
                                <td><span class="dash-badge {{ $badgeClass($row['cutoff_status']['badge'] ?? null) }}">{{ $row['cutoff_status']['label'] ?? '—' }}</span></td>
                                <td><span class="dash-badge {{ $badgeClass($row['status']['badge'] ?? null) }}">{{ $row['status']['label'] ?? '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state">Aucun chauffeur à suivre sur cette période.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ui-card card-pad">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><i class="fas fa-money-check-alt"></i> Paiements du jour</h2>
                        <p class="card-subtitle">Toujours aujourd’hui (date de paiement réelle) — fixe, ne bouge pas avec le filtre de période ci-dessus.</p>
                    </div>
                    <div class="card-actions">
                        <label class="block-search-field" aria-label="Rechercher dans les paiements du jour">
                            <i class="fas fa-search"></i>
                            <input class="block-search-input" type="search" placeholder="Rechercher..." data-table-filter="#paymentsTable, #paymentsByDriverTable">
                        </label>
                    </div>
                </div>

                <div class="payments-summary">
                    <div class="ledger-tile success">
                        <span>Total encaissé</span>
                        <strong>{{ $money($paymentsSummary['total_amount'] ?? 0) }}</strong>
                    </div>
                    <div class="ledger-tile">
                        <span>Paiements validés</span>
                        <strong>{{ $paymentsSummary['payments_count'] ?? 0 }}</strong>
                    </div>
                    <div class="ledger-tile">
                        <span>Chauffeurs distincts</span>
                        <strong>{{ $paymentsSummary['drivers_count'] ?? 0 }}</strong>
                    </div>
                </div>

                <div class="chart-switch-head" style="margin-bottom:.5rem;">
                    <div class="small-chart-title">
                        <strong>Détail des paiements</strong>
                        <span>Basculer entre transactions et total par chauffeur</span>
                    </div>
                    <div class="chart-mode-toggle" aria-label="Changer la vue des paiements du jour">
                        <button type="button" class="chart-mode-btn active" data-payments-view="list">
                            <i class="fas fa-list"></i> Liste
                        </button>
                        <button type="button" class="chart-mode-btn" data-payments-view="driver">
                            <i class="fas fa-user-check"></i> Par chauffeur
                        </button>
                    </div>
                </div>

                <div class="table-scroll payments" data-payments-panel="list">
                    <table class="dashboard-table" id="paymentsTable">
                        <thead><tr><th>Date de paiement</th><th>Chauffeur</th><th>Véhicule</th><th>Type contrat</th><th>Montant</th><th>Méthode</th><th>Statut</th></tr></thead>
                        <tbody>
                        @forelse(($tables['payments_today'] ?? []) as $payment)
                            <tr data-search="{{ $payment['search'] ?? '' }}">
                                <td>{{ $payment['date_paiement'] ?? '—' }}</td>
                                <td><span class="driver-name">{{ $payment['driver'] ?? '—' }}</span></td>
                                <td>{{ $payment['lease'] ?? '—' }}</td>
                                <td>{{ $payment['contract_type'] ?? '—' }}</td>
                                <td class="amount-success">{{ $money($payment['amount'] ?? 0) }}</td>
                                <td>{{ $payment['method'] ?? '—' }}</td>
                                <td><span class="dash-badge {{ $badgeClass($payment['status']['badge'] ?? null) }}">{{ $payment['status']['label'] ?? '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state">Aucun paiement enregistré aujourd’hui.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="table-scroll payments" data-payments-panel="driver" style="display:none;">
                    <table class="dashboard-table" id="paymentsByDriverTable">
                        <thead><tr><th>Chauffeur</th><th>Montant total payé</th><th>Nb paiements</th><th>Dernier paiement</th></tr></thead>
                        <tbody>
                        @forelse(($paymentsSummary['by_driver'] ?? []) as $row)
                            <tr data-search="{{ $row['search'] ?? '' }}">
                                <td><span class="driver-name">{{ $row['driver'] ?? '—' }}</span></td>
                                <td class="amount-success">{{ $money($row['amount_total'] ?? 0) }}</td>
                                <td>{{ $row['payments_count'] ?? 0 }}</td>
                                <td>{{ $row['last_payment_at'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><div class="empty-state">Aucun paiement enregistré aujourd’hui.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="ops-grid">
            <div class="ui-card card-pad">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><i class="fas fa-file-contract"></i> Règles par contrat</h2>
                        <p class="card-subtitle">Contrat principal et sous-contrats indépendants — état actuel, indépendant de la période sélectionnée.</p>
                    </div>
                </div>
                <div class="metric-grid" style="margin-bottom:.55rem;">
                    <div class="metric-tile"><span>Principaux actifs</span><strong>{{ $contractsSummary['main_active'] ?? 0 }}</strong></div>
                    <div class="metric-tile"><span>Sous-contrats actifs</span><strong>{{ $contractsSummary['sub_active'] ?? 0 }}</strong></div>
                </div>
                <div class="table-scroll" style="max-height:360px;">
                    <table class="dashboard-table" id="rulesTable">
                        <thead><tr><th>Contrat</th><th>Véhicule</th><th>Type</th><th>Nature</th><th>Règle</th><th>Heure</th><th>Sécurité</th></tr></thead>
                        <tbody>
                        @forelse(($tables['contract_rules'] ?? []) as $ruleRow)
                            <tr data-search="{{ $ruleRow['search'] ?? '' }}">
                                <td>{{ $ruleRow['contract'] ?? '—' }}</td>
                                <td>{{ $ruleRow['vehicle'] ?? '—' }}</td>
                                <td>{{ $ruleRow['type'] ?? '—' }}</td>
                                <td>{{ $ruleRow['kind'] ?? '—' }}</td>
                                <td><span class="dash-badge {{ $badgeClass($ruleRow['rule_status']['badge'] ?? null) }}">{{ $ruleRow['rule_status']['label'] ?? '—' }}</span></td>
                                <td>{{ $ruleRow['cutoff_time'] ?? '—' }}</td>
                                <td>{{ ($ruleRow['only_when_stopped'] ?? true) ? 'Arrêt obligatoire' : 'À revoir' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state">Aucun contrat local synchronisé avec une règle de coupure.</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ui-card card-pad">
                <div class="card-head">
                    <div>
                        <h2 class="card-title"><i class="fas fa-power-off"></i> Dernières décisions coupure</h2>
                        <p class="card-subtitle">Queues, annulations, pardons et confirmations sur la période sélectionnée · {{ $period['label'] ?? 'Aujourd’hui' }}.</p>
                    </div>
                </div>
                <div class="timeline-scroll">
                    <div class="timeline" id="cutoffTimeline">
                        @forelse(($tables['cutoffs'] ?? []) as $cutoff)
                            <div class="timeline-item" data-search="{{ $cutoff['search'] ?? '' }}">
                                <div class="timeline-icon"><i class="fas fa-power-off"></i></div>
                                <div>
                                    <p class="timeline-title">{{ $cutoff['vehicle'] ?? 'Véhicule' }} · {{ $cutoff['type'] ?? 'Contrat' }}</p>
                                    <p class="timeline-desc">{{ $cutoff['reason'] ?? '' }} {{ $cutoff['updated_at'] ?? '' }}</p>
                                </div>
                                <span class="dash-badge {{ $badgeClass($cutoff['badge'] ?? null) }}">{{ $cutoff['status_label'] ?? '—' }}</span>
                            </div>
                        @empty
                            <div class="timeline-item">
                                <div class="timeline-icon"><i class="fas fa-check"></i></div>
                                <div><p class="timeline-title">Aucune coupure à suivre</p><p class="timeline-desc">Aucune action enregistrée aujourd’hui.</p></div>
                                <span class="dash-badge success">OK</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>window.leaseDashboardData = @json($dashboard);</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-recouvrement-dashboard]');
    if (!root) return;

    const dashboardData = window.leaseDashboardData || {};
    const recovery = dashboardData?.charts?.recovery || {};
    const periodForm = document.getElementById('leaseDashboardFilters');
    const periodSelect = periodForm?.querySelector('[data-period-select]');
    const dateFilterGroups = periodForm ? Array.from(periodForm.querySelectorAll('[data-date-filter]')) : [];

    function togglePeriodDateFields() {
        if (!periodSelect) return;
        const selected = periodSelect.value || 'today';
        dateFilterGroups.forEach(group => {
            group.classList.toggle('is-visible', group.dataset.dateFilter === selected);
        });
    }

    periodSelect?.addEventListener('change', function () {
        togglePeriodDateFields();
        if (!['date', 'range'].includes(this.value)) {
            periodForm?.submit();
        }
    });

    togglePeriodDateFields();

    /**
     * Affichage par défaut : diagramme à bâtons.
     * Sans cette valeur initiale, le graphique peut rester vide tant que
     * l'utilisateur n'a pas cliqué sur un bouton de changement de mode.
     */
    let weeklyChartMode = 'bar';

    function cssVar(name, fallback) {
        const bodyValue = getComputedStyle(document.body).getPropertyValue(name).trim();
        const rootValue = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return bodyValue || rootValue || fallback;
    }

    function setupCanvas(canvas) {
        const parent = canvas.parentElement;
        const rect = parent.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const width = Math.max(1, rect.width);
        const height = Math.max(1, rect.height);
        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        return { ctx, width, height };
    }

    function roundRect(ctx, x, y, w, h, r) {
        const radius = Math.min(r, Math.abs(w) / 2, Math.abs(h) / 2);
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.arcTo(x + w, y, x + w, y + h, radius);
        ctx.arcTo(x + w, y + h, x, y + h, radius);
        ctx.arcTo(x, y + h, x, y, radius);
        ctx.arcTo(x, y, x + w, y, radius);
        ctx.closePath();
    }

    function drawEmpty(ctx, width, height, message) {
        ctx.clearRect(0, 0, width, height);
        ctx.fillStyle = cssVar('--color-secondary-text', '#6b7280');
        ctx.font = '12px Lato, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(message, width / 2, height / 2);
    }

    function drawRecoveryBars() {
        const canvas = document.getElementById('weeklyRecoveryChart');
        if (!canvas) return;
        const { ctx, width, height } = setupCanvas(canvas);
        const labels = recovery.labels || [];
        const expected = recovery.expected || [];
        const paid = recovery.paid || [];
        const remaining = recovery.remaining || [];

        if (!labels.length) return drawEmpty(ctx, width, height, 'Aucune donnée sur cette période');

        const primary = cssVar('--color-primary', '#f58220');
        const success = cssVar('--color-success', '#16a34a');
        const error = cssVar('--color-error', '#dc2626');
        const muted = cssVar('--color-secondary-text', '#64748b');
        const border = cssVar('--color-border-subtle', '#e2e8f0');
        const max = Math.max(1, ...expected, ...paid, ...remaining) * 1.15;
        const p = { top: 18, right: 10, bottom: 28, left: 40 };
        const chartW = width - p.left - p.right;
        const chartH = height - p.top - p.bottom;

        ctx.clearRect(0, 0, width, height);
        ctx.font = '10px Lato, Arial, sans-serif';

        for (let i = 0; i <= 4; i++) {
            const y = p.top + chartH - chartH * i / 4;
            ctx.strokeStyle = border;
            ctx.beginPath(); ctx.moveTo(p.left, y); ctx.lineTo(width - p.right, y); ctx.stroke();
            ctx.fillStyle = muted; ctx.textAlign = 'right';
            const label = Math.round(max * i / 4 / 1000);
            ctx.fillText(label === 0 ? '0' : label + 'k', p.left - 6, y + 3);
        }

        const groupW = chartW / labels.length;
        const barW = Math.min(12, groupW / 5);
        labels.forEach((label, index) => {
            const xCenter = p.left + groupW * index + groupW / 2;
            [
                { value: expected[index] || 0, color: primary, offset: -barW - 3 },
                { value: paid[index] || 0, color: success, offset: 0 },
                { value: remaining[index] || 0, color: error, offset: barW + 3 },
            ].forEach(bar => {
                const h = chartH * bar.value / max;
                const x = xCenter + bar.offset - barW / 2;
                const y = p.top + chartH - h;
                ctx.fillStyle = bar.color;
                roundRect(ctx, x, y, barW, h, 4);
                ctx.fill();
            });
            ctx.fillStyle = muted; ctx.textAlign = 'center';
            ctx.fillText(label, xCenter, height - 8);
        });
    }

    function drawRecoveryLine() {
        const canvas = document.getElementById('weeklyRecoveryChart');
        if (!canvas) return;
        const { ctx, width, height } = setupCanvas(canvas);
        const labels = recovery.labels || [];
        const expected = recovery.expected || [];
        const paid = recovery.paid || [];
        const remaining = recovery.remaining || [];
        if (!labels.length) return drawEmpty(ctx, width, height, 'Aucune tendance sur cette période');

        const primary = cssVar('--color-primary', '#f58220');
        const success = cssVar('--color-success', '#16a34a');
        const error = cssVar('--color-error', '#dc2626');
        const muted = cssVar('--color-secondary-text', '#64748b');
        const border = cssVar('--color-border-subtle', '#e2e8f0');
        const max = Math.max(1, ...expected, ...paid, ...remaining) * 1.15;
        const p = { top: 36, right: 16, bottom: 28, left: 40 };
        const chartW = width - p.left - p.right;
        const chartH = height - p.top - p.bottom;

        function buildPoints(values) {
            return values.map((value, index) => ({
                x: p.left + (labels.length === 1 ? chartW / 2 : chartW * index / (labels.length - 1)),
                y: p.top + chartH - chartH * (Number(value || 0) / max),
                value: Number(value || 0),
            }));
        }

        function drawSeries(values, color) {
            const points = buildPoints(values);
            ctx.strokeStyle = color;
            ctx.lineWidth = 2.4;
            ctx.beginPath();
            points.forEach((point, index) => index === 0 ? ctx.moveTo(point.x, point.y) : ctx.lineTo(point.x, point.y));
            ctx.stroke();
            points.forEach(point => {
                ctx.fillStyle = color;
                ctx.beginPath(); ctx.arc(point.x, point.y, 3.5, 0, Math.PI * 2); ctx.fill();
            });
        }

        function drawLegend(items) {
            let x = p.left;
            const y = 16;
            ctx.font = '10px Lato, Arial, sans-serif';
            ctx.textAlign = 'left';
            items.forEach(item => {
                ctx.fillStyle = item.color;
                roundRect(ctx, x, y - 6, 10, 10, 3);
                ctx.fill();
                ctx.fillStyle = muted;
                ctx.fillText(item.label, x + 15, y + 3);
                x += ctx.measureText(item.label).width + 42;
            });
        }

        ctx.clearRect(0, 0, width, height);
        ctx.font = '10px Lato, Arial, sans-serif';
        for (let i = 0; i <= 4; i++) {
            const y = p.top + chartH - chartH * i / 4;
            ctx.strokeStyle = border; ctx.beginPath(); ctx.moveTo(p.left, y); ctx.lineTo(width - p.right, y); ctx.stroke();
            ctx.fillStyle = muted; ctx.textAlign = 'right';
            const label = Math.round(max * i / 4 / 1000);
            ctx.fillText(label === 0 ? '0' : label + 'k', p.left - 6, y + 3);
        }

        drawLegend([
            { label: 'Attendu', color: primary },
            { label: 'Collecté', color: success },
            { label: 'Reste', color: error },
        ]);

        drawSeries(expected, primary);
        drawSeries(paid, success);
        drawSeries(remaining, error);

        ctx.fillStyle = muted;
        ctx.textAlign = 'center';
        labels.forEach((label, index) => {
            const x = p.left + (labels.length === 1 ? chartW / 2 : chartW * index / (labels.length - 1));
            ctx.fillText(label, x, height - 8);
        });
    }


    function renderWeeklyChart() {
        const title = document.getElementById('weeklyChartTitle');
        const subtitle = document.getElementById('weeklyChartSubtitle');
        const buttons = root.querySelectorAll('[data-week-chart-mode]');

        buttons.forEach(button => {
            const isActive = button.dataset.weekChartMode === weeklyChartMode;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        const groupingLabel = recovery.grouping_label || 'par jour';

        if (weeklyChartMode === 'line') {
            if (title) title.textContent = 'Tendance des montants ' + groupingLabel;
            if (subtitle) subtitle.textContent = 'Attendu · Collecté · Reste';
            drawRecoveryLine();
            return;
        }

        if (title) title.textContent = 'Montants ' + groupingLabel;
        if (subtitle) subtitle.textContent = 'Attendu · Collecté · Reste';
        drawRecoveryBars();
    }

    root.querySelectorAll('[data-week-chart-mode]').forEach(button => {
        button.addEventListener('click', function () {
            weeklyChartMode = this.dataset.weekChartMode || 'bar';
            renderWeeklyChart();
        });
    });

    function rowMatches(row, search) {
        const haystack = ((row.dataset.search || '') + ' ' + row.textContent).toLowerCase();
        return !search || haystack.includes(search);
    }

    function applySearch() {
        const globalSearch = (root.querySelector('[data-filter="search"]')?.value || '').toLowerCase().trim();

        root.querySelectorAll('[data-search], #rulesTable tbody tr, .type-progress-row, .timeline-item').forEach(row => {
            row.style.display = rowMatches(row, globalSearch) ? '' : 'none';
        });

        root.querySelectorAll('[data-table-filter]').forEach(input => {
            const tables = root.querySelectorAll(input.dataset.tableFilter || '');
            if (!tables.length) return;

            const localSearch = (input.value || '').toLowerCase().trim();
            tables.forEach(table => {
                table.querySelectorAll('tbody tr').forEach(row => {
                    row.style.display = rowMatches(row, globalSearch) && rowMatches(row, localSearch) ? '' : 'none';
                });
            });
        });
    }

    root.querySelectorAll('[data-payments-view]').forEach(button => {
        button.addEventListener('click', function () {
            const mode = this.dataset.paymentsView || 'list';

            root.querySelectorAll('[data-payments-view]').forEach(btn => {
                const isActive = btn === this;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            root.querySelectorAll('[data-payments-panel]').forEach(panel => {
                panel.style.display = panel.dataset.paymentsPanel === mode ? '' : 'none';
            });
        });
    });

    root.querySelectorAll('[data-ledger-view]').forEach(button => {
        button.addEventListener('click', function () {
            const mode = this.dataset.ledgerView || 'driver';

            root.querySelectorAll('[data-ledger-view]').forEach(btn => {
                const isActive = btn === this;
                btn.classList.toggle('active', isActive);
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            root.querySelectorAll('[data-ledger-panel]').forEach(panel => {
                panel.style.display = panel.dataset.ledgerPanel === mode ? '' : 'none';
            });
        });
    });

    root.querySelector('[data-filter="search"]')?.addEventListener('input', applySearch);
    root.querySelectorAll('[data-table-filter]').forEach(input => input.addEventListener('input', applySearch));

    let resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            renderWeeklyChart();
        }, 120);
    });

    requestAnimationFrame(function () {
        renderWeeklyChart();
        applySearch();

        // Deuxième rendu léger : utile lorsque la carte vient juste de finir
        // de prendre sa largeur après le chargement CSS.
        setTimeout(renderWeeklyChart, 120);
    });
});
</script>
@endpush
