@extends('layouts.app')

@section('title', 'Liste des Trajets')

@push('styles')
<style>

/* ════════════════════════════════════════════════════════════════
   TRAJETS INDEX
════════════════════════════════════════════════════════════════ */

/* ── Wrapper plein écran ────────────────────────────────────── */
.trajets-page {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--navbar-h) - var(--kpi-h, 0px) - (var(--sp-xl) * 2) );
    overflow: hidden;
}

/* ── Topbar ─────────────────────────────────────────────────── */
.tj-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 10px var(--sp-xl);
    background: var(--color-card);
    border-bottom: 1px solid var(--color-border-subtle);
    flex-shrink: 0;
    flex-wrap: wrap;
}

.tj-title {
    font-family: var(--font-display);
    font-size: 1rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0;
    white-space: nowrap;
}

.tj-topbar-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.tj-topbar{
  border-top-left-radius: var(--r-lg);
  border-top-right-radius: var(--r-lg);
}

/* ── Pills filtres rapides ──────────────────────────────────── */
.quick-pills {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}

.quick-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: var(--r-pill);
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    font-family: var(--font-display);
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--color-secondary-text);
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
    user-select: none;
}

.quick-pill:hover {
    border-color: var(--color-primary-border);
    color: var(--color-primary);
    background: var(--color-primary-light);
}

.quick-pill.is-active {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
}

/* ── Séparateur vertical ────────────────────────────────────── */
.tj-sep {
    width: 1px;
    height: 20px;
    background: var(--color-border-subtle);
    flex-shrink: 0;
}

/* ── Bouton filtres avancés ─────────────────────────────────── */
.btn-adv {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: var(--r-md);
    border: 1px solid var(--color-border-subtle);
    background: transparent;
    font-family: var(--font-display);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--color-secondary-text);
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
    flex-shrink: 0;
}

.btn-adv:hover,
.btn-adv.is-open {
    border-color: var(--color-primary-border);
    color: var(--color-primary);
    background: var(--color-primary-light);
}

.btn-adv .adv-badge {
    background: var(--color-primary);
    color: #fff;
    border-radius: var(--r-pill);
    padding: 1px 6px;
    font-size: 0.58rem;
    font-weight: 700;
    display: none;
}

.btn-adv.has-filters .adv-badge { display: inline; }

.btn-adv .adv-chevron {
    font-size: 0.55rem;
    transition: transform 0.3s ease;
}

.btn-adv.is-open .adv-chevron { transform: rotate(180deg); }

/* ── Panneau filtres avancés ────────────────────────────────── */
.adv-panel {
    background: var(--color-card);
    border-bottom: 1px solid var(--color-border-subtle);
    flex-shrink: 0;
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease;
}

.adv-panel.is-open { max-height: 120px; }

.adv-inner {
    padding: 10px var(--sp-xl);
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}

.adv-label {
    font-family: var(--font-display);
    font-size: 0.6rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-secondary-text);
    margin-bottom: 3px;
}

.adv-inner input,
.adv-inner select {
    height: 32px;
    font-size: 0.78rem;
    padding: 0 0.5rem;
}

.adv-input-icon {
    position: relative;
}

.adv-input-icon input {
    padding-left: 1.75rem !important;
}

.adv-input-icon i {
    position: absolute;
    left: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 0.65rem;
    color: var(--color-secondary-text);
    pointer-events: none;
}

.adv-actions {
    display: flex;
    gap: 6px;
    align-items: flex-end;
}

/* ── Zone tableau fixe + scroll ─────────────────────────────── */
.tj-table-zone {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    background: var(--color-card);
}

.tj-scroll {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: auto;
    min-height: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--color-border-subtle) transparent;
}

.tj-scroll::-webkit-scrollbar       { width: 5px; height: 5px; }
.tj-scroll::-webkit-scrollbar-thumb { background: var(--color-border-subtle); border-radius: 3px; }

/* ── Tableau ────────────────────────────────────────────────── */
.tj-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--font-body);
    font-size: 0.8rem;
}

.tj-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    font-family: var(--font-display);
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--color-bg-subtle, #e8eaed);
    color: var(--color-secondary-text);
    padding: 9px 14px;
    text-align: left;
    border-bottom: 2px solid var(--color-primary);
    white-space: nowrap;
}

.dark-mode .tj-table thead th { background: #161b22; }

.tj-table tbody tr {
    border-bottom: 1px solid var(--color-border-subtle);
    transition: background 0.1s;
}

.tj-table tbody tr:last-child { border-bottom: none; }
.tj-table tbody tr:hover { background: var(--color-primary-light); }

.tj-table tbody td {
    padding: 8px 14px;
    color: var(--color-text);
    vertical-align: middle;
    white-space: nowrap;
}

.cell-immat {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.84rem;
    color: var(--color-text);
}

.cell-time {
    font-family: var(--font-mono, monospace);
    font-size: 0.75rem;
}

.cell-sub {
    font-size: 0.65rem;
    color: var(--color-secondary-text);
    margin-top: 2px;
}

.cell-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: var(--r-pill);
    font-family: var(--font-display);
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}

