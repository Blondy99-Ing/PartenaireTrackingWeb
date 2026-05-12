@extends('layouts.app')

@section('title', 'Dashboard Recouvrement')

@php
    $dashboard = $dashboard ?? [];

    $filters = $filters
        ?? ($dashboard['filters'] ?? [
            'period' => 'today',
            'type' => 'all',
            'status' => 'all',
            'search' => '',
        ]);

    $kpis = $dashboard['kpis'] ?? [];
    $priorities = $dashboard['priorities'] ?? [];
    $charts = $dashboard['charts'] ?? [];
    $tables = $dashboard['tables'] ?? [];
    $contractsSummary = $dashboard['contracts_summary'] ?? [];
    $cutoffSummary = $dashboard['cutoff_summary'] ?? [];
    $warnings = $dashboard['warnings'] ?? [];
    $period = $dashboard['period'] ?? [];
    $weekPeriod = $dashboard['week_period'] ?? [];
    $pageError = $pageError ?? null;

    $money = function ($value) {
        return number_format((float) $value, 0, ',', ' ') . ' FCFA';
    };

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

    $iconClass = function (?string $type) {
        return match ($type) {
            'danger' => 'danger',
            'warning' => 'warning',
            'info' => 'info',
            'success' => 'success',
            default => 'primary',
        };
    };

    $periodOptions = [
        'today' => 'Aujourd’hui',
        'yesterday' => 'Hier',
        'week' => '7 derniers jours',
        'month' => '30 derniers jours',
    ];

    $typeOptions = [
        'all' => 'Tous les types',
        'Moto' => 'Moto',
        'Téléphone' => 'Téléphone',
        'Parapluie' => 'Parapluie',
    ];

    $statusOptions = [
        'all' => 'Tous',
        'À relancer' => 'À relancer',
        'Coupure planifiée' => 'Coupure planifiée',
        'En attente arrêt' => 'En attente arrêt',
        'À jour' => 'À jour',
    ];

    $recoveryChart = $charts['recovery'] ?? [
        'labels' => [],
        'dates' => [],
        'expected' => [],
        'paid' => [],
        'remaining' => [],
    ];

    $paymentBreakdown = $charts['payment_breakdown'] ?? [
        'rate' => 0,
        'total' => 0,
        'items' => [],
    ];

    $typeBreakdown = $charts['type_breakdown'] ?? [
        'total' => 0,
        'items' => [],
    ];
@endphp

