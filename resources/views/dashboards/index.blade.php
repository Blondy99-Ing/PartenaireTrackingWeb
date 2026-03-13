{{-- resources/views/dashboard/live.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard Live')

@push('styles')
<style>
/* ============================================================
   LIVE DASHBOARD — Light by default (modern minimal)
   FIXES:
   - tripModal s'ouvre systématiquement + robustesse backend
   - vehicleModal ajouté
   - replay: vitesse 0.25x→16x + follow toggle
   - switchTab conserve état trajet/polyline
   - replaySlower min 0.25x, replayFaster max 16x
   - Follow map centering pendant replay
   ============================================================ */

:root {
    --z-kpi: 0;
    --z-map-ui: 140;
    --z-modal: 170;
    --z-legend: 130;
}

/* Sticky KPI bar */
.kpi-sticky {
    position: fixed;
    top: var(--navbar-h, 52px);
    left: 0;
    right: 0;
    z-index: var(--z-kpi);
    background: var(--color-bg);
    padding: .5rem 1.25rem .35rem;
    box-shadow: 0 6px 24px rgba(0, 0, 0, .08);
}

.dark-mode .kpi-sticky {
    box-shadow: 0 10px 40px rgba(0, 0, 0, .45)
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: .5rem
}

@media(max-width:1023px) {
    .kpi-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr))
    }
}

.card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-lg, 10px);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.kpi {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .35rem .55rem;
    cursor: pointer;
    transition: .15s
}

.kpi:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
    border-color: var(--color-primary-border)
}

.kpi .lbl {
    font-family: var(--font-display);
    font-size: .62rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e);
    margin: 0
}

.kpi .val {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 1.2rem;
    line-height: 1;
    color: var(--color-primary);
    margin: .1rem 0 0
}

.kpi .ico {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary-light)
}

.kpi .ico i {
    color: var(--color-primary)
}

.kpi-panel {
    padding: .55rem .75rem
}

.kpi-types {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: .5rem;
}

@media(max-width:1023px) {
    .kpi-types {
        grid-template-columns: repeat(2, minmax(0, 1fr))
    }
}

.type {
    border: 1px solid var(--color-border-subtle);
    border-radius: 10px;
    padding: .45rem .55rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    cursor: pointer;
    transition: .15s;
    background: rgba(0, 0, 0, .03)
}

.dark-mode .type {
    background: rgba(255, 255, 255, .03)
}

.type:hover {
    border-color: var(--color-primary-border);
    background: var(--color-primary-light)
}

.type .t {
    font-family: var(--font-body);
    font-size: .62rem;
    color: var(--color-secondary-text, #8b949e);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis
}

.type .n {
    font-family: var(--font-display);
    font-weight: 900;
    font-size: 1.1rem;
    color: var(--color-text);
    margin: .05rem 0 0
}

/* Content spacing */
.content {
    margin-top: calc(var(--kpi-h, 96px) + .75rem);
    display: flex;
    flex-direction: column;
    gap: 1rem
}

/* Layout */
.grid-main {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem
}

@media(max-width:1023px) {
    .grid-main {
        grid-template-columns: 1fr
    }
}

@media(min-width:1024px) {
    .grid-main {
        height: calc(100vh - var(--navbar-h, 52px) - var(--kpi-h, 96px) - 2.5rem)
    }

    .col-left,
    .col-map {
        min-height: 0
    }
}

/* Left panel */
.tabs {
    display: flex;
    border-bottom: 1px solid var(--color-border-subtle)
}

.tab {
    flex: 1;
    text-align: center;
    padding: .55rem .4rem;
    font-family: var(--font-display);
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e);
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer
}

.tab:hover {
    color: var(--color-text)
}

.tab.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
    background: var(--color-primary-light)
}

.badge {
    background: #dc2626;
    color: #fff;
    border-radius: 9999px;
    font-size: .55rem;
    font-weight: 900;
    padding: 0 .35rem;
    min-width: 16px;
    display: inline-flex;
    justify-content: center
}

.pane {
    display: none;
    flex-direction: column;
    min-height: 0;
    flex: 1
}

.pane.active {
    display: flex
}

.search {
    padding: .55rem .75rem 0
}

.swrap {
    position: relative
}

.swrap i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: .7rem;
    color: var(--color-secondary-text, #8b949e)
}

.swrap input {
    width: 100%;
    border: 1px solid var(--color-border-subtle);
    border-radius: 10px;
    padding: .5rem .6rem .5rem 2rem;
    font-size: .78rem;
    background: var(--color-card);
    color: var(--color-text);
    outline: none
}

.swrap input:focus {
    border-color: var(--color-primary)
}

.sclear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    display: none;
    width: 18px;
    height: 18px;
    border-radius: 9999px;
    border: none;
    background: var(--color-border-subtle);
    color: var(--color-secondary-text, #8b949e);
    font-weight: 900;
    cursor: pointer
}

.sclear.show {
    display: block
}

.modebar {
    display: flex;
    gap: .35rem;
    padding: .45rem .75rem 0
}

.mbtn {
    flex: 1;
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    border-radius: 9999px;
    padding: .35rem .5rem;
    font-family: var(--font-display);
    font-size: .6rem;
    font-weight: 800;
    color: var(--color-secondary-text, #8b949e);
    cursor: pointer;
    transition: .12s
}

.mbtn:hover {
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.mbtn.active {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.fbar {
    display: flex;
    gap: .4rem;
    align-items: center;
    justify-content: space-between;
    padding: .45rem .75rem .15rem
}

.fbtn {
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    border-radius: 10px;
    padding: .35rem .55rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    cursor: pointer;
}

.fbtn:hover {
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.fbtn2 {
    border: 1px solid var(--color-border-subtle);
    background: var(--color-primary-light);
    color: var(--color-primary);
    border-radius: 10px;
    padding: .35rem .55rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    cursor: pointer;
}

.fbtn2:hover {
    border-color: var(--color-primary)
}

.filters,
.quickbar,
.datebox {
    display: none
}

.filters.show,
.quickbar.show,
.datebox.show {
    display: flex
}

.datebox.show {
    display: block
}

.filters {
    gap: .35rem;
    flex-wrap: wrap;
    padding: .25rem .75rem .35rem
}

.f {
    border: 1px solid var(--color-border-subtle);
    border-radius: 9999px;
    padding: .22rem .55rem;
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 800;
    color: var(--color-secondary-text, #8b949e);
    cursor: pointer;
    transition: .12s;
    background: transparent
}

.f:hover {
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.f.active {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.quickbar {
    gap: .35rem;
    flex-wrap: wrap;
    padding: .25rem .75rem .1rem
}

.qc {
    border: 1px solid var(--color-border-subtle);
    border-radius: 9999px;
    padding: .22rem .55rem;
    font-family: var(--font-display);
    font-size: .58rem;
    font-weight: 900;
    color: var(--color-secondary-text, #8b949e);
    cursor: pointer;
    transition: .12s;
    background: transparent;
}

.qc:hover {
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.qc.active {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.datebox {
    padding: .35rem .75rem .35rem
}

.dr {
    display: flex;
    gap: .35rem;
    align-items: center;
    margin-bottom: .35rem
}

.dr input {
    flex: 1;
    min-width: 0;
    border: 1px solid var(--color-border-subtle);
    border-radius: 10px;
    padding: .42rem .5rem;
    background: var(--color-card);
    color: var(--color-text);
    font-family: var(--font-mono, monospace);
    font-size: .64rem;
    outline: none
}

.dr input:focus {
    border-color: var(--color-primary)
}

.dr span {
    color: var(--color-secondary-text, #8b949e);
    font-size: .65rem
}

.scroll {
    flex: 1;
    min-height: 0;
    overflow: auto;
    border-top: 1px solid var(--color-border-subtle)
}

.scroll::-webkit-scrollbar {
    height: 6px;
    width: 6px
}

.scroll::-webkit-scrollbar-thumb {
    background: var(--color-border-subtle);
    border-radius: 999px
}

.item {
    padding: .65rem .75rem;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: .12s
}

.item:hover {
    background: rgba(128, 128, 128, .06)
}

.item.sel {
    background: var(--color-primary-light);
    border-left-color: var(--color-primary)
}

.hrow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem
}

.title {
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .78rem
}

.sub {
    font-family: var(--font-body);
    font-size: .66rem;
    color: var(--color-secondary-text, #8b949e);
    margin-top: .15rem
}

.tags {
    display: flex;
    gap: .35rem;
    flex-wrap: wrap;
    margin-top: .35rem
}

.tag {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: .55rem;
    padding: .2rem .45rem;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    gap: .25rem
}

.dot {
    width: 7px;
    height: 7px;
    border-radius: 9999px
}

@keyframes pulse {
    0%, 100% { opacity: 1 }
    50% { opacity: .25 }
}

.empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--color-secondary-text, #8b949e);
    font-family: var(--font-display);
    font-weight: 800
}

.sep {
    height: 1px;
    background: var(--color-border-subtle)
}

/* Map */
.mapwrap {
    position: relative;
    min-height: 0;
    display: flex;
    flex-direction: column
}

.maphead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .65rem .75rem;
    border-bottom: 1px solid var(--color-border-subtle)
}

.maphead h2 {
    margin: 0;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .85rem
}

#fleetMap {
    flex: 1;
    min-height: 280px
}

@media(min-width:1024px) {
    #fleetMap {
        min-height: 0
    }
}

.sse {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    border: 1px solid var(--color-border-subtle);
    border-radius: 9999px;
    padding: .22rem .5rem;
    font-size: .65rem;
    color: var(--color-secondary-text, #8b949e)
}

.ssedot {
    width: 7px;
    height: 7px;
    border-radius: 9999px;
    background: #9ca3af
}

/* Toast */
#toast {
    position: absolute;
    left: 50%;
    bottom: 26px;
    transform: translateX(-50%);
    display: none;
    background: #16a34a;
    color: #fff;
    border-radius: 12px;
    padding: .55rem .9rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .72rem;
    box-shadow: 0 12px 35px rgba(0, 0, 0, .35);
    z-index: 80;
    white-space: nowrap
}

/* =========================
   TOP-CENTER Trips KPIs
   ========================= */
#topTripsKpis {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    z-index: var(--z-map-ui);
    display: none;
    gap: .45rem;
    flex-wrap: wrap;
    justify-content: center;
    padding: .15rem .15rem;
    pointer-events: auto;
}

#topTripsKpis.show {
    display: flex
}

.pill {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border: 1px solid rgba(255, 255, 255, .12);
    background: rgba(17, 24, 39, .62);
    color: #fff;
    border-radius: 9999px;
    padding: .35rem .55rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    cursor: default;
}

.pill i {
    opacity: .9
}

.pill .v {
    font-size: .70rem
}

.pill.clickable {
    cursor: pointer
}

.pill.clickable:hover {
    border-color: rgba(255, 255, 255, .25);
    transform: translateY(-1px)
}

/* =========================
   Map type dropdown
   ========================= */
.maptype {
    position: absolute;
    top: 70px;
    left: 14px;
    z-index: var(--z-map-ui);
    pointer-events: auto;
}

.maptype .btn {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 255, 255, .14);
    background: rgba(17, 24, 39, .62);
    color: #fff;
    box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
    backdrop-filter: blur(10px);
    cursor: pointer;
}

.maptype .btn:hover {
    border-color: rgba(255, 255, 255, .28)
}

.maptype .menu {
    margin-top: .5rem;
    min-width: 180px;
    display: none;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, .12);
    background: rgba(17, 24, 39, .62);
    box-shadow: 0 18px 50px rgba(0, 0, 0, .35);
    backdrop-filter: blur(10px);
}

.maptype .menu.show {
    display: block
}

.maptype .it {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .6rem .7rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    color: #fff;
    cursor: pointer;
}

.maptype .it:hover {
    background: rgba(255, 255, 255, .08)
}

.maptype .it .ck {
    width: 18px;
    height: 18px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, .18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .6rem;
    opacity: .9;
}

.maptype .it.active .ck {
    background: rgba(34, 197, 94, .25);
    border-color: rgba(34, 197, 94, .45)
}

.maptype .it small {
    opacity: .85;
    font-family: var(--font-body);
    font-weight: 600
}

/* =========================
   Bottom legend
   ========================= */
.legend {
    position: absolute;
    left: 50%;
    bottom: 14px;
    transform: translateX(-50%);
    z-index: var(--z-legend);
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    justify-content: center;
    pointer-events: auto;
}

.leg {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border: 1px solid rgba(255, 255, 255, .12);
    background: rgba(17, 24, 39, .62);
    color: #fff;
    border-radius: 9999px;
    padding: .35rem .6rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .60rem;
}

.leg .d {
    width: 8px;
    height: 8px;
    border-radius: 999px
}

/* =========================
   Trip detail modal
   ========================= */
#tripModal {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 360px;
    max-width: calc(100% - 28px);
    z-index: var(--z-modal);
    display: none;
}

#tripModal.show {
    display: block
}

.tm-h {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .6rem;
    padding: .75rem;
}

.tm-h strong {
    font-family: var(--font-display);
    font-size: .82rem
}

.tm-h small {
    display: block;
    color: var(--color-secondary-text, #8b949e);
    font-size: .65rem;
    margin-top: .15rem
}

.tm-b {
    padding: 0 .75rem .75rem
}

.tm-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .5rem;
    margin-top: .35rem;
}

.tm-box {
    border: 1px solid var(--color-border-subtle);
    border-radius: 14px;
    padding: .55rem .55rem;
    background: rgba(0, 0, 0, .03);
}

.dark-mode .tm-box {
    background: rgba(255, 255, 255, .03)
}

.tm-box .k {
    font-family: var(--font-display);
    font-size: .56rem;
    font-weight: 900;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--color-secondary-text, #8b949e)
}

.tm-box .v {
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .92rem;
    color: var(--color-text);
    margin-top: .08rem
}

.tm-actions {
    display: flex;
    gap: .4rem;
    margin-top: .6rem
}

.tm-actions button {
    flex: 1;
    border-radius: 12px;
    padding: .45rem .5rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    cursor: pointer;
    transition: .15s
}

.tm-actions .b1 {
    background: transparent;
    border: 1px solid var(--color-border-subtle);
    color: var(--color-secondary-text, #8b949e)
}

.tm-actions .b1:hover {
    background: rgba(128, 128, 128, .08);
    color: var(--color-text)
}

.tm-actions .b2 {
    background: var(--color-primary);
    border: none;
    color: #fff
}

.tm-actions .b2:hover {
    background: var(--color-primary-hover, #e07318)
}

/* =========================
   Vehicle modal
   ========================= */
#vehicleModal {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 320px;
    max-width: calc(100% - 28px);
    z-index: var(--z-modal);
    display: none;
}

#vehicleModal.show {
    display: block
}

/* =========================
   Replay UI
   ========================= */
#tripReplay {
    position: absolute;
    left: 14px;
    bottom: 56px;
    right: 14px;
    display: none;
    z-index: 75
}