.badge-dist { background: rgba(37,99,235,.10);  color: #2563eb; }
.badge-avg  { background: var(--color-warning-bg); color: var(--color-warning); }
.badge-max  { background: var(--color-error-bg);   color: var(--color-error); }

.tj-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--color-secondary-text);
}

.tj-empty i {
    display: block;
    font-size: 2rem;
    opacity: 0.2;
    margin-bottom: 0.5rem;
}

.tj-empty span {
    font-family: var(--font-display);
    font-size: 0.82rem;
}

/* ── Pagination ─────────────────────────────────────────────── */
.tj-pagination {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px var(--sp-xl);
    border-top: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    gap: 1rem;
    flex-wrap: wrap;
}

.tj-pag-info {
    font-family: var(--font-body);
    font-size: 0.72rem;
    color: var(--color-secondary-text);
    white-space: nowrap;
}

/* Override liens Laravel pagination */
.tj-pagination nav > div:first-child { display: none; }

.tj-pagination nav span[aria-current="page"] > span,
.tj-pagination nav a,
.tj-pagination nav span[aria-disabled="true"] > span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 28px;
    padding: 0 7px;
    margin: 0 1px;
    border-radius: var(--r-md);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-card);
    color: var(--color-text);
    font-family: var(--font-display);
    font-size: 0.72rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.12s;
    white-space: nowrap;
}

.tj-pagination nav a:hover {
    background: var(--color-primary-light);
    border-color: var(--color-primary-border);
    color: var(--color-primary);
}

.tj-pagination nav span[aria-current="page"] > span {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
    font-weight: 700;
}

.tj-pagination nav span[aria-disabled="true"] > span {
    opacity: 0.3;
    pointer-events: none;
}

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .adv-inner { grid-template-columns: repeat(3, 1fr) auto; }
    .adv-panel.is-open { max-height: 160px; }
}

@media (max-width: 767px) {
    .adv-inner { grid-template-columns: 1fr 1fr; padding: 10px var(--sp-md); }
    .adv-panel.is-open { max-height: 260px; }
    .adv-actions { grid-column: 1 / -1; justify-content: flex-end; }
    .tj-topbar { padding: 8px var(--sp-md); }
    .tj-sep, .quick-pills { display: none; }
    .tj-pagination { padding: 6px var(--sp-md); }
}

</style>
@endpush

@section('content')
@php
    $quick = request('quick', 'today');

    $hasFilters = !empty(array_filter([
        request('vehicule'),
        request('start_date'),
        request('end_date'),
        request('start_time'),
        request('end_time'),
    ]));

    $quickLinks = [
        'today'     => "Aujourd'hui",
        'yesterday' => 'Hier',
        'week'      => 'Cette semaine',
        'month'     => 'Ce mois',
        'year'      => 'Cette année',
    ];
@endphp