@push('styles')
<style>
.recouvrement-dashboard {
    padding: var(--sp-xl, 1.25rem);
    background: var(--color-bg, #f5f7fb);
    color: var(--color-text, #111827);
    min-height: calc(100vh - var(--navbar-h, 64px));
}

.recouvrement-dashboard .dash-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: .75rem;
}

.recouvrement-dashboard .dash-title h1 {
    font-family: var(--font-display, system-ui);
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--color-text, #111827);
    margin: 0;
    display: flex;
    align-items: center;
    gap: .5rem;
}

.recouvrement-dashboard .dash-title h1 i {
    color: var(--color-primary, #f58220);
}

.recouvrement-dashboard .dash-title p {
    font-size: .74rem;
    color: var(--color-secondary-text, #6b7280);
    margin: .22rem 0 0;
}

.recouvrement-dashboard .partner-scope {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    border-radius: var(--r-pill, 999px);
    background: var(--color-primary-light, rgba(245,130,32,.12));
    color: var(--color-primary, #f58220);
    font-family: var(--font-display, system-ui);
    font-size: .72rem;
    font-weight: 800;
    padding: .42rem .7rem;
}

.recouvrement-dashboard .page-grid {
    display: grid;
    gap: var(--dash-gap, 1rem);
}

.recouvrement-dashboard .filter-card {
    padding: .75rem;
    margin-bottom: .75rem;
}

.recouvrement-dashboard .filter-strip {
    display: grid;
    grid-template-columns: 180px 190px 190px minmax(220px, 1fr) auto;
    gap: .5rem;
    align-items: end;
}

.recouvrement-dashboard .filter-field {
    position: relative;
}

.recouvrement-dashboard .filter-label {
    display: block;
    font-family: var(--font-display, system-ui);
    font-size: .58rem;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #6b7280);
    margin: 0 0 .25rem .1rem;
}

.recouvrement-dashboard .filter-input,
.recouvrement-dashboard .filter-select {
    width: 100%;
    height: 38px;
    border: 1px solid var(--color-input-border, var(--color-border-subtle, #e5e7eb));
    background: var(--color-input-bg, var(--color-card, #fff));
    color: var(--color-text, #111827);
    border-radius: var(--r-md, .75rem);
    padding: 0 .65rem;
    font-size: .72rem;
    font-family: var(--font-body, system-ui);
    outline: none;
}

.recouvrement-dashboard .filter-input:focus,
.recouvrement-dashboard .filter-select:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary, #f58220) 18%, transparent);
}

.recouvrement-dashboard .filter-input {
    padding-left: 2rem;
}

.recouvrement-dashboard .filter-search-icon {
    position: absolute;
    left: .75rem;
    bottom: .68rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .7rem;
}

.recouvrement-dashboard .filter-actions {
    display: flex;
    align-items: center;
    gap: .4rem;
}

.recouvrement-dashboard .dashboard-btn {
    height: 38px;
    border: 1px solid var(--color-primary-border, var(--color-primary, #f58220));
    background: var(--color-primary, #f58220);
    color: #fff;
    border-radius: var(--r-md, .75rem);
    padding: 0 .8rem;
    font-family: var(--font-display, system-ui);
    font-size: .7rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    white-space: nowrap;
}

.recouvrement-dashboard .dashboard-btn.secondary {
    background: var(--color-card, #fff);
    color: var(--color-primary, #f58220);
}

.recouvrement-dashboard .lease-kpi-grid {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: .45rem;
    margin-bottom: .75rem;
}

.recouvrement-dashboard .lkpi {
    min-height: 86px;
    background: var(--color-card, #fff);
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: var(--r-lg, 1rem);
    padding: .45rem .65rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .4rem;
    transition: transform .15s, box-shadow .15s, border-color .15s;
    overflow: hidden;
    position: relative;
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.06));
}

.recouvrement-dashboard .lkpi:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md, 0 8px 20px rgba(0,0,0,.08));
    border-color: var(--color-primary-border, var(--color-primary, #f58220));
}

.recouvrement-dashboard .lkpi-left {
    min-width: 0;
    flex: 1;
}

.recouvrement-dashboard .lkpi-label {
    font-family: var(--font-display, system-ui);
    font-size: .6rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #6b7280);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}

.recouvrement-dashboard .lkpi-value {
    font-family: var(--font-display, system-ui);
    font-weight: 800;
    font-size: 1.02rem;
    line-height: 1.1;
    color: var(--color-primary, #f58220);
    margin: .08rem 0 0;
    white-space: nowrap;
}

.recouvrement-dashboard .lkpi-value.neutral { color: var(--color-text, #111827); }
.recouvrement-dashboard .lkpi-value.success { color: var(--color-success, #16a34a); }
.recouvrement-dashboard .lkpi-value.danger  { color: var(--color-error, #dc2626); }
.recouvrement-dashboard .lkpi-value.warning { color: var(--color-warning, #d97706); }
.recouvrement-dashboard .lkpi-value.info    { color: var(--color-info, #2563eb); }

.recouvrement-dashboard .kpi-note {
    margin-top: .25rem;
    font-size: .58rem;
    color: var(--color-secondary-text, #6b7280);
    font-weight: 700;
}

.recouvrement-dashboard .kpi-note.success { color: var(--color-success, #16a34a); }
.recouvrement-dashboard .kpi-note.danger { color: var(--color-error, #dc2626); }
.recouvrement-dashboard .kpi-note.warning { color: var(--color-warning, #d97706); }

.recouvrement-dashboard .lkpi-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--r-md, .75rem);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .82rem;
}

.recouvrement-dashboard .lkpi-icon.green { background: var(--color-success-bg, rgba(22,163,74,.12)); color: var(--color-success, #16a34a); }
.recouvrement-dashboard .lkpi-icon.red   { background: var(--color-error-bg, rgba(220,38,38,.12)); color: var(--color-error, #dc2626); }
.recouvrement-dashboard .lkpi-icon.blue  { background: var(--color-info-bg, rgba(37,99,235,.12)); color: var(--color-info, #2563eb); }
.recouvrement-dashboard .lkpi-icon.amber { background: var(--color-warning-bg, rgba(217,119,6,.14)); color: var(--color-warning, #d97706); }
.recouvrement-dashboard .lkpi-icon.grey  { background: rgba(107,114,128,.12); color: #6b7280; }

.recouvrement-dashboard .card-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .8rem;
}

.recouvrement-dashboard .card-title {
    font-family: var(--font-display, system-ui);
    font-size: .95rem;
    font-weight: 800;
    color: var(--color-text, #111827);
    margin: 0;
    display: flex;
    align-items: center;
    gap: .45rem;
}

.recouvrement-dashboard .card-title i {
    color: var(--color-primary, #f58220);
}

.recouvrement-dashboard .card-subtitle {
    font-size: .68rem;
    color: var(--color-secondary-text, #6b7280);
    margin: .12rem 0 0;
}

.recouvrement-dashboard .warning-list {
    display: grid;
    gap: .4rem;
    margin-bottom: .75rem;
}

.recouvrement-dashboard .dashboard-alert {
    border-radius: var(--r-md, .75rem);
    border: 1px solid var(--color-warning, #d97706);
    background: var(--color-warning-bg, rgba(217,119,6,.12));
    color: var(--color-text, #111827);
    padding: .65rem .75rem;
    font-size: .72rem;
    display: flex;
    align-items: flex-start;
    gap: .55rem;
}

.recouvrement-dashboard .dashboard-alert.error {
    border-color: var(--color-error, #dc2626);
    background: var(--color-error-bg, rgba(220,38,38,.12));
}

.recouvrement-dashboard .dashboard-alert i {
    color: var(--color-warning, #d97706);
    margin-top: .1rem;
}

.recouvrement-dashboard .dashboard-alert.error i {
    color: var(--color-error, #dc2626);
}

.recouvrement-dashboard .priority-grid {
    display: grid;
    grid-template-columns: 1.05fr 1fr 1fr;
    gap: var(--dash-gap, 1rem);
}

.recouvrement-dashboard .priority-card {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
}

.recouvrement-dashboard .priority-icon {
    width: 42px;
    height: 42px;
    border-radius: var(--r-md, .75rem);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.recouvrement-dashboard .priority-icon.danger { background: var(--color-error-bg, rgba(220,38,38,.12)); color: var(--color-error, #dc2626); }
.recouvrement-dashboard .priority-icon.warning { background: var(--color-warning-bg, rgba(217,119,6,.14)); color: var(--color-warning, #d97706); }
.recouvrement-dashboard .priority-icon.info { background: var(--color-info-bg, rgba(37,99,235,.12)); color: var(--color-info, #2563eb); }
.recouvrement-dashboard .priority-icon.success { background: var(--color-success-bg, rgba(22,163,74,.12)); color: var(--color-success, #16a34a); }
.recouvrement-dashboard .priority-icon.primary { background: var(--color-primary-light, rgba(245,130,32,.12)); color: var(--color-primary, #f58220); }

.recouvrement-dashboard .priority-content h3 {
    font-family: var(--font-display, system-ui);
    font-size: .9rem;
    font-weight: 800;
    margin: 0;
    color: var(--color-text, #111827);
}

.recouvrement-dashboard .priority-content p {
    font-size: .68rem;
    color: var(--color-secondary-text, #6b7280);
    margin: .25rem 0 .55rem;
    line-height: 1.45;
}

.recouvrement-dashboard .priority-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}

.recouvrement-dashboard .analysis-grid {
    display: grid;
    grid-template-columns: 1.25fr .85fr .8fr;
    gap: var(--dash-gap, 1rem);
}

.recouvrement-dashboard .chart-wrap {
    height: 225px;
    position: relative;
}

.recouvrement-dashboard .donut-layout {
    display: grid;
    grid-template-columns: 150px 1fr;
    align-items: center;
    gap: .8rem;
}

.recouvrement-dashboard .donut-wrap {
    width: 150px;
    height: 150px;
    position: relative;
}

.recouvrement-dashboard .donut-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.recouvrement-dashboard .donut-center strong {
    font-family: var(--font-display, system-ui);
    font-size: 1.25rem;
    color: var(--color-text, #111827);
}

.recouvrement-dashboard .donut-center span {
    font-size: .62rem;
    color: var(--color-secondary-text, #6b7280);
}

.recouvrement-dashboard .legend-list {
    display: grid;
    gap: .45rem;
}

.recouvrement-dashboard .legend-row,
.recouvrement-dashboard .metric-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .6rem;
    font-size: .72rem;
    color: var(--color-text, #111827);
}

.recouvrement-dashboard .legend-row span,
.recouvrement-dashboard .metric-row span {
    color: var(--color-secondary-text, #6b7280);
}

.recouvrement-dashboard .legend-row strong,
.recouvrement-dashboard .metric-row strong {
    font-family: var(--font-display, system-ui);
    font-weight: 800;
    color: var(--color-text, #111827);
}

.recouvrement-dashboard .dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 99px;
    margin-right: .35rem;
}

.recouvrement-dashboard .dot.primary { background: var(--color-primary, #f58220); }
.recouvrement-dashboard .dot.success { background: var(--color-success, #16a34a); }
.recouvrement-dashboard .dot.warning { background: var(--color-warning, #d97706); }
.recouvrement-dashboard .dot.danger,
.recouvrement-dashboard .dot.error { background: var(--color-error, #dc2626); }
.recouvrement-dashboard .dot.info { background: var(--color-info, #2563eb); }
.recouvrement-dashboard .dot.muted { background: var(--color-secondary-text, #6b7280); }

.recouvrement-dashboard .table-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: var(--dash-gap, 1rem);
}

.recouvrement-dashboard .ops-grid {
    display: grid;
    grid-template-columns: .9fr 1.1fr;
    gap: var(--dash-gap, 1rem);
}

.recouvrement-dashboard .table-scroll {
    overflow-x: auto;
}

.recouvrement-dashboard .dashboard-table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
}

.recouvrement-dashboard .dashboard-table th {
    font-family: var(--font-display, system-ui);
    font-size: .62rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--color-secondary-text, #6b7280);
    text-align: left;
    border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);
    padding: .55rem .45rem;
    white-space: nowrap;
}

.recouvrement-dashboard .dashboard-table td {
    font-size: .7rem;
    color: var(--color-text, #111827);
    border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);
    padding: .58rem .45rem;
    white-space: nowrap;
}

.recouvrement-dashboard .dashboard-table tbody tr:hover {
    background: var(--color-bg-subtle, rgba(148,163,184,.08));
}

.recouvrement-dashboard .driver-name {
    font-weight: 800;
    color: var(--color-text, #111827);
}

.recouvrement-dashboard .small-muted {
    display: block;
    font-size: .6rem;
    color: var(--color-secondary-text, #6b7280);
    margin-top: .05rem;
}

.recouvrement-dashboard .amount-danger { color: var(--color-error, #dc2626); font-weight: 800; }
.recouvrement-dashboard .amount-success { color: var(--color-success, #16a34a); font-weight: 800; }
.recouvrement-dashboard .amount-warning { color: var(--color-warning, #d97706); font-weight: 800; }

.recouvrement-dashboard .mini-action {
    border: 1px solid var(--color-primary-border, var(--color-primary, #f58220));
    background: var(--color-card, #fff);
    color: var(--color-primary, #f58220);
    border-radius: var(--r-pill, 999px);
    padding: .25rem .5rem;
    font-family: var(--font-display, system-ui);
    font-size: .64rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: .25rem;
}

.recouvrement-dashboard .mini-action:hover {
    background: var(--color-primary-light, rgba(245,130,32,.12));
}

.recouvrement-dashboard .timeline {
    display: grid;
    gap: .55rem;
}

.recouvrement-dashboard .timeline-item {
    display: grid;
    grid-template-columns: 34px 1fr auto;
    gap: .6rem;
    align-items: center;
    padding: .55rem;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: var(--r-md, .75rem);
    background: var(--color-bg, #f5f7fb);
}

.recouvrement-dashboard .timeline-icon {
    width: 34px;
    height: 34px;
    border-radius: var(--r-md, .75rem);
    display: flex;
    align-items: center;
    justify-content: center;
}

.recouvrement-dashboard .timeline-icon.warning { background: var(--color-warning-bg, rgba(217,119,6,.14)); color: var(--color-warning, #d97706); }
.recouvrement-dashboard .timeline-icon.info { background: var(--color-info-bg, rgba(37,99,235,.12)); color: var(--color-info, #2563eb); }
.recouvrement-dashboard .timeline-icon.success { background: var(--color-success-bg, rgba(22,163,74,.12)); color: var(--color-success, #16a34a); }
.recouvrement-dashboard .timeline-icon.danger { background: var(--color-error-bg, rgba(220,38,38,.12)); color: var(--color-error, #dc2626); }
.recouvrement-dashboard .timeline-icon.muted { background: var(--color-bg-subtle, rgba(148,163,184,.12)); color: var(--color-secondary-text, #6b7280); }

.recouvrement-dashboard .timeline-title {
    font-family: var(--font-display, system-ui);
    font-size: .76rem;
    font-weight: 800;
    color: var(--color-text, #111827);
    margin: 0;
}

.recouvrement-dashboard .timeline-desc {
    font-size: .63rem;
    color: var(--color-secondary-text, #6b7280);
    margin: .1rem 0 0;
    line-height: 1.45;
}

.recouvrement-dashboard .dash-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .25rem;
    border-radius: var(--r-pill, 999px);
    padding: .22rem .48rem;
    font-family: var(--font-display, system-ui);
    font-size: .62rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
}

.recouvrement-dashboard .dash-badge.success { background: var(--color-success-bg, rgba(22,163,74,.12)); color: var(--color-success, #16a34a); }
.recouvrement-dashboard .dash-badge.danger { background: var(--color-error-bg, rgba(220,38,38,.12)); color: var(--color-error, #dc2626); }
.recouvrement-dashboard .dash-badge.warning { background: var(--color-warning-bg, rgba(217,119,6,.14)); color: var(--color-warning, #d97706); }
.recouvrement-dashboard .dash-badge.info { background: var(--color-info-bg, rgba(37,99,235,.12)); color: var(--color-info, #2563eb); }
.recouvrement-dashboard .dash-badge.primary { background: var(--color-primary-light, rgba(245,130,32,.12)); color: var(--color-primary, #f58220); }
.recouvrement-dashboard .dash-badge.muted { background: var(--color-bg-subtle, rgba(148,163,184,.12)); color: var(--color-secondary-text, #6b7280); }

.recouvrement-dashboard .empty-state {
    border: 1px dashed var(--color-border-subtle, #e5e7eb);
    border-radius: var(--r-md, .75rem);
    padding: 1rem;
    text-align: center;
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}

@media (max-width: 1600px) {
    .recouvrement-dashboard .lease-kpi-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 1380px) {
    .recouvrement-dashboard .analysis-grid,
    .recouvrement-dashboard .table-grid,
    .recouvrement-dashboard .ops-grid,
    .recouvrement-dashboard .priority-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 900px) {
    .recouvrement-dashboard {
        padding: var(--sp-md, .9rem);
    }

    .recouvrement-dashboard .filter-strip {
        grid-template-columns: 1fr;
    }

    .recouvrement-dashboard .filter-actions {
        justify-content: stretch;
    }

    .recouvrement-dashboard .filter-actions .dashboard-btn {
        flex: 1;
        justify-content: center;
    }

    .recouvrement-dashboard .lease-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .recouvrement-dashboard .donut-layout {
        grid-template-columns: 1fr;
        justify-items: center;
    }

    .recouvrement-dashboard .timeline-item {
        grid-template-columns: 34px 1fr;
    }

    .recouvrement-dashboard .timeline-item > .dash-badge {
        grid-column: 2;
        justify-self: start;
    }
}

@media (max-width: 540px) {
    .recouvrement-dashboard .lease-kpi-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@section('content')
<div class="recouvrement-dashboard" data-recouvrement-dashboard>
    <div class="dash-top">
        <div class="dash-title">
            <h1>
                <i class="fas fa-chart-line"></i>
                Dashboard Recouvrement Flotte
            </h1>
            <p>
                Vue claire des versements, impayés, chauffeurs actifs/inactifs, relances et coupures moteur du partenaire connecté.
            </p>
        </div>

        <div class="partner-scope">
            <i class="fas fa-building"></i>
            <span>Partenaire connecté uniquement</span>
        </div>
    </div>

    @if($pageError)
        <div class="dashboard-alert error">
            <i class="fas fa-triangle-exclamation"></i>
            <div>{{ $pageError }}</div>
        </div>
    @endif

    @if(!empty($warnings))
        <div class="warning-list">
            @foreach($warnings as $warning)
                <div class="dashboard-alert">
                    <i class="fas fa-info-circle"></i>
                    <div>{{ $warning }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <form class="ui-card filter-card" method="GET" action="{{ route('leases.dashboard') }}">
        <div class="filter-strip">
            <div class="filter-field">
                <label class="filter-label" for="periodFilter">Période</label>
                <select id="periodFilter" class="filter-select" name="period" data-filter="period">
                    @foreach($periodOptions as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['period'] ?? 'today') === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="contractTypeFilter">Type de contrat</label>
                <select id="contractTypeFilter" class="filter-select" name="type" data-filter="type">
                    @foreach($typeOptions as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['type'] ?? 'all') === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="riskFilter">Statut opérationnel</label>
                <select id="riskFilter" class="filter-select" name="status" data-filter="status">
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['status'] ?? 'all') === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-field">
                <label class="filter-label" for="searchFilter">Recherche</label>
                <i class="fas fa-search filter-search-icon"></i>
                <input id="searchFilter"
                       class="filter-input"
                       type="search"
                       name="search"
                       value="{{ $filters['search'] ?? '' }}"
                       placeholder="Chauffeur, véhicule, lease..."
                       data-filter="search">
            </div>

            <div class="filter-actions">
                <button class="dashboard-btn" type="submit">
                    <i class="fas fa-filter"></i>
                    Appliquer
                </button>

                <a class="dashboard-btn secondary" href="{{ route('leases.dashboard') }}">
                    <i class="fas fa-rotate-left"></i>
                    Réinitialiser
                </a>
            </div>
        </div>
    </form>

    <div class="lease-kpi-grid">
        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Taux recouvrement</p>
                <p class="lkpi-value success" data-kpi="rate">
                    {{ $kpis['recovery_rate'] ?? 0 }} %
                </p>
                <div class="kpi-note success">
                    {{ $kpis['drivers_paid'] ?? 0 }} versé / {{ $kpis['total_expected_drivers'] ?? 0 }} attendus
                </div>
            </div>
            <div class="lkpi-icon green"><i class="fas fa-chart-pie"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Montant attendu</p>
                <p class="lkpi-value neutral" data-kpi="expected">
                    {{ $money($kpis['expected_amount'] ?? 0) }}
                </p>
                <div class="kpi-note">
                    {{ $period['label'] ?? 'Aujourd’hui' }}
                </div>
            </div>
            <div class="lkpi-icon grey"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Montant collecté</p>
                <p class="lkpi-value success" data-kpi="paid">
                    {{ $money($kpis['paid_amount'] ?? 0) }}
                </p>
                <div class="kpi-note success">Paiements validés</div>
            </div>
            <div class="lkpi-icon green"><i class="fas fa-coins"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Reste à payer</p>
                <p class="lkpi-value danger" data-kpi="remaining">
                    {{ $money($kpis['remaining_amount'] ?? 0) }}
                </p>
                <div class="kpi-note danger">
                    {{ $kpis['drivers_unpaid'] ?? 0 }} n’ayant pas versé
                </div>
            </div>
            <div class="lkpi-icon red"><i class="fas fa-exclamation-circle"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Chauffeurs actifs</p>
                <p class="lkpi-value success">
                    {{ $kpis['active_chauffeurs'] ?? 0 }}
                </p>
                <div class="kpi-note success">
                    {{ $kpis['active_contract_drivers'] ?? 0 }} avec contrat actif
                </div>
            </div>
            <div class="lkpi-icon green"><i class="fas fa-user-check"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Chauffeurs inactifs</p>
                <p class="lkpi-value neutral">
                    {{ $kpis['inactive_chauffeurs'] ?? 0 }}
                </p>
                <div class="kpi-note">
                    Hors activité côté recouvrement
                </div>
            </div>
            <div class="lkpi-icon grey"><i class="fas fa-user-slash"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">À relancer</p>
                <p class="lkpi-value warning" data-kpi="driversRisk">
                    {{ $kpis['drivers_to_call'] ?? 0 }}
                </p>
                <div class="kpi-note warning">
                    {{ $kpis['unpaid_leases_count'] ?? 0 }} leases impayés
                </div>
            </div>
            <div class="lkpi-icon amber"><i class="fas fa-phone-volume"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Coupures à suivre</p>
                <p class="lkpi-value info" data-kpi="cutoffs">
                    {{ $kpis['cutoffs_to_follow'] ?? 0 }}
                </p>
                <div class="kpi-note">
                    {{ $kpis['cutoffs_confirmed'] ?? 0 }} confirmées
                </div>
            </div>
            <div class="lkpi-icon blue"><i class="fas fa-power-off"></i></div>
        </div>
    </div>

    <div class="page-grid">
        <div class="priority-grid">
            @forelse($priorities as $priority)
                <div class="ui-card priority-card">
                    <div class="priority-icon {{ $iconClass($priority['type'] ?? null) }}">
                        <i class="{{ $priority['icon'] ?? 'fas fa-info-circle' }}"></i>
                    </div>

                    <div class="priority-content">
                        <h3>{{ $priority['title'] ?? 'Priorité' }}</h3>
                        <p>{{ $priority['description'] ?? '' }}</p>

                        @if(!empty($priority['badges']))
                            <div class="priority-meta">
                                @foreach($priority['badges'] as $badge)
                                    <span class="dash-badge {{ $badgeClass($badge['type'] ?? null) }}">
                                        {{ $badge['label'] ?? '' }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="ui-card priority-card">
                    <div class="priority-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="priority-content">
                        <h3>Aucune priorité critique</h3>
                        <p>Aucun chauffeur critique ou événement de coupure à traiter sur la période sélectionnée.</p>
                        <div class="priority-meta">
                            <span class="dash-badge success">Situation stable</span>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="analysis-grid">
            <div class="ui-card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">
                            <i class="fas fa-chart-column"></i>
                            Recouvrement semaine courante
                        </h2>
                        <p class="card-subtitle">
                            Attendu, collecté et reste à payer de lundi à dimanche.
                        </p>
                    </div>
                </div>

                <div class="chart-wrap">
                    <canvas id="recoveryChart"></canvas>
                </div>
            </div>

            <div class="ui-card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">
                            <i class="fas fa-chart-pie"></i>
                            Collecté vs reste à payer
                        </h2>
                        <p class="card-subtitle">Lecture rapide du taux de recouvrement.</p>
                    </div>
                </div>

                <div class="donut-layout">
                    <div class="donut-wrap">
                        <canvas id="typeDonut" width="150" height="150"></canvas>
                        <div class="donut-center">
                            <strong>{{ $paymentBreakdown['rate'] ?? 0 }} %</strong>
                            <span>recouvré</span>
                        </div>
                    </div>

                    <div class="legend-list">
                        @forelse(($paymentBreakdown['items'] ?? []) as $index => $item)
                            <div class="legend-row">
                                <span>
                                    <i class="dot {{ $item['badge'] ?? 'muted' }}"></i>
                                    {{ $item['label'] ?? '—' }}
                                </span>
                                <strong>{{ $item['percent'] ?? 0 }} %</strong>
                            </div>
                        @empty
                            <div class="legend-row">
                                <span><i class="dot muted"></i>Aucune donnée</span>
                                <strong>0 %</strong>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="ui-card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">
                            <i class="fas fa-shield-alt"></i>
                            Sécurité coupure
                        </h2>
                        <p class="card-subtitle">État des règles, queues et historiques de coupure.</p>
                    </div>
                </div>

                <div class="legend-list">
                    <div class="metric-row">
                        <span><i class="dot primary"></i>Coupures planifiées</span>
                        <strong>{{ $cutoffSummary['planned'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot warning"></i>En attente arrêt</span>
                        <strong>{{ $cutoffSummary['waiting_stop'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot info"></i>Commandes envoyées</span>
                        <strong>{{ $cutoffSummary['command_sent'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot success"></i>Coupures confirmées</span>
                        <strong>{{ $cutoffSummary['confirmed'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot muted"></i>Annulées paiement reçu</span>
                        <strong>{{ $cutoffSummary['cancelled_paid'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot error"></i>Échecs GPS</span>
                        <strong>{{ $cutoffSummary['gps_failed'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot primary"></i>Règles actives</span>
                        <strong>{{ $cutoffSummary['active_rules'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot success"></i>Véhicules avec règle</span>
                        <strong>{{ $cutoffSummary['vehicles_with_rules'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot error"></i>Véhicules sans règle</span>
                        <strong>{{ $cutoffSummary['vehicles_without_rules'] ?? 0 }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-grid">
            <div class="ui-card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">
                            <i class="fas fa-users"></i>
                            Chauffeurs à surveiller
                        </h2>
                        <p class="card-subtitle">Priorité relance, paiement et décision de coupure.</p>
                    </div>
                </div>

                <div class="table-scroll">
                    <table class="dashboard-table" id="driversTable">
                        <thead>
                        <tr>
                            <th>Chauffeur</th>
                            <th>Véhicule</th>
                            <th>Impayés</th>
                            <th>Montant dû</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Action utile</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse(($tables['drivers_risk'] ?? []) as $row)
                            <tr
                                data-type="{{ $row['types'] ?? '' }}"
                                data-status="{{ $row['status']['label'] ?? '' }}"
                                data-search="{{ $row['search'] ?? '' }}"
                            >
                                <td>
                                    <span class="driver-name">{{ $row['driver'] ?? '—' }}</span>
                                    <span class="small-muted">{{ $row['last_info'] ?? '' }}</span>
                                </td>
                                <td>{{ $row['vehicle'] ?? '—' }}</td>
                                <td class="{{ ($row['unpaid_count'] ?? 0) > 0 ? 'amount-danger' : 'amount-success' }}">
                                    {{ $row['unpaid_count'] ?? 0 }}
                                </td>
                                <td class="{{ ($row['amount_due'] ?? 0) > 0 ? 'amount-danger' : 'amount-success' }}">
                                    {{ $money($row['amount_due'] ?? 0) }}
                                </td>
                                <td>{{ $row['types'] ?? '—' }}</td>
                                <td>
                                    <span class="dash-badge {{ $badgeClass($row['status']['badge'] ?? null) }}">
                                        {{ $row['status']['label'] ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <button class="mini-action" type="button">
                                        <i class="fas fa-phone"></i>
                                        {{ $row['action'] ?? 'Suivre' }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        Aucun chauffeur à risque sur la période sélectionnée.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ui-card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">
                            <i class="fas fa-money-check-alt"></i>
                            Paiements sur la période
                        </h2>
                        <p class="card-subtitle">Derniers paiements validés ou en attente.</p>
                    </div>
                </div>

                <div class="table-scroll">
                    <table class="dashboard-table" style="min-width: 620px;">
                        <thead>
                        <tr>
                            <th>Heure</th>
                            <th>Chauffeur</th>
                            <th>Lease</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Statut</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse(($tables['payments_today'] ?? []) as $payment)
                            <tr>
                                <td>{{ $payment['time'] ?? '—' }}</td>
                                <td><span class="driver-name">{{ $payment['driver'] ?? '—' }}</span></td>
                                <td>{{ $payment['lease'] ?? '—' }}</td>
                                <td>{{ $money($payment['amount'] ?? 0) }}</td>
                                <td>{{ $payment['method'] ?? '—' }}</td>
                                <td>
                                    <span class="dash-badge {{ $badgeClass($payment['status']['badge'] ?? null) }}">
                                        {{ $payment['status']['label'] ?? '—' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        Aucun paiement enregistré sur la période.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="ops-grid">
            <div class="ui-card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">
                            <i class="fas fa-file-contract"></i>
                            Contrats du partenaire
                        </h2>
                        <p class="card-subtitle">Santé contractuelle utile pour le gestionnaire.</p>
                    </div>
                </div>

                <div class="legend-list">
                    <div class="metric-row">
                        <span><i class="dot primary"></i>Contrats principaux actifs</span>
                        <strong>{{ $contractsSummary['main_active'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot info"></i>Sous-contrats actifs</span>
                        <strong>{{ $contractsSummary['sub_active'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot success"></i>Contrats soldés</span>
                        <strong>{{ $contractsSummary['sold'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot warning"></i>Contrats suspendus</span>
                        <strong>{{ $contractsSummary['suspended'] ?? 0 }}</strong>
                    </div>
                    <div class="metric-row">
                        <span><i class="dot error"></i>Montant restant global</span>
                        <strong>{{ $money($contractsSummary['remaining_total'] ?? 0) }}</strong>
                    </div>
                </div>
            </div>

            <div class="ui-card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">
                            <i class="fas fa-power-off"></i>
                            Suivi des coupures moteur
                        </h2>
                        <p class="card-subtitle">
                            Chaque ligne explique quel type de contrat a déclenché l’action.
                        </p>
                    </div>
                </div>

                <div class="timeline" id="cutoffTimeline">
                    @forelse(($tables['cutoffs'] ?? []) as $cutoff)
                        <div
                            class="timeline-item"
                            data-type="{{ $cutoff['type'] ?? '' }}"
                            data-status="{{ $cutoff['status_label'] ?? '' }}"
                            data-search="{{ mb_strtolower(($cutoff['vehicle'] ?? '') . ' ' . ($cutoff['lease'] ?? '') . ' ' . ($cutoff['type'] ?? ''), 'UTF-8') }}"
                        >
                            <div class="timeline-icon {{ $badgeClass($cutoff['badge'] ?? null) }}">
                                <i class="fas fa-power-off"></i>
                            </div>

                            <div>
                                <p class="timeline-title">
                                    {{ $cutoff['vehicle'] ?? 'Véhicule' }} — Lease {{ $cutoff['lease'] ?? '—' }}
                                </p>
                                <p class="timeline-desc">
                                    Le type <strong>{{ $cutoff['type'] ?? 'Contrat' }}</strong> est concerné.
                                    {{ $cutoff['reason'] ?? '' }}
                                </p>
                            </div>

                            <span class="dash-badge {{ $badgeClass($cutoff['badge'] ?? null) }}">
                                {{ $cutoff['status_label'] ?? '—' }}
                            </span>
                        </div>
                    @empty
                        <div class="timeline-item">
                            <div class="timeline-icon success">
                                <i class="fas fa-check"></i>
                            </div>

                            <div>
                                <p class="timeline-title">Aucune coupure à suivre</p>
                                <p class="timeline-desc">
                                    Aucune décision de coupure active sur la période sélectionnée.
                                </p>
                            </div>

                            <span class="dash-badge success">OK</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.leaseDashboardData = @json($dashboard);
</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-recouvrement-dashboard]');
    if (!root) return;

    const dashboardData = window.leaseDashboardData || {};

    const activePreset = {
        labels: dashboardData?.charts?.recovery?.labels || [],
        expectedSeries: dashboardData?.charts?.recovery?.expected || [],
        paidSeries: dashboardData?.charts?.recovery?.paid || [],
        remainingSeries: dashboardData?.charts?.recovery?.remaining || [],
    };

    const donutItems = dashboardData?.charts?.payment_breakdown?.items || [];

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
        const radius = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.arcTo(x + w, y, x + w, y + h, radius);
        ctx.arcTo(x + w, y + h, x, y + h, radius);
        ctx.arcTo(x, y + h, x, y, radius);
        ctx.arcTo(x, y, x + w, y, radius);
        ctx.closePath();
    }

    function drawEmptyChart(ctx, width, height, message) {
        const muted = cssVar('--color-secondary-text', '#6b7280');
        const border = cssVar('--color-border-subtle', '#e5e7eb');

        ctx.clearRect(0, 0, width, height);
        ctx.strokeStyle = border;
        ctx.setLineDash([5, 5]);
        ctx.strokeRect(8, 8, width - 16, height - 16);
        ctx.setLineDash([]);

        ctx.fillStyle = muted;
        ctx.font = '12px Lato, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(message, width / 2, height / 2);
    }

    function drawRecoveryChart() {
        const canvas = document.getElementById('recoveryChart');
        if (!canvas) return;

        const { ctx, width, height } = setupCanvas(canvas);

        if (!activePreset.labels.length) {
            drawEmptyChart(ctx, width, height, 'Aucune donnée de recouvrement pour la semaine courante');
            return;
        }

        const primary = cssVar('--color-primary', '#F58220');
        const success = cssVar('--color-success', '#16a34a');
        const error = cssVar('--color-error', '#dc2626');
        const muted = cssVar('--color-secondary-text', '#64748b');
        const border = cssVar('--color-border-subtle', '#e2e8f0');
        const text = cssVar('--color-text', '#0f172a');

        const max = Math.max(
            1,
            ...activePreset.expectedSeries,
            ...activePreset.paidSeries,
            ...activePreset.remainingSeries
        ) * 1.18;

        const p = { top: 24, right: 14, bottom: 34, left: 45 };
        const chartW = width - p.left - p.right;
        const chartH = height - p.top - p.bottom;

        ctx.clearRect(0, 0, width, height);
        ctx.font = '11px Lato, Arial, sans-serif';

        for (let i = 0; i <= 5; i++) {
            const y = p.top + chartH - chartH * i / 5;

            ctx.strokeStyle = border;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(p.left, y);
            ctx.lineTo(width - p.right, y);
            ctx.stroke();

            ctx.fillStyle = muted;
            ctx.textAlign = 'right';
            const label = Math.round(max * i / 5 / 1000);
            ctx.fillText(label === 0 ? '0' : label + 'k', p.left - 8, y + 4);
        }

        const groupW = chartW / activePreset.labels.length;
        const barW = Math.min(15, groupW / 5);

        activePreset.labels.forEach((label, index) => {
            const xCenter = p.left + groupW * index + groupW / 2;

            [
                { value: activePreset.expectedSeries[index] || 0, color: primary, offset: -barW - 3 },
                { value: activePreset.paidSeries[index] || 0, color: success, offset: 0 },
                { value: activePreset.remainingSeries[index] || 0, color: error, offset: barW + 3 }
            ].forEach(bar => {
                const h = chartH * bar.value / max;
                const x = xCenter + bar.offset - barW / 2;
                const y = p.top + chartH - h;

                ctx.fillStyle = bar.color;
                roundRect(ctx, x, y, barW, h, 4);
                ctx.fill();
            });

            ctx.fillStyle = text;
            ctx.textAlign = 'center';
            ctx.fillText(label, xCenter, height - 10);
        });

        const legend = [
            { label: 'Attendu', color: primary },
            { label: 'Collecté', color: success },
            { label: 'Reste', color: error }
        ];

        let lx = p.left;

        legend.forEach(item => {
            ctx.fillStyle = item.color;
            roundRect(ctx, lx, 6, 13, 8, 3);
            ctx.fill();

            ctx.fillStyle = muted;
            ctx.textAlign = 'left';
            ctx.fillText(item.label, lx + 18, 14);
            lx += 88;
        });
    }

    function drawDonut() {
        const canvas = document.getElementById('typeDonut');
        if (!canvas) return;

        const dpr = window.devicePixelRatio || 1;
        const size = 150;

        canvas.width = size * dpr;
        canvas.height = size * dpr;
        canvas.style.width = size + 'px';
        canvas.style.height = size + 'px';

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, size, size);

        const success = cssVar('--color-success', '#16a34a');
        const error = cssVar('--color-error', '#dc2626');
        const muted = cssVar('--color-border-subtle', '#e5e7eb');
        const card = cssVar('--color-card', '#ffffff');

        const colorsByBadge = {
            success: success,
            danger: error,
            error: error,
            muted: muted,
        };

        let values = donutItems.length
            ? donutItems.map(item => ({
                value: Number(item.percent || 0),
                color: colorsByBadge[item.badge] || muted,
            }))
            : [];

        const totalPercent = values.reduce((sum, item) => sum + item.value, 0);

        if (!values.length || totalPercent <= 0) {
            values = [{ value: 100, color: muted }];
        }

        let start = -Math.PI / 2;

        values.forEach(item => {
            const angle = Math.PI * 2 * item.value / 100;

            ctx.beginPath();
            ctx.arc(size / 2, size / 2, 56, start, start + angle);
            ctx.strokeStyle = item.color;
            ctx.lineWidth = 28;
            ctx.lineCap = 'butt';
            ctx.stroke();

            start += angle;
        });

        ctx.beginPath();
        ctx.arc(size / 2, size / 2, 38, 0, Math.PI * 2);
        ctx.fillStyle = card;
        ctx.fill();
    }

    function applyClientFilters() {
        const typeEl = root.querySelector('[data-filter="type"]');
        const statusEl = root.querySelector('[data-filter="status"]');
        const searchEl = root.querySelector('[data-filter="search"]');

        const type = typeEl ? typeEl.value.toLowerCase() : 'all';
        const status = statusEl ? statusEl.value.toLowerCase() : 'all';
        const search = searchEl ? searchEl.value.toLowerCase().trim() : '';

        const rows = root.querySelectorAll('#driversTable tbody tr, #cutoffTimeline [data-search]');

        rows.forEach(row => {
            const rowType = (row.dataset.type || '').toLowerCase();
            const rowStatus = (row.dataset.status || '').toLowerCase();
            const rowSearch = ((row.dataset.search || '') + ' ' + row.textContent).toLowerCase();

            const typeOk = type === 'all' || rowType.includes(type);
            const statusOk = status === 'all' || rowStatus.includes(status);
            const searchOk = !search || rowSearch.includes(search);

            row.style.display = typeOk && statusOk && searchOk ? '' : 'none';
        });
    }

    root.querySelectorAll('[data-filter="type"], [data-filter="status"], [data-filter="search"]').forEach(el => {
        el.addEventListener('input', applyClientFilters);
    });

    root.querySelector('[data-filter="period"]')?.addEventListener('change', function () {
        this.closest('form')?.submit();
    });

    let resizeTimer = null;

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);

        resizeTimer = setTimeout(function () {
            drawRecoveryChart();
            drawDonut();
        }, 120);
    });

    setTimeout(function () {
        drawRecoveryChart();
        drawDonut();
        applyClientFilters();
    }, 80);
});
</script>
@endpush