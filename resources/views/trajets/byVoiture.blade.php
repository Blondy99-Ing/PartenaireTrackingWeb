{{-- resources/views/trajets/byVoiture.blade.php --}}
@extends('layouts.app')

@section('title', 'Trajets sur carte')

@push('head')
<style>

/* ════════════════════════════════════════════════════════════
   PAGE TRAJETS — Layout full-screen professionnel
════════════════════════════════════════════════════════════ */

/* ── Wrapper principal plein écran ─────────────────────── */
.trajets-layout {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--navbar-h) - var(--kpi-h, 0px));
    gap: 0;
    overflow: hidden;
}

/* ── Topbar : titre + stats + bouton filtre ───────────── */
.trajets-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 10px var(--sp-xl);
    background: var(--color-card);
    border-bottom: 1px solid var(--color-border-subtle);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.trajets-topbar-left {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
    flex-wrap: wrap;
}

.trajets-title {
    font-family: var(--font-display);
    font-size: clamp(0.9rem, 1.5vw, 1.1rem);
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    white-space: nowrap;
}

.trajets-title span { color: var(--color-primary); }

/* ── Stats inline ───────────────────────────────────────── */
.stat-pills {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: var(--color-bg);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--color-text);
    white-space: nowrap;
}

.stat-pill i {
    font-size: 0.6rem;
    color: var(--color-primary);
}

.stat-pill .val {
    color: var(--color-primary);
    font-size: 0.85rem;
}

/* ── Bouton filtre toggle ───────────────────────────────── */
.btn-filter-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--color-bg);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--r-md);
    font-family: var(--font-display);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--color-secondary-text);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    flex-shrink: 0;
}

.btn-filter-toggle:hover,
.btn-filter-toggle.active {
    background: var(--color-primary-light);
    border-color: var(--color-primary-border);
    color: var(--color-primary);
}

.btn-filter-toggle .filter-count {
    background: var(--color-primary);
    color: #fff;
    border-radius: var(--r-pill);
    padding: 1px 6px;
    font-size: 0.6rem;
    font-weight: 700;
    display: none;
}

.btn-filter-toggle.has-filters .filter-count { display: inline; }

/* ── Panneau filtres (collapsible) ─────────────────────── */
.filters-panel {
    background: var(--color-card);
    border-bottom: 1px solid var(--color-border-subtle);
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease, padding 0.3s ease;
    flex-shrink: 0;
}

.filters-panel.open {
    max-height: 200px;
}

.filters-inner {
    padding: 12px var(--sp-xl);
    display: grid;
    grid-template-columns: 180px 150px 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}

@media (max-width: 1100px) {
    .filters-inner {
        grid-template-columns: repeat(3, 1fr) auto;
    }
}

@media (max-width: 767px) {
    .filters-inner {
        grid-template-columns: 1fr 1fr;
        padding: 10px var(--sp-md);
    }
    .filters-inner .filter-btn-wrap {
        grid-column: 1 / -1;
    }
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.filter-label {
    font-family: var(--font-display);
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
}

.filter-group select,
.filter-group input {
    height: 34px;
    font-size: 0.78rem;
    padding: 0 0.5rem;
}

.date-range-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}

/* ── Zone map (prend tout l'espace restant) ─────────────── */
.map-zone {
    flex: 1 1 auto;
    position: relative;
    min-height: 0;
    overflow: hidden;
}

#map {
    width: 100%;
    height: 100%;
    display: block;
}

/* ── Boutons flottants carte ────────────────────────────── */
.map-top-actions {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 20;
    display: flex;
    gap: 8px;
}

.floating-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: var(--r-pill);
    border: 1px solid rgba(255,255,255,.18);
    background: rgba(15,20,30,.55);
    color: #fff;
    backdrop-filter: blur(8px);
    cursor: pointer;
    transition: .15s;
    user-select: none;
    font-family: var(--font-display);
    font-size: 0.75rem;
    font-weight: 700;
    box-shadow: 0 4px 16px rgba(0,0,0,.22);
    white-space: nowrap;
}

