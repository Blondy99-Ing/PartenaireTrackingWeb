@extends('layouts.app')

@section('title', 'Lease — Paiements')

@push('styles')
<style>
.lease-kpi-bar {
    position: sticky;
    top: var(--navbar-h, 64px);
    z-index: var(--z-kpi, 9);
    background: var(--color-bg);
    padding: .45rem 0 .4rem;
    box-shadow: 0 4px 18px rgba(0,0,0,.07);
    margin-bottom: 0;
}
.dark-mode .lease-kpi-bar {
    box-shadow: 0 6px 24px rgba(0,0,0,.4);
}

.lease-kpi-grid {
    display: grid;
    grid-template-columns: repeat(9, minmax(0, 1fr));
    gap: .45rem;
}
@media (max-width: 1280px) {
    .lease-kpi-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 767px) {
    .lease-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

.lkpi {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    padding: .45rem .65rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .4rem;
    transition: transform .15s, box-shadow .15s, border-color .15s;
    overflow: hidden;
    position: relative;
}
.lkpi:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
    border-color: var(--color-primary-border);
}
.lkpi-left { min-width: 0; flex: 1; }
.lkpi-label {
    font-family: var(--font-display);
    font-size: .6rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin: 0;
}
.lkpi-value {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 1.15rem;
    line-height: 1.1;
    color: var(--color-primary);
    margin: .08rem 0 0;
    white-space: nowrap;
}
.lkpi-value.neutral { color: var(--color-text); }
.lkpi-value.success { color: var(--color-success); }
.lkpi-value.danger  { color: var(--color-error); }
.lkpi-value.warning { color: var(--color-warning); }
.lkpi-value.info    { color: var(--color-info); }

.lkpi-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--r-md);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .82rem;
}
.lkpi-icon.green { background: var(--color-success-bg); color: var(--color-success); }
.lkpi-icon.red   { background: var(--color-error-bg); color: var(--color-error); }
.lkpi-icon.blue  { background: var(--color-info-bg); color: var(--color-info); }
.lkpi-icon.amber { background: var(--color-warning-bg); color: var(--color-warning); }
.lkpi-icon.grey  { background: rgba(107,114,128,.1); color: #6b7280; }

.lease-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: .75rem;
    padding-top: .75rem;
}
.lease-header-left h1 {
    font-family: var(--font-display);
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--color-text);
    margin: 0;
    letter-spacing: -.01em;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.lease-header-left h1 i {
    color: var(--color-primary);
    font-size: .9rem;
}
.lease-header-left .sub {
    font-family: var(--font-body);
    font-size: .75rem;
    color: var(--color-secondary-text);
    margin: .2rem 0 0;
}

.status-hub-wrapper {
    flex: 1;
    display: flex;
    justify-content: flex-end;
    align-items: center;
}
.status-hub-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    display: flex;
    align-items: center;
    padding: .5rem 1.2rem;
    gap: 1rem;
    box-shadow: var(--shadow-md);
    position: relative;
    flex-wrap: wrap;
}
.hub-section {
    display: flex;
    flex-direction: column;
}
.hub-sep {
    width: 1px;
    height: 30px;
    background: var(--color-border-subtle);
}
.hub-label {
    font-family: var(--font-display);
    font-size: .6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--color-secondary-text);
}
.hub-value {
    font-family: var(--font-display);
    font-size: .9rem;
    font-weight: 800;
    color: var(--color-text);
}
.hub-value.highlight {
    color: var(--color-error);
}
.hub-timer {
    font-family: var(--font-mono, monospace);
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--color-error);
    font-variant-numeric: tabular-nums;
}
.toggle-container {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}
.toggle-text {
    font-size: .55rem;
    font-weight: 800;
    text-transform: uppercase;
    color: var(--color-primary);
}
.fl-switch {
    position: relative;
    display: inline-block;
    width: 38px;
    height: 20px;
}
.fl-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.fl-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background-color: var(--color-border-subtle);
    transition: .3s;
    border-radius: 20px;
}
.fl-slider:before {
    position: absolute;
    content: "";
    height: 14px;
    width: 14px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,.2);
}
input:checked + .fl-slider {
    background-color: var(--color-success);
}
input:checked + .fl-slider:before {
    transform: translateX(18px);
}
.hub-inline-control {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    min-width: 130px;
}
.hub-inline-control input[type="time"] {
    height: 32px;
    border: 1px solid var(--color-input-border);
    background: var(--color-input-bg);
    color: var(--color-text);
    border-radius: var(--r-sm);
    padding: .15rem .4rem;
    font-size: .75rem;
    outline: none;
}
.hub-upcoming {
    font-family: var(--font-body);
    font-size: .68rem;
    color: var(--color-secondary-text);
    white-space: nowrap;
}

.lease-toolbar {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
    margin-bottom: .65rem;
}
.filter-pill-wrap {
    position: relative;
}
.filter-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .32rem .65rem;
    border-radius: var(--r-pill);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    color: var(--color-text);
    font-family: var(--font-display);
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .02em;
    cursor: pointer;
    transition: border-color .15s, background .15s, color .15s;
    white-space: nowrap;
    position: relative;
}
.filter-pill-btn:hover,
.filter-pill-btn.active {
    border-color: var(--color-primary);
    background: var(--color-primary-light);
    color: var(--color-primary);
}
.filter-pill-btn .fchev {
    font-size: .5rem;
    color: var(--color-secondary-text);
    transition: transform .2s;
}
.filter-pill-btn.open .fchev {
    transform: rotate(180deg);
}
.filter-dropdown-menu {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    min-width: 200px;
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-lg);
    z-index: var(--z-dropdown);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-4px);
    transition: opacity .16s, transform .16s, visibility 0s .16s;
    padding: .35rem 0;
    overflow: hidden;
}
.filter-dropdown-menu.open {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    transition: opacity .16s, transform .16s, visibility 0s;
}
.fdrop-item {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .42rem .85rem;
    font-family: var(--font-display);
    font-weight: 600;
    font-size: .72rem;
    color: var(--color-text);
    cursor: pointer;
    transition: background .1s, color .1s;
    white-space: nowrap;
}
.fdrop-item:hover {
    background: var(--color-sidebar-active);
    color: var(--color-primary);
}
.fdrop-item.selected {
    background: var(--color-primary-light);
    color: var(--color-primary);
}
.fdrop-item .fdot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.fdrop-item .fcheck {
    margin-left: auto;
    font-size: .6rem;
    color: var(--color-primary);
    opacity: 0;
}
.fdrop-item.selected .fcheck {
    opacity: 1;
}
.fdrop-label {
    padding: .3rem .85rem .1rem;
    font-family: var(--font-display);
    font-size: .55rem;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    opacity: .7;
}
.fdrop-sep {
    height: 1px;
    background: var(--color-border-subtle);
    margin: .25rem 0;
}
.fdrop-input-wrap {
    padding: .35rem .6rem;
}
.fdrop-input-wrap input,
.fdrop-date-range input {
    width: 100%;
    border: 1px solid var(--color-input-border);
    background: var(--color-input-bg);
    color: var(--color-text);
    border-radius: var(--r-sm);
    padding: .3rem .5rem;
    font-size: .72rem;
    font-family: var(--font-body);
    outline: none;
}
.fdrop-date-range {
    padding: .35rem .6rem;
    display: flex;
    align-items: center;
    gap: .3rem;
}
.fdrop-date-range span {
    font-size: .6rem;
    color: var(--color-secondary-text);
    flex-shrink: 0;
}
.toolbar-sep {
    width: 1px;
    height: 20px;
    background: var(--color-border-subtle);
    flex-shrink: 0;
}
.lease-search-wrap {
    position: relative;
    flex: 1;
    min-width: 180px;
    max-width: 280px;
}
.lease-search-wrap i {
    position: absolute;
    left: .6rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: .7rem;
    color: var(--color-secondary-text);
    pointer-events: none;
}
.lease-search-wrap input {
    width: 100%;
    border: 1px solid var(--color-input-border);
    background: var(--color-input-bg);
    color: var(--color-text);
    border-radius: var(--r-pill);
    padding: .32rem .6rem .32rem 2rem;
    font-size: .78rem;
    font-family: var(--font-body);
    outline: none;
}
.active-filters-strip {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
    margin-bottom: .5rem;
}
.active-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .18rem .5rem .18rem .55rem;
    border-radius: var(--r-pill);
    background: var(--color-primary-light);
    border: 1px solid var(--color-primary-border);
    color: var(--color-primary);
    font-family: var(--font-display);
    font-size: .6rem;
    font-weight: 700;
}
.active-filter-chip button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-primary);
    font-size: .65rem;
    padding: 0;
    line-height: 1;
    opacity: .7;
}