.rp {
    padding: .65rem .75rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap
}

.rp strong {
    font-family: var(--font-display);
    font-size: .7rem
}

.rp button {
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    border-radius: 10px;
    padding: .35rem .5rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    cursor: pointer
}

.rp button:hover {
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.rp button.active-btn {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.rp input[type="range"] {
    flex: 1;
    min-width: 140px
}

.rp small {
    color: var(--color-secondary-text, #8b949e);
    font-family: var(--font-mono, monospace);
    font-size: .62rem
}

.speed-chips {
    display: flex;
    gap: .2rem;
    align-items: center
}

.speed-chip {
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    border-radius: 8px;
    padding: .25rem .4rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .58rem;
    cursor: pointer;
    color: var(--color-secondary-text, #8b949e)
}

.speed-chip:hover {
    border-color: var(--color-primary);
    color: var(--color-primary)
}

.speed-chip.active-chip {
    background: var(--color-primary-light);
    border-color: var(--color-primary);
    color: var(--color-primary)
}

/* Alert detail */
#alertDetail {
    position: absolute;
    top: 14px;
    right: 14px;
    width: 320px;
    max-width: calc(100% - 28px);
    display: none;
    z-index: 70
}

.ad-h {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .5rem;
    padding: .75rem
}

.ad-h strong {
    font-family: var(--font-display);
    font-size: .82rem
}

.ad-h small {
    display: block;
    color: var(--color-secondary-text, #8b949e);
    font-size: .65rem;
    margin-top: .15rem
}

.ad-b {
    padding: 0 .75rem .75rem
}

.ad-desc {
    background: rgba(128, 128, 128, .06);
    border: 1px solid var(--color-border-subtle);
    border-radius: 12px;
    padding: .6rem;
    font-size: .72rem;
    line-height: 1.55;
    color: var(--color-text);
    white-space: pre-line;
}

.ad-btns {
    display: flex;
    gap: .4rem;
    margin-top: .55rem
}

.ad-btns button {
    flex: 1;
    border-radius: 10px;
    padding: .45rem .5rem;
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .62rem;
    cursor: pointer;
    transition: .15s
}

.ad-btns .b1 {
    background: transparent;
    border: 1px solid var(--color-border-subtle);
    color: var(--color-secondary-text, #8b949e)
}

.ad-btns .b1:hover {
    background: rgba(128, 128, 128, .08);
    color: var(--color-text)
}

.ad-btns .b2 {
    background: var(--color-primary);
    border: none;
    color: #fff
}

.ad-btns .b2:hover {
    background: var(--color-primary-hover, #e07318)
}

/* Selected vehicle follow state */
.follow-pill {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: calc(var(--z-map-ui) + 2);
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    border: 1px solid rgba(255, 255, 255, .14);
    background: rgba(17, 24, 39, .62);
    color: #fff;
    border-radius: 9999px;
    padding: .38rem .65rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
    font-family: var(--font-display);
    font-weight: 900;
    font-size: .60rem;
    cursor: pointer;
    user-select: none;
}

.follow-pill:hover {
    border-color: rgba(255, 255, 255, .28);
}

.follow-pill.off {
    opacity: .72;
}

.follow-pill .d {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #22c55e;
}
</style>
@endpush

@section('content')
@php
$alertTypesMeta = [
'stolen' => ['Vol', 'fa-mask'],
'geofence' => ['Geofence', 'fa-draw-polygon'],
'safe_zone' => ['Safe Zone', 'fa-shield-halved'],
'speed' => ['Vitesse', 'fa-gauge-high'],
'time_zone' => ['Time Zone', 'fa-calendar-alt'],
];
@endphp

<div class="kpi-sticky" id="kpiBar">
    <div class="kpi-grid">
        <div class="card kpi" style="grid-column:span 2" onclick="window.switchTab('flotte')">
            <div>
                <p class="lbl">Chauffeurs</p>
                <p class="val" id="kUsers">{{ (int)($usersCount ?? 0) }}</p>
            </div>
            <div class="ico"><i class="fas fa-users"></i></div>
        </div>

        <div class="card kpi" style="grid-column:span 2" onclick="window.switchTab('flotte')">
            <div>
                <p class="lbl">Véhicules</p>
                <p class="val" style="color:var(--color-text)" id="kVeh">{{ (int)($vehiclesCount ?? 0) }}</p>
            </div>
            <div class="ico" style="background:rgba(107,114,128,.1)">
                <i class="fas fa-car-alt" style="color:var(--color-secondary-text,#8b949e)"></i>
            </div>
        </div>

        <div class="card kpi-panel" style="grid-column:span 8">
            <div class="kpi-types">
                @foreach($alertTypesMeta as $k => [$label,$icon])
                <div class="type" onclick="window.switchTab('alertes');window.filterAlertsByType('{{ $k }}')">
                    <div style="min-width:0">
                        <p class="t">{{ $label }}</p>
                        <p class="n" id="kA_{{ $k }}">{{ (int)($alertStats[$k] ?? 0) }}</p>
                    </div>
                    <div style="width:30px;height:25px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:var(--color-primary-light)">
                        <i class="fas {{ $icon }}" style="color:var(--color-primary);font-size:.7rem"></i>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="grid-main">

        <div class="col-left">
            <div class="card" style="height:100%;display:flex;flex-direction:column;min-height:0">

                <div class="tabs">
                    <button class="tab active" id="tab-flotte" onclick="window.switchTab('flotte')">📍 Flotte</button>
                    <button class="tab" id="tab-trajets" onclick="window.switchTab('trajets')">🛣️ Trajets</button>
                    <button class="tab" id="tab-alertes" onclick="window.switchTab('alertes')">🚨 <span class="badge" id="bAlerts">0</span></button>
                </div>

                <div class="search">
                    <div class="swrap">
                        <i class="fas fa-search"></i>
                        <input id="q" placeholder="Immat., chauffeur…" oninput="window.doSearch()" autocomplete="off">
                        <button id="qClear" class="sclear" onclick="window.clearSearch()">×</button>
                    </div>
                </div>

                <div class="pane active" id="pane-flotte">
                    <div class="modebar">
                        <button class="mbtn" id="mode-flotte-simple" onclick="window.setMode('flotte','simple')">Liste</button>
                        <button class="mbtn active" id="mode-flotte-detailed" onclick="window.setMode('flotte','detailed')">Détaillé</button>
                    </div>
                    <div class="fbar">
                        <button class="fbtn" onclick="window.togglePaneFilters('flotte')"><i class="fas fa-sliders-h"></i> Filtres</button>
                        <button class="fbtn2" onclick="window.resetFleetFilters()"><i class="fas fa-rotate-left"></i> Reset</button>
                    </div>
                    <div class="filters" id="vf">
                        <span class="f active" data-f="all" onclick="window.setVehFilter(this,'all')">Tous</span>
                        <span class="f" data-f="moving" onclick="window.setVehFilter(this,'moving')">● En mouvement</span>
                        <span class="f" data-f="idle" onclick="window.setVehFilter(this,'idle')">● En arrêt</span>
                        <span class="f" data-f="online" onclick="window.setVehFilter(this,'online')">● Online</span>
                        <span class="f" data-f="offline" onclick="window.setVehFilter(this,'offline')">● Offline</span>
                    </div>
                    <div class="scroll" id="vehList">
                        <div class="empty"><i class="fas fa-circle-notch fa-spin"></i><div style="margin-top:.6rem">Chargement…</div></div>
                    </div>
                </div>

                <div class="pane" id="pane-trajets">
                    <div class="modebar">
                        <button class="mbtn" id="mode-trajets-simple" onclick="window.setMode('trajets','simple')">Liste</button>
                        <button class="mbtn active" id="mode-trajets-detailed" onclick="window.setMode('trajets','detailed')">Détaillé</button>
                    </div>
                    <div class="fbar">
                        <button class="fbtn" onclick="window.togglePaneFilters('trajets')"><i class="fas fa-sliders-h"></i> Filtres</button>
                        <button class="fbtn2" onclick="window.toggleTripsCustom()"><i class="fas fa-calendar"></i> Personnaliser</button>
                    </div>
                    <div class="quickbar" id="tQuick">
                        <span class="qc active" data-q="today" onclick="window.setTripsQuick(this,'today')">Aujourd'hui</span>
                        <span class="qc" data-q="yesterday" onclick="window.setTripsQuick(this,'yesterday')">Hier</span>
                        <span class="qc" data-q="this_week" onclick="window.setTripsQuick(this,'this_week')">Semaine</span>
                        <span class="qc" data-q="this_month" onclick="window.setTripsQuick(this,'this_month')">Mois</span>
                        <span class="qc" data-q="this_year" onclick="window.setTripsQuick(this,'this_year')">Année</span>
                    </div>
                    <div class="datebox" id="tDateBox">
                        <div class="dr">
                            <input type="date" id="tFrom">
                            <span>→</span>
                            <input type="date" id="tTo">
                        </div>
                    </div>
                    <div class="filters" id="tf">
                        <span class="f active" data-f="all" onclick="window.setTripFilter(this,'all')">Tous</span>
                        <span class="f" data-f="active" onclick="window.setTripFilter(this,'active')">En cours</span>
                        <span class="f" data-f="done" onclick="window.setTripFilter(this,'done')">Terminés</span>
                    </div>
                    <div class="scroll" id="tripList">
                        <div class="empty"><i class="fas fa-route"></i><div style="margin-top:.6rem">Aucun trajet chargé</div></div>
                    </div>
                </div>

                <div class="pane" id="pane-alertes">
                    <div class="modebar">
                        <button class="mbtn" id="mode-alertes-simple" onclick="window.setMode('alertes','simple')">Liste</button>
                        <button class="mbtn active" id="mode-alertes-detailed" onclick="window.setMode('alertes','detailed')">Détaillé</button>
                    </div>
                    <div class="fbar">
                        <button class="fbtn" onclick="window.togglePaneFilters('alertes')"><i class="fas fa-sliders-h"></i> Filtres</button>
                        <button class="fbtn2" onclick="window.toggleAlertsCustom()"><i class="fas fa-calendar"></i> Personnaliser</button>
                    </div>
                    <div class="quickbar" id="aQuick">
                        <span class="qc active" data-q="today" onclick="window.setAlertsQuick(this,'today')">Aujourd'hui</span>
                        <span class="qc" data-q="yesterday" onclick="window.setAlertsQuick(this,'yesterday')">Hier</span>
                        <span class="qc" data-q="this_week" onclick="window.setAlertsQuick(this,'this_week')">Semaine</span>
                        <span class="qc" data-q="this_month" onclick="window.setAlertsQuick(this,'this_month')">Mois</span>
                        <span class="qc" data-q="this_year" onclick="window.setAlertsQuick(this,'this_year')">Année</span>
                    </div>

                    <div class="filters" id="af">
                        <span class="f active" data-at="all" onclick="window.setAlertType(this,'all')">Toutes</span>
                        <span class="f" data-at="stolen" onclick="window.setAlertType(this,'stolen')">🔴 Vol</span>
                        <span class="f" data-at="geofence" onclick="window.setAlertType(this,'geofence')">🔲 Geo</span>
                        <span class="f" data-at="safe_zone" onclick="window.setAlertType(this,'safe_zone')">🛡️ Safe</span>
                        <span class="f" data-at="speed" onclick="window.setAlertType(this,'speed')">⚡ Vitesse</span>
                        <span class="f" data-at="time_zone" onclick="window.setAlertType(this,'time_zone')">🌙 Time</span>
                    </div>
                    <div class="datebox" id="aDateBox">
                        <div class="dr">
                            <input type="date" id="aFrom">
                            <span>→</span>
                            <input type="date" id="aTo">
                        </div>
                        <div class="dr">
                            <input type="time" id="aHFrom" placeholder="HH:MM">
                            <span>→</span>
                            <input type="time" id="aHTo" placeholder="HH:MM">
                        </div>
                    </div>
                    <div class="scroll" id="alertList">
                        <div class="empty"><i class="fas fa-bell"></i><div style="margin-top:.6rem">Aucune alerte chargée</div></div>
                    </div>
                </div>

            </div>
        </div>

        <div class="col-map" style="grid-column:span 3;min-height:0">
            <div class="card mapwrap" style="height:100%">

                <div class="maphead">
                    <h2>Localisation de la flotte</h2>
                    <div style="display:flex;align-items:center;gap:.6rem">
                        <span class="sse"><span class="ssedot" id="sseDot"></span><span id="sseTxt">Connexion…</span></span>
                        <span id="lastUp" style="font-size:.65rem;color:var(--color-secondary-text,#8b949e)"></span>
                    </div>
                </div>

                <div id="fleetMap"></div>

                <div class="follow-pill" id="followSelectedPill" onclick="window.toggleSelectedVehicleFollow()">
                    <span class="d"></span>
                    <span id="followSelectedTxt">Suivi véhicule : ON</span>
                </div>

                <div class="maptype" id="mapTypeCtrl">
                    <button class="btn" onclick="window.toggleMapTypeMenu()" title="Type de carte">
                        <i class="fas fa-map"></i>
                    </button>
                    <div class="menu" id="mapTypeMenu">
                        <div class="it active" data-type="roadmap" onclick="window.setMapType(this,'roadmap')">
                            <span class="ck">✓</span> Carte <small>(Roadmap)</small>
                        </div>
                        <div class="it" data-type="satellite" onclick="window.setMapType(this,'satellite')">
                            <span class="ck">✓</span> Satellite
                        </div>
                        <div class="it" data-type="hybrid" onclick="window.setMapType(this,'hybrid')">
                            <span class="ck">✓</span> Hybride
                        </div>
                        <div class="it" data-type="terrain" onclick="window.setMapType(this,'terrain')">
                            <span class="ck">✓</span> Terrain
                        </div>
                    </div>
                </div>

                <div id="topTripsKpis">
                    <div class="pill"><i class="fas fa-route"></i> <span class="v" id="tkCount">0</span> trajets</div>
                    <div class="pill"><i class="fas fa-road"></i> <span class="v" id="tkDist">0.0 km</span></div>
                    <div class="pill"><i class="fas fa-clock"></i> <span class="v" id="tkDur">0m</span></div>
                    <div class="pill"><i class="fas fa-gauge-high"></i> <span class="v" id="tkMax">0 km/h</span></div>
                </div>

                <div class="legend">
                    <div class="leg"><span class="d" style="background:#16a34a"></span> En mouvement</div>
                    <div class="leg"><span class="d" style="background:#2563eb"></span> En ligne</div>
                    <div class="leg"><span class="d" style="background:#d97706"></span> À l’arrêt</div>
                    <div class="leg"><span class="d" style="background:#6b7280"></span> Offline</div>
                </div>

                <div class="card" id="tripModal">
                    <div class="tm-h">
                        <div style="min-width:0">
                            <strong id="tmTitle">Trajet</strong>
                            <small id="tmSub">—</small>
                        </div>
                        <button class="mbtn" style="flex:0 0 auto;padding:.3rem .55rem;border-radius:10px" onclick="window.closeTripModal()">✕</button>
                    </div>
                    <div class="tm-b">
                        <div class="tm-grid">
                            <div class="tm-box">
                                <div class="k">Distance</div>
                                <div class="v" id="tmDist">0.0 km</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Durée</div>
                                <div class="v" id="tmDur">0m</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Vitesse max</div>
                                <div class="v" id="tmMax">0 km/h</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Points</div>
                                <div class="v" id="tmPts">0</div>
                            </div>
                        </div>
                        <div class="tm-grid" style="margin-top:.35rem">
                            <div class="tm-box" style="grid-column:span 2">
                                <div class="k">Départ</div>
                                <div class="v" id="tmStart" style="font-size:.75rem">—</div>
                            </div>
                            <div class="tm-box" style="grid-column:span 2">
                                <div class="k">Arrivée</div>
                                <div class="v" id="tmEnd" style="font-size:.75rem">—</div>
                            </div>
                        </div>
                        <div class="tm-actions">
                            <button class="b1" onclick="window.replayPlay()">▶ Jouer</button>
                            <button class="b1" onclick="window.replayStop()">⏹ Reset</button>
                            <button class="b2" onclick="window.focusTrip()">📍 Centrer</button>
                        </div>
                    </div>
                </div>

                <div class="card" id="vehicleModal">
                    <div class="tm-h">
                        <div style="min-width:0">
                            <strong id="vmTitle">Véhicule</strong>
                            <small id="vmSub">—</small>
                        </div>
                        <button class="mbtn" style="flex:0 0 auto;padding:.3rem .55rem;border-radius:10px" onclick="window.closeVehicleModal()">✕</button>
                    </div>
                    <div class="tm-b">
                        <div class="tm-grid">
                            <div class="tm-box">
                                <div class="k">Immatriculation</div>
                                <div class="v" id="vmImmat">—</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Marque / Modèle</div>
                                <div class="v" id="vmBrand" style="font-size:.78rem">—</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Chauffeur</div>
                                <div class="v" id="vmDriver" style="font-size:.75rem">—</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Vitesse</div>
                                <div class="v" id="vmSpeed">— km/h</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Statut</div>
                                <div class="v" id="vmStatus">—</div>
                            </div>
                            <div class="tm-box">
                                <div class="k">Dernière MàJ</div>
                                <div class="v" id="vmUpdated" style="font-size:.72rem">—</div>
                            </div>
                        </div>
                        <div class="tm-grid" style="margin-top:.35rem">
                            <div class="tm-box" style="grid-column:span 2">
                                <div class="k">Position</div>
                                <div class="v" id="vmPos" style="font-size:.72rem;font-family:var(--font-mono,monospace)">—</div>
                            </div>
                        </div>
                        <div class="tm-actions">
                            <button class="b2" onclick="window.locateVehicleFromModal()">📍 Localiser</button>
                        </div>
                    </div>
                </div>

                <div class="card" id="tripReplay">
                    <div class="rp">
                        <strong>Replay</strong>
                        <button onclick="window.replayPlay()">▶</button>
                        <button onclick="window.replayPause()">⏸</button>
                        <button onclick="window.replayStop()">⏹</button>

                        <div class="speed-chips">
                            <button class="speed-chip" data-spd="0.25" onclick="window.replaySetSpeed(0.25)">¼x</button>
                            <button class="speed-chip" data-spd="0.5" onclick="window.replaySetSpeed(0.5)">½x</button>
                            <button class="speed-chip active-chip" data-spd="1" onclick="window.replaySetSpeed(1)">1x</button>
                            <button class="speed-chip" data-spd="2" onclick="window.replaySetSpeed(2)">2x</button>
                            <button class="speed-chip" data-spd="4" onclick="window.replaySetSpeed(4)">4x</button>
                            <button class="speed-chip" data-spd="8" onclick="window.replaySetSpeed(8)">8x</button>
                            <button class="speed-chip" data-spd="16" onclick="window.replaySetSpeed(16)">16x</button>
                        </div>

                        <button onclick="window.replaySlower()" title="Ralentir">−</button>
                        <small>x<span id="rpSpeed">1</span></small>
                        <button onclick="window.replayFaster()" title="Accélérer">+</button>

                        <button id="rpFollow" class="active-btn" onclick="window.toggleFollow()" title="Suivre le véhicule">
                            🎯 Suivre
                        </button>

                        <input id="rpRange" type="range" min="0" max="0" value="0" oninput="window.replaySeek(this.value)">
                        <small id="rpMeta">0/0</small>
                        <button onclick="window.closeReplay()">✕</button>
                    </div>
                </div>

                {{-- Alert detail --}}
                <div class="card" id="alertDetail">
                    <div class="ad-h">
                        <div style="min-width:0">
                            <strong id="adTitle">—</strong>
                            <small id="adVeh">—</small>
                        </div>
                        <button class="mbtn" style="flex:0 0 auto;padding:.3rem .55rem;border-radius:10px" onclick="window.closeAlertDetail()">✕</button>
                    </div>
                    <div class="ad-b">
                        <div class="ad-desc" id="adDesc">—</div>
                        <div class="ad-btns">
                            <button class="b2" onclick="window.locateAlert()">📍 Localiser</button>
                        </div>
                    </div>
                </div>

                <div id="toast"></div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';

    const R = {
        stream: @json(route('dashboard.stream')),
        alertsDay: @json(url('/alerts')),
        trajetsList: @json(route('trajets.index')),
        trajetDetail: (vId, tId) => {
            const base = @json(url('/trajets/show'));
            return `${base}/${encodeURIComponent(vId)}/${encodeURIComponent(tId)}?format=json`;
        },
    };

    const COLORS = {
        moving: '#16a34a',
        online: '#2563eb',
        idle: '#d97706',
        offline: '#6b7280',
    };

    const ALLOWED_ALERT_TYPES = new Set(['stolen', 'geofence', 'safe_zone', 'time_zone', 'speed']);

    let map = null;
    let sse = null;
    let markers = {};
    let selectedVehicleId = null;
    let selectedVehicleIndicator = null;
    let selectedVehicleIndicatorEl = null;
    let followSelectedVehicle = true;
    let vehicleHeadingCache = new Map();

    let vehicles = @json($vehicles ?? []);
    let trips = [];
    let alerts = [];

    let vehFilter = 'all';
    let tripFilter = 'all';
    let alertType = 'all';
    let alertsQuick = 'today';
    let tripsQuick = 'today';
    let resolvedFilter = 'open';
    let viewMode = {
        flotte: 'detailed',
        trajets: 'detailed',
        alertes: 'detailed'
    };
    let currentAlert = null;

    let currentTrip = null;
    let tripPolyline = null;
    let tripCursor = null;
    let replayPoints = [];
    let replayIndex = 0;
    let replayTimer = null;
    let replaySpeedFactor = 1;
    let replayFollow = true;

    const SPEED_STEPS = [0.25, 0.5, 1, 2, 4, 8, 16];

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[m]));

    const fmtMin = (m) => {
        m = parseInt(m || 0, 10);
        const h = Math.floor(m / 60), r = m % 60;
        if (h <= 0) return r + 'm';
        return h + 'h' + String(r).padStart(2, '0');
    };

    function toast(msg, ok = true) {
        const t = document.getElementById('toast');
        if (!t) return;
        t.style.background = ok ? '#16a34a' : '#dc2626';
        t.textContent = msg;
        t.style.display = 'block';
        clearTimeout(window.__toastT);
        window.__toastT = setTimeout(() => t.style.display = 'none', 2200);
    }

    function debounce(fn, ms = 350) {
        let t = null;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }

    const ymd = (d) => {
        const z = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate());
    };

    function setDateRangeInputs(fromId, toId, from, to) {
        const a = document.getElementById(fromId);
        const b = document.getElementById(toId);
        if (a) a.value = from || '';
        if (b) b.value = to || '';
    }

    function measureHeights() {
        const k = document.getElementById('kpiBar');
        const n = document.getElementById('navbar');
        if (k) document.documentElement.style.setProperty('--kpi-h', Math.round(k.getBoundingClientRect().height) + 'px');
        if (n) document.documentElement.style.setProperty('--navbar-h', Math.round(n.getBoundingClientRect().height) + 'px');
    }

    function toMsSafe(v) {
        if (v == null) return null;

        if (typeof v === 'number' && Number.isFinite(v)) {
            return v > 1e12 ? v : v * 1000;
        }

        if (typeof v === 'string') {
            const s = v.trim();
            if (!s) return null;

            if (/^\d+$/.test(s)) {
                const n = Number(s);
                return n > 1e12 ? n : n * 1000;
            }

            const parsed = Date.parse(s.replace(' ', 'T'));
            return Number.isFinite(parsed) ? parsed : null;
        }

        return null;
    }

    function humanDurationFromSeconds(sec) {
        sec = parseInt(sec || 0, 10);
        if (sec < 0) sec = 0;

        const d = Math.floor(sec / 86400);
        const h = Math.floor((sec % 86400) / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;

        if (d > 0) return `${d}j ${h}h ${m}min`;
        if (h > 0) return `${h}h ${m}min`;
        if (m > 0) return `${m}min${s > 0 ? ` ${s}s` : ''}`;
        return `${s}s`;
    }

    function computeBearing(lat1, lon1, lat2, lon2) {
        const toRad = (d) => d * Math.PI / 180;
        const toDeg = (r) => r * 180 / Math.PI;

        const φ1 = toRad(lat1);
        const φ2 = toRad(lat2);
        const λ1 = toRad(lon1);
        const λ2 = toRad(lon2);

        const y = Math.sin(λ2 - λ1) * Math.cos(φ2);
        const x = Math.cos(φ1) * Math.sin(φ2) -
            Math.sin(φ1) * Math.cos(φ2) * Math.cos(λ2 - λ1);

        return (toDeg(Math.atan2(y, x)) + 360) % 360;
    }

    function updateVehicleHeadingCache(nextFleet) {
        const prevById = new Map(vehicles.map(v => [String(v.id), v]));

        (Array.isArray(nextFleet) ? nextFleet : []).forEach(v => {
            const prev = prevById.get(String(v.id));

            const lat1 = Number(prev?.lat);
            const lng1 = Number(prev?.lon);
            const lat2 = Number(v?.lat);
            const lng2 = Number(v?.lon);

            const explicitDirection = Number(v?.direction);
            if (Number.isFinite(explicitDirection)) {
                vehicleHeadingCache.set(String(v.id), explicitDirection);
                return;
            }

            if (
                Number.isFinite(lat1) && Number.isFinite(lng1) &&
                Number.isFinite(lat2) && Number.isFinite(lng2) &&
                (lat1 !== lat2 || lng1 !== lng2)
            ) {
                vehicleHeadingCache.set(String(v.id), computeBearing(lat1, lng1, lat2, lng2));
            }
        });
    }

    function getVehicleHeading(v) {
        const explicit = Number(v?.direction);
        if (Number.isFinite(explicit)) return explicit;

        const cached = vehicleHeadingCache.get(String(v?.id));
        if (Number.isFinite(cached)) return cached;

        return 0;
    }

    function getVehicleLiveStatus(v) {
        return v?.live_status || {};
    }

    function enrichVehicleLiveStatus(v) {
        if (!v) return v;

        const ls = { ...(v.live_status || {}) };
        const now = Date.now();

        const thresholdMinutes = parseInt(ls.offline_threshold_minutes || 10, 10);
        const thresholdMs = thresholdMinutes * 60 * 1000;

        const lastSeenMs =
            toMsSafe(ls.heart_time_ms) ||
            toMsSafe(ls.datetime_ms) ||
            toMsSafe(ls.sys_time_ms) ||
            toMsSafe(v?.gps?.last_seen);

        const isOnline = lastSeenMs ? ((now - lastSeenMs) < thresholdMs) : false;

        if (!isOnline) {
            const offlineSinceMs = toMsSafe(ls.offline_since_ms) || lastSeenMs || now;
            const offlineSinceSeconds = Math.max(0, Math.floor((now - offlineSinceMs) / 1000));

            ls.is_online = false;
            ls.is_moving = null;
            ls.ui_status = 'OFFLINE';
            ls.movement_state = 'OFFLINE';
            ls.connectivity_state = 'OFFLINE';
            ls.offline_since_ms = offlineSinceMs;
            ls.offline_since_seconds = offlineSinceSeconds;
            ls.offline_since_human = humanDurationFromSeconds(offlineSinceSeconds);
        } else {
            ls.is_online = true;
            ls.offline_since_ms = null;
            ls.offline_since_seconds = null;
            ls.offline_since_human = null;

            if (ls.movement_state === 'STOPPED') {
                ls.ui_status = 'ONLINE_STOPPED';
                ls.connectivity_state = 'ONLINE_STATIONARY';
                ls.is_moving = false;
            } else if (ls.movement_state === 'MOVING') {
                ls.ui_status = 'ONLINE_MOVING';
                ls.connectivity_state = 'ONLINE_MOVING';
                ls.is_moving = true;
            } else {
                ls.ui_status = 'UNKNOWN';
                ls.connectivity_state = 'UNKNOWN';
            }
        }

        if (ls.movement_state === 'STOPPED' && ls.stopped_since_ms) {
            const stoppedSinceMs = toMsSafe(ls.stopped_since_ms);
            if (stoppedSinceMs) {
                const stoppedSinceSeconds = Math.max(0, Math.floor((now - stoppedSinceMs) / 1000));
                ls.stopped_since_seconds = stoppedSinceSeconds;
                ls.stopped_since_human = humanDurationFromSeconds(stoppedSinceSeconds);
            }
        }

        return {
            ...v,
            gps: {
                ...(v.gps || {}),
                online: !!ls.is_online,
                state: ls.is_online ? 'ONLINE' : 'OFFLINE',
                last_seen: v?.gps?.last_seen ?? ls.heart_time ?? ls.datetime ?? ls.sys_time ?? '—',
            },
            live_status: ls,
        };
    }

    function getVehicleUiStatus(v) {
        return getVehicleLiveStatus(v).ui_status || 'UNKNOWN';
    }

    function getVehicleSpeed(v) {
        const ls = getVehicleLiveStatus(v);
        const speed = ls.speed ?? v.speed ?? 0;
        return parseInt(speed || 0, 10) || 0;
    }

    function isVehicleOnline(v) {
        const ls = getVehicleLiveStatus(v);
        if (typeof ls.is_online === 'boolean') return ls.is_online;
        return !!v?.gps?.online;
    }

    function isVehicleMoving(v) {
        const ls = getVehicleLiveStatus(v);
        if (typeof ls.is_moving === 'boolean') return ls.is_moving;
        return isVehicleOnline(v) && getVehicleSpeed(v) > 0;
    }

    function getVehicleStatusColor(v) {
        const ui = getVehicleUiStatus(v);
        const online = isVehicleOnline(v);
        const moving = isVehicleMoving(v);

        if (ui === 'ONLINE_MOVING') return COLORS.moving;
        if (ui === 'ONLINE_STOPPED') return COLORS.idle;
        if (ui === 'OFFLINE') return COLORS.offline;

        if (online && moving) return COLORS.moving;
        if (online && !moving) return COLORS.idle;
        if (online) return COLORS.online;

        return COLORS.offline;
    }

    function getVehicleStatusText(v) {
        const ls = getVehicleLiveStatus(v);
        const speed = getVehicleSpeed(v);

        if (ls.ui_status === 'ONLINE_MOVING') {
            return `${speed} km/h • En mouvement`;
        }

        if (ls.ui_status === 'ONLINE_STOPPED') {
            return ls.stopped_since_human ? `À l’arrêt depuis ${ls.stopped_since_human}` : 'À l’arrêt';
        }

        if (ls.ui_status === 'OFFLINE') {
            return ls.offline_since_human ? `Offline depuis ${ls.offline_since_human}` : 'Offline';
        }

        if (isVehicleOnline(v)) return 'En ligne';

        return 'Statut inconnu';
    }

    function getVehicleUpdatedAt(v) {
        const ls = getVehicleLiveStatus(v);
        return ls.heart_time ?? ls.sys_time ?? ls.datetime ?? v?.gps?.last_seen ?? v?.updated_at ?? '—';
    }

    function getVehicleDotAnimation(v) {
        return getVehicleUiStatus(v) === 'ONLINE_MOVING' ? 'animation:pulse 1.5s infinite' : '';
    }

    function mergeFleetRealtime(nextFleet) {
        updateVehicleHeadingCache(nextFleet);
        return (Array.isArray(nextFleet) ? nextFleet : []).map(enrichVehicleLiveStatus);
    }

    window.togglePaneFilters = (pane) => {
        if (pane === 'flotte') {
            document.getElementById('vf')?.classList.toggle('show');
        }
        if (pane === 'trajets') {
            document.getElementById('tQuick')?.classList.toggle('show');
            document.getElementById('tf')?.classList.toggle('show');
        }
        if (pane === 'alertes') {
            document.getElementById('aQuick')?.classList.toggle('show');
            document.getElementById('af')?.classList.toggle('show');
        }
    };

    window.switchTab = (t) => {
        ['flotte', 'trajets', 'alertes'].forEach(x => {
            document.getElementById('tab-' + x)?.classList.toggle('active', x === t);
            document.getElementById('pane-' + x)?.classList.toggle('active', x === t);
        });

        document.getElementById('topTripsKpis')?.classList.toggle('show', t === 'trajets');

        if (t !== 'trajets') {
            document.getElementById('tripModal')?.classList.remove('show');
            const rp = document.getElementById('tripReplay');
            if (rp) rp.style.display = 'none';
            tripPolyline?.setMap(null);
            tripCursor?.setMap(null);
            window.replayPause();
        }

        if (t === 'trajets' && currentTrip) {
            if (tripPolyline) tripPolyline.setMap(map);
            if (tripCursor) tripCursor.setMap(map);
            document.getElementById('tripModal')?.classList.add('show');
            if (replayPoints.length > 0) {
                const rp = document.getElementById('tripReplay');
                if (rp) rp.style.display = 'block';
            }
        }

        if (t !== 'flotte') {
            document.getElementById('vehicleModal')?.classList.remove('show');
        }

        if (t !== 'alertes') {
            const ad = document.getElementById('alertDetail');
            if (ad) ad.style.display = 'none';
        }

        const q = document.getElementById('q');
        const qc = document.getElementById('qClear');
        if (q) q.value = '';
        qc?.classList.remove('show');
        window.doSearch();

        if (t === 'flotte') {
            renderVehicleList();
            updateSelectedVehicleIndicator();
        }
        if (t === 'trajets') loadTrips();
        if (t === 'alertes') loadAlerts();
    };

    window.doSearch = () => {
        const qEl = document.getElementById('q');
        const q = (qEl?.value || '').toLowerCase().trim();
        document.getElementById('qClear')?.classList.toggle('show', q.length > 0);

        const activePane = document.querySelector('.pane.active')?.id || 'pane-flotte';
        const sel = activePane === 'pane-flotte' ? '#vehList .item' :
            activePane === 'pane-trajets' ? '#tripList .item' :
            '#alertList .item';

        document.querySelectorAll(sel).forEach(el => {
            const s = (el.dataset.s || '');
            el.style.display = (!q || s.includes(q)) ? '' : 'none';
        });
    };

    window.clearSearch = () => {
        const q = document.getElementById('q');
        if (q) q.value = '';
        document.getElementById('qClear')?.classList.remove('show');
        window.doSearch();
    };

    window.setMode = (tab, mode) => {
        viewMode[tab] = mode;
        ['simple', 'detailed'].forEach(m => {
            document.getElementById(`mode-${tab}-${m}`)?.classList.toggle('active', m === mode);
        });
        if (tab === 'flotte') renderVehicleList();
        if (tab === 'trajets') renderTripList();
        if (tab === 'alertes') renderAlertList();
    };

    window.toggleTripsCustom = () => {
        const box = document.getElementById('tDateBox');
        if (!box) return;
        const willShow = !box.classList.contains('show');
        box.classList.toggle('show', willShow);
        if (willShow) {
            tripsQuick = 'range';
            loadTrips();
        }
    };

    window.toggleAlertsCustom = () => {
        const box = document.getElementById('aDateBox');
        if (!box) return;
        const willShow = !box.classList.contains('show');
        box.classList.toggle('show', willShow);
        if (willShow) {
            alertsQuick = 'range';
            loadAlerts();
        }
    };

    function rangeForQuick(q) {
        const now = new Date();
        let from, to;

        if (q === 'today') {
            from = to = ymd(now);
        } else if (q === 'yesterday') {
            const d = new Date(now);
            d.setDate(d.getDate() - 1);
            from = to = ymd(d);
        } else if (q === 'this_week') {
            const d = new Date(now);
            const day = (d.getDay() + 6) % 7;
            const start = new Date(d);
            start.setDate(d.getDate() - day);
            const end = new Date(start);
            end.setDate(start.getDate() + 6);
            from = ymd(start);
            to = ymd(end);
        } else if (q === 'this_month') {
            const start = new Date(now.getFullYear(), now.getMonth(), 1);
            const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            from = ymd(start);
            to = ymd(end);
        } else if (q === 'this_year') {
            from = ymd(new Date(now.getFullYear(), 0, 1));
            to = ymd(new Date(now.getFullYear(), 11, 31));
        } else {
            from = to = ymd(now);
        }

        return { from, to };
    }

    window.setTripsQuick = (el, q) => {
        document.querySelectorAll('#tQuick .qc').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        document.getElementById('tDateBox')?.classList.remove('show');
        const r = rangeForQuick(q);
        setDateRangeInputs('tFrom', 'tTo', r.from, r.to);
        tripsQuick = (q === 'this_year') ? 'range' : q;
        loadTrips();
    };

    window.setAlertsQuick = (el, q) => {
        document.querySelectorAll('#aQuick .qc').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        document.getElementById('aDateBox')?.classList.remove('show');
        const r = rangeForQuick(q);
        setDateRangeInputs('aFrom', 'aTo', r.from, r.to);
        alertsQuick = (q === 'this_year') ? 'range' : q;
        loadAlerts();
    };

    window.resetFleetFilters = () => {
        vehFilter = 'all';
        document.querySelectorAll('#vf .f').forEach(x => x.classList.remove('active'));
        document.querySelector('#vf .f[data-f="all"]')?.classList.add('active');
        renderVehicleList();
    };

    window.setVehFilter = (el, f) => {
        vehFilter = f;
        document.querySelectorAll('#vf .f').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        renderVehicleList();
    };

    function vehMatchesFilter(v) {
        const online = isVehicleOnline(v);
        const moving = isVehicleMoving(v);

        if (vehFilter === 'all') return true;
        if (vehFilter === 'moving') return online && moving;
        if (vehFilter === 'idle') return online && !moving;
        if (vehFilter === 'online') return online;
        if (vehFilter === 'offline') return !online;

        return true;
    }

    function renderVehicleList() {
        const box = document.getElementById('vehList');
        if (!box) return;

        const list = vehicles.filter(vehMatchesFilter);

        if (!list.length) {
            box.innerHTML = `<div class="empty"><i class="fas fa-filter"></i><div style="margin-top:.6rem">Aucun résultat</div></div>`;
            return;
        }

        box.innerHTML = list.map((v, i) => {
            const uiStatus = getVehicleUiStatus(v);
            const spd = getVehicleSpeed(v);
            const dotC = getVehicleStatusColor(v);
            const dotA = getVehicleDotAnimation(v);
            const statusText = getVehicleStatusText(v);

            const imm = esc(v.immatriculation || '—');
            const drv = esc(v.driver?.label ?? v.users ?? v.driver_label ?? 'Non associé');
            const brand = esc((`${v.marque || ''} ${v.model || ''}`).trim() || '—');
            const s = `${imm} ${drv} ${statusText}`.toLowerCase();
            const sel = (selectedVehicleId == v.id) ? ' sel' : '';

            if (viewMode.flotte === 'simple') {
                return `
      <div class="item${sel}" data-id="${v.id}" data-s="${s}">
        <div class="hrow">
          <div class="title">${imm}</div>
          <div class="dot" style="background:${dotC};${dotA}"></div>
        </div>
        <div class="sub">👤 ${drv}</div>
        <div class="sub">${esc(statusText)}</div>
      </div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
            }

            let tagHtml = '';

            if (uiStatus === 'ONLINE_MOVING') {
                tagHtml = `
        <span class="tag" style="background:rgba(22,163,74,.12);color:${COLORS.moving}">
          <span class="dot" style="background:${COLORS.moving};${dotA}"></span>
          ${spd} km/h • En mouvement
        </span>
      `;
            } else if (uiStatus === 'ONLINE_STOPPED') {
                tagHtml = `
        <span class="tag" style="background:rgba(217,119,6,.12);color:${COLORS.idle}">
          <span class="dot" style="background:${COLORS.idle}"></span>
          ${esc(statusText)}
        </span>
      `;
            } else if (uiStatus === 'OFFLINE') {
                tagHtml = `
        <span class="tag" style="background:rgba(107,114,128,.12);color:${COLORS.offline}">
          <span class="dot" style="background:${COLORS.offline}"></span>
          ${esc(statusText)}
        </span>
      `;
            } else if (isVehicleOnline(v)) {
                tagHtml = `
        <span class="tag" style="background:rgba(37,99,235,.12);color:${COLORS.online}">
          <span class="dot" style="background:${COLORS.online}"></span>
          En ligne
        </span>
      `;
            } else {
                tagHtml = `
        <span class="tag" style="background:rgba(107,114,128,.12);color:${COLORS.offline}">
          <span class="dot" style="background:${COLORS.offline}"></span>
          Statut inconnu
        </span>
      `;
            }

            return `
    <div class="item${sel}" data-id="${v.id}" data-s="${s}">
      <div class="hrow">
        <div class="title">${imm}</div>
        <div class="dot" style="background:${dotC};${dotA}"></div>
      </div>
      <div class="sub">${brand}</div>
      <div class="sub">👤 ${drv}</div>
      <div class="tags">${tagHtml}</div>
    </div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
        }).join('');

        box.querySelectorAll('.item').forEach(el => {
            el.addEventListener('click', () => {
                box.querySelectorAll('.item').forEach(x => x.classList.remove('sel'));
                el.classList.add('sel');
                selectedVehicleId = parseInt(el.dataset.id, 10);
                followSelectedVehicle = true;
                updateFollowSelectedPill();
                focusVehicle(selectedVehicleId, true);
                openVehicleModal(selectedVehicleId);

                if (document.getElementById('pane-alertes')?.classList.contains('active')) loadAlerts();
                if (document.getElementById('pane-trajets')?.classList.contains('active')) loadTrips();
            });
        });

        window.doSearch();
    }

    function openVehicleModal(id) {
        const v = vehicles.find(x => String(x.id) === String(id));
        if (!v) return;

        const spd = getVehicleSpeed(v);
        const imm = v.immatriculation || '—';
        const brand = (`${v.marque || ''} ${v.model || ''}`).trim() || '—';
        const drv = v.driver?.label ?? v.users ?? v.driver_label ?? 'Non associé';
        const lat = v.lat != null ? parseFloat(v.lat).toFixed(5) : '—';
        const lng = v.lon != null ? parseFloat(v.lon).toFixed(5) : '—';
        const updated = getVehicleUpdatedAt(v);

        const statusTxt = getVehicleStatusText(v);
        const statusColor = getVehicleStatusColor(v);

        const t = document.getElementById('vmTitle');
        const s = document.getElementById('vmSub');
        const i = document.getElementById('vmImmat');
        const b = document.getElementById('vmBrand');
        const d = document.getElementById('vmDriver');
        const sp = document.getElementById('vmSpeed');
        const st = document.getElementById('vmStatus');
        const up = document.getElementById('vmUpdated');
        const p = document.getElementById('vmPos');
        const modal = document.getElementById('vehicleModal');

        if (t) t.textContent = `Véhicule • ${imm}`;
        if (s) s.textContent = brand;
        if (i) i.textContent = imm;
        if (b) b.textContent = brand;
        if (d) d.textContent = drv;
        if (sp) sp.textContent = `${spd} km/h`;
        if (st) st.innerHTML = `<span style="color:${statusColor}">${esc(statusTxt)}</span>`;
        if (up) up.textContent = updated;
        if (p) p.textContent = lat !== '—' ? `${lat}, ${lng}` : '—';

        if (modal) {
            modal.dataset.vid = id;
            modal.classList.add('show');
        }
    }

    window.closeVehicleModal = () => {
        document.getElementById('vehicleModal')?.classList.remove('show');
    };

    window.locateVehicleFromModal = () => {
        const id = document.getElementById('vehicleModal')?.dataset?.vid;
        if (id) focusVehicle(id, true);
    };

    function selectVehicleInList(id) {
        document.querySelectorAll('#vehList .item').forEach(x => x.classList.remove('sel'));
        document.querySelector(`#vehList .item[data-id="${id}"]`)?.classList.add('sel');
    }

    function getAlertVehicleId(a) {
        if (!a) return null;

        const direct = a.vehicle_id ?? a.vehicule_id ?? a.car_id ?? a.voiture_id ?? null;
        if (direct != null && String(direct).trim() !== '') return String(direct);

        const nested = a.vehicle?.id ?? a.voiture?.id ?? null;
        if (nested != null && String(nested).trim() !== '') return String(nested);

        const imm = a.vehicle?.label ?? a.immatriculation ?? null;
        if (imm) {
            const v = vehicles.find(x => String(x.immatriculation || '').trim().toLowerCase() === String(imm).trim().toLowerCase());
            if (v?.id != null) return String(v.id);
        }

        return null;
    }

    window.initFleetMap = function() {
        map = new google.maps.Map(document.getElementById('fleetMap'), {
            center: { lat: 4.0511, lng: 9.7679 },
            zoom: 7,
            disableDefaultUI: true,
            gestureHandling: 'greedy',
            mapTypeId: 'roadmap',
        });

        ensureSelectedVehicleIndicator();
        moveFollowPillNearMapType();
        renderVehicleMarkers(true);
        updateFollowSelectedPill();
        startSSE();
        setTimeout(measureHeights, 250);
        map.addListener('click', () => document.getElementById('mapTypeMenu')?.classList.remove('show'));
        map.addListener('zoom_changed', () => updateSelectedVehicleIndicator());
        map.addListener('center_changed', () => updateSelectedVehicleIndicator());
        google.maps.event.addListenerOnce(map, 'idle', () => updateSelectedVehicleIndicator());
    };

    function loadGoogleMaps() {
        if (window.google?.maps) {
            window.initFleetMap();
            return;
        }

        const s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=AIzaSyBn88TP5X-xaRCYo5gYxvGnVy_0WYotZWo&callback=initFleetMap';
        s.async = true;
        s.defer = true;
        document.head.appendChild(s);
    }

    function carIcon() {
        return {
            url: @json(asset('assets/icons/car_icon.png')),
            scaledSize: new google.maps.Size(34, 34),
            anchor: new google.maps.Point(17, 17),
        };
    }

    function ensureSelectedVehicleIndicator() {
        if (!map || selectedVehicleIndicator) return;

        const el = document.createElement('div');
        el.className = 'selected-vehicle-indicator';
        el.style.position = 'absolute';
        el.style.width = '30px';
        el.style.height = '42px';
        el.style.display = 'none';
        el.style.pointerEvents = 'none';
        el.style.transform = 'translate(-50%, -100%)';
        el.style.zIndex = '1000';

        el.innerHTML = `
            <div style="
                position:absolute;
                left:50%;
                top:0;
                transform:translateX(-50%);
                animation:selectedVehicleBounce 1.1s ease-in-out infinite;
                display:flex;
                flex-direction:column;
                align-items:center;
                gap:2px;
            ">
                <div style="
                    width:26px;
                    height:26px;
                    border-radius:9999px;
                    background:rgba(17,24,39,.78);
                    border:2px solid #fff;
                    box-shadow:0 8px 24px rgba(0,0,0,.28);
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    color:#fff;
                    font-size:13px;
                    line-height:1;
                ">
                    <i class="fas fa-location-arrow"></i>
                </div>
                <div style="
                    width:0;
                    height:0;
                    border-left:7px solid transparent;
                    border-right:7px solid transparent;
                    border-top:10px solid rgba(17,24,39,.78);
                    filter:drop-shadow(0 4px 8px rgba(0,0,0,.2));
                "></div>
            </div>
        `;

        const style = document.createElement('style');
        style.textContent = `
            @keyframes selectedVehicleBounce {
                0%, 100% { transform: translateX(-50%) translateY(0); }
                50% { transform: translateX(-50%) translateY(-8px); }
            }
        `;
        document.head.appendChild(style);

        selectedVehicleIndicator = new google.maps.OverlayView();

        selectedVehicleIndicator.onAdd = function() {
            const panes = this.getPanes();
            panes.overlayMouseTarget.appendChild(el);
        };

        selectedVehicleIndicator.draw = function() {
            updateSelectedVehicleIndicator();
        };

        selectedVehicleIndicator.onRemove = function() {
            if (el.parentNode) el.parentNode.removeChild(el);
        };

        selectedVehicleIndicator.setMap(map);
        selectedVehicleIndicatorEl = el;
    }

    function updateSelectedVehicleIndicator() {
        if (!map || !selectedVehicleIndicatorEl || !selectedVehicleIndicator) return;

        if (!selectedVehicleId) {
            selectedVehicleIndicatorEl.style.display = 'none';
            return;
        }

        const v = vehicles.find(x => String(x.id) === String(selectedVehicleId));
        if (!v || v.lat == null || v.lon == null) {
            selectedVehicleIndicatorEl.style.display = 'none';
            return;
        }

        const projection = selectedVehicleIndicator.getProjection();
        if (!projection) {
            selectedVehicleIndicatorEl.style.display = 'none';
            return;
        }

        const point = projection.fromLatLngToDivPixel(
            new google.maps.LatLng(parseFloat(v.lat), parseFloat(v.lon))
        );

        if (!point) {
            selectedVehicleIndicatorEl.style.display = 'none';
            return;
        }

        selectedVehicleIndicatorEl.style.display = 'block';
        selectedVehicleIndicatorEl.style.left = `${point.x}px`;
        selectedVehicleIndicatorEl.style.top = `${point.y - 24}px`;

        const bubble = selectedVehicleIndicatorEl.querySelector('div > div');
        const arrow = bubble?.querySelector('i');
        const color = getVehicleStatusColor(v);

        if (bubble) {
            bubble.style.background = 'rgba(17,24,39,.78)';
            bubble.style.boxShadow = `0 8px 24px rgba(0,0,0,.28), 0 0 0 3px ${color}33`;
        }
        if (arrow) {
            arrow.style.color = color;
            arrow.style.transform = `rotate(${getVehicleHeading(v)}deg)`;
            arrow.style.display = 'inline-block';
        }
    }

    function moveFollowPillNearMapType() {
        const pill = document.getElementById('followSelectedPill');
        const ctrl = document.getElementById('mapTypeCtrl');
        if (!pill || !ctrl) return;

        const btn = ctrl.querySelector('.btn');
        if (!btn) return;

        pill.style.position = 'absolute';
        pill.style.top = '70px';
        pill.style.left = '66px';
        pill.style.zIndex = 'calc(var(--z-map-ui) + 3)';
    }

    function updateFollowSelectedPill() {
        const pill = document.getElementById('followSelectedPill');
        const txt = document.getElementById('followSelectedTxt');
        if (!pill || !txt) return;

        const shouldShow = !!selectedVehicleId;
        pill.style.display = shouldShow ? 'inline-flex' : 'none';
        pill.classList.toggle('off', !followSelectedVehicle);
        txt.textContent = followSelectedVehicle ? 'Suivi véhicule : ON' : 'Suivi véhicule : OFF';
    }

    window.toggleSelectedVehicleFollow = () => {
        followSelectedVehicle = !followSelectedVehicle;
        updateFollowSelectedPill();

        if (followSelectedVehicle && selectedVehicleId) {
            focusVehicle(selectedVehicleId, false);
        }
    };

    function renderVehicleMarkers(fit) {
        if (!map) return;

        const bounds = new google.maps.LatLngBounds();
        const seen = new Set();

        vehicles.forEach(v => {
            if (v.lat == null || v.lon == null) return;

            const id = String(v.id);
            seen.add(id);

            const pos = {
                lat: parseFloat(v.lat),
                lng: parseFloat(v.lon)
            };

            let m = markers[id];

            if (!m) {
                m = new google.maps.Marker({
                    map,
                    position: pos,
                    title: v.immatriculation || '',
                    icon: carIcon(),
                    zIndex: 20,
                });

                m.addListener('click', () => {
                    selectedVehicleId = parseInt(v.id, 10);
                    followSelectedVehicle = true;
                    updateFollowSelectedPill();

                    selectVehicleInList(v.id);
                    focusVehicle(v.id, true);
                    openVehicleModal(v.id);

                    if (document.getElementById('pane-alertes')?.classList.contains('active')) loadAlerts();
                    if (document.getElementById('pane-trajets')?.classList.contains('active')) loadTrips();
                });

                markers[id] = m;
            } else {
                m.setPosition(pos);
                m.setIcon(carIcon());
            }

            bounds.extend(pos);
        });

        Object.keys(markers).forEach(id => {
            if (!seen.has(id)) {
                markers[id].setMap(null);
                delete markers[id];
            }
        });

        updateSelectedVehicleIndicator();

        if (fit && !bounds.isEmpty()) {
            map.fitBounds(bounds);
            const l = google.maps.event.addListener(map, 'idle', () => {
                if (map.getZoom() > 14) map.setZoom(14);
                google.maps.event.removeListener(l);
                updateSelectedVehicleIndicator();
            });
        }
    }

    function focusVehicle(id, forceFollow = false) {
        const m = markers[String(id)];
        if (!m || !map) return;

        if (forceFollow) {
            followSelectedVehicle = true;
            updateFollowSelectedPill();
        }

        map.setCenter(m.getPosition());
        map.setZoom(15);
        updateSelectedVehicleIndicator();
    }

    window.toggleMapTypeMenu = () => document.getElementById('mapTypeMenu')?.classList.toggle('show');

    window.setMapType = (el, type) => {
        if (!map) return;
        map.setMapTypeId(type);
        document.querySelectorAll('#mapTypeMenu .it').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        document.getElementById('mapTypeMenu')?.classList.remove('show');
    };

    function sseState(state) {
        const dot = document.getElementById('sseDot');
        const txt = document.getElementById('sseTxt');

        const m = {
            connected: { c: '#22c55e', t: 'Connecté' },
            connecting: { c: '#eab308', t: 'Connexion…' },
            reconnecting: { c: '#f97316', t: 'Reconnexion…' },
            paused: { c: '#9ca3af', t: 'En pause' },
        }[state] || { c: '#9ca3af', t: '—' };

        if (dot) dot.style.background = m.c;
        if (txt) txt.textContent = m.t;
    }

    function startSSE() {
        try {
            sse?.close();
            sse = new EventSource(R.stream, { withCredentials: true });
            sseState('connecting');

            sse.addEventListener('hello', () => sseState('connected'));

            sse.addEventListener('dashboard.init', (e) => {
                sseState('connected');

                let p;
                try {
                    p = JSON.parse(e.data);
                } catch {
                    return;
                }

                if (p.ts) {
                    const lastUp = document.getElementById('lastUp');
                    if (lastUp) lastUp.textContent = 'Maj: ' + p.ts;
                }

                if (p.stats) {
                    if (p.stats.usersCount != null) {
                        const kUsers = document.getElementById('kUsers');
                        if (kUsers) kUsers.textContent = parseInt(p.stats.usersCount, 10);
                    }

                    if (p.stats.vehiclesCount != null) {
                        const kVeh = document.getElementById('kVeh');
                        if (kVeh) kVeh.textContent = parseInt(p.stats.vehiclesCount, 10);
                    }

                    if (p.stats.alertsByType) {
                        Object.entries(p.stats.alertsByType).forEach(([type, count]) => {
                            const el = document.getElementById('kA_' + type);
                            if (el) el.textContent = parseInt(count || 0, 10);
                        });
                    }

                    if (p.stats.alertsCount != null) {
                        const b = document.getElementById('bAlerts');
                        if (b) b.textContent = parseInt(p.stats.alertsCount, 10);
                    }
                }

                if (Array.isArray(p.fleet)) {
                    vehicles = mergeFleetRealtime(p.fleet);
                    renderVehicleList();
                    renderVehicleMarkers(false);

                    if (selectedVehicleId) {
                        const stillExists = vehicles.some(v => String(v.id) === String(selectedVehicleId));

                        if (!stillExists) {
                            selectedVehicleId = null;
                            updateFollowSelectedPill();
                            updateSelectedVehicleIndicator();
                            document.getElementById('vehicleModal')?.classList.remove('show');
                        } else {
                            updateSelectedVehicleIndicator();

                            if (document.getElementById('vehicleModal')?.classList.contains('show')) {
                                openVehicleModal(selectedVehicleId);
                            }

                            if (followSelectedVehicle) {
                                focusVehicle(selectedVehicleId, false);
                            }
                        }
                    }
                }

                if (Array.isArray(p.alerts)) {
                    alerts = p.alerts;
                    renderAlertList();
                }
            });

            sse.addEventListener('fleet.reset', (e) => {
                sseState('connected');

                let p;
                try {
                    p = JSON.parse(e.data);
                } catch {
                    return;
                }

                if (Array.isArray(p.fleet)) {
                    vehicles = mergeFleetRealtime(p.fleet);
                    renderVehicleList();
                    renderVehicleMarkers(false);

                    if (selectedVehicleId) {
                        const stillExists = vehicles.some(v => String(v.id) === String(selectedVehicleId));

                        if (!stillExists) {
                            selectedVehicleId = null;
                            updateFollowSelectedPill();
                            updateSelectedVehicleIndicator();
                            document.getElementById('vehicleModal')?.classList.remove('show');
                        } else {
                            updateSelectedVehicleIndicator();

                            if (document.getElementById('vehicleModal')?.classList.contains('show')) {
                                openVehicleModal(selectedVehicleId);
                            }

                            if (followSelectedVehicle) {
                                focusVehicle(selectedVehicleId, false);
                            }
                        }
                    }
                }
            });

            sse.addEventListener('vehicle.updated', (e) => {
                sseState('connected');

                let p;
                try {
                    p = JSON.parse(e.data);
                } catch {
                    return;
                }

                if (!p?.vehicle || !p.vehicle.id) return;

                const next = enrichVehicleLiveStatus(p.vehicle);
                const idx = vehicles.findIndex(v => String(v.id) === String(next.id));

                if (idx >= 0) {
                    vehicles[idx] = next;
                } else {
                    vehicles.push(next);
                }

                renderVehicleList();
                renderVehicleMarkers(false);

                if (selectedVehicleId && String(selectedVehicleId) === String(next.id)) {
                    updateSelectedVehicleIndicator();

                    if (document.getElementById('vehicleModal')?.classList.contains('show')) {
                        openVehicleModal(selectedVehicleId);
                    }

                    if (followSelectedVehicle) {
                        focusVehicle(selectedVehicleId, false);
                    }
                }
            });

            sse.addEventListener('alerts.updated', (e) => {
                sseState('connected');

                let p;
                try {
                    p = JSON.parse(e.data);
                } catch {
                    return;
                }

                if (Array.isArray(p.alerts)) {
                    alerts = p.alerts;
                    renderAlertList();
                }
            });

            sse.addEventListener('stats.updated', (e) => {
                sseState('connected');

                let p;
                try {
                    p = JSON.parse(e.data);
                } catch {
                    return;
                }

                if (!p.stats) return;

                if (p.stats.usersCount != null) {
                    const kUsers = document.getElementById('kUsers');
                    if (kUsers) kUsers.textContent = parseInt(p.stats.usersCount, 10);
                }

                if (p.stats.vehiclesCount != null) {
                    const kVeh = document.getElementById('kVeh');
                    if (kVeh) kVeh.textContent = parseInt(p.stats.vehiclesCount, 10);
                }

                if (p.stats.alertsByType) {
                    Object.entries(p.stats.alertsByType).forEach(([type, count]) => {
                        const el = document.getElementById('kA_' + type);
                        if (el) el.textContent = parseInt(count || 0, 10);
                    });
                }

                if (p.stats.alertsCount != null) {
                    const b = document.getElementById('bAlerts');
                    if (b) b.textContent = parseInt(p.stats.alertsCount, 10);
                }
            });

            sse.onerror = () => sseState('reconnecting');

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    sse?.close();
                    sse = null;
                    sseState('paused');
                } else if (!sse) {
                    startSSE();
                }
            });

            window.addEventListener('beforeunload', () => sse?.close());
        } catch {
            sseState('paused');
        }
    }

    function setTopTripsKpis({ count = 0, dist = 0, durMin = 0, max = 0 }) {
        const c = document.getElementById('tkCount');
        const d = document.getElementById('tkDist');
        const du = document.getElementById('tkDur');
        const m = document.getElementById('tkMax');

        if (c) c.textContent = String(count);
        if (d) d.textContent = `${Number(dist || 0).toFixed(1)} km`;
        if (du) du.textContent = fmtMin(durMin || 0);
        if (m) m.textContent = `${Math.round(max || 0)} km/h`;
    }

    function computeTripAgg(list) {
        const dist = list.reduce((s, t) => s + (parseFloat(t.total_distance_km || 0) || 0), 0);
        const dur = list.reduce((s, t) => s + (parseInt(t.duration_minutes || 0, 10) || 0), 0);
        const max = list.reduce((m, t) => Math.max(m, (parseFloat(t.max_speed_kmh || 0) || 0)), 0);
        return { count: list.length, dist, durMin: dur, max };
    }

    function filterTripsForUi(list) {
        let out = list.slice();
        if (tripFilter === 'active') out = out.filter(t => !(t.end_time || t.end_at));
        if (tripFilter === 'done') out = out.filter(t => !!(t.end_time || t.end_at));
        return out;
    }

    window.setTripFilter = (el, f) => {
        tripFilter = f;
        document.querySelectorAll('#tf .f').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        renderTripList();
    };

    window.loadTrips = () => {
        const box = document.getElementById('tripList');
        if (box) {
            box.innerHTML = `<div class="empty"><i class="fas fa-circle-notch fa-spin"></i><div style="margin-top:.6rem">Chargement…</div></div>`;
        }

        const from = document.getElementById('tFrom')?.value || '';
        const to = document.getElementById('tTo')?.value || '';

        const url = new URL(R.trajetsList);
        url.searchParams.set('format', 'json');
        url.searchParams.set('per_page', '50');

        if (selectedVehicleId) url.searchParams.set('vehicle_id', String(selectedVehicleId));

        if (tripsQuick && tripsQuick !== 'range') {
            url.searchParams.set('quick', tripsQuick);
        } else {
            if (from) url.searchParams.set('start_date', from);
            if (to) url.searchParams.set('end_date', to);
            url.searchParams.set('quick', 'range');
        }

        fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(json => {
            trips = json.data || [];
            renderTripList();
            if (!currentTrip) {
                setTopTripsKpis(computeTripAgg(filterTripsForUi(trips)));
            }
        })
        .catch(() => {
            if (box) {
                box.innerHTML = `<div class="empty"><i class="fas fa-exclamation-triangle"></i><div style="margin-top:.6rem">Erreur endpoint trajets JSON</div></div>`;
            }
        });
    };

    function renderTripList() {
        const box = document.getElementById('tripList');
        if (!box) return;

        const list = filterTripsForUi(trips);

        if (!currentTrip) setTopTripsKpis(computeTripAgg(list));

        if (!list.length) {
            box.innerHTML = `<div class="empty"><i class="fas fa-route"></i><div style="margin-top:.6rem">Aucun trajet</div></div>`;
            return;
        }

        box.innerHTML = list.map((t, i) => {
            const imm = esc(t.immatriculation || '—');
            const drv = esc(t.driver_label || '—');
            const isAct = !(t.end_time || t.end_at);
            const s = `${imm} ${drv}`.toLowerCase();
            const km = parseFloat(t.total_distance_km || 0).toFixed(1);
            const dur = fmtMin(t.duration_minutes || 0);
            const mx = Math.round(t.max_speed_kmh || 0);
            const isCurrent = currentTrip && String(currentTrip.id) === String(t.id);
            const dotStyle = `background:${isAct ? COLORS.moving : COLORS.offline};${isAct ? 'animation:pulse 1.5s infinite' : ''}`;

            if (viewMode.trajets === 'simple') {
                return `
      <div class="item${isCurrent ? ' sel' : ''}" data-s="${s}" data-trip="${t.id}" data-veh="${t.vehicle_id}">
        <div class="hrow">
          <div class="title">${imm}</div>
          <div class="dot" style="${dotStyle}"></div>
        </div>
        <div class="sub">👤 ${drv}</div>
      </div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
            }

            return `
    <div class="item${isCurrent ? ' sel' : ''}" data-s="${s}" data-trip="${t.id}" data-veh="${t.vehicle_id}">
      <div class="hrow">
        <div class="title">${imm}</div>
        <div class="dot" style="${dotStyle}"></div>
      </div>
      <div class="sub">👤 ${drv}</div>
      <div class="tags">
        <span class="tag" style="background:var(--color-primary-light);color:var(--color-primary)">📏 ${km} km</span>
        <span class="tag" style="background:rgba(37,99,235,.12);color:#2563eb">⏱ ${dur}</span>
        <span class="tag" style="background:rgba(245,158,11,.12);color:#d97706">🏁 ${mx} km/h</span>
        <span class="tag" style="background:rgba(107,114,128,.10);color:var(--color-text)">🗺️ Voir sur carte</span>
      </div>
    </div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
        }).join('');

        box.querySelectorAll('.item').forEach(el => {
            el.addEventListener('click', async () => {
                const tripId = el.dataset.trip;
                const vehId = el.dataset.veh;
                if (!tripId || !vehId) return;

                if (currentTrip && String(currentTrip.id) === String(tripId)) {
                    document.getElementById('tripModal')?.classList.add('show');
                    return;
                }

                box.querySelectorAll('.item').forEach(x => x.classList.remove('sel'));
                el.classList.add('sel');

                await openTrip(vehId, tripId);
            });
        });

        window.doSearch();
    }

    async function openTrip(vehicleId, trajetId) {
        document.getElementById('tripModal')?.classList.remove('show');

        try {
            const url = R.trajetDetail(vehicleId, trajetId);
            const res = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const json = await res.json();

            const data = json?.data ?? json ?? {};
            const trajet = data?.trajet ?? data ?? {};
            const track = data?.track ?? {};

            const pointsRaw = Array.isArray(track?.points) ? track.points :
                Array.isArray(data?.points) ? data.points : [];

            const points = pointsRaw.map(p => ({
                lat: parseFloat(p.lat ?? p.latitude ?? NaN),
                lng: parseFloat(p.lng ?? p.longitude ?? p.lon ?? NaN),
                ts: p.ts ?? p.time ?? p.created_at ?? '',
                spd: parseFloat(p.speed ?? p.vitesse ?? 0),
            })).filter(p => isFinite(p.lat) && isFinite(p.lng));

            if (points.length === 0) {
                toast('Aucun point GPS pour ce trajet', false);
            }

            const dist = Number(trajet.stats?.distance ?? trajet.total_distance_km ?? trajet.distance ?? 0);
            const dur = Number(trajet.stats?.duration ?? trajet.duration_minutes ?? trajet.duration ?? 0);
            const max = Number(trajet.stats?.max_speed ?? trajet.max_speed_kmh ?? trajet.max_speed ?? 0);
            const ptCount = track?.count ?? points.length;

            currentTrip = {
                vehicle_id: Number(vehicleId),
                id: Number(trajetId),
                points,
                bounds: null,
            };

            setTopTripsKpis({ count: 1, dist, durMin: dur, max });

            const imm = esc(trajet.immatriculation ?? '');
            const tmTitle = document.getElementById('tmTitle');
            const tmSub = document.getElementById('tmSub');
            const tmDist = document.getElementById('tmDist');
            const tmDur = document.getElementById('tmDur');
            const tmMax = document.getElementById('tmMax');
            const tmPts = document.getElementById('tmPts');
            const tmStart = document.getElementById('tmStart');
            const tmEnd = document.getElementById('tmEnd');

            if (tmTitle) tmTitle.textContent = imm ? `Trajet • ${imm}` : `Trajet #${trajetId}`;
            if (tmSub) tmSub.textContent = `${trajet.start_time ?? trajet.start_at ?? '—'} → ${trajet.end_time ?? trajet.end_at ?? '—'}`;
            if (tmDist) tmDist.textContent = `${Number(dist).toFixed(2)} km`;
            if (tmDur) tmDur.textContent = fmtMin(dur);
            if (tmMax) tmMax.textContent = `${Math.round(max)} km/h`;
            if (tmPts) tmPts.textContent = String(ptCount);
            if (tmStart) tmStart.textContent = trajet.start_time ?? trajet.start_at ?? '—';
            if (tmEnd) tmEnd.textContent = trajet.end_time ?? trajet.end_at ?? '—';

            document.getElementById('tripModal')?.classList.add('show');

            if (points.length >= 2) {
                drawTrip(points);
                toast('Trajet affiché sur la carte');
            } else {
                toast('Trajet chargé (pas assez de points GPS pour le replay)', false);
            }

        } catch (e) {
            console.error('[openTrip]', e);
            toast('Erreur lors du chargement du trajet', false);
            document.getElementById('tripModal')?.classList.add('show');
        }
    }

    window.closeTripModal = () => {
        document.getElementById('tripModal')?.classList.remove('show');
    };

    window.focusTrip = () => {
        if (!map || !currentTrip?.points?.length) return;
        const b = new google.maps.LatLngBounds();
        currentTrip.points.forEach(p => b.extend({ lat: p.lat, lng: p.lng }));
        map.fitBounds(b);
    };

    function drawTrip(points) {
        if (!map) return;

        tripPolyline?.setMap(null);
        tripCursor?.setMap(null);
        window.replayPause();

        const path = points.map(p => ({ lat: p.lat, lng: p.lng }));

        tripPolyline = new google.maps.Polyline({
            map,
            path,
            strokeColor: '#2563eb',
            strokeOpacity: 1,
            strokeWeight: 4,
        });

        const b = new google.maps.LatLngBounds();
        path.forEach(pt => b.extend(pt));
        map.fitBounds(b);
        if (currentTrip) currentTrip.bounds = b;

        tripCursor = new google.maps.Marker({
            map,
            position: path[0],
            title: 'Replay',
            icon: carIcon(),
            zIndex: 200,
        });

        replayPoints = points;
        replayIndex = 0;
        replaySpeedFactor = 1;

        updateSpeedUI(1);

        const rg = document.getElementById('rpRange');
        if (rg) {
            rg.min = 0;
            rg.max = Math.max(0, replayPoints.length - 1);
            rg.value = 0;
        }

        const meta = document.getElementById('rpMeta');
        if (meta) meta.textContent = `1/${replayPoints.length}`;

        const replay = document.getElementById('tripReplay');
        if (replay) replay.style.display = 'block';
    }

    function updateSpeedUI(spd) {
        const rp = document.getElementById('rpSpeed');
        if (rp) rp.textContent = spd % 1 === 0 ? String(spd) : spd.toString();

        document.querySelectorAll('.speed-chip').forEach(c => {
            c.classList.toggle('active-chip', parseFloat(c.dataset.spd) === spd);
        });
    }

    function setReplayIndex(i) {
        i = Math.max(0, Math.min(replayPoints.length - 1, parseInt(i, 10) || 0));
        replayIndex = i;
        const p = replayPoints[replayIndex];
        const pos = { lat: p.lat, lng: p.lng };
        tripCursor?.setPosition(pos);

        if (replayFollow && map && replayTimer) {
            map.panTo(pos);
        }

        const range = document.getElementById('rpRange');
        const meta = document.getElementById('rpMeta');
        if (range) range.value = replayIndex;
        if (meta) meta.textContent = `${replayIndex + 1}/${replayPoints.length}`;
    }

    window.replayPlay = () => {
        if (!replayPoints.length) return;
        clearInterval(replayTimer);
        replayTimer = setInterval(() => {
            if (replayIndex >= replayPoints.length - 1) {
                window.replayPause();
                return;
            }
            setReplayIndex(replayIndex + 1);
        }, Math.max(30, 200 / replaySpeedFactor));
    };

    window.replayPause = () => {
        clearInterval(replayTimer);
        replayTimer = null;
    };

    window.replayStop = () => {
        clearInterval(replayTimer);
        replayTimer = null;
        setReplayIndex(0);
    };

    window.replaySeek = (v) => {
        window.replayPause();
        setReplayIndex(v);
    };

    window.replaySetSpeed = (spd) => {
        replaySpeedFactor = spd;
        updateSpeedUI(spd);
        if (replayTimer) window.replayPlay();
    };

    window.replayFaster = () => {
        const idx = SPEED_STEPS.indexOf(replaySpeedFactor);
        const next = idx < SPEED_STEPS.length - 1 ? SPEED_STEPS[idx + 1] : SPEED_STEPS[SPEED_STEPS.length - 1];
        window.replaySetSpeed(next);
    };

    window.replaySlower = () => {
        const idx = SPEED_STEPS.indexOf(replaySpeedFactor);
        const prev = idx > 0 ? SPEED_STEPS[idx - 1] : SPEED_STEPS[0];
        window.replaySetSpeed(prev);
    };

    window.toggleFollow = () => {
        replayFollow = !replayFollow;
        const btn = document.getElementById('rpFollow');
        if (!btn) return;
        btn.classList.toggle('active-btn', replayFollow);
        btn.textContent = replayFollow ? '🎯 Suivre' : '🎯 Libre';
    };

    window.closeReplay = () => {
        window.replayPause();
        const rp = document.getElementById('tripReplay');
        if (rp) rp.style.display = 'none';

        tripPolyline?.setMap(null);
        tripPolyline = null;
        tripCursor?.setMap(null);
        tripCursor = null;
        replayPoints = [];
        replayIndex = 0;
        currentTrip = null;

        document.getElementById('tripModal')?.classList.remove('show');

        const inTrajets = document.getElementById('pane-trajets')?.classList.contains('active');
        if (inTrajets) {
            setTopTripsKpis(computeTripAgg(filterTripsForUi(trips)));
            renderTripList();
        }
    };

    window.setAlertType = (el, t) => {
        alertType = t;
        document.querySelectorAll('#af .f').forEach(x => x.classList.remove('active'));
        el?.classList.add('active');
        loadAlerts();
    };

    window.filterAlertsByType = (t) => {
        alertType = t;
        window.switchTab('alertes');
        document.querySelectorAll('#af .f').forEach(x => x.classList.toggle('active', x.dataset.at === t));
        loadAlerts();
    };

    window.loadAlerts = () => {
        const box = document.getElementById('alertList');
        if (box) {
            box.innerHTML = `<div class="empty"><i class="fas fa-circle-notch fa-spin"></i><div style="margin-top:.6rem">Chargement…</div></div>`;
        }

        const dFrom = document.getElementById('aFrom')?.value || '';
        const dTo = document.getElementById('aTo')?.value || '';
        const hFrom = document.getElementById('aHFrom')?.value || '';
        const hTo = document.getElementById('aHTo')?.value || '';

        const url = new URL(R.alertsDay);
        url.searchParams.set('per_page', '50');

        if (alertsQuick && alertsQuick !== 'range') {
            url.searchParams.set('quick', alertsQuick);
        } else {
            if (dFrom) url.searchParams.set('date_from', dFrom);
            if (dTo) url.searchParams.set('date_to', dTo);
            if (hFrom) url.searchParams.set('hour_from', hFrom);
            if (hTo) url.searchParams.set('hour_to', hTo);
            url.searchParams.set('quick', 'range');
        }

        if (selectedVehicleId) url.searchParams.set('vehicle_id', String(selectedVehicleId));
        if (alertType !== 'all' && ALLOWED_ALERT_TYPES.has(alertType)) url.searchParams.set('alert_type', alertType);

        fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => {
            if (!r.ok) throw new Error(r.status);
            return r.json();
        })
        .then(json => {
            alerts = json.data || [];
            renderAlertList();

            const by = json.stats?.by_type || {};
            Object.entries(by).forEach(([type, count]) => {
                const el = document.getElementById('kA_' + type);
                if (el) el.textContent = count;
            });

            const b = document.getElementById('bAlerts');
            if (b) b.textContent = (json.meta?.total ?? alerts.length);
        })
        .catch(() => {
            if (box) {
                box.innerHTML = `<div class="empty"><i class="fas fa-exclamation-triangle"></i><div style="margin-top:.6rem">Erreur endpoint alertes JSON</div></div>`;
            }
        });
    };

    function renderAlertList() {
        const box = document.getElementById('alertList');
        if (!box) return;

        const list = alerts.slice();

        if (!list.length) {
            box.innerHTML = `<div class="empty"><i class="fas fa-bell-slash"></i><div style="margin-top:.6rem">Aucune alerte</div></div>`;
            return;
        }

        const simple = viewMode.alertes === 'simple';

        box.innerHTML = list.map((a, i) => {
            const type = a.type ?? a.alert_type ?? '—';
            const imm = esc(a.vehicle?.label ?? a.immatriculation ?? '—');
            const title = type === 'stolen' ? 'Vol détecté' :
                type === 'geofence' ? 'Geofence' :
                type === 'safe_zone' ? 'Safe Zone' :
                type === 'speed' ? 'Survitesse' :
                type === 'time_zone' ? 'Time Zone' :
                'Alerte';

            const sevColor = type === 'stolen' ? '#dc2626' : type === 'speed' ? '#d97706' : 'var(--color-primary)';
            const s = `${imm} ${title} ${type}`.toLowerCase();
            const tss = esc(a.created_at ?? '—');
            const read = !!(a.is_read);
            const badge = read ?
               `<span class="tag" style="background:rgba(22,163,74,.12);color:#16a34a">Lue</span>` :
               `<span class="tag" style="background:rgba(217,119,6,.12);color:#d97706">Nouvelle</span>`;

            if (simple) {
                return `
      <div class="item" data-s="${s}" data-id="${a.id}">
        <div class="hrow">
          <div class="title">${imm}</div>
          <div class="dot" style="background:${sevColor};animation:pulse 1.2s infinite"></div>
        </div>
        <div class="sub">${esc(title)}</div>
      </div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
            }

            return `
    <div class="item" data-s="${s}" data-id="${a.id}">
      <div class="hrow">
        <div class="title">${imm}</div>
        <div class="dot" style="background:${sevColor};animation:pulse 1.2s infinite"></div>
      </div>
      <div class="sub">${esc(title)} • <span style="font-family:var(--font-mono,monospace)">${tss}</span></div>
      <div class="tags">
        <span class="tag" style="background:rgba(37,99,235,.12);color:#2563eb">${esc(type)}</span>
        ${badge}
      </div>
    </div>${i < list.length - 1 ? '<div class="sep"></div>' : ''}`;
        }).join('');

        box.querySelectorAll('.item').forEach(el => {
            el.addEventListener('click', () => {
                box.querySelectorAll('.item').forEach(x => x.classList.remove('sel'));
                el.classList.add('sel');
                const id = el.dataset.id;
                const a = alerts.find(x => String(x.id) === String(id));
                if (a) openAlertDetail(a);
            });
        });

        window.doSearch();
    }

    function openAlertDetail(a) {
        const type = a.type ?? a.alert_type ?? '—';
        const imm = a.vehicle?.label ?? a.immatriculation ?? '—';
        const title = type === 'stolen' ? 'Vol détecté' :
            type === 'geofence' ? 'Geofence' :
            type === 'safe_zone' ? 'Safe Zone' :
            type === 'speed' ? 'Survitesse' :
            type === 'time_zone' ? 'Time Zone' :
            'Alerte';

        const desc = [
            `Type : ${type}`,
            `Véhicule : ${imm}`,
            a.speed ? `Vitesse : ${a.speed} km/h` : null,
            a.location ? `Lieu : ${a.location}` : null,
            a.lat && a.lng ? `Position : ${parseFloat(a.lat).toFixed(5)}, ${parseFloat(a.lng).toFixed(5)}` : null,
            a.created_at ? `Date : ${a.created_at}` : null,
            a.description ? `${a.description}` : null,
        ].filter(Boolean).join('\n');

        const adTitle = document.getElementById('adTitle');
        const adVeh = document.getElementById('adVeh');
        const adDesc = document.getElementById('adDesc');
        const ad = document.getElementById('alertDetail');

        if (adTitle) adTitle.textContent = title;
        if (adVeh) adVeh.textContent = imm;
        if (adDesc) adDesc.textContent = desc || '—';

        currentAlert = a;

        if (ad) ad.style.display = 'block';

        if (a.lat && a.lng && map) {
            map.setCenter({
                lat: parseFloat(a.lat),
                lng: parseFloat(a.lng)
            });
            map.setZoom(15);
        }
    }

    window.closeAlertDetail = () => {
        const ad = document.getElementById('alertDetail');
        if (ad) ad.style.display = 'none';
        document.querySelectorAll('#alertList .item').forEach(x => x.classList.remove('sel'));
        currentAlert = null;
    };

    window.markAlertRead = () => {
        if (currentAlert) currentAlert.is_read = true;
        toast('Alerte marquée comme lue');
    };

    window.markAlertProcessed = () => toast('Alerte marquée comme traitée');

    window.locateAlert = () => {
        if (!currentAlert) return;

        const vehicleId = getAlertVehicleId(currentAlert);

        if (vehicleId) {
            selectedVehicleId = parseInt(vehicleId, 10);
            followSelectedVehicle = true;
            updateFollowSelectedPill();

            window.switchTab('flotte');

            requestAnimationFrame(() => {
                renderVehicleList();
                selectVehicleInList(vehicleId);
                focusVehicle(vehicleId, true);
                openVehicleModal(vehicleId);
                window.closeAlertDetail();
                toast('Véhicule localisé');
            });

            return;
        }

        if (currentAlert?.lat && currentAlert?.lng && map) {
            window.switchTab('flotte');
            map.setCenter({
                lat: parseFloat(currentAlert.lat),
                lng: parseFloat(currentAlert.lng)
            });
            map.setZoom(16);
            window.closeAlertDetail();
            toast('Position de l’alerte localisée');
            return;
        }

        toast('Impossible de localiser ce véhicule', false);
    };

    function bindAutoFilters() {
        const loadTripsDeb = debounce(() => loadTrips(), 250);
        const loadAlertsDeb = debounce(() => loadAlerts(), 250);

        ['tFrom', 'tTo'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', () => {
                tripsQuick = 'range';
                document.getElementById('tDateBox')?.classList.add('show');
                loadTripsDeb();
            });
        });

        ['aFrom', 'aTo', 'aHFrom', 'aHTo'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', () => {
                alertsQuick = 'range';
                document.getElementById('aDateBox')?.classList.add('show');
                loadAlertsDeb();
            });
        });

        document.addEventListener('click', (e) => {
            const menu = document.getElementById('mapTypeMenu');
            const ctrl = document.getElementById('mapTypeCtrl');
            if (!menu || !ctrl) return;
            if (!ctrl.contains(e.target)) menu.classList.remove('show');
        });
    }

    function initDates() {
        const today = new Date().toISOString().slice(0, 10);

        const tFrom = document.getElementById('tFrom');
        const tTo = document.getElementById('tTo');
        const aFrom = document.getElementById('aFrom');
        const aTo = document.getElementById('aTo');
        const aHFrom = document.getElementById('aHFrom');
        const aHTo = document.getElementById('aHTo');

        if (tFrom) tFrom.value = today;
        if (tTo) tTo.value = today;
        if (aFrom) aFrom.value = today;
        if (aTo) aTo.value = today;
        if (aHFrom) aHFrom.value = '';
        if (aHTo) aHTo.value = '';

        ['vf', 'tQuick', 'tf', 'tDateBox', 'aQuick', 'af', 'aDateBox'].forEach(id => {
            document.getElementById(id)?.classList.remove('show');
        });
    }

    setInterval(() => {
        if (!vehicles.length) return;

        vehicles = vehicles.map(enrichVehicleLiveStatus);

        const inFleetTab = document.getElementById('pane-flotte')?.classList.contains('active');
        const modalOpen = document.getElementById('vehicleModal')?.classList.contains('show');

        if (inFleetTab) {
            renderVehicleList();
        }

        if (selectedVehicleId) {
            updateSelectedVehicleIndicator();

            if (modalOpen) {
                openVehicleModal(selectedVehicleId);
            }

            if (followSelectedVehicle && map && markers[String(selectedVehicleId)]) {
                focusVehicle(selectedVehicleId, false);
            }
        }
    }, 1000);

    window.addEventListener('resize', () => {
        measureHeights();
        moveFollowPillNearMapType();
        updateSelectedVehicleIndicator();
    });

    document.addEventListener('DOMContentLoaded', () => {
        vehicles = mergeFleetRealtime(vehicles);
        initDates();
        bindAutoFilters();
        renderVehicleList();
        updateFollowSelectedPill();
        loadGoogleMaps();
        measureHeights();
        loadAlerts();
    });
})();
</script>
@endpush