.floating-btn:hover { border-color: rgba(245,130,32,.9); transform: translateY(-1px); }
.floating-btn.active { border-color: rgba(245,130,32,.95); background: rgba(245,130,32,.22); }

/* ── Panels flottants ──────────────────────────────────── */
.floating-panel {
    position: absolute;
    top: 56px;
    right: 12px;
    z-index: 20;
    width: min(340px, calc(100% - 24px));
    display: none;
}

.fp-card {
    border-radius: var(--r-xl);
    background: rgba(10,14,22,.72);
    color: #fff;
    border: 1px solid rgba(255,255,255,.12);
    backdrop-filter: blur(12px);
    padding: 12px 14px;
    box-shadow: 0 16px 48px rgba(0,0,0,.30);
}

.fp-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 10px;
    font-family: var(--font-display);
    font-size: 0.82rem;
    font-weight: 800;
}

.fp-title .fp-close {
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--r-sm);
    border: 1px solid rgba(255,255,255,.15);
    background: rgba(255,255,255,.06);
    color: #fff;
    cursor: pointer;
    font-size: 0.7rem;
    transition: .15s;
    flex-shrink: 0;
}

.fp-close:hover { background: rgba(220,38,38,.25); border-color: rgba(220,38,38,.5); }

.mini-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.mini-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: var(--r-md);
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.06);
    color: #fff;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 0.72rem;
    font-weight: 600;
    transition: .15s;
    user-select: none;
    white-space: nowrap;
}

.mini-btn:hover { border-color: rgba(245,130,32,.9); transform: translateY(-1px); }
.mini-btn.active { border-color: rgba(245,130,32,.95); background: rgba(245,130,32,.18); }

.speed-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 10px;
    border-radius: var(--r-pill);
    border: 1px solid rgba(255,255,255,.14);
    background: rgba(255,255,255,.06);
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 0.75rem;
    min-width: 48px;
    color: #fff;
}

.fp-muted {
    opacity: .72;
    font-family: var(--font-body);
    font-size: 0.7rem;
    margin-top: 8px;
    line-height: 1.5;
}

.fp-progress {
    width: 100%;
    height: 6px;
    border-radius: var(--r-pill);
    background: rgba(255,255,255,.12);
    overflow: hidden;
    margin-top: 10px;
}

.fp-progress > div {
    height: 100%;
    width: 0%;
    background: var(--color-primary);
    transition: width .08s linear;
    border-radius: var(--r-pill);
}

/* ── Focus badge ─────────────────────────────────────────── */
.focus-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    background: var(--color-primary-light);
    border: 1px solid var(--color-primary-border);
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--color-primary);
    white-space: nowrap;
}

/* ── Mobile ─────────────────────────────────────────────── */
@media (max-width: 767px) {
    .trajets-topbar { padding: 8px var(--sp-md); }
    .stat-pills { display: none; }
    .filters-panel.open { max-height: 320px; }
}
</style>

{{-- Google Maps boot solide --}}
<script>
window.__gm_ready = false;
window.__dom_ready = false;
window.__startMap = null;

window.initMap = function () {
    window.__gm_ready = true;
    try {
        if (typeof window.__startMap === 'function' && window.__dom_ready) window.__startMap();
    } catch (e) { console.error('[Trajets] initMap crash:', e); }
};

document.addEventListener('DOMContentLoaded', () => {
    window.__dom_ready = true;
    try {
        if (typeof window.__startMap === 'function' && window.__gm_ready) window.__startMap();
    } catch (e) { console.error('[Trajets] DOM crash:', e); }
});
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&callback=initMap&libraries=geometry"
    async defer></script>
@endpush

@section('content')
@php
    $filters = $filters ?? request()->all();
    $focusId = $focusId ?? request('focus_trajet_id');
    $hasFilters = !empty(array_filter([
        request('date'), request('start_date'), request('end_date'),
        request('start_time'), request('end_time'),
        request('quick') && request('quick') !== 'today' ? request('quick') : null
    ]));
    $h = floor(($totalDuration ?? 0) / 60);
    $m = ($totalDuration ?? 0) % 60;