.lease-table-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.lease-table-scroll {
    overflow-x: auto;
    overflow-y: auto;
    max-height: calc(100vh - var(--navbar-h, 64px) - var(--kpi-h, 0px) - 220px);
    min-height: 400px;
}
#leaseTable {
    min-width: 1400px;
}
#leaseTable thead {
    position: sticky;
    top: 0;
    z-index: 2;
}
#leaseTable thead th {
    background: var(--color-bg-subtle) !important;
    white-space: nowrap;
}
.dark-mode #leaseTable thead th {
    background: #161b22 !important;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: .28rem;
    padding: .22rem .55rem;
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 700;
    letter-spacing: .04em;
    white-space: nowrap;
}
.status-paid {
    background: var(--color-success-bg);
    color: var(--color-success);
    border: 1px solid rgba(22,163,74,.2);
}
.status-unpaid {
    background: var(--color-error-bg);
    color: var(--color-error);
    border: 1px solid rgba(220,38,38,.2);
}
.status-forgiven {
    background: var(--color-warning-bg);
    color: var(--color-warning);
    border: 1px solid rgba(217,119,6,.2);
}
.cut-badge {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    padding: .2rem .48rem;
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 700;
}
.cut-yes {
    background: rgba(220,38,38,.1);
    color: #dc2626;
}
.cut-no {
    background: rgba(22,163,74,.1);
    color: #16a34a;
}
.method-badge {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    padding: .2rem .5rem;
    border-radius: var(--r-sm);
    font-family: var(--font-display);
    font-size: .6rem;
    font-weight: 700;
    background: var(--color-primary-light);
    color: var(--color-primary);
    border: 1px solid var(--color-primary-border);
}
.amount-cell {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: .78rem;
    white-space: nowrap;
}
.amount-cell.required {
    color: var(--color-text);
}
.amount-cell.paid-ok {
    color: var(--color-success);
}
.amount-cell.paid-no {
    color: var(--color-error);
}
.immat-badge {
    display: inline-block;
    padding: .2rem .5rem;
    border-radius: var(--r-sm);
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border-subtle);
    font-family: var(--font-mono, monospace);
    font-size: .72rem;
    font-weight: 700;
    color: var(--color-text);
    letter-spacing: .02em;
    white-space: nowrap;
}
.time-cell {
    font-family: var(--font-mono, monospace);
    font-size: .72rem;
    color: var(--color-secondary-text);
    white-space: nowrap;
}
.tbl-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: var(--r-sm);
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    cursor: pointer;
    font-size: .72rem;
    transition: background .12s, color .12s, border-color .12s;
    flex-shrink: 0;
}
.tbl-action.pay {
    color: var(--color-success);
    border-color: rgba(22,163,74,.3);
}
.tbl-action.pay:hover {
    background: var(--color-success-bg);
    border-color: var(--color-success);
}
.tbl-action.forgive {
    color: var(--color-warning);
    border-color: rgba(217,119,6,.3);
}
.tbl-action.forgive:hover {
    background: var(--color-warning-bg);
    border-color: var(--color-warning);
}
.tbl-action.cut {
    color: var(--color-error);
    border-color: rgba(220,38,38,.3);
}
.tbl-action.cut:hover {
    background: var(--color-error-bg);
    border-color: var(--color-error);
}
.tbl-action:disabled {
    opacity: .3;
    cursor: not-allowed;
    pointer-events: none;
}
.lease-table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .65rem 1rem;
    border-top: 1px solid var(--color-border-subtle);
    flex-wrap: wrap;
}
.lease-table-info {
    font-family: var(--font-display);
    font-size: .68rem;
    color: var(--color-secondary-text);
    display: flex;
    align-items: center;
    gap: .4rem;
}
.lease-pagination {
    display: flex;
    align-items: center;
    gap: .2rem;
}
.page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 .4rem;
    border-radius: var(--r-sm);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    color: var(--color-text);
    font-family: var(--font-body);
    font-size: .72rem;
    cursor: pointer;
}
.page-btn:hover {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary);
}
.page-btn.active {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
    font-weight: 700;
}
.page-btn.disabled {
    opacity: .3;
    pointer-events: none;
}
.lease-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--color-secondary-text);
    font-family: var(--font-display);
    font-size: .82rem;
}
.lease-empty i {
    font-size: 2rem;
    color: var(--color-border);
    display: block;
    margin-bottom: .6rem;
}
.perpage-select {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    font-family: var(--font-display);
    font-size: .65rem;
    color: var(--color-secondary-text);
}
.perpage-select select {
    border: 1px solid var(--color-input-border);
    background: var(--color-input-bg);
    color: var(--color-text);
    border-radius: var(--r-sm);
    padding: .2rem .4rem;
    font-size: .68rem;
    font-family: var(--font-display);
    outline: none;
    cursor: pointer;
    width: auto;
    appearance: auto;
}

.fl-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: var(--z-modal);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(3px);
}
.fl-modal-overlay.open {
    display: flex;
}
.fl-modal-panel {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-xl);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: var(--shadow-xl);
    transform: translateY(12px) scale(.98);
    opacity: 0;
    transition: transform .22s ease, opacity .22s ease;
}
.fl-modal-panel.visible {
    transform: translateY(0) scale(1);
    opacity: 1;
}
.fl-modal-panel.sm {
    max-width: 400px;
}
.fl-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .75rem;
    padding: 1.25rem 1.25rem .75rem;
    border-bottom: 1px solid var(--color-border-subtle);
}
.fl-modal-title {
    font-family: var(--font-display);
    font-size: .9rem;
    font-weight: 800;
    color: var(--color-text);
    margin: 0;
    letter-spacing: -.005em;
}
.fl-modal-subtitle {
    font-family: var(--font-body);
    font-size: .72rem;
    color: var(--color-secondary-text);
    margin: .2rem 0 0;
}
.fl-modal-close {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    color: var(--color-secondary-text);
    font-size: 1rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: background .12s, color .12s;
    line-height: 1;
}
.fl-modal-close:hover {
    background: var(--color-error-bg);
    color: var(--color-error);
    border-color: rgba(220,38,38,.3);
}
.fl-modal-body {
    padding: 1rem 1.25rem;
}
.fl-modal-footer {
    padding: .75rem 1.25rem 1.25rem;
    display: flex;
    gap: .5rem;
    justify-content: flex-end;
    border-top: 1px solid var(--color-border-subtle);
}
.modal-row-summary {
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-md);
    padding: .65rem .85rem;
    margin-bottom: .85rem;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .4rem .75rem;
}
.mrs-item .k {
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    margin: 0;
}
.mrs-item .v {
    font-family: var(--font-body);
    font-size: .78rem;
    font-weight: 600;
    color: var(--color-text);
    margin: .1rem 0 0;
}
.fl-form-label {
    display: block;
    font-family: var(--font-display);
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    margin-bottom: .3rem;
}
.fl-form-group {
    margin-bottom: .7rem;
}
.fl-confirm-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto .75rem;
    font-size: 1.25rem;
}
.fl-confirm-icon.warn {
    background: var(--color-warning-bg);
    color: var(--color-warning);
}
.fl-confirm-icon.danger {
    background: var(--color-error-bg);
    color: var(--color-error);
}
.fl-confirm-title {
    font-family: var(--font-display);
    font-size: .9rem;
    font-weight: 800;
    text-align: center;
    color: var(--color-text);
    margin: 0 0 .4rem;
}
.fl-confirm-msg {
    font-family: var(--font-body);
    font-size: .78rem;
    text-align: center;
    color: var(--color-secondary-text);
    line-height: 1.55;
    margin: 0;
}
.fl-confirm-detail {
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-md);
    padding: .55rem .75rem;
    margin-top: .7rem;
    font-family: var(--font-display);
    font-size: .72rem;
    font-weight: 700;
    text-align: center;
    color: var(--color-text);
}
.btn-danger,
.btn-success {
    display: inline-flex;
    align-items: center;
    gap: var(--sp-xs);
    color: #fff;
    padding: .45rem 1rem;
    border-radius: var(--r-md);
    font-family: var(--font-display);
    font-weight: 700;
    font-size: .82rem;
    border: none;
    cursor: pointer;
    min-height: 36px;
    white-space: nowrap;
    transition: background .15s, transform .1s;
}
.btn-danger {
    background: var(--color-error);
}
.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-1px);
}
.btn-success {
    background: var(--color-success);
}
.btn-success:hover {
    background: #15803d;
    transform: translateY(-1px);
}

