@extends('layouts.app')

@section('title', 'Historique des coupures lease')

@push('styles')
<style>
/* ══════════════════════════════════════════════════════════════
   IMPORTS
══════════════════════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&family=DM+Mono:wght@400;500&display=swap');

/* ══════════════════════════════════════════════════════════════
   TOKENS LOCAUX
══════════════════════════════════════════════════════════════ */
:root {
    --ch-radius: 16px;
    --ch-radius-sm: 10px;
    --ch-radius-pill: 100px;
    --ch-gap: 1rem;
    --ch-transition: 140ms cubic-bezier(.4,0,.2,1);
    --ch-font: 'DM Sans', var(--font-body, sans-serif);
    --ch-mono: 'DM Mono', var(--font-mono, monospace);
}

/* ══════════════════════════════════════════════════════════════
   LAYOUT
══════════════════════════════════════════════════════════════ */
.ch {
    display: flex;
    flex-direction: column;
    gap: var(--ch-gap);
    font-family: var(--ch-font);
}

/* ══════════════════════════════════════════════════════════════
   HERO HEADER
══════════════════════════════════════════════════════════════ */
.ch-hero {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--ch-radius);
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}

.ch-hero::before {
    content: '';
    position: absolute;
    inset: 0 0 0 0;
    background: linear-gradient(135deg, var(--color-primary) 0%, transparent 60%);
    opacity: .04;
    pointer-events: none;
}

.ch-hero-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-border));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.2rem;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}

.ch-hero-content { flex: 1; min-width: 0; }

.ch-hero-title {
    font-size: 1rem;
    font-weight: 800;
    color: var(--color-text);
    margin: 0 0 .2rem;
    letter-spacing: -.015em;
}

.ch-hero-sub {
    font-size: .78rem;
    color: var(--color-text-muted);
    margin: 0;
    line-height: 1.5;
}

.ch-hero-meta {
    display: flex;
    gap: .5rem;
    flex-shrink: 0;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.ch-hero-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .35rem .75rem;
    border-radius: var(--ch-radius-pill);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-bg, #f8fafc);
    font-size: .72rem;
    font-weight: 700;
    color: var(--color-text-muted);
    letter-spacing: .01em;
    white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════════
   KPI STRIP
══════════════════════════════════════════════════════════════ */
.ch-kpi-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: .5rem;
}

@media (max-width: 640px) { .ch-kpi-strip { grid-template-columns: repeat(2, 1fr); } }

.ch-kpi {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--ch-radius);
    padding: .9rem 1rem;
    box-shadow: var(--shadow-xs);
    cursor: pointer;
    transition: border-color var(--ch-transition), box-shadow var(--ch-transition), transform var(--ch-transition), background var(--ch-transition);
    text-decoration: none;
    display: block;
    position: relative;
    overflow: hidden;
}

.ch-kpi::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--ch-kpi-color, var(--color-primary));
    opacity: 0;
    border-radius: var(--ch-radius) var(--ch-radius) 0 0;
    transition: opacity var(--ch-transition);
}

.ch-kpi:hover  { border-color: var(--color-primary-border); box-shadow: var(--shadow-sm); transform: translateY(-2px); }
.ch-kpi:hover::after { opacity: .6; }

.ch-kpi.active-filter {
    border-color: var(--color-primary);
    background: var(--color-primary-light);
    box-shadow: 0 0 0 2px var(--color-primary-border);
}

.ch-kpi.active-filter::after { opacity: 1; }

.ch-kpi-label {
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: .45rem;
    display: flex;
    align-items: center;
    gap: .35rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ch-kpi-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
    background: var(--ch-kpi-color, #6b7280);
}

.ch-kpi-val {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--color-text);
    line-height: 1;
    letter-spacing: -.03em;
}

/* ══════════════════════════════════════════════════════════════
   FILTERS PANEL
══════════════════════════════════════════════════════════════ */
.ch-filters {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--ch-radius);
    overflow: hidden;
    box-shadow: var(--shadow-xs);
}

.ch-filters-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1.1rem;
    border-bottom: 1px solid var(--color-border-subtle);
    background: var(--color-bg-subtle, #f9fafb);
    gap: 1rem;
    flex-wrap: wrap;
}

.dark-mode .ch-filters-header { background: rgba(255,255,255,.03); }

.ch-filters-title {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    gap: .4rem;
}

/* Periods pills inside header */
.ch-period-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
}

.ch-pill {
    display: inline-flex;
    align-items: center;
    height: 30px;
    padding: 0 .75rem;
    border-radius: var(--ch-radius-pill);
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-muted);
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .01em;
    text-decoration: none;
    cursor: pointer;
    transition: background var(--ch-transition), border-color var(--ch-transition), color var(--ch-transition), transform var(--ch-transition);
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

/* Main filter row */
.ch-filter-body {
    padding: .9rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: .75rem;
}

.ch-filter-row {
    display: grid;
    grid-template-columns: minmax(280px, 1.8fr) minmax(180px, .7fr) minmax(200px, .75fr) auto;
    gap: .65rem;
    align-items: center;
}

@media (max-width: 1180px) {
    .ch-filter-row { grid-template-columns: 1fr 1fr; }
    .ch-filter-actions { grid-column: 1 / -1; }
}
@media (max-width: 740px) {
    .ch-filter-row { grid-template-columns: 1fr; }
}

.ch-search-wrap { position: relative; }

.ch-search-icon {
    position: absolute;
    left: .9rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: .78rem;
    pointer-events: none;
}