<div class="trajets-page">

    {{-- ════════════════════════════════════════════════════
         TOPBAR
    ════════════════════════════════════════════════════ --}}
    <div class="tj-topbar">

        <h1 class="tj-title">
            <i class="fas fa-route" style="color:var(--color-primary);margin-right:6px;" aria-hidden="true"></i>
            Liste des Trajets
        </h1>

        <div class="tj-topbar-right">

            {{-- Pills rapides --}}
            <div class="quick-pills" role="group" aria-label="Filtres rapides">
                @foreach($quickLinks as $val => $label)
                    <a href="{{ request()->fullUrlWithQuery(['quick' => $val, 'page' => null]) }}"
                       class="quick-pill {{ $quick === $val ? 'is-active' : '' }}"
                       aria-current="{{ $quick === $val ? 'true' : 'false' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <div class="tj-sep"></div>

            {{-- Bouton filtres avancés --}}
            <button class="btn-adv {{ $hasFilters ? 'has-filters is-open' : '' }}"
                    id="btn-adv"
                    type="button"
                    aria-expanded="{{ $hasFilters ? 'true' : 'false' }}"
                    aria-controls="adv-panel">
                <i class="fas fa-sliders-h" aria-hidden="true"></i>
                Filtres
                <span class="adv-badge">actifs</span>
                <i class="fas fa-chevron-down adv-chevron" aria-hidden="true"></i>
            </button>

        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         FILTRES AVANCÉS
    ════════════════════════════════════════════════════ --}}
    <div class="adv-panel {{ $hasFilters ? 'is-open' : '' }}" id="adv-panel" role="region" aria-label="Filtres avancés">
        <form method="GET" class="adv-inner">

            <input type="hidden" name="quick" value="{{ $quick }}">

            <div>
                <div class="adv-label">Véhicule</div>
                <div class="adv-input-icon">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="text" name="vehicule"
                           placeholder="Immatriculation…"
                           value="{{ request('vehicule') }}"
                           class="ui-input-style">
                </div>
            </div>

            <div>
                <div class="adv-label">Date début</div>
                <input type="date" name="start_date" value="{{ request('start_date') }}" class="ui-input-style">
            </div>

            <div>
                <div class="adv-label">Date fin</div>
                <input type="date" name="end_date" value="{{ request('end_date') }}" class="ui-input-style">
            </div>

            <div>
                <div class="adv-label">Heure début</div>
                <input type="time" name="start_time" value="{{ request('start_time') }}" class="ui-input-style">
            </div>

            <div>
                <div class="adv-label">Heure fin</div>
                <input type="time" name="end_time" value="{{ request('end_time') }}" class="ui-input-style">
            </div>

            <div class="adv-actions">
                <a href="{{ route('trajets.index') }}"
                   class="btn-secondary"
                   style="padding:4px 10px;min-height:32px;font-size:0.72rem;"
                   title="Réinitialiser">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </a>
                <button type="submit" class="btn-primary" style="padding:4px 14px;min-height:32px;font-size:0.72rem;">
                    <i class="fas fa-filter" aria-hidden="true"></i> Appliquer
                </button>
            </div>

        </form>
    </div>

    {{-- ════════════════════════════════════════════════════
         TABLEAU FIXE + SCROLL VERTICAL
    ════════════════════════════════════════════════════ --}}
    <div class="tj-table-zone">

        <div class="tj-scroll">
            <table class="tj-table">
                <thead>
                    <tr>
                        <th scope="col">Véhicule</th>
                        <th scope="col">Départ</th>
                        <th scope="col">Arrivée</th>
                        <th scope="col">Distance</th>
                        <th scope="col">Vit. Moy</th>
                        <th scope="col">Vit. Max</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($trajets as $trajet)
                    <tr>
                        <td>
                            <span class="cell-immat">
                                {{ $trajet->voiture->immatriculation ?? 'N/A' }}
                            </span>
                        </td>
                        <td>
                            <div class="cell-time">
                                {{ \Carbon\Carbon::parse($trajet->start_time)->format('d/m/Y H:i') }}
                            </div>
                            <div class="cell-sub">
                                {{ number_format($trajet->start_longitude, 4) }}, {{ number_format($trajet->start_latitude, 4) }}
                            </div>
                        </td>
                        <td>
                            <div class="cell-time">
                                {{ \Carbon\Carbon::parse($trajet->end_time)->format('d/m/Y H:i') }}
                            </div>
                            <div class="cell-sub">
                                {{ number_format($trajet->end_longitude, 4) }}, {{ number_format($trajet->end_latitude, 4) }}
                            </div>
                        </td>
                        <td>
                            <span class="cell-badge badge-dist">
                                <i class="fas fa-road" aria-hidden="true"></i>
                                {{ number_format($trajet->total_distance_km, 2) }} km
                            </span>
                        </td>
                        <td>
                            <span class="cell-badge badge-avg">
                                <i class="fas fa-gauge" aria-hidden="true"></i>
                                {{ number_format($trajet->avg_speed_kmh, 1) }} km/h
                            </span>
                        </td>
                        <td>
                            <span class="cell-badge badge-max">
                                <i class="fas fa-bolt" aria-hidden="true"></i>
                                {{ number_format($trajet->max_speed_kmh, 1) }} km/h
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('voitures.trajet.detail', ['vehicle_id' => $trajet->vehicle_id, 'trajet_id' => $trajet->id] + request()->query()) }}"
                               class="btn-secondary"
                               style="padding:3px 10px;min-height:26px;font-size:0.7rem;"
                               title="Voir sur la carte">
                                <i class="fas fa-map-marked-alt" aria-hidden="true"></i> Carte
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="tj-empty">
                            <i class="fas fa-route" aria-hidden="true"></i>
                            <span>Aucun trajet trouvé pour cette période.</span>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="tj-pagination">
            <div class="tj-pag-info">
                @if($trajets->total() > 0)
                    Trajets {{ $trajets->firstItem() }}–{{ $trajets->lastItem() }}
                    sur <strong>{{ $trajets->total() }}</strong>
                @else
                    Aucun résultat
                @endif
            </div>
            <div>
                {{ $trajets->appends(request()->query())->links() }}
            </div>
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var btn   = document.getElementById('btn-adv');
    var panel = document.getElementById('adv-panel');
    if (!btn || !panel) return;

    // Sync chevron initial si filtres actifs
    if (btn.classList.contains('is-open')) {
        btn.querySelector('.adv-chevron').style.transform = 'rotate(180deg)';
    }

    btn.addEventListener('click', function () {
        var open = panel.classList.toggle('is-open');
        btn.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', String(open));
        btn.querySelector('.adv-chevron').style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
    });

})();
</script>
@endpush