@media (max-width: 992px) {
    .status-hub-card {
        gap: .8rem;
        padding: .4rem .8rem;
    }
    .hub-timer {
        font-size: .9rem;
    }
}
</style>
@endpush

@section('content')

@if(!empty($pageError))
    <div class="alert alert-danger" style="margin-bottom:.75rem;">
        {{ $pageError }}
    </div>
@endif

@php
    $lease_data = $lease_data ?? [];
    $cutoffHub = $cutoffHub ?? [
        'global_enabled' => false,
        'global_time' => null,
        'next_cutoff_time' => null,
        'upcoming_cutoff_times' => [],
        'active_rules_count' => 0,
        'eligible_unpaid_count' => 0,
    ];
@endphp

<div class="lease-kpi-bar" id="leaseKpiBar">
    <div class="lease-kpi-grid" id="kpiGrid">
        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Collecté</p>
                <p class="lkpi-value" id="kCollected">—</p>
            </div>
            <div class="lkpi-icon green"><i class="fas fa-coins"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Attendu</p>
                <p class="lkpi-value neutral" id="kExpected">—</p>
            </div>
            <div class="lkpi-icon grey"><i class="fas fa-file-invoice-dollar"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Payés</p>
                <p class="lkpi-value success" id="kPaid">—</p>
            </div>
            <div class="lkpi-icon green"><i class="fas fa-check-circle"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Impayés</p>
                <p class="lkpi-value danger" id="kUnpaid">—</p>
            </div>
            <div class="lkpi-icon red"><i class="fas fa-times-circle"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Pardonnés</p>
                <p class="lkpi-value warning" id="kForgiven">—</p>
            </div>
            <div class="lkpi-icon amber"><i class="fas fa-hand-holding-heart"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Montant pardonné</p>
                <p class="lkpi-value warning" id="kForgivenAmount">—</p>
            </div>
            <div class="lkpi-icon amber"><i class="fas fa-hand-holding-heart"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Auto-cut actif</p>
                <p class="lkpi-value danger" id="kCut">—</p>
            </div>
            <div class="lkpi-icon red"><i class="fas fa-power-off"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Auto-cut inactif</p>
                <p class="lkpi-value success" id="kNotCut">—</p>
            </div>
            <div class="lkpi-icon green"><i class="fas fa-bolt"></i></div>
        </div>

        <div class="lkpi">
            <div class="lkpi-left">
                <p class="lkpi-label">Total lignes</p>
                <p class="lkpi-value info" id="kTotal">—</p>
            </div>
            <div class="lkpi-icon blue"><i class="fas fa-list-ol"></i></div>
        </div>
    </div>
</div>