@endphp

<div class="trajets-layout">

    {{-- ── TOPBAR ─────────────────────────────────────────── --}}
    <div class="trajets-topbar">

        <div class="trajets-topbar-left">

            {{-- Retour + Titre --}}
            <a href="{{ route('trajets.index', $filters) }}" class="btn-secondary" style="padding:5px 12px;min-height:30px;font-size:0.75rem;">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span class="hidden sm:inline"></span>
            </a>

            <h1 class="trajets-title">
                <span>{{ $voiture->immatriculation }}</span>
            </h1>

           

            {{-- Stats inline ─────────────────────────────── --}}
            <div class="stat-pills">
                <div class="stat-pill">
                    <i class="fas fa-route" aria-hidden="true"></i>
                    <span class="val">{{ $trajets->count() }}</span> trajet(s)
                </div>
                <div class="stat-pill">
                    <i class="fas fa-road" aria-hidden="true"></i>
                    <span class="val">{{ $totalDistance ?? 0 }} km</span>
                </div>
                <div class="stat-pill">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <span class="val">{{ $h }}h{{ $m }}m</span>
                </div>
                <div class="stat-pill">
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                    <span class="val">{{ $maxSpeed ?? 0 }} km/h</span> max
                </div>
                <div class="stat-pill">
                    <i class="fas fa-gauge-high" aria-hidden="true"></i>
                    <span class="val">{{ $avgSpeed ?? 0 }} km/h</span> moy.
                </div>
            </div>

        </div>

        {{-- Bouton filtre --}}
        <button class="btn-filter-toggle {{ $hasFilters ? 'has-filters active' : '' }}"
                id="btn-filter-toggle"
                aria-expanded="{{ $hasFilters ? 'true' : 'false' }}"
                aria-controls="filters-panel">
            <i class="fas fa-sliders-h" aria-hidden="true"></i>
            Filtres
            <span class="filter-count">actifs</span>
            <i class="fas fa-chevron-down" id="filter-chevron" style="font-size:0.55rem;transition:transform 0.3s;" aria-hidden="true"></i>
        </button>

    </div>

    {{-- ── PANNEAU FILTRES (caché par défaut) ─────────────── --}}
    <div class="filters-panel {{ $hasFilters ? 'open' : '' }}" id="filters-panel" role="region" aria-label="Filtres">
        <form method="GET" action="{{ url()->current() }}" class="filters-inner" id="filter-form">

            <div class="filter-group">
                <label class="filter-label" for="filter-type">Période</label>
                <select id="filter-type" name="quick" class="ui-select-style">
                    <option value="today"     {{ request('quick','today')=='today'    ?'selected':'' }}>Aujourd'hui</option>
                    <option value="yesterday" {{ request('quick')=='yesterday'        ?'selected':'' }}>Hier</option>
                    <option value="week"      {{ request('quick')=='week'             ?'selected':'' }}>Cette semaine</option>
                    <option value="month"     {{ request('quick')=='month'            ?'selected':'' }}>Ce mois</option>
                    <option value="year"      {{ request('quick')=='year'             ?'selected':'' }}>Cette année</option>
                    <option value="date"      {{ request('quick')=='date'             ?'selected':'' }}>Date précise</option>
                    <option value="range"     {{ request('quick')=='range'            ?'selected':'' }}>Plage</option>
                </select>
            </div>

            {{-- Date précise --}}
            <div class="filter-group" id="wrap-single-date" style="{{ request('quick')=='date' ? '' : 'display:none' }}">
                <label class="filter-label" for="dateInput">Date</label>
                <input id="dateInput" type="date" name="date" class="ui-input-style" value="{{ request('date') }}">
            </div>

            {{-- Plage --}}
            <div class="filter-group" id="wrap-date-range" style="{{ request('quick')=='range' ? '' : 'display:none' }}">
                <label class="filter-label">Du — Au</label>
                <div class="date-range-wrap">
                    <input type="date" name="start_date" class="ui-input-style" value="{{ request('start_date') }}">
                    <input type="date" name="end_date"   class="ui-input-style" value="{{ request('end_date') }}">
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">Heure début</label>
                <input type="time" name="start_time" class="ui-input-style" value="{{ request('start_time') }}">
            </div>

            <div class="filter-group">
                <label class="filter-label">Heure fin</label>
                <input type="time" name="end_time" class="ui-input-style" value="{{ request('end_time') }}">
            </div>

            <div class="filter-group filter-btn-wrap" style="justify-content:flex-end;flex-direction:row;gap:8px;">
                <a href="{{ url()->current() }}" class="btn-secondary" style="padding:5px 12px;min-height:34px;font-size:0.75rem;">
                    <i class="fas fa-times" aria-hidden="true"></i> Reset
                </a>
                <button type="submit" class="btn-primary" style="padding:5px 16px;min-height:34px;font-size:0.75rem;">
                    <i class="fas fa-filter" aria-hidden="true"></i> Filtrer
                </button>
            </div>

        </form>
    </div>

    {{-- ── ZONE CARTE (plein écran restant) ──────────────── --}}
    <div class="map-zone">

        {{-- Boutons flottants --}}
        <div class="map-top-actions">
            <button type="button" class="floating-btn" id="btnMode">
                <i class="fas fa-layer-group" aria-hidden="true"></i> Mode
            </button>
            <button type="button" class="floating-btn" id="btnReplay">
                <i class="fas fa-play-circle" aria-hidden="true"></i> Replay
            </button>
        </div>

        {{-- Panel Mode --}}
        <div class="floating-panel" id="panelMode">
            <div class="fp-card">
                <div class="fp-title">
                    <span><i class="fas fa-layer-group" style="color:var(--color-primary);margin-right:6px;" aria-hidden="true"></i>Mode carte</span>
                    <button type="button" class="fp-close" data-close="panelMode" aria-label="Fermer"><i class="fas fa-times"></i></button>
                </div>
                <div class="mini-actions">
                    <button type="button" class="mini-btn active" data-maptype="roadmap">Plan</button>
                    <button type="button" class="mini-btn" data-maptype="hybrid">Hybride</button>
                    <button type="button" class="mini-btn" data-maptype="satellite">Satellite</button>
                    <button type="button" class="mini-btn" data-maptype="terrain">Terrain</button>
                </div>
                <div class="mini-actions" style="margin-top:8px;">
                    <button type="button" class="mini-btn" id="btnTraffic">
                        <i class="fas fa-traffic-light" aria-hidden="true"></i> Trafic
                    </button>
                    <button type="button" class="mini-btn" id="btnLocate">
                        <i class="fas fa-crosshairs" aria-hidden="true"></i> Ma position
                    </button>
                </div>
            </div>
        </div>

        {{-- Panel Replay --}}
        <div class="floating-panel" id="panelReplay">
            <div class="fp-card">
                <div class="fp-title">
                    <span><i class="fas fa-play-circle" style="color:var(--color-primary);margin-right:6px;" aria-hidden="true"></i>Replay</span>
                    <button type="button" class="fp-close" data-close="panelReplay" aria-label="Fermer"><i class="fas fa-times"></i></button>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                    <div class="mini-actions">
                        <button type="button" class="mini-btn" id="rpPrev"  title="Précédent"><i class="fas fa-step-backward"></i></button>
                        <button type="button" class="mini-btn" id="rpPlay"  title="Play"><i class="fas fa-play"></i></button>
                        <button type="button" class="mini-btn" id="rpPause" title="Pause"><i class="fas fa-pause"></i></button>
                        <button type="button" class="mini-btn" id="rpStop"  title="Stop"><i class="fas fa-stop"></i></button>
                        <button type="button" class="mini-btn" id="rpNext"  title="Suivant"><i class="fas fa-step-forward"></i></button>
                    </div>
                    <div class="mini-actions" style="align-items:center;">
                        <button type="button" class="mini-btn" id="rpSlow" title="Ralentir"><i class="fas fa-minus"></i></button>
                        <span class="speed-pill" id="rpSpeed">x1</span>
                        <button type="button" class="mini-btn" id="rpFast" title="Accélérer"><i class="fas fa-plus"></i></button>
                    </div>
                </div>

                <div class="fp-muted">
                    <div><b>Heure :</b> <span id="rpTime">—</span></div>
                    <div id="rpCoords">—</div>
                    <div><b>Vitesse :</b> <span id="rpV">—</span></div>
                </div>

                <div class="fp-progress"><div id="rpBar"></div></div>
            </div>
        </div>

        {{-- Carte --}}
        <div id="map" role="application" aria-label="Carte des trajets"></div>

    </div>