.ch-input, .ch-select, .ch-date {
    height: 40px;
    border: 1px solid var(--color-input-border);
    border-radius: var(--ch-radius-sm);
    background: var(--color-input-bg);
    color: var(--color-text);
    font-family: var(--ch-font);
    font-size: .84rem;
    transition: border-color var(--ch-transition), box-shadow var(--ch-transition);
}

.ch-input:focus, .ch-select:focus, .ch-date:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: var(--focus-ring);
}

.ch-input-search {
    width: 100%;
    padding: 0 .9rem 0 2.4rem;
    font-weight: 500;
}

.ch-select { padding: 0 .8rem; appearance: auto; }

.ch-filter-actions {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    justify-content: flex-end;
}

.ch-filter-sub {
    display: none;
    align-items: center;
    gap: .65rem;
    flex-wrap: wrap;
}

.ch-filter-sub.visible { display: flex; }

.ch-filter-sub-label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    white-space: nowrap;
}

.ch-date-group {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    flex-wrap: wrap;
}

.ch-date-helper { font-size: .73rem; color: var(--color-text-muted); }

/* ══════════════════════════════════════════════════════════════
   TABLE CARD
══════════════════════════════════════════════════════════════ */
.ch-table-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--ch-radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

/* Table toolbar */
.ch-table-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .75rem 1.1rem;
    border-bottom: 1px solid var(--color-border-subtle);
    gap: .75rem;
    flex-wrap: wrap;
}

.ch-table-count {
    font-size: .78rem;
    color: var(--color-text-muted);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: .4rem;
}

.ch-table-count strong { color: var(--color-text); }

.ch-table-controls {
    display: flex;
    align-items: center;
    gap: .5rem;
}

.ch-table-wrap { overflow-x: auto; }

.ch-table {
    width: 100%;
    min-width: 1000px;
    border-collapse: collapse;
    font-family: var(--ch-font);
    font-size: .82rem;
}

/* Sticky header */
.ch-table thead { position: sticky; top: 0; z-index: 2; }