<div style="padding-top:.75rem;">
    <div class="lease-header">
        <div class="lease-header-left">
            <h1><i class="fas fa-motorcycle"></i> Paiements Lease</h1>
            <p class="sub">Gestion des collectes quotidiennes et coupures automatiques</p>
        </div>

        <div class="status-hub-wrapper">
            <div class="status-hub-card" id="autoCutHub">
                <div class="hub-section">
                    <span class="hub-label">Collecte du jour</span>
                    <span class="hub-value" id="currentDateDisplay">{{ now()->format('d/m/Y') }}</span>
                </div>

                <div class="hub-sep"></div>

                <div class="hub-section" id="nextCutSection">
                    <span class="hub-label">Prochaine coupure</span>
                    <span class="hub-value highlight" id="nextCutTimeDisplay">
                        {{ $cutoffHub['next_cutoff_time'] ?? '—' }}
                    </span>
                </div>

                <div class="hub-sep" id="countdownSep"></div>

                <div class="hub-section countdown-box" id="countdownBox">
                    <span class="hub-label">Échéance</span>
                    <span class="hub-timer" id="globalTimer">00:00:00</span>
                </div>

                <div class="hub-sep"></div>

                <div class="hub-inline-control">
                    <span class="hub-label">Heure globale</span>
                    <input type="time" id="globalCutoffTime" value="{{ $cutoffHub['global_time'] ?? '' }}">
                </div>

                <div class="hub-action">
                    <div class="toggle-container">
                        <span class="toggle-text">Coupure Auto</span>
                        <label class="fl-switch">
                            <input type="checkbox" id="masterAutoCutToggle" {{ !empty($cutoffHub['global_enabled']) ? 'checked' : '' }}>
                            <span class="fl-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="hub-action">
                    <button class="btn-primary" type="button" id="saveGlobalCutoffBtn" onclick="window.saveGlobalCutoff()">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>

                <div class="hub-section" style="min-width:160px;">
                    <span class="hub-label">Suivantes</span>
                    <span class="hub-upcoming" id="upcomingCutoffPreview">
                        {{ !empty($cutoffHub['upcoming_cutoff_times']) ? implode(' • ', array_slice($cutoffHub['upcoming_cutoff_times'], 0, 3)) : '—' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="lease-toolbar" id="leaseToolbar">
        <div class="lease-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="leaseSearch" placeholder="Véhicule, chauffeur, agence…" autocomplete="off">
        </div>

        <div class="toolbar-sep"></div>

        <div class="filter-pill-wrap" id="wrap-statut">
            <button class="filter-pill-btn" id="btn-statut" onclick="window.toggleDrop('statut')">
                <i class="fas fa-tag" style="font-size:.6rem;"></i>
                Statut
                <i class="fas fa-chevron-down fchev"></i>
            </button>
            <div class="filter-dropdown-menu" id="drop-statut">
                <div class="fdrop-label">Filtrer par statut</div>
                <div class="fdrop-item selected" data-filter="statut" data-val="all" onclick="window.setFilter('statut','all',this)">
                    <span class="fdot" style="background:var(--color-border)"></span>
                    Tous les statuts
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="statut" data-val="paid" onclick="window.setFilter('statut','paid',this)">
                    <span class="fdot" style="background:var(--color-success)"></span>
                    Payés
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="statut" data-val="unpaid" onclick="window.setFilter('statut','unpaid',this)">
                    <span class="fdot" style="background:var(--color-error)"></span>
                    Impayés
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="statut" data-val="forgiven" onclick="window.setFilter('statut','forgiven',this)">
                    <span class="fdot" style="background:var(--color-warning)"></span>
                    Pardonnés
                    <i class="fas fa-check fcheck"></i>
                </div>
            </div>
        </div>

        <div class="filter-pill-wrap" id="wrap-coupure">
            <button class="filter-pill-btn" id="btn-coupure" onclick="window.toggleDrop('coupure')">
                <i class="fas fa-power-off" style="font-size:.6rem;"></i>
                Coupure
                <i class="fas fa-chevron-down fchev"></i>
            </button>
            <div class="filter-dropdown-menu" id="drop-coupure">
                <div class="fdrop-label">Filtrer par coupure</div>
                <div class="fdrop-item selected" data-filter="coupure" data-val="all" onclick="window.setFilter('coupure','all',this)">
                    <span class="fdot" style="background:var(--color-border)"></span>
                    Toutes
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="coupure" data-val="cut" onclick="window.setFilter('coupure','cut',this)">
                    <span class="fdot" style="background:var(--color-error)"></span>
                    Coupure auto active
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="coupure" data-val="notcut" onclick="window.setFilter('coupure','notcut',this)">
                    <span class="fdot" style="background:var(--color-success)"></span>
                    Coupure auto inactive
                    <i class="fas fa-check fcheck"></i>
                </div>
            </div>
        </div>

        <div class="filter-pill-wrap" id="wrap-date">
            <button class="filter-pill-btn" id="btn-date" onclick="window.toggleDrop('date')">
                <i class="fas fa-calendar-alt" style="font-size:.6rem;"></i>
                <span id="date-label">Période</span>
                <i class="fas fa-chevron-down fchev"></i>
            </button>
            <div class="filter-dropdown-menu" id="drop-date" style="min-width:240px;">
                <div class="fdrop-label">Période rapide</div>
                <div class="fdrop-item selected" data-filter="date" data-val="all" onclick="window.setFilter('date','all',this)">
                    <i class="fas fa-infinity" style="font-size:.65rem;width:12px;"></i>
                    Toutes dates
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="date" data-val="today" onclick="window.setFilter('date','today',this)">
                    <i class="fas fa-sun" style="font-size:.65rem;width:12px;"></i>
                    Aujourd'hui
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="date" data-val="yesterday" onclick="window.setFilter('date','yesterday',this)">
                    <i class="fas fa-moon" style="font-size:.65rem;width:12px;"></i>
                    Hier
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="date" data-val="week" onclick="window.setFilter('date','week',this)">
                    <i class="fas fa-calendar-week" style="font-size:.65rem;width:12px;"></i>
                    Cette semaine
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="date" data-val="month" onclick="window.setFilter('date','month',this)">
                    <i class="fas fa-calendar" style="font-size:.65rem;width:12px;"></i>
                    Ce mois
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div class="fdrop-item" data-filter="date" data-val="year" onclick="window.setFilter('date','year',this)">
                    <i class="fas fa-calendar-check" style="font-size:.65rem;width:12px;"></i>
                    Cette année
                    <i class="fas fa-check fcheck"></i>
                </div>

                <div class="fdrop-sep"></div>

                <div class="fdrop-item" data-filter="date" data-val="specific" onclick="window.setFilter('date','specific',this)">
                    <i class="fas fa-calendar-day" style="font-size:.65rem;width:12px;"></i>
                    Date spécifique…
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div id="date-specific-input" style="display:none;">
                    <div class="fdrop-input-wrap">
                        <input type="date" id="filterDateSpecific" onchange="window.applyDateFilter()">
                    </div>
                </div>

                <div class="fdrop-item" data-filter="date" data-val="range" onclick="window.setFilter('date','range',this)">
                    <i class="fas fa-calendar-minus" style="font-size:.65rem;width:12px;"></i>
                    Plage de dates…
                    <i class="fas fa-check fcheck"></i>
                </div>
                <div id="date-range-inputs" style="display:none;">
                    <div class="fdrop-date-range">
                        <input type="date" id="filterDateFrom" onchange="window.applyDateFilter()">
                        <span>→</span>
                        <input type="date" id="filterDateTo" onchange="window.applyDateFilter()">
                    </div>
                </div>
            </div>
        </div>

        <button class="filter-pill-btn" id="btnResetFilters" onclick="window.resetAllFilters()" title="Réinitialiser les filtres" style="display:none;">
            <i class="fas fa-rotate-left" style="font-size:.6rem;"></i>
            Réinitialiser
        </button>
    </div>

    <div class="active-filters-strip" id="activeFiltersStrip"></div>

    <div class="lease-table-card">
        <div class="lease-table-scroll">
            <table class="ui-table" id="leaseTable">
                <thead>
                    <tr>
                        <th style="cursor:pointer;" onclick="window.sortBy('date')" title="Trier par date">
                            Date <i class="fas fa-sort" style="font-size:.55rem;opacity:.4;"></i>
                        </th>
                        <th>Véhicule</th>
                        <th style="cursor:pointer;" onclick="window.sortBy('chauffeur')">
                            Chauffeur <i class="fas fa-sort" style="font-size:.55rem;opacity:.4;"></i>
                        </th>
                        <th>Agence</th>
                        <th style="text-align:right;">Requis</th>
                        <th style="text-align:right;">Payé</th>
                        <th>Encaissé par</th>
                        <th>Méthode</th>
                        <th>Pardonné par</th>
                        <th>Statut</th>
                        <th>Coupure</th>
                        <th>H. coupure</th>
                        <th>H. enreg.</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="leaseTableBody"></tbody>
            </table>
        </div>

        <div class="lease-table-footer">
            <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
                <div class="lease-table-info">
                    <i class="fas fa-info-circle" style="color:var(--color-primary);font-size:.65rem;"></i>
                    <span id="tableInfo">— lignes</span>
                </div>
                <div class="perpage-select">
                    Afficher
                    <select id="perPage" onchange="window.setPerPage(this.value)">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    lignes
                </div>
            </div>
            <div class="lease-pagination" id="pagination"></div>
        </div>
    </div>
</div>

<div id="modalPayment" class="fl-modal-overlay" aria-modal="true" role="dialog">
    <div class="fl-modal-panel" id="modalPaymentPanel">
        <div class="fl-modal-header">
            <div>
                <h2 class="fl-modal-title">
                    <i class="fas fa-coins" style="color:var(--color-success);font-size:.85rem;margin-right:.35rem;"></i>
                    Enregistrer un paiement cash
                </h2>
                <p class="fl-modal-subtitle" id="payModalSub">—</p>
            </div>
            <button class="fl-modal-close" onclick="window.closeModal('modalPayment')">&times;</button>
        </div>

        <div class="fl-modal-body">
            <div class="modal-row-summary" id="payModalSummary">
                <div class="mrs-item">
                    <p class="k">Véhicule</p>
                    <p class="v" id="pms-vehicule">—</p>
                </div>
                <div class="mrs-item">
                    <p class="k">Chauffeur</p>
                    <p class="v" id="pms-chauffeur">—</p>
                </div>
                <div class="mrs-item">
                    <p class="k">Date</p>
                    <p class="v" id="pms-date">—</p>
                </div>
                <div class="mrs-item">
                    <p class="k">Montant requis</p>
                    <p class="v" id="pms-requis" style="color:var(--color-primary);">—</p>
                </div>
            </div>

            <div class="fl-form-group">
                <label class="fl-form-label">Enregistré par</label>
                <input type="text"
                       id="paymentRecordedBy"
                       class="ui-input-style"
                       value="{{ $connectedUserName ?? 'Utilisateur connecté' }}"
                       readonly>
            </div>

            <div class="fl-form-group">
                <label class="fl-form-label">Montant payé (XAF) <span style="color:var(--color-error);">*</span></label>
                <input type="number" id="payAmount" class="ui-input-style" placeholder="2500" min="1" step="100">
            </div>
        </div>

        <div class="fl-modal-footer">
            <button class="btn-secondary" onclick="window.closeModal('modalPayment')">Annuler</button>
            <button class="btn-success" id="confirmPaymentBtn" onclick="window.confirmPayment()">
                <i class="fas fa-check"></i> Valider le paiement
            </button>
        </div>
    </div>
</div>

<div id="modalForgive" class="fl-modal-overlay" aria-modal="true" role="dialog">
    <div class="fl-modal-panel sm" id="modalForgivePanel">
        <div class="fl-modal-header">
            <div>
                <h2 class="fl-modal-title">Confirmer le pardon</h2>
            </div>
            <button class="fl-modal-close" onclick="window.closeModal('modalForgive')">&times;</button>
        </div>

        <div class="fl-modal-body" style="text-align:center;padding-top:1.25rem;">
            <div class="fl-confirm-icon warn"><i class="fas fa-hand-holding-heart"></i></div>
            <p class="fl-confirm-title">Accorder un pardon ?</p>
            <p class="fl-confirm-msg">Vous allez marquer ce paiement comme pardonné. Le chauffeur ne sera pas débité pour cette journée.</p>
            <div class="fl-confirm-detail" id="forgiveDetail">—</div>
            <div style="margin-top:.75rem;">
                <label class="fl-form-label" style="text-align:left;display:block;">Pardonné par</label>
                <input type="text"
                       id="forgiveBy"
                       class="ui-input-style"
                       value="{{ $connectedUserName ?? 'Utilisateur connecté' }}"
                       readonly>
            </div>

            <div style="margin-top:.75rem;">
                <label class="fl-form-label" style="text-align:left;display:block;">Raison du pardon</label>
                <textarea id="forgiveReason"
                          class="ui-input-style"
                          rows="3"
                          placeholder="Ex: paiement en retard, arrangement validé, problème technique, pardon préventif..."
                          style="resize:vertical;"></textarea>
            </div>
        </div>

        <div class="fl-modal-footer">
            <button class="btn-secondary" onclick="window.closeModal('modalForgive')">Annuler</button>
            <button class="btn-primary" onclick="window.confirmForgive()" style="background:var(--color-warning);">
                <i class="fas fa-hand-holding-heart"></i> Accorder le pardon
            </button>
        </div>
    </div>
</div>

<div id="modalCut" class="fl-modal-overlay" aria-modal="true" role="dialog">
    <div class="fl-modal-panel sm" id="modalCutPanel">
        <div class="fl-modal-header">
            <div>
                <h2 class="fl-modal-title">Confirmer la coupure</h2>
            </div>
            <button class="fl-modal-close" onclick="window.closeModal('modalCut')">&times;</button>
        </div>

        <div class="fl-modal-body" style="text-align:center;padding-top:1.25rem;">
            <div class="fl-confirm-icon danger"><i class="fas fa-power-off"></i></div>
            <p class="fl-confirm-title">Couper le moteur ?</p>
            <p class="fl-confirm-msg">Cette action va envoyer une commande de coupure moteur au véhicule.</p>
            <div class="fl-confirm-detail" id="cutDetail">—</div>
        </div>

        <div class="fl-modal-footer">
            <button class="btn-secondary" onclick="window.closeModal('modalCut')">Annuler</button>
            <button class="btn-danger" onclick="window.confirmCut()">
                <i class="fas fa-power-off"></i> Confirmer la coupure
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const RAW_DATA = @json($lease_data ?? []);
    const HUB_DATA = @json($cutoffHub ?? []);
    const CONNECTED_USER_NAME = @json($connectedUserName ?? 'Utilisateur connecté');

    const GLOBAL_CUTOFF_UPDATE_URL = @json(route('leases.global-cutoff.update'));
    const CASH_PAYMENT_URL = @json(route('leases.payments.cash'));
    const FORGIVE_URL_TEMPLATE = @json(route('leases.forgive', ['leaseId' => '__LEASE_ID__']));

    let filteredData = [...RAW_DATA];
    let currentPage = 1;
    let perPage = 25;
    let sortKey = 'date';
    let sortDir = 'desc';

    let activeFilters = {
        statut: 'all',
        coupure: 'all',
        date: 'all'
    };

    let searchQuery = '';
    let pendingRowId = null;

    const FORGIVEN_STATUSES = [
        'forgiven',
        'forgiven_before_cut',
        'forgiven_after_cut',
        'forgiven_reactivation_pending',
        'forgiven_reactivation_failed',
    ];

    const fmt = n => Number(n || 0).toLocaleString('fr-FR') + ' XAF';

    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[m]));

    const today = () => new Date().toISOString().slice(0, 10);

    const yesterday = () => {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return d.toISOString().slice(0, 10);
    };

    const weekStart = () => {
        const d = new Date();
        d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
        return d.toISOString().slice(0, 10);
    };

    const monthStart = () => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
    };

    const yearStart = () => `${new Date().getFullYear()}-01-01`;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function buildTargetDate(timeStr) {
        if (!timeStr || !/^\d{2}:\d{2}$/.test(timeStr)) {
            return null;
        }

        const [hour, minute] = timeStr.split(':').map(Number);
        const now = new Date();
        const target = new Date();

        target.setHours(hour, minute, 0, 0);

        if (target <= now) {
            target.setDate(target.getDate() + 1);
        }

        return target;
    }

    function normalizeUpcomingTimes() {
        const list = Array.isArray(HUB_DATA.upcoming_cutoff_times)
            ? HUB_DATA.upcoming_cutoff_times
            : [];

        return [...new Set(list.filter(Boolean))]
            .map(t => String(t).slice(0, 5))
            .sort((a, b) => {
                const ta = buildTargetDate(a);
                const tb = buildTargetDate(b);
                return (ta?.getTime() || 0) - (tb?.getTime() || 0);
            });
    }

    function getNextUpcomingTime() {
        const sorted = normalizeUpcomingTimes();
        return sorted[0] || null;
    }

    function renderUpcomingPreview() {
        const el = document.getElementById('upcomingCutoffPreview');

        if (!el) {
            return;
        }

        const list = normalizeUpcomingTimes();
        el.textContent = list.length ? list.slice(0, 4).join(' • ') : '—';
    }

    function rotateUpcomingTimeIfNeeded() {
        const next = HUB_DATA.next_cutoff_time || getNextUpcomingTime();

        if (!next) {
            return;
        }

        const target = buildTargetDate(next);

        if (!target) {
            return;
        }

        if (target.getTime() <= Date.now()) {
            const list = normalizeUpcomingTimes();
            HUB_DATA.next_cutoff_time = list[0] || null;
        }
    }

    function runHubCountdown() {
        const nextCutTimeDisplay = document.getElementById('nextCutTimeDisplay');
        const timerDisplay = document.getElementById('globalTimer');

        rotateUpcomingTimeIfNeeded();

        const nextTime = HUB_DATA.next_cutoff_time || getNextUpcomingTime();

        if (!nextTime) {
            if (nextCutTimeDisplay) {
                nextCutTimeDisplay.textContent = '—';
            }

            if (timerDisplay) {
                timerDisplay.textContent = '00:00:00';
            }

            return;
        }

        const target = buildTargetDate(nextTime);

        if (!target) {
            if (timerDisplay) {
                timerDisplay.textContent = '00:00:00';
            }

            return;
        }

        const diff = target.getTime() - Date.now();

        if (nextCutTimeDisplay) {
            nextCutTimeDisplay.textContent = nextTime;
        }

        if (diff <= 0) {
            if (timerDisplay) {
                timerDisplay.textContent = '00:00:00';
            }

            return;
        }

        const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
        const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
        const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');

        if (timerDisplay) {
            timerDisplay.textContent = `${h}:${m}:${s}`;
        }
    }

    window.saveGlobalCutoff = async function () {
        const enabled = !!document.getElementById('masterAutoCutToggle')?.checked;
        const cutoffTime = document.getElementById('globalCutoffTime')?.value || '';
        const btn = document.getElementById('saveGlobalCutoffBtn');

        if (enabled && !cutoffTime) {
            if (window.showToast) {
                window.showToast('Heure requise', 'Veuillez définir une heure globale.', 'warning');
            } else {
                alert('Veuillez définir une heure globale.');
            }

            return;
        }

        try {
            if (btn) {
                btn.disabled = true;
            }

            const response = await fetch(GLOBAL_CUTOFF_UPDATE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    enabled: enabled,
                    cutoff_time: cutoffTime || null,
                }),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || "Erreur lors de l'enregistrement.");
            }

            if (window.showToast) {
                window.showToast(
                    'Configuration enregistrée',
                    payload.message || 'Mise à jour réussie.',
                    'success'
                );
            }

            window.location.reload();
        } catch (e) {
            if (window.showToast) {
                window.showToast('Erreur', e.message || "Impossible d'enregistrer.", 'error');
            } else {
                alert(e.message || "Impossible d'enregistrer.");
            }
        } finally {
            if (btn) {
                btn.disabled = false;
            }
        }
    };

    function applyFilters() {
        let data = [...RAW_DATA];

        if (activeFilters.statut !== 'all') {
            if (activeFilters.statut === 'forgiven') {
                data = data.filter(r => FORGIVEN_STATUSES.includes(r.statut));
            } else {
                data = data.filter(r => r.statut === activeFilters.statut);
            }
        }

        if (activeFilters.coupure === 'cut') {
            data = data.filter(r => !!r.coupure_auto);
        } else if (activeFilters.coupure === 'notcut') {
            data = data.filter(r => !r.coupure_auto);
        }

        const df = activeFilters.date;

        if (df === 'today') {
            data = data.filter(r => r.date === today());
        } else if (df === 'yesterday') {
            data = data.filter(r => r.date === yesterday());
        } else if (df === 'week') {
            data = data.filter(r => r.date >= weekStart());
        } else if (df === 'month') {
            data = data.filter(r => r.date >= monthStart());
        } else if (df === 'year') {
            data = data.filter(r => r.date >= yearStart());
        } else if (df === 'specific') {
            const v = document.getElementById('filterDateSpecific')?.value;

            if (v) {
                data = data.filter(r => r.date === v);
            }
        } else if (df === 'range') {
            const from = document.getElementById('filterDateFrom')?.value;
            const to = document.getElementById('filterDateTo')?.value;

            if (from) {
                data = data.filter(r => r.date >= from);
            }

            if (to) {
                data = data.filter(r => r.date <= to);
            }
        }

        if (searchQuery.trim()) {
            const q = searchQuery.toLowerCase();

            data = data.filter(r =>
                (r.vehicule || '').toLowerCase().includes(q) ||
                (r.chauffeur || '').toLowerCase().includes(q) ||
                (r.agence || '').toLowerCase().includes(q) ||
                (r.partenaire || '').toLowerCase().includes(q) ||
                (r.phone || '').includes(q) ||
                (r.paye_par || '').toLowerCase().includes(q)
            );
        }

        data.sort((a, b) => {
            let va = a[sortKey] ?? '';
            let vb = b[sortKey] ?? '';

            if (typeof va === 'number') {
                return sortDir === 'asc' ? va - vb : vb - va;
            }

            return sortDir === 'asc'
                ? String(va).localeCompare(String(vb))
                : String(vb).localeCompare(String(va));
        });

        filteredData = data;
        currentPage = 1;

        renderTable();
        renderKPIs();
        renderPagination();
        renderActiveFiltersStrip();
        updateResetBtn();
    }

    function renderTable() {
        const tbody = document.getElementById('leaseTableBody');

        if (!tbody) {
            return;
        }

        const start = (currentPage - 1) * perPage;
        const end = start + perPage;
        const page = filteredData.slice(start, end);

        const info = document.getElementById('tableInfo');

        if (info) {
            const startVal = filteredData.length ? start + 1 : 0;
            const endVal = Math.min(end, filteredData.length);

            info.textContent = `${filteredData.length} ligne${filteredData.length !== 1 ? 's' : ''} (${startVal}–${endVal})`;
        }

        if (!page.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="14">
                        <div class="lease-empty">
                            <i class="fas fa-filter"></i>
                            Aucune ligne ne correspond aux filtres actifs.
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        const statusMap = {
            paid: `
                <span class="status-badge status-paid">
                    <i class="fas fa-check-circle"></i>
                    Payé
                </span>
            `,

            unpaid: `
                <span class="status-badge status-unpaid">
                    <i class="fas fa-times-circle"></i>
                    Impayé
                </span>
            `,

            forgiven: `
                <span class="status-badge status-forgiven">
                    <i class="fas fa-hand-holding-heart"></i>
                    Pardonné
                </span>
            `,

            forgiven_before_cut: `
                <span class="status-badge status-forgiven">
                    <i class="fas fa-shield-heart"></i>
                    Pardon avant coupure
                </span>
            `,

            forgiven_after_cut: `
                <span class="status-badge status-paid">
                    <i class="fas fa-power-off"></i>
                    Pardon après coupure / rallumé
                </span>
            `,

            forgiven_reactivation_pending: `
                <span class="status-badge status-forgiven">
                    <i class="fas fa-rotate"></i>
                    Pardon + rallumage demandé
                </span>
            `,

            forgiven_reactivation_failed: `
                <span class="status-badge status-unpaid">
                    <i class="fas fa-triangle-exclamation"></i>
                    Pardon / rallumage échoué
                </span>
            `,
        };

        tbody.innerHTML = page.map(r => {
            const isPaid = r.statut === 'paid';
            const isUnpaid = r.statut === 'unpaid';
            const isForgiven = FORGIVEN_STATUSES.includes(r.statut);

            const cutBadge = isUnpaid
                ? (
                    r.coupure_auto
                        ? `<span class="cut-badge cut-yes"><i class="fas fa-toggle-on"></i> Auto active</span>`
                        : `<span class="cut-badge cut-no"><i class="fas fa-toggle-off"></i> Auto inactive</span>`
                )
                : `<span style="color:var(--color-secondary-text);font-size:.7rem;">—</span>`;

            const methodBadge = r.methode
                ? `<span class="method-badge">${esc(r.methode)}</span>`
                : `<span style="color:var(--color-secondary-text);font-size:.7rem;">—</span>`;

            const btnPay = !isPaid && !isForgiven
                ? `<button class="tbl-action pay" onclick="window.openPayModal(${Number(r.id)})" title="Enregistrer paiement"><i class="fas fa-coins"></i></button>`
                : `<button class="tbl-action pay" disabled title="Déjà payé ou pardonné"><i class="fas fa-coins"></i></button>`;

            const canForgive = !isForgiven;
            const forgiveTitle = isPaid
                ? 'Pardon après paiement en retard / rallumer'
                : 'Pardonner préventivement';

            const btnForgive = canForgive
                ? `<button class="tbl-action forgive" onclick="window.openForgiveModal(${Number(r.id)})" title="${forgiveTitle}"><i class="fas fa-hand-holding-heart"></i></button>`
                : `<button class="tbl-action forgive" disabled title="Déjà pardonné"><i class="fas fa-hand-holding-heart"></i></button>`;

            const btnCut = isUnpaid
                ? `<button class="tbl-action cut" onclick="window.openCutModal(${Number(r.id)})" title="Couper le moteur"><i class="fas fa-power-off"></i></button>`
                : `<button class="tbl-action cut" disabled title="N/A"><i class="fas fa-power-off"></i></button>`;

            const amountPaidClass = isPaid ? 'paid-ok' : (isUnpaid ? 'paid-no' : '');

            return `
                <tr data-id="${esc(r.id)}">
                    <td><span class="time-cell">${esc(r.date)}</span></td>

                    <td><span class="immat-badge">${esc(r.vehicule)}</span></td>

                    <td style="white-space:nowrap;">
                        <span style="font-weight:600;font-size:.8rem;">${esc(r.chauffeur)}</span>
                    </td>

                    <td style="font-size:.78rem;color:var(--color-secondary-text);white-space:nowrap;">
                        ${esc(r.agence)}
                    </td>

                    <td style="text-align:right;">
                        <span class="amount-cell required">${fmt(r.montant_requis)}</span>
                    </td>

                    <td style="text-align:right;">
                        <span class="amount-cell ${amountPaidClass}">
                            ${Number(r.montant_paye || 0) > 0 ? fmt(r.montant_paye) : '—'}
                        </span>
                    </td>

                    <td style="font-size:.78rem;color:var(--color-secondary-text);">
                        ${esc(r.paye_par) || '—'}
                    </td>

                    <td>${methodBadge}</td>

                    <td style="font-size:.75rem;color:var(--color-secondary-text);">
                        ${esc(r.pardonne_par) || '—'}
                    </td>

                    <td>${statusMap[r.statut] || '—'}</td>

                    <td>${cutBadge}</td>

                    <td><span class="time-cell">${esc(r.heure_coupure) || '—'}</span></td>

                    <td><span class="time-cell">${esc(r.heure_enreg) || '—'}</span></td>

                    <td>
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:.2rem;">
                            ${btnPay}
                            ${btnForgive}
                            ${btnCut}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function renderKPIs() {
        const data = filteredData;

        const paid = data.filter(r => r.statut === 'paid');
        const unpaid = data.filter(r => r.statut === 'unpaid');
        const forgiven = data.filter(r => FORGIVEN_STATUSES.includes(r.statut));
        const cut = data.filter(r => !!r.coupure_auto && r.statut === 'unpaid');
        const notCut = data.filter(r => !r.coupure_auto && r.statut === 'unpaid');

        const collected = paid.reduce((s, r) => s + Number(r.montant_paye || 0), 0);
        const expected = data.reduce((s, r) => s + Number(r.montant_requis || 0), 0);
        const forgivenAmount = forgiven.reduce((s, r) => s + Number(r.montant_requis || 0), 0);

        const set = (id, val) => {
            const el = document.getElementById(id);

            if (el) {
                el.textContent = val;
            }
        };

        set('kCollected', Number(collected).toLocaleString('fr-FR') + ' F');
        set('kExpected', Number(expected).toLocaleString('fr-FR') + ' F');
        set('kPaid', paid.length);
        set('kUnpaid', unpaid.length);
        set('kForgiven', forgiven.length);
        set('kForgivenAmount', Number(forgivenAmount).toLocaleString('fr-FR') + ' F');
        set('kCut', cut.length);
        set('kNotCut', notCut.length);
        set('kTotal', data.length);
    }

    function renderPagination() {
        const totalPages = Math.ceil(filteredData.length / perPage) || 1;
        const pag = document.getElementById('pagination');

        if (!pag) {
            return;
        }

        let html = `<button class="page-btn${currentPage === 1 ? ' disabled' : ''}" onclick="window.goPage(${currentPage - 1})">‹</button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (totalPages > 7) {
                if (i !== 1 && i !== totalPages && Math.abs(i - currentPage) > 2) {
                    if (i === 2 || i === totalPages - 1) {
                        html += `<span style="font-size:.7rem;padding:0 .15rem;color:var(--color-secondary-text);">…</span>`;
                    }

                    continue;
                }
            }

            html += `<button class="page-btn${i === currentPage ? ' active' : ''}" onclick="window.goPage(${i})">${i}</button>`;
        }

        html += `<button class="page-btn${currentPage === totalPages ? ' disabled' : ''}" onclick="window.goPage(${currentPage + 1})">›</button>`;
        pag.innerHTML = html;
    }

    window.goPage = function (n) {
        const total = Math.ceil(filteredData.length / perPage) || 1;

        if (n < 1 || n > total) {
            return;
        }

        currentPage = n;
        renderTable();
        renderPagination();

        document.querySelector('.lease-table-scroll')?.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    window.setPerPage = function (v) {
        perPage = parseInt(v, 10);
        currentPage = 1;
        renderTable();
        renderPagination();
    };

    window.sortBy = function (key) {
        if (sortKey === key) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey = key;
            sortDir = 'desc';
        }

        applyFilters();
    };

    window.toggleDrop = function (name) {
        const menu = document.getElementById('drop-' + name);
        const btn = document.getElementById('btn-' + name);

        if (!menu || !btn) {
            return;
        }

        const isOpen = menu.classList.contains('open');

        document.querySelectorAll('.filter-dropdown-menu.open').forEach(m => m.classList.remove('open'));
        document.querySelectorAll('.filter-pill-btn.open').forEach(b => b.classList.remove('open'));

        if (!isOpen) {
            menu.classList.add('open');
            btn.classList.add('open');
        }
    };

    document.addEventListener('click', e => {
        if (!e.target.closest('.filter-pill-wrap')) {
            document.querySelectorAll('.filter-dropdown-menu.open').forEach(m => m.classList.remove('open'));
            document.querySelectorAll('.filter-pill-btn.open').forEach(b => b.classList.remove('open'));
        }
    });

    window.setFilter = function (name, val, el) {
        activeFilters[name] = val;

        const menu = document.getElementById('drop-' + name);

        menu?.querySelectorAll('.fdrop-item').forEach(item => {
            item.classList.toggle('selected', item.dataset.val === val);
        });

        if (name === 'date') {
            const specInput = document.getElementById('date-specific-input');
            const rangeInput = document.getElementById('date-range-inputs');

            if (specInput) {
                specInput.style.display = val === 'specific' ? 'block' : 'none';
            }

            if (rangeInput) {
                rangeInput.style.display = val === 'range' ? 'block' : 'none';
            }

            const labelMap = {
                all: 'Période',
                today: "Aujourd'hui",
                yesterday: 'Hier',
                week: 'Cette semaine',
                month: 'Ce mois',
                year: 'Cette année',
                specific: 'Date…',
                range: 'Plage…',
            };

            const dlEl = document.getElementById('date-label');

            if (dlEl) {
                dlEl.textContent = labelMap[val] || 'Période';
            }
        }

        const btn = document.getElementById('btn-' + name);
        btn?.classList.toggle('active', val !== 'all');

        applyFilters();
    };

    window.applyDateFilter = function () {
        applyFilters();
    };

    window.resetAllFilters = function () {
        activeFilters = {
            statut: 'all',
            coupure: 'all',
            date: 'all',
        };

        searchQuery = '';

        const searchInput = document.getElementById('leaseSearch');

        if (searchInput) {
            searchInput.value = '';
        }

        ['statut', 'coupure', 'date'].forEach(name => {
            const menu = document.getElementById('drop-' + name);

            menu?.querySelectorAll('.fdrop-item').forEach(item => {
                item.classList.toggle('selected', item.dataset.val === 'all');
            });

            document.getElementById('btn-' + name)?.classList.remove('active');
        });

        const dlEl = document.getElementById('date-label');

        if (dlEl) {
            dlEl.textContent = 'Période';
        }

        const spec = document.getElementById('date-specific-input');
        const range = document.getElementById('date-range-inputs');

        if (spec) {
            spec.style.display = 'none';
        }

        if (range) {
            range.style.display = 'none';
        }

        const specific = document.getElementById('filterDateSpecific');
        const from = document.getElementById('filterDateFrom');
        const to = document.getElementById('filterDateTo');

        if (specific) specific.value = '';
        if (from) from.value = '';
        if (to) to.value = '';

        applyFilters();
    };

    function updateResetBtn() {
        const hasActive = Object.values(activeFilters).some(v => v !== 'all') || searchQuery.trim() !== '';
        const btn = document.getElementById('btnResetFilters');

        if (btn) {
            btn.style.display = hasActive ? 'inline-flex' : 'none';
        }
    }

    function renderActiveFiltersStrip() {
        const strip = document.getElementById('activeFiltersStrip');

        if (!strip) {
            return;
        }

        const chips = [];

        const labelMap = {
            statut: {
                paid: 'Payés',
                unpaid: 'Impayés',
                forgiven: 'Pardonnés',
            },
            coupure: {
                cut: 'Coupure auto active',
                notcut: 'Coupure auto inactive',
            },
            date: {
                today: "Aujourd'hui",
                yesterday: 'Hier',
                week: 'Cette semaine',
                month: 'Ce mois',
                year: 'Cette année',
                specific: 'Date spécifique',
                range: 'Plage de dates',
            },
        };

        ['statut', 'coupure', 'date'].forEach(name => {
            const v = activeFilters[name];

            if (v && v !== 'all') {
                const label = labelMap[name]?.[v] || v;

                chips.push(`
                    <div class="active-filter-chip">
                        ${esc(label)}
                        <button onclick="window.setFilter('${name}','all',null)" title="Retirer ce filtre">&times;</button>
                    </div>
                `);
            }
        });

        if (searchQuery.trim()) {
            chips.push(`
                <div class="active-filter-chip">
                    Rech : "${esc(searchQuery)}"
                    <button onclick="window.clearSearch()" title="Effacer la recherche">&times;</button>
                </div>
            `);
        }

        strip.innerHTML = chips.join('');
    }

    window.clearSearch = function () {
        searchQuery = '';

        const searchInput = document.getElementById('leaseSearch');

        if (searchInput) {
            searchInput.value = '';
        }

        applyFilters();
    };

    document.getElementById('leaseSearch')?.addEventListener('input', function () {
        searchQuery = this.value;
        applyFilters();
    });

    window.openModal = function (id) {
        const overlay = document.getElementById(id);
        const panel = overlay?.querySelector('.fl-modal-panel');

        if (!overlay) {
            return;
        }

        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                panel?.classList.add('visible');
            });
        });
    };

    window.closeModal = function (id) {
        const overlay = document.getElementById(id);
        const panel = overlay?.querySelector('.fl-modal-panel');

        panel?.classList.remove('visible');
        document.body.style.overflow = '';

        setTimeout(() => {
            overlay?.classList.remove('open');
        }, 220);
    };

    ['modalPayment', 'modalForgive', 'modalCut'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', function (e) {
            if (e.target === this) {
                window.closeModal(id);
            }
        });
    });

    window.openPayModal = function (rowId) {
        const row = RAW_DATA.find(r => Number(r.id) === Number(rowId));

        if (!row) {
            return;
        }

        pendingRowId = rowId;

        const sub = document.getElementById('payModalSub');

        if (sub) {
            sub.textContent = `${row.vehicule || '—'} — ${row.chauffeur || '—'} — ${row.date || '—'}`;
        }

        const set = (id, value) => {
            const el = document.getElementById(id);

            if (el) {
                el.textContent = value || '—';
            }
        };

        set('pms-vehicule', row.vehicule);
        set('pms-chauffeur', row.chauffeur);
        set('pms-date', row.date);
        set('pms-requis', fmt(row.montant_requis || row.reste_a_payer || 0));

        const amountInput = document.getElementById('payAmount');

        if (amountInput) {
            amountInput.value = row.reste_a_payer || row.montant_requis || '';
        }

        const recordedBy = document.getElementById('paymentRecordedBy');

        if (recordedBy) {
            recordedBy.value = CONNECTED_USER_NAME;
        }

        window.openModal('modalPayment');
    };

    window.confirmPayment = async function () {
        const amount = parseInt(document.getElementById('payAmount')?.value, 10);
        const btn = document.getElementById('confirmPaymentBtn');

        if (!amount || amount <= 0) {
            alert('Veuillez saisir un montant valide.');
            return;
        }

        const row = RAW_DATA.find(r => Number(r.id) === Number(pendingRowId));

        if (!row) {
            alert('Ligne de paiement introuvable.');
            return;
        }

        const leaseId = row.source_lease_id || row.id;

        try {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            }

            const response = await fetch(CASH_PAYMENT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    lease_id: Number(leaseId),
                    montant: amount,
                    recorded_by_name: CONNECTED_USER_NAME,
                }),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || "Impossible d'enregistrer le paiement.");
            }

            window.closeModal('modalPayment');

            if (window.showToast) {
                window.showToast('Paiement enregistré', `${fmt(amount)} en cash`, 'success');
            }

            window.location.reload();
        } catch (e) {
            alert(e.message || "Erreur pendant l'enregistrement du paiement.");
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Valider le paiement';
            }
        }
    };

    window.openForgiveModal = function (rowId) {
        const row = RAW_DATA.find(r => Number(r.id) === Number(rowId));

        if (!row) {
            return;
        }

        pendingRowId = rowId;

        const detail = document.getElementById('forgiveDetail');

        if (detail) {
            const statusLabel = row.statut === 'paid'
                ? 'Payé en retard / vérifier rallumage'
                : 'Impayé / pardon préventif';

            detail.textContent = `${row.vehicule} — ${row.chauffeur} — ${row.date} — ${fmt(row.montant_requis)} — ${statusLabel}`;
        }

        const forgiveBy = document.getElementById('forgiveBy');
        const forgiveReason = document.getElementById('forgiveReason');

        if (forgiveBy) {
            forgiveBy.value = CONNECTED_USER_NAME;
        }

        if (forgiveReason) {
            forgiveReason.value = row.statut === 'paid'
                ? 'Paiement effectué en retard alors que le véhicule était déjà coupé. Demande de rallumage.'
                : 'Pardon préventif accordé avant coupure automatique.';
        }

        window.openModal('modalForgive');
    };

    window.confirmForgive = async function () {
        const row = RAW_DATA.find(r => Number(r.id) === Number(pendingRowId));

        if (!row) {
            alert('Ligne de paiement introuvable.');
            return;
        }

        const leaseId = row.source_lease_id || row.id;
        const url = FORGIVE_URL_TEMPLATE.replace('__LEASE_ID__', String(leaseId));
        const forgivenBy = document.getElementById('forgiveBy')?.value || '';
        const reason = document.getElementById('forgiveReason')?.value || '';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    forgiven_by: forgivenBy || CONNECTED_USER_NAME,
                    reason: reason,
                }),
            });

            const payload = await response.json();

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || "Impossible d'enregistrer le pardon.");
            }

            window.closeModal('modalForgive');

            if (window.showToast) {
                window.showToast(
                    'Pardon enregistré',
                    payload.message || 'Pardon traité.',
                    'success'
                );
            }

            window.location.reload();
        } catch (e) {
            alert(e.message || 'Erreur pendant le pardon.');
        }
    };

    window.openCutModal = function (rowId) {
        const row = RAW_DATA.find(r => Number(r.id) === Number(rowId));

        if (!row) {
            return;
        }

        pendingRowId = rowId;

        const detail = document.getElementById('cutDetail');

        if (detail) {
            detail.textContent = `${row.vehicule} — ${row.chauffeur}`;
        }

        window.openModal('modalCut');
    };

    window.confirmCut = function () {
        const row = RAW_DATA.find(r => Number(r.id) === Number(pendingRowId));

        if (row) {
            row.coupe = true;
        }

        window.closeModal('modalCut');

        if (window.showToast) {
            window.showToast(
                'Coupure confirmée',
                `Moteur coupé : ${row?.vehicule || ''}`,
                'error'
            );
        }

        applyFilters();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const perPageSelect = document.getElementById('perPage');

        if (perPageSelect) {
            perPage = parseInt(perPageSelect.value || '25', 10);
        }

        renderTable();
        renderKPIs();
        renderPagination();
        renderActiveFiltersStrip();
        renderUpcomingPreview();
        updateResetBtn();
        runHubCountdown();

        setInterval(runHubCountdown, 1000);
    });
})();
</script>
@endpush