</div>
{{-- /trajets-layout --}}


{{-- ════════════════════════════════════════════════════════
     SCRIPTS
════════════════════════════════════════════════════════ --}}
@push('scripts')
<script>
(function () {
    'use strict';

    /* ── Toggle filtres ──────────────────────────────────── */
    const btnToggle   = document.getElementById('btn-filter-toggle');
    const filtersPanel = document.getElementById('filters-panel');
    const chevron     = document.getElementById('filter-chevron');

    if (btnToggle && filtersPanel) {
        btnToggle.addEventListener('click', function () {
            const open = filtersPanel.classList.toggle('open');
            btnToggle.setAttribute('aria-expanded', String(open));
            btnToggle.classList.toggle('active', open);
            chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
        });
    }

    /* ── Filtre type select ─────────────────────────────── */
    const filterType   = document.getElementById('filter-type');
    const wrapSingle   = document.getElementById('wrap-single-date');
    const wrapRange    = document.getElementById('wrap-date-range');

    function updateFilterUI() {
        wrapSingle?.style.setProperty('display', filterType?.value === 'date'  ? '' : 'none');
        wrapRange?.style.setProperty('display',  filterType?.value === 'range' ? '' : 'none');
    }

    updateFilterUI();
    filterType?.addEventListener('change', updateFilterUI);

})();
</script>