.ch-table thead th {
    background: var(--color-bg-subtle, #f8fafc);
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    padding: .65rem .9rem;
    border-bottom: 2px solid var(--color-primary);
    white-space: nowrap;
    text-align: left;
}

.dark-mode .ch-table thead th { background: #161b22; }

.ch-table tbody td {
    padding: .9rem .9rem;
    border-bottom: 1px solid var(--color-border-subtle);
    color: var(--color-text);
    vertical-align: middle;
}

.ch-table tbody tr:last-child td { border-bottom: none; }

.ch-table tbody tr.main-row { cursor: default; }

.ch-table tbody tr.main-row:hover td {
    background: var(--color-sidebar-active);
}

/* Row index */
.ch-row-num {
    font-size: .7rem;
    color: var(--color-text-muted);
    font-weight: 500;
    font-family: var(--ch-mono);
}

/* ══════════════════════════════════════════════════════════════
   STATUS BADGE (compact & clear)
══════════════════════════════════════════════════════════════ */
.ch-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .3rem .65rem;
    border-radius: var(--ch-radius-pill);
    font-size: .67rem;
    font-weight: 700;
    letter-spacing: .02em;
    white-space: nowrap;
    border: 1px solid transparent;
}

.ch-badge i { font-size: .55rem; }

.ch-badge-pending   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.ch-badge-waiting   { background: #fff7ed; color: #c2410c; border-color: #fdba74; }
.ch-badge-sent      { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
.ch-badge-cut       { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.ch-badge-cancelled { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
.ch-badge-review    { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.ch-badge-failed    { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

.dark-mode .ch-badge-pending   { background: rgba(29,78,216,.18);  color: #93c5fd; border-color: rgba(147,197,253,.25); }
.dark-mode .ch-badge-waiting   { background: rgba(194,65,12,.18);  color: #fdba74; border-color: rgba(253,186,116,.25); }
.dark-mode .ch-badge-sent      { background: rgba(109,40,217,.18); color: #c4b5fd; border-color: rgba(196,181,253,.25); }
.dark-mode .ch-badge-cut       { background: rgba(4,120,87,.18);   color: #6ee7b7; border-color: rgba(110,231,183,.25); }
.dark-mode .ch-badge-cancelled { background: rgba(75,85,99,.18);   color: #9ca3af; border-color: rgba(156,163,175,.25); }
.dark-mode .ch-badge-review    { background: rgba(180,83,9,.18);   color: #fcd34d; border-color: rgba(252,211,77,.25); }
.dark-mode .ch-badge-failed    { background: rgba(185,28,28,.18);  color: #fca5a5; border-color: rgba(252,165,165,.25); }

/* ══════════════════════════════════════════════════════════════
   VEHICLE CELL
══════════════════════════════════════════════════════════════ */
.ch-vehicle {
    display: flex;
    align-items: center;
    gap: .65rem;
}

.ch-vehicle-icon {
    width: 34px; height: 34px;
    border-radius: 9px;
    background: var(--color-primary-light, #eff6ff);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary);
    font-size: .8rem;
    flex-shrink: 0;
}

.ch-vehicle-info { min-width: 0; }

.ch-vehicle-plate {
    font-weight: 800;
    font-size: .88rem;
    color: var(--color-text);
    letter-spacing: -.01em;
    white-space: nowrap;
}

.ch-vehicle-mac {
    font-size: .7rem;
    color: var(--color-text-muted);
    font-family: var(--ch-mono);
    margin-top: .1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 130px;
}

/* ══════════════════════════════════════════════════════════════
   PAYMENT CONTEXT CELL
══════════════════════════════════════════════════════════════ */
.ch-pay { display: flex; flex-direction: column; gap: .2rem; }

.ch-pay-driver {
    font-weight: 600;
    font-size: .82rem;
    color: var(--color-text);
    white-space: nowrap;
}

.ch-pay-amount {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    font-size: .75rem;
    font-weight: 700;
    color: #c2410c;
}

.ch-pay-due {
    font-size: .7rem;
    color: var(--color-text-muted);
}

/* ══════════════════════════════════════════════════════════════
   SCHEDULED DATE
══════════════════════════════════════════════════════════════ */
.ch-date-cell { white-space: nowrap; }
.ch-date-main { font-weight: 700; font-size: .82rem; color: var(--color-text); }
.ch-date-time { font-size: .7rem; color: var(--color-text-muted); margin-top: .1rem; }

/* ══════════════════════════════════════════════════════════════
   TIMELINE (compact horizontal)
══════════════════════════════════════════════════════════════ */
.ch-tl {
    display: flex;
    align-items: center;
    gap: 0;
    min-width: 160px;
}

.ch-tl-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    position: relative;
}

.ch-tl-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 12px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: var(--color-border-subtle);
    z-index: 0;
}

.ch-tl-step.tl-done:not(:last-child)::after {
    background: linear-gradient(90deg, #047857, var(--color-border-subtle));
}

.ch-tl-node {
    width: 24px; height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .55rem;
    position: relative;
    z-index: 1;
    border: 2px solid var(--color-border-subtle);
    background: var(--color-card);
    color: var(--color-text-muted);
    transition: border-color var(--ch-transition), background var(--ch-transition);
}

.ch-tl-step.tl-done .ch-tl-node {
    background: #ecfdf5;
    border-color: #047857;
    color: #047857;
}

.dark-mode .ch-tl-step.tl-done .ch-tl-node {
    background: rgba(4,120,87,.18);
    border-color: #6ee7b7;
    color: #6ee7b7;
}

.ch-tl-label {
    font-size: .6rem;
    font-weight: 600;
    color: var(--color-text-muted);
    margin-top: .3rem;
    white-space: nowrap;
}

.ch-tl-step.tl-done .ch-tl-label {
    color: var(--color-text);
    font-weight: 700;
}

.ch-tl-time {
    font-size: .58rem;
    color: var(--color-text-muted);
    font-family: var(--ch-mono);
    margin-top: .1rem;
    white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════════
   CONTROL CELL (speed + ignition)
══════════════════════════════════════════════════════════════ */
.ch-ctrl { display: flex; flex-direction: column; gap: .3rem; }

.ch-ctrl-speed {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .8rem;
    font-weight: 700;
    color: var(--color-text);
}

.ch-ctrl-speed i { color: var(--color-text-muted); font-size: .65rem; }

.ch-ignition {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    font-size: .7rem;
    font-weight: 700;
    font-family: var(--ch-font);
}

.ch-ign-on  { color: #047857; }
.ch-ign-off { color: var(--color-text-muted); }

.ch-ign-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
}

.ch-ign-on  .ch-ign-dot { background: #10b981; box-shadow: 0 0 0 2px rgba(16,185,129,.25); }
.ch-ign-off .ch-ign-dot { background: var(--color-border); }

/* ══════════════════════════════════════════════════════════════
   REASON CELL
══════════════════════════════════════════════════════════════ */
.ch-reason {
    font-size: .79rem;
    color: var(--color-text);
    line-height: 1.5;
    max-width: 280px;
}

.ch-reason-empty { color: var(--color-text-muted); font-style: italic; }

/* ══════════════════════════════════════════════════════════════
   DETAIL TOGGLE BUTTON
══════════════════════════════════════════════════════════════ */
.ch-detail-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .38rem .65rem;
    border-radius: var(--ch-radius-sm);
    border: 1px solid var(--color-border);
    background: transparent;
    color: var(--color-text-muted);
    font-family: var(--ch-font);
    font-size: .68rem;
    font-weight: 700;
    cursor: pointer;
    transition: background var(--ch-transition), border-color var(--ch-transition), color var(--ch-transition), transform var(--ch-transition);
    white-space: nowrap;
}

.ch-detail-btn:hover,
.ch-detail-btn.open {
    background: var(--color-primary-light);
    border-color: var(--color-primary-border);
    color: var(--color-primary);
}

.ch-detail-btn:hover { transform: translateY(-1px); }

.ch-chevron {
    font-size: .55rem;
    transition: transform .2s;
}

.ch-detail-btn.open .ch-chevron { transform: rotate(180deg); }

/* ══════════════════════════════════════════════════════════════
   EXPANDED DETAIL PANEL
══════════════════════════════════════════════════════════════ */
.ch-detail-row td {
    background: var(--color-bg, #f9fafb) !important;
    border-bottom: 2px solid var(--color-border-subtle) !important;
    padding: 0 !important;
}

.dark-mode .ch-detail-row td { background: rgba(255,255,255,.02) !important; }

.ch-detail-inner {
    display: none;
    padding: 1rem 1.25rem 1.25rem;
    gap: .75rem;
    flex-wrap: wrap;
}

.ch-detail-inner.visible { display: flex; }

.ch-detail-section {
    flex: 1;
    min-width: 230px;
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--ch-radius);
    padding: .85rem .95rem;
}

.ch-detail-section-title {
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: .65rem;
    padding-bottom: .4rem;
    border-bottom: 1px solid var(--color-border-subtle);
    display: flex;
    align-items: center;
    gap: .35rem;
}

.ch-detail-field {
    display: flex;
    gap: .5rem;
    font-size: .79rem;
    margin-bottom: .4rem;
    align-items: flex-start;
}

.ch-detail-field:last-child { margin-bottom: 0; }

.ch-detail-field-key {
    color: var(--color-text-muted);
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
    min-width: 90px;
}

.ch-detail-field-val {
    color: var(--color-text);
    word-break: break-word;
    line-height: 1.45;
}

.ch-code-block {
    background: var(--color-bg-subtle, #f8fafc);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--ch-radius-sm);
    padding: .6rem .75rem;
    font-family: var(--ch-mono);
    font-size: .7rem;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    max-width: 100%;
    max-height: 200px;
    overflow-y: auto;
    color: var(--color-text);
    line-height: 1.5;
}

.dark-mode .ch-code-block { background: rgba(0,0,0,.25); }

/* ══════════════════════════════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════════════════════════════ */
.ch-empty {
    padding: 3.5rem 2rem;
    text-align: center;
}

.ch-empty-icon {
    width: 56px; height: 56px;
    border-radius: 18px;
    background: var(--color-bg-subtle, #f3f4f6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: var(--color-border);
    margin: 0 auto .9rem;
}

.ch-empty-text {
    font-size: .95rem;
    font-weight: 800;
    color: var(--color-text);
    margin-bottom: .35rem;
}

.ch-empty-sub {
    font-size: .78rem;
    color: var(--color-text-muted);
}

/* ══════════════════════════════════════════════════════════════
   PAGINATION
══════════════════════════════════════════════════════════════ */
.ch-pagination {
    padding: .85rem 1.1rem;
    border-top: 1px solid var(--color-border-subtle);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
    background: var(--color-bg-subtle, #f9fafb);
}

.dark-mode .ch-pagination { background: rgba(255,255,255,.02); }

.ch-pagination-info {
    font-size: .73rem;
    color: var(--color-text-muted);
    font-weight: 600;
}

.ch-pagination-info strong { color: var(--color-text); }

/* ══════════════════════════════════════════════════════════════
   MISC UTILS
══════════════════════════════════════════════════════════════ */
.ch-muted { color: var(--color-text-muted); }
.ch-dash  { color: var(--color-border); font-weight: 400; }
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

<div class="ch">

    {{-- ── HERO HEADER ─────────────────────────────────────────── --}}
    <div class="ch-hero">
        <div class="ch-hero-icon">
            <i class="fa-solid fa-bolt"></i>
        </div>
        <div class="ch-hero-content">
            <h1 class="ch-hero-title">Historique des coupures automatiques lease</h1>
            <p class="ch-hero-sub">
                Suivi des coupures planifiées, commandes envoyées, confirmations effectives,
                attentes de sécurité, annulations après paiement et échecs finaux.
            </p>
        </div>
        <div class="ch-hero-meta">
            <span class="ch-hero-chip">
                <i class="fas fa-circle-dot" style="color:var(--color-primary);font-size:.55rem;"></i>
                {{ $histories->total() }} enregistrement{{ $histories->total() > 1 ? 's' : '' }}
            </span>
            <span class="ch-hero-chip">
                <i class="fas fa-calendar-day" style="font-size:.7rem;"></i>
                {{ now()->format('d/m/Y') }}
            </span>
        </div>
    </div>

    {{-- ── KPI STRIP ────────────────────────────────────────────── --}}
    @php
        $kpis = [
            ['label' => 'Total',           'val' => $summary['total_all'] ?? 0,      'dot' => '#6b7280', 'key' => ''],
            ['label' => 'Confirmées',      'val' => $summary['cut_off'] ?? 0,        'dot' => '#047857', 'key' => 'CUT_OFF'],
            ['label' => 'En attente',      'val' => ($summary['pending'] ?? 0) + ($summary['waiting_stop'] ?? 0), 'dot' => '#1d4ed8', 'key' => 'PENDING'],
            ['label' => 'Attente arrêt',   'val' => $summary['waiting_stop'] ?? 0,   'dot' => '#c2410c', 'key' => 'WAITING_STOP'],
            ['label' => 'Cmd. envoyée',    'val' => $summary['command_sent'] ?? 0,   'dot' => '#6d28d9', 'key' => 'COMMAND_SENT'],
            ['label' => 'Annulés / payés', 'val' => $summary['cancelled_paid'] ?? 0,'dot' => '#4b5563', 'key' => 'CANCELLED_PAID'],
            ['label' => 'À vérifier',      'val' => $summary['cancelled_unverified'] ?? 0, 'dot' => '#b45309', 'key' => 'CANCELLED_UNVERIFIED'],
            ['label' => 'Pardon avant',    'val' => $summary['cancelled_forgiven_before_cut'] ?? 0, 'dot' => '#0f766e', 'key' => 'CANCELLED_FORGIVEN_BEFORE_CUT'],
            ['label' => 'Rallumés',        'val' => $summary['reactivated_after_forgiveness'] ?? 0, 'dot' => '#15803d', 'key' => 'REACTIVATED_AFTER_FORGIVENESS'],
            ['label' => 'Échecs finaux',   'val' => $summary['failed'] ?? 0,         'dot' => '#b91c1c', 'key' => 'FAILED'],
        ];
    @endphp

    <div class="ch-kpi-strip">
        @foreach($kpis as $kpi)
            <a href="{{ route('lease.cutoff-history.index', array_merge(request()->except('status', 'page'), $kpi['key'] ? ['status' => $kpi['key']] : [])) }}"
               class="ch-kpi {{ $status === $kpi['key'] && $kpi['key'] ? 'active-filter' : '' }}"
               style="--ch-kpi-color: {{ $kpi['dot'] }};">
                <div class="ch-kpi-label">
                    <span class="ch-kpi-dot"></span>
                    {{ $kpi['label'] }}
                </div>
                <div class="ch-kpi-val">{{ number_format($kpi['val']) }}</div>
            </a>
        @endforeach
    </div>

    {{-- ── FILTERS PANEL ────────────────────────────────────────── --}}
    <div class="ch-filters">

        {{-- Header : titre + période rapide --}}
        <div class="ch-filters-header">
            <span class="ch-filters-title">
                <i class="fas fa-sliders-h"></i>
                Filtres
                @if($search || $status || $period)
                    <span class="ch-badge ch-badge-pending" style="font-size:.6rem;padding:.18rem .5rem;">
                        Actifs
                    </span>
                @endif
            </span>

            <div class="ch-period-pills">
                @foreach([
                    'today'      => "Aujourd'hui",
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
        </div>

        {{-- Filter body --}}
        <div class="ch-filter-body">
            <form method="GET" action="{{ route('lease.cutoff-history.index') }}">

                <div class="ch-filter-row">
                    {{-- Recherche --}}
                    <div class="ch-search-wrap">
                        <i class="fas fa-search ch-search-icon"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ $search }}"
                            class="ch-input ch-input-search"
                            placeholder="Immatriculation, contrat, sous-contrat, lease, chauffeur, motif…"
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
                        <option value="">Toutes les périodes</option>
                        <option value="today"         {{ $period === 'today'         ? 'selected' : '' }}>Aujourd'hui</option>
                        <option value="yesterday"     {{ $period === 'yesterday'     ? 'selected' : '' }}>Hier</option>
                        <option value="this_week"     {{ $period === 'this_week'     ? 'selected' : '' }}>Cette semaine</option>
                        <option value="this_month"    {{ $period === 'this_month'    ? 'selected' : '' }}>Ce mois</option>
                        <option value="this_year"     {{ $period === 'this_year'     ? 'selected' : '' }}>Cette année</option>
                        <option value="specific_date" {{ $period === 'specific_date' ? 'selected' : '' }}>Date spécifique</option>
                        <option value="range"         {{ $period === 'range'         ? 'selected' : '' }}>Plage de dates</option>
                    </select>

                    {{-- Actions --}}
                    <div class="ch-filter-actions">
                        <select name="per_page" class="ch-select" style="min-width:110px;">
                            <option value="20"  {{ request('per_page', 20) == 20  ? 'selected' : '' }}>20 / page</option>
                            <option value="50"  {{ request('per_page') == 50      ? 'selected' : '' }}>50 / page</option>
                            <option value="100" {{ request('per_page') == 100     ? 'selected' : '' }}>100 / page</option>
                        </select>

                        <button type="submit" class="btn-primary">
                            <i class="fas fa-filter" aria-hidden="true"></i>
                            Filtrer
                        </button>

                        <a href="{{ route('lease.cutoff-history.index') }}" class="btn-secondary">
                            <i class="fas fa-rotate-left" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                {{-- Date conditionnelle --}}
                <div class="ch-filter-sub {{ in_array($period, ['specific_date', 'range'], true) ? 'visible' : '' }}" id="ch-date-filter-box">
                    <span class="ch-filter-sub-label">
                        <i class="fas fa-calendar" style="font-size:.7rem;"></i>
                        Filtre date
                    </span>

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

            </form>
        </div>
    </div>

    {{-- ── DATA TABLE ───────────────────────────────────────────── --}}
    <div class="ch-table-card">

        {{-- Toolbar --}}
        <div class="ch-table-toolbar">
            <span class="ch-table-count">
                <i class="fas fa-table" style="font-size:.75rem;color:var(--color-text-muted);"></i>
                <strong>{{ number_format($histories->total()) }}</strong>
                événement{{ $histories->total() > 1 ? 's' : '' }}
                &nbsp;·&nbsp;
                page {{ $histories->currentPage() }} / {{ $histories->lastPage() }}
            </span>

            @if($status)
                <span class="ch-badge {{ match($status) {
                    'CUT_OFF'        => 'ch-badge-cut',
                    'PENDING'        => 'ch-badge-pending',
                    'WAITING_STOP'   => 'ch-badge-waiting',
                    'COMMAND_SENT'   => 'ch-badge-sent',
                    'CANCELLED_PAID' => 'ch-badge-cancelled',
                    'CANCELLED_UNVERIFIED' => 'ch-badge-review',
                    'FAILED'         => 'ch-badge-failed',
                    default          => 'ch-badge-pending',
                } }}">
                    Filtre actif : {{ $statuses[$status] ?? $status }}
                </span>
            @endif
        </div>

        <div class="ch-table-wrap">
            <table class="ch-table">
                <thead>
                    <tr>
                        <th style="width:38px;">#</th>
                        <th>Véhicule</th>
                        <th>Contrat / Paiement</th>
                        <th>Statut</th>
                        <th>Planifié</th>
                        <th>Progression</th>
                        <th>Contrôle</th>
                        <th>Motif</th>
                        <th style="width:80px;text-align:center;">Détail</th>
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
                        'CANCELLED_UNVERIFIED' => 'ch-badge-review',
                        'CANCELLED_PAID',
                        'CANCELLED_RULE_MISSING',
                        'CANCELLED_RULE_DISABLED',
                        'CANCELLED_FORGIVEN_BEFORE_CUT',
                        'REACTIVATED_AFTER_FORGIVENESS' => 'ch-badge-cancelled',
                        'FAILED',
                        'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'ch-badge-failed',
                        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'ch-badge-sent',
                        default          => 'ch-badge-pending',
                    };

                    $statusLabel = match($history->status) {
                        'PENDING'        => 'En attente',
                        'WAITING_STOP'   => 'Attente arrêt',
                        'COMMAND_SENT'   => 'Cmd. envoyée',
                        'CUT_OFF'        => 'Coupure conf.',
                        'CANCELLED_PAID' => 'Annulé / payé',
                        'CANCELLED_UNVERIFIED' => 'À vérifier',
                        'CANCELLED_RULE_MISSING' => 'Annulé : règle absente',
                        'CANCELLED_RULE_DISABLED' => 'Annulé : règle incomplète',
                        'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Pardon avant coupure — dette ouverte',
                        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'Rallumage demandé',
                        'REACTIVATED_AFTER_FORGIVENESS' => 'Rallumé après pardon — dette ouverte',
                        'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'Échec rallumage',
                        'FAILED'         => 'Échec final',
                        default          => $history->status ?? '—',
                    };

                    $statusIcon = match($history->status) {
                        'PENDING'        => 'fa-clock',
                        'WAITING_STOP'   => 'fa-hourglass-half',
                        'COMMAND_SENT'   => 'fa-paper-plane',
                        'CUT_OFF'        => 'fa-check',
                        'CANCELLED_PAID' => 'fa-ban',
                        'CANCELLED_UNVERIFIED' => 'fa-circle-question',
                        'CANCELLED_RULE_MISSING' => 'fa-link-slash',
                        'CANCELLED_RULE_DISABLED' => 'fa-toggle-off',
                        'CANCELLED_FORGIVEN_BEFORE_CUT' => 'fa-shield-heart',
                        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'fa-rotate',
                        'REACTIVATED_AFTER_FORGIVENESS' => 'fa-bolt',
                        'FAILED',
                        'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'fa-xmark',
                        default          => 'fa-circle',
                    };

                    $ignitionRaw = strtolower((string)($history->ignition_state ?? ''));
                    $ignOn = in_array($ignitionRaw, ['on', '1', 'true', 'running'], true);

                    $tl = [
                        ['label' => 'Détecté',  'val' => $history->detected_at,         'done' => (bool) $history->detected_at],
                        ['label' => 'Cmd.',     'val' => $history->cutoff_requested_at, 'done' => (bool) $history->cutoff_requested_at],
                        ['label' => 'Confirmé', 'val' => $history->cutoff_executed_at,  'done' => (bool) $history->cutoff_executed_at],
                    ];

                    $contractKindLabel = $history->contract_kind === 'SUB' ? 'Sous-contrat' : 'Contrat principal';
                    $contractTypeLabel = $history->type_contrat_label
                        ?: optional($history->contractLink)->type_contrat_label
                        ?: '—';
                    $contractLinkId = $history->contract_link_id ?: optional($history->contractLink)->id;
                    $ruleTime = optional($history->contractRule)->cutoff_time
                        ? substr((string) optional($history->contractRule)->cutoff_time, 0, 5)
                        : null;

                    $paymentSnapshot = is_array($history->payment_status_snapshot ?? null)
                        ? $history->payment_status_snapshot
                        : [];

                    $usefulPaymentFields = [
                        'chauffeur_nom_complet' => 'Chauffeur',
                        'date_echeance'         => 'Échéance',
                        'reste_a_payer'         => 'Reste à payer',
                        'montant_attendu'       => 'Montant attendu',
                        'montant_paye'          => 'Montant payé',
                        'statut'                => 'Statut paiement',
                    ];

                    $cleanBusinessText = function (?string $value, string $fallback = '—') {
                        $value = trim((string) $value);

                        if ($value === '') {
                            return $fallback;
                        }

                        $value = preg_replace('/\bType\s*#?\d+\b/i', '', $value);
                        $value = preg_replace('/\b(?:contrat|sous-contrat|lien|règle|regle|lease)\s*#?\d+\b/i', '', $value);
                        $value = preg_replace('/#\d+/', '', $value);
                        $value = preg_replace('/\s*[·|,-]\s*(?=\s*[·|,-]|$)/', ' ', $value);
                        $value = preg_replace('/\s+/', ' ', trim($value, " ·|-\t\n\r\0\x0B"));

                        return $value !== '' ? $value : $fallback;
                    };

                    $businessTypeLabel = $cleanBusinessText(
                        $contractTypeLabel,
                        $history->contract_kind === 'SUB' ? 'sous-contrat' : 'contrat principal'
                    );

                    $businessNatureLabel = $history->contract_kind === 'SUB'
                        ? 'sous-contrat'
                        : 'contrat principal';

                    $businessCause = $history->contract_kind === 'SUB'
                        ? 'Cause : sous-contrat ' . $businessTypeLabel
                        : 'Cause : contrat principal ' . $businessTypeLabel;

                    $businessReason = $cleanBusinessText($history->reason, 'Motif non renseigné.');

                    $businessActionLabel = match($history->status) {
                        'CUT_OFF' => 'Chauffeur coupé',
                        'COMMAND_SENT' => 'Commande de coupure envoyée',
                        'WAITING_STOP' => 'Coupure en attente d’arrêt du véhicule',
                        'PENDING' => 'Coupure planifiée',
                        'CANCELLED_PAID' => 'Coupure annulée : paiement régularisé',
                        'CANCELLED_UNVERIFIED' => 'Coupure annulée : à vérifier (paiement non confirmé)',
                        'CANCELLED_RULE_MISSING' => 'Coupure annulée : règle de coupure absente',
                        'CANCELLED_RULE_DISABLED' => 'Coupure annulée : règle de coupure inactive',
                        'CANCELLED_FORGIVEN_BEFORE_CUT' => 'Coupure annulée : pardon accordé avant coupure',
                        'REACTIVATION_REQUESTED_AFTER_FORGIVENESS' => 'Rallumage demandé après pardon',
                        'REACTIVATED_AFTER_FORGIVENESS' => 'Véhicule rallumé après pardon',
                        'REACTIVATION_FAILED_AFTER_FORGIVENESS' => 'Échec du rallumage après pardon',
                        'FAILED' => 'Coupure non effectuée',
                        default => $statusLabel,
                    };

                    $businessSummary = $businessActionLabel . ' — ' . $businessCause;

                    $displayDriverName = $paymentSnapshot['chauffeur_nom_complet']
                        ?? $history->driver?->nom_complet
                        ?? $history->driver?->name
                        ?? null;
                @endphp

                {{-- ── MAIN ROW ── --}}
                <tr class="main-row">

                    {{-- # --}}
                    <td>
                        <span class="ch-row-num">{{ $histories->firstItem() + $index }}</span>
                    </td>

                    {{-- Véhicule --}}
                    <td>
                        <div class="ch-vehicle">
                            <div class="ch-vehicle-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="ch-vehicle-info">
                                <div class="ch-vehicle-plate">{{ $history->vehicle->immatriculation ?? '—' }}</div>
                                @if($history->vehicle->mac_id_gps ?? false)
                                    <div class="ch-vehicle-mac">{{ $history->vehicle->mac_id_gps }}</div>
                                @endif
                            </div>
                        </div>
                    </td>

                    {{-- Contrat / Paiement --}}
                    <td>
                        <div class="ch-pay">
                            <div class="ch-pay-driver">
                                <i class="fas fa-file-contract" style="font-size:.65rem;color:var(--color-text-muted);margin-right:.2rem;"></i>
                                {{ $businessSummary }}
                            </div>

                            @if($history->lease_date_echeance)
                                <div class="ch-pay-due">
                                    Échéance du {{ optional($history->lease_date_echeance)->format('d/m/Y') }}
                                </div>
                            @endif

                            @if(!empty($displayDriverName))
                                <div class="ch-pay-driver" style="margin-top:.25rem;">
                                    <i class="fas fa-user" style="font-size:.65rem;color:var(--color-text-muted);margin-right:.2rem;"></i>
                                    {{ $displayDriverName }}
                                </div>
                            @endif

                            @if(!empty($paymentSnapshot['reste_a_payer']))
                                <div class="ch-pay-amount">
                                    <i class="fas fa-triangle-exclamation" style="font-size:.65rem;"></i>
                                    {{ $paymentSnapshot['reste_a_payer'] }} restant
                                </div>
                            @endif
                        </div>
                    </td>

                    {{-- Statut --}}
                    <td style="white-space:nowrap;">
                        <span class="ch-badge {{ $statusClass }}">
                            <i class="fas {{ $statusIcon }}"></i>
                            {{ $statusLabel }}
                        </span>
                    </td>

                    {{-- Planifié --}}
                    <td>
                        @if($history->scheduled_for)
                            <div class="ch-date-cell">
                                <div class="ch-date-main">{{ optional($history->scheduled_for)->format('d/m/Y') }}</div>
                                <div class="ch-date-time">{{ optional($history->scheduled_for)->format('H:i') }}</div>
                            </div>
                        @else
                            <span class="ch-dash">—</span>
                        @endif
                    </td>

                    {{-- Progression (timeline horizontale) --}}
                    <td>
                        <div class="ch-tl">
                            @foreach($tl as $i => $step)
                                <div class="ch-tl-step {{ $step['done'] ? 'tl-done' : '' }}">
                                    <div class="ch-tl-node">
                                        <i class="fas {{ $step['done'] ? 'fa-check' : 'fa-minus' }}"></i>
                                    </div>
                                    <div class="ch-tl-label">{{ $step['label'] }}</div>
                                    @if($step['done'])
                                        <div class="ch-tl-time">{{ optional($step['val'])->format('d/m H:i') }}</div>
                                    @else
                                        <div class="ch-tl-time">&nbsp;</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </td>

                    {{-- Contrôle --}}
                    <td>
                        <div class="ch-ctrl">
                            @if($history->speed_at_check !== null)
                                <div class="ch-ctrl-speed">
                                    <i class="fas fa-gauge"></i>
                                    {{ $history->speed_at_check }} km/h
                                </div>
                            @endif
                            <div class="ch-ignition {{ $ignOn ? 'ch-ign-on' : 'ch-ign-off' }}">
                                <span class="ch-ign-dot"></span>
                                {{ $ignOn ? 'Moteur ON' : 'Moteur OFF' }}
                            </div>
                        </div>
                    </td>

                    {{-- Motif --}}
                    <td>
                        <div class="ch-reason {{ !$history->reason ? 'ch-reason-empty' : '' }}">
                            {{ $businessReason }}
                        </div>
                    </td>

                    {{-- Détail toggle --}}
                    <td style="text-align:center;">
                        <button type="button"
                                class="ch-detail-btn"
                                onclick="chToggle('{{ $rowId }}')"
                                id="btn-{{ $rowId }}"
                                aria-expanded="false"
                                aria-controls="inner-{{ $rowId }}">
                            Détail
                            <i class="fas fa-chevron-down ch-chevron"></i>
                        </button>
                    </td>
                </tr>

                {{-- ── DETAIL ROW ── --}}
                <tr class="ch-detail-row" id="{{ $rowId }}">
                    <td colspan="9">
                        <div class="ch-detail-inner" id="inner-{{ $rowId }}">

                            {{-- Résumé métier --}}
                            <div class="ch-detail-section">
                                <div class="ch-detail-section-title">
                                    <i class="fas fa-file-signature"></i>
                                    Résumé métier
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Action</span>
                                    <span class="ch-detail-field-val">{{ $businessActionLabel }}</span>
                                </div>

                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Cause</span>
                                    <span class="ch-detail-field-val">{{ $businessCause }}</span>
                                </div>

                                @if($history->lease_date_echeance)
                                    <div class="ch-detail-field">
                                        <span class="ch-detail-field-key">Échéance concernée</span>
                                        <span class="ch-detail-field-val">{{ optional($history->lease_date_echeance)->format('d/m/Y') }}</span>
                                    </div>
                                @endif

                                @if(!empty($displayDriverName))
                                    <div class="ch-detail-field">
                                        <span class="ch-detail-field-key">Chauffeur</span>
                                        <span class="ch-detail-field-val">{{ $displayDriverName }}</span>
                                    </div>
                                @endif

                                <div class="ch-detail-field" style="display:block;">
                                    <div class="ch-detail-field-key" style="margin-bottom:.3rem;">Message</div>
                                    <div class="ch-detail-field-val">{{ $businessReason }}</div>
                                </div>
                            </div>

                            {{-- État véhicule --}}
                            <div class="ch-detail-section">
                                <div class="ch-detail-section-title">
                                    <i class="fas fa-car-side"></i>
                                    État véhicule
                                </div>
                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Vitesse contrôlée</span>
                                    <span class="ch-detail-field-val">
                                        {{ $history->speed_at_check !== null ? $history->speed_at_check . ' km/h' : 'Non disponible' }}
                                    </span>
                                </div>
                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Moteur</span>
                                    <span class="ch-detail-field-val">{{ $ignOn ? 'Allumé' : 'Éteint' }}</span>
                                </div>
                            </div>

                            {{-- Horodatage --}}
                            <div class="ch-detail-section">
                                <div class="ch-detail-section-title">
                                    <i class="fas fa-clock"></i>
                                    Horodatage complet
                                </div>
                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Planifié</span>
                                    <span class="ch-detail-field-val">{{ optional($history->scheduled_for)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>
                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Détecté</span>
                                    <span class="ch-detail-field-val">{{ optional($history->detected_at)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>
                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Commande</span>
                                    <span class="ch-detail-field-val">{{ optional($history->cutoff_requested_at)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>
                                <div class="ch-detail-field">
                                    <span class="ch-detail-field-key">Confirmée</span>
                                    <span class="ch-detail-field-val">{{ optional($history->cutoff_executed_at)->format('d/m/Y H:i:s') ?? '—' }}</span>
                                </div>
                            </div>

                            {{-- Paiement (si snapshot disponible) --}}
                            @if(!empty($paymentSnapshot))
                                <div class="ch-detail-section">
                                    <div class="ch-detail-section-title">
                                        <i class="fas fa-credit-card"></i>
                                        Détails paiement
                                    </div>
                                    @foreach($usefulPaymentFields as $key => $label)
                                        @if(!empty($paymentSnapshot[$key]))
                                            <div class="ch-detail-field">
                                                <span class="ch-detail-field-key">{{ $label }}</span>
                                                <span class="ch-detail-field-val">
                                                    {{ is_array($paymentSnapshot[$key]) ? json_encode($paymentSnapshot[$key], JSON_UNESCAPED_UNICODE) : $paymentSnapshot[$key] }}
                                                </span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            {{-- Diagnostic (si notes ou réponse provider) --}}
                            @if(!empty($history->notes) || !empty($history->command_response))
                                <div class="ch-detail-section">
                                    <div class="ch-detail-section-title">
                                        <i class="fas fa-terminal"></i>
                                        Diagnostic
                                    </div>
                                    @if(!empty($history->notes))
                                        <div class="ch-detail-field" style="display:block;">
                                            <div class="ch-detail-field-key" style="margin-bottom:.3rem;">Notes</div>
                                            <div class="ch-detail-field-val">{{ $history->notes }}</div>
                                        </div>
                                    @endif
                                    @if(!empty($history->command_response))
                                        <div class="ch-detail-field" style="display:block;">
                                            <div class="ch-detail-field-key" style="margin-bottom:.3rem;">Réponse provider GPS</div>
                                            <pre class="ch-detail-field-val" style="white-space:pre-wrap;margin:0;font-size:.68rem;">{{ json_encode($history->command_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
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
                            <div class="ch-empty-sub">Modifiez les filtres ou réinitialisez la recherche pour afficher des résultats.</div>
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
                <strong>{{ number_format($histories->total()) }}</strong>
                événement{{ $histories->total() > 1 ? 's' : '' }}
                &nbsp;·&nbsp;
                page <strong>{{ $histories->currentPage() }}</strong> / <strong>{{ $histories->lastPage() }}</strong>
            </span>
            {{ $histories->links() }}
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function chToggle(rowId) {
    var inner  = document.getElementById('inner-' + rowId);
    var btn    = document.getElementById('btn-'   + rowId);
    if (!inner || !btn) return;
    var isOpen = inner.classList.contains('visible');
    inner.classList.toggle('visible', !isOpen);
    btn.classList.toggle('open',      !isOpen);
    btn.setAttribute('aria-expanded', String(!isOpen));
}

(function() {
    var periodSelect = document.getElementById('ch-period-select');
    var wrapper      = document.getElementById('ch-date-filter-box');
    var specific     = document.getElementById('ch-specific-date-group');
    var range        = document.getElementById('ch-range-date-group');

    function handlePeriodChange() {
        if (!periodSelect || !wrapper || !specific || !range) return;
        var value = periodSelect.value;
        wrapper.classList.remove('visible');
        specific.style.display = 'none';
        range.style.display    = 'none';
        if (value === 'specific_date') {
            wrapper.classList.add('visible');
            specific.style.display = 'inline-flex';
        } else if (value === 'range') {
            wrapper.classList.add('visible');
            range.style.display = 'inline-flex';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        handlePeriodChange();
        if (periodSelect) periodSelect.addEventListener('change', handlePeriodChange);
    });
})();
</script>
@endpush