<script>
/* ════════════════════════════════════════════════════════
   MAP + TRACKS + REPLAY
════════════════════════════════════════════════════════ */
window.__startMap = function bootMap(){
  try{
    const mapDiv = document.getElementById('map');
    if(!mapDiv || !window.google || !google.maps) return;

    const tracksRaw = @json($tracks ?? []);
    const focusId   = @json($focusId);

    const btnMode   = document.getElementById('btnMode');
    const btnReplay = document.getElementById('btnReplay');
    const panelMode   = document.getElementById('panelMode');
    const panelReplay = document.getElementById('panelReplay');

    const btnTraffic  = document.getElementById('btnTraffic');
    const btnLocate   = document.getElementById('btnLocate');
    const mapTypeBtns = Array.from(document.querySelectorAll('[data-maptype]'));

    const rpPrev  = document.getElementById('rpPrev');
    const rpPlay  = document.getElementById('rpPlay');
    const rpPause = document.getElementById('rpPause');
    const rpStop  = document.getElementById('rpStop');
    const rpNext  = document.getElementById('rpNext');
    const rpSlow  = document.getElementById('rpSlow');
    const rpFast  = document.getElementById('rpFast');
    const rpSpeed = document.getElementById('rpSpeed');
    const rpTime  = document.getElementById('rpTime');
    const rpCoords= document.getElementById('rpCoords');
    const rpV     = document.getElementById('rpV');
    const rpBar   = document.getElementById('rpBar');

    const primary = (getComputedStyle(document.documentElement).getPropertyValue('--color-primary') || '').trim() || '#F58220';

    /* ── Auto-hide panels ─────────────────────────────── */
    window.__replayPlaying = false;

    function makeAutoHide(panelEl, getPlaying){
      let timer=null, inside=false;
      function schedule(ms){ clearTimeout(timer); timer=setTimeout(()=>{ if(!inside) panelEl.style.display='none'; }, ms); }
      panelEl.addEventListener('mouseenter', ()=>{ inside=true; clearTimeout(timer); });
      panelEl.addEventListener('mouseleave', ()=>{ inside=false; schedule(getPlaying() ? 800 : 5000); });
      panelEl.__schedule = schedule;
    }

    if(panelMode)   makeAutoHide(panelMode,   ()=>window.__replayPlaying);
    if(panelReplay) makeAutoHide(panelReplay, ()=>window.__replayPlaying);

    function togglePanel(panelEl, otherEl){
      if(!panelEl) return;
      const open = panelEl.style.display === 'block';
      if(otherEl) otherEl.style.display = 'none';
      panelEl.style.display = open ? 'none' : 'block';
      if(!open) panelEl.__schedule && panelEl.__schedule(1400);
    }

    btnMode?.addEventListener('click', e=>{ e.stopPropagation(); togglePanel(panelMode, panelReplay); });
    btnReplay?.addEventListener('click', e=>{ e.stopPropagation(); togglePanel(panelReplay, panelMode); });

    document.querySelectorAll('[data-close]').forEach(x=>{
      x.addEventListener('click', ()=>{
        const el = document.getElementById(x.getAttribute('data-close'));
        if(el) el.style.display='none';
      });
    });

    document.addEventListener('click', e=>{
      const inMode   = panelMode   && panelMode.contains(e.target);
      const inReplay = panelReplay && panelReplay.contains(e.target);
      const isBtn    = (btnMode && btnMode.contains(e.target)) || (btnReplay && btnReplay.contains(e.target));
      if(inMode || inReplay || isBtn) return;
      if(panelMode)   panelMode.style.display='none';
      if(panelReplay) panelReplay.style.display='none';
    });

    /* ── Map init ─────────────────────────────────────── */
    let center = { lat: 4.05, lng: 9.7 };
    for(const tr of (tracksRaw||[])){
      if(tr?.points?.length){ center={lat:+tr.points[0].lat, lng:+tr.points[0].lng}; break; }
    }

    const map = new google.maps.Map(mapDiv, {
      zoom: 13, center,
      mapTypeId: "roadmap",
      mapTypeControl: false,
      fullscreenControl: true,
      streetViewControl: true,
      gestureHandling: "greedy"
    });

    /* ── Traffic ─────────────────────────────────────── */
    const trafficLayer = new google.maps.TrafficLayer();
    let trafficOn = false;
    btnTraffic?.addEventListener('click', ()=>{
      trafficOn = !trafficOn;
      trafficLayer.setMap(trafficOn ? map : null);
      btnTraffic.classList.toggle('active', trafficOn);
    });

    /* ── Map types ───────────────────────────────────── */
    mapTypeBtns.forEach(btn=>{
      btn.addEventListener('click', ()=>{
        mapTypeBtns.forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        map.setMapTypeId(btn.getAttribute('data-maptype'));
      });
    });

    /* ── Locate ──────────────────────────────────────── */
    let myMarker=null, myCircle=null;
    btnLocate?.addEventListener('click', ()=>{
      if(!navigator.geolocation) return;
      navigator.geolocation.getCurrentPosition(pos=>{
        const lat=pos.coords.latitude, lng=pos.coords.longitude, acc=pos.coords.accuracy||0;
        const latLng=new google.maps.LatLng(lat,lng);
        map.panTo(latLng); if(map.getZoom()<16) map.setZoom(16);
        if(!myMarker){
          myMarker=new google.maps.Marker({ map, position:latLng, title:"Ma position",
            icon:{ path:google.maps.SymbolPath.CIRCLE, fillColor:"#2563eb", fillOpacity:1, strokeColor:"#fff", strokeWeight:2, scale:9 } });
        } else myMarker.setPosition(latLng);
        if(!myCircle){
          myCircle=new google.maps.Circle({ map, center:latLng, radius:acc, strokeOpacity:.2, fillOpacity:.08 });
        } else { myCircle.setCenter(latLng); myCircle.setRadius(acc); }
      }, ()=>{}, { enableHighAccuracy:true, timeout:8000 });
    });

    /* ── Helpers géo ─────────────────────────────────── */
    function haversineMeters(a,b){
      const R=6371000, rad=x=>x*Math.PI/180;
      const dLat=rad(b.lat-a.lat), dLng=rad(b.lng-a.lng);
      const s=Math.sin(dLat/2)**2+Math.cos(rad(a.lat))*Math.cos(rad(b.lat))*Math.sin(dLng/2)**2;
      return 2*R*Math.atan2(Math.sqrt(s),Math.sqrt(1-s));
    }

    function safeTimeMs(t){
      if(!t) return null;
      const ms=Date.parse(String(t).replace(' ','T'));
      return Number.isNaN(ms) ? null : ms;
    }

    function cleanPoints(raw){
      const pts=(raw||[]).map(p=>({lat:+p.lat,lng:+p.lng,t:p.t||null,speed:+(p.speed||0)}));
      if(pts.length<2) return pts;
      const out=[]; let prev=null, prevKey=null;
      for(const p of pts){
        const key=p.lat.toFixed(6)+','+p.lng.toFixed(6);
        if(key===prevKey) continue; prevKey=key;
        if(!prev){ out.push(p); prev=p; continue; }
        const d=haversineMeters(prev,p);
        if(d<2) continue;
        const t1=safeTimeMs(prev.t), t2=safeTimeMs(p.t);
        if(t1!=null&&t2!=null){
          const dt=Math.abs(t2-t1)/1000;
          if(dt>0){ const v=(d/dt)*3.6; if(v>170) continue; if(d>600&&dt<=8) continue; }
          else { if(d>600) continue; }
        } else { if(d>1200) continue; }
        out.push(p); prev=p;
      }
      return out;
    }

    function splitSegments(points){
      const segs=[]; if(points.length<2) return segs;
      let seg=[points[0]];
      for(let i=1;i<points.length;i++){
        const d=haversineMeters(points[i-1],points[i]);
        if(d>350){ if(seg.length>=2) segs.push(seg); seg=[points[i]]; } else seg.push(points[i]);
      }
      if(seg.length>=2) segs.push(seg);
      return segs;
    }

    function prepareTrack(tr){
      const pts=cleanPoints(tr.points||[]);
      return {...tr, __pts:pts, __segments:splitSegments(pts)};
    }

    const tracks=(tracksRaw||[]).map(prepareTrack);

    /* ── Draw tracks ──────────────────────────────────── */
    const boundsAll=new google.maps.LatLngBounds();

    function circleIcon(fill){
      return { path:google.maps.SymbolPath.CIRCLE, fillColor:fill, fillOpacity:1, strokeColor:"#fff", strokeWeight:2, scale:9 };
    }

    tracks.forEach(tr=>{
      const isFocus = focusId && String(tr.trajet_id)===String(focusId);
      const segs = isFocus ? [tr.__pts||[]] : (tr.__segments||[]);
      if(!segs.length) return;
      segs.forEach(seg=>{
        if(seg.length<2) return;
        const path=seg.map(p=>({lat:p.lat,lng:p.lng}));
        path.forEach(p=>boundsAll.extend(p));
        new google.maps.Polyline({ path, strokeColor:primary, strokeOpacity:isFocus?1:.82, strokeWeight:isFocus?7:4, geodesic:true, map });
        if(isFocus){
          new google.maps.Marker({ position:path[0], map, icon:circleIcon("#16a34a"), label:{text:"D",color:"#fff",fontWeight:"800"} });
          new google.maps.Marker({ position:path[path.length-1], map, icon:circleIcon("#dc2626"), label:{text:"A",color:"#fff",fontWeight:"800"} });
        }
      });
    });

    if(!boundsAll.isEmpty()){
      map.fitBounds(boundsAll, 40);
      google.maps.event.addListenerOnce(map,"idle",()=>{ if(map.getZoom()>18) map.setZoom(18); });
    }

    /* ── Replay ───────────────────────────────────────── */
    let currentPoints=[], idx=0, timer=null, marker=null, trail=null;
    const speedSteps=[1,2,4,6,8,12,16];
    let speedIndex=0;
    const speedMult=()=>speedSteps[speedIndex]||1;

    function updateSpeedUI(){ if(rpSpeed) rpSpeed.textContent=`x${speedMult()}`; }
    updateSpeedUI();

    function ensureReplay(){
      if(marker) return;
      marker=new google.maps.Marker({ map, position:map.getCenter(), title:"Replay",
        icon:{ path:google.maps.SymbolPath.CIRCLE, fillColor:primary, fillOpacity:1, strokeColor:"#fff", strokeWeight:2, scale:8 } });
      trail=new google.maps.Polyline({ map, path:[], strokeColor:primary, strokeOpacity:.9, strokeWeight:5, geodesic:true });
    }

    function pause(){ window.__replayPlaying=false; if(timer){ clearInterval(timer); timer=null; } }

    function stop(){
      pause(); idx=0;
      if(trail) trail.setPath([]);
      if(rpBar) rpBar.style.width='0%';
      if(rpTime) rpTime.textContent='—';
      if(rpCoords) rpCoords.textContent='—';
      if(rpV) rpV.textContent='—';
    }

    function stepTo(i){
      if(!currentPoints.length) return;
      idx=Math.max(0,Math.min(currentPoints.length-1,i));
      const p=currentPoints[idx];
      ensureReplay();
      const pos=new google.maps.LatLng(p.lat,p.lng);
      marker.setPosition(pos);
      map.panTo(pos);
      const path=trail.getPath();
      path.push(pos);
      if(path.getLength()>2500) path.removeAt(0);
      if(rpTime) rpTime.textContent=p.t||'—';
      if(rpCoords) rpCoords.textContent=`Lat ${p.lat.toFixed(6)} • Lng ${p.lng.toFixed(6)}`;
      if(rpV) rpV.textContent=`${Number(p.speed||0).toFixed(1)} km/h`;
      const pct=(idx/Math.max(1,currentPoints.length-1))*100;
      if(rpBar) rpBar.style.width=`${pct.toFixed(2)}%`;
    }

    function tick(){ const step=Math.max(1,Math.round(speedMult()/2)); stepTo(idx+step); if(idx>=currentPoints.length-1) pause(); }

    function play(){
      if(!currentPoints.length) return;
      window.__replayPlaying=true;
      if(panelReplay) panelReplay.style.display='none';
      if(timer) clearInterval(timer);
      timer=setInterval(tick, 220);
    }

    function selectTrackForReplay(tr){
      currentPoints=(tr.__pts||[]).slice();
      if(currentPoints.length<2) return;
      stop(); stepTo(0);
      if(panelReplay){ panelReplay.style.display='block'; panelReplay.__schedule&&panelReplay.__schedule(1400); }
    }

    const defaultTrack = focusId ? tracks.find(x=>String(x.trajet_id)===String(focusId)) : (tracks[0]||null);
    if(defaultTrack) selectTrackForReplay(defaultTrack);

    rpPlay?.addEventListener('click',  ()=>play());
    rpPause?.addEventListener('click', ()=>pause());
    rpStop?.addEventListener('click',  ()=>stop());
    rpPrev?.addEventListener('click',  ()=>{ pause(); stepTo(idx-30); });
    rpNext?.addEventListener('click',  ()=>{ pause(); stepTo(idx+30); });
    rpSlow?.addEventListener('click',  ()=>{ speedIndex=Math.max(0,speedIndex-1); updateSpeedUI(); });
    rpFast?.addEventListener('click',  ()=>{ speedIndex=Math.min(speedSteps.length-1,speedIndex+1); updateSpeedUI(); });

  }catch(e){ console.error('[Trajets] bootMap crash:', e); }
};
</script>
@endpush
@endsection