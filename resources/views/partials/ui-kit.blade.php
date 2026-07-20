{{--
    Système de composants partagé Fleetra.

    Rôle : une seule source de vérité pour les cartes, badges, tuiles KPI,
    tableaux, filtres de période et barres de recherche utilisés sur tout le
    site partenaire. Avant ce fichier, chaque page réimplémentait ses propres
    styles dans un bloc <style> local — même palette de couleurs (héritée des
    variables --color-* globales de layouts/app.blade.php), mais des
    composants visuellement différents d'une page à l'autre.

    Inclus une seule fois dans layouts/app.blade.php, avant @stack('styles') :
    toute page peut donc utiliser ces classes directement, et une page peut
    toujours surcharger une règle précise via son propre @push('styles'),
    qui se rend après ce bloc dans le <head>.

    Toutes les couleurs référencent les variables --color-* définies dans
    layouts/app.blade.php (clair/sombre) avec une valeur de repli, jamais de
    couleur en dur — c'est ce qui garantit la cohérence avec le thème actif.
--}}
<style>
/* ── Page header (bandeau de titre) ─────────────────────────────────── */
.ui-page { padding: .65rem 1rem 1rem; }
.ui-page-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
    margin: 0 0 .55rem;
}
.ui-page-title h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: .45rem;
    font-family: var(--font-display, system-ui);
    font-size: 1.12rem;
    font-weight: 850;
    color: var(--color-text, #111827);
}
.ui-page-title h1 i { color: var(--color-primary, #f58220); }
.ui-page-title p {
    margin: .16rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}
.ui-scope-chip {
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

/* ── Alertes de page ─────────────────────────────────────────────────── */
.ui-alert {
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
.ui-alert.error { color: var(--color-error, #dc2626); }

/* ── Recherche globale de page ───────────────────────────────────────── */
.ui-search-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .7rem;
    padding: .55rem .7rem;
    margin-bottom: .6rem;
}
.ui-search-field { position: relative; flex: 1; min-width: 240px; }
.ui-search-field i {
    position: absolute;
    left: .78rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}
.ui-search-input {
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
.ui-search-input:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.14);
}

/* ── Filtre de période ───────────────────────────────────────────────── */
.ui-period-chip {
    white-space: nowrap;
    border-radius: 999px;
    background: var(--color-bg-subtle, rgba(148,163,184,.10));
    color: var(--color-secondary-text, #6b7280);
    font-size: .68rem;
    font-weight: 800;
    padding: .42rem .65rem;
}
.ui-period-bar {
    display: flex;
    flex-direction: column;
    gap: .55rem;
    padding: .65rem .75rem;
    margin-bottom: .65rem;
}
.ui-period-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .65rem;
    flex-wrap: wrap;
}
.ui-period-select-wrap { display: flex; align-items: center; gap: .4rem; flex: 0 0 auto; }
.ui-period-select {
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
.ui-period-select:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.14);
}
.ui-date-filter-group {
    display: none;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    width: 100%;
}
.ui-date-filter-group.is-visible { display: flex; }
.ui-date-filter-group input[type="date"] {
    height: 34px;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .7rem;
    padding: 0 .55rem;
    font-size: .7rem;
    color: var(--color-text, #111827);
    background: var(--color-card, #fff);
}
.ui-date-filter-group .ui-date-label {
    font-size: .64rem;
    font-weight: 850;
    color: var(--color-secondary-text, #6b7280);
}
.ui-filter-submit {
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

/* ── Carte de base ───────────────────────────────────────────────────── */
.ui-card {
    background: var(--color-card, #fff);
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: 1rem;
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.06));
}
.ui-card-pad { padding: .75rem; }
.ui-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .65rem;
    margin-bottom: .65rem;
}
.ui-card-title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: .4rem;
    font-family: var(--font-display, system-ui);
    font-size: .88rem;
    font-weight: 850;
}
.ui-card-title i { color: var(--color-primary, #f58220); }
.ui-card-subtitle {
    margin: .15rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .65rem;
}
.ui-card-actions { display: flex; align-items: center; gap: .45rem; margin-left: auto; }

/* ── Recherche locale d'un bloc ──────────────────────────────────────── */
.ui-block-search-field { position: relative; width: min(260px, 34vw); }
.ui-block-search-field i {
    position: absolute;
    left: .68rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-secondary-text, #6b7280);
    font-size: .68rem;
}
.ui-block-search-input {
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
.ui-block-search-input:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.12);
}

/* ── Tuiles KPI ──────────────────────────────────────────────────────── */
.ui-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .5rem;
    margin-bottom: .65rem;
}
.ui-kpi {
    min-height: 82px;
    padding: .58rem .7rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .45rem;
    overflow: hidden;
}
.ui-kpi-highlight {
    grid-column: span 2;
    min-height: auto;
    border-color: rgba(22,163,74,.35);
    background: linear-gradient(135deg, rgba(22,163,74,.07), var(--color-card, #fff) 65%);
}
.ui-kpi-highlight .ui-kpi-note { white-space: normal; }
@media (max-width: 640px) { .ui-kpi-highlight { grid-column: span 1; } }
.ui-kpi-label {
    margin: 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    font-weight: 800;
    white-space: nowrap;
}
.ui-kpi-value {
    margin: .1rem 0 0;
    font-family: var(--font-display, system-ui);
    font-size: 1.02rem;
    font-weight: 900;
    white-space: nowrap;
    color: var(--color-text, #111827);
}
.ui-kpi-value.success { color: var(--color-success, #16a34a); }
.ui-kpi-value.danger { color: var(--color-error, #dc2626); }
.ui-kpi-value.warning { color: var(--color-warning, #d97706); }
.ui-kpi-note {
    margin-top: .22rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 700;
}
.ui-kpi-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    border-radius: .75rem;
    flex-shrink: 0;
}
.ui-kpi-icon.green { background: rgba(22,163,74,.12); color: #16a34a; }
.ui-kpi-icon.red { background: rgba(220,38,38,.12); color: #dc2626; }
.ui-kpi-icon.orange { background: rgba(245,130,32,.13); color: #f58220; }
.ui-kpi-icon.blue { background: rgba(37,99,235,.12); color: #2563eb; }
.ui-kpi-icon.grey { background: rgba(107,114,128,.12); color: #6b7280; }

/* ── Grilles de mise en page ─────────────────────────────────────────── */
.ui-page-grid, .ui-analysis-grid, .ui-table-grid, .ui-ops-grid { display: grid; gap: .75rem; }
.ui-analysis-grid { grid-template-columns: 1.45fr 1.05fr .8fr; }
.ui-table-grid { grid-template-columns: 1.15fr 1fr; }
.ui-ops-grid { grid-template-columns: .85fr 1.15fr; }

/* ── Bascule de vue (Barres/Courbe, Liste/Par chauffeur, etc.) ──────────── */
.ui-switch-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .45rem;
}
.ui-switch-title {
    display: flex;
    flex-direction: column;
    gap: .08rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .64rem;
    font-weight: 800;
}
.ui-switch-title strong { color: var(--color-text, #0f172a); font-size: .78rem; }
.ui-mode-toggle {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    background: var(--color-bg-subtle, #f8fafc);
    border: 1px solid var(--color-border-subtle, #e2e8f0);
    border-radius: 999px;
    padding: .2rem;
    flex-shrink: 0;
}
.ui-mode-btn {
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
.ui-mode-btn.active {
    background: var(--color-card, #ffffff);
    color: var(--color-primary, #f58220);
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(15,23,42,.08));
}
.ui-chart-wrap { height: 245px; position: relative; }

/* ── Progression par type (barres de collecte, etc.) ─────────────────── */
.ui-progress-list { display: grid; gap: .55rem; max-height: 300px; overflow-y: auto; padding-right: .2rem; }
.ui-progress-row {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .8rem;
    padding: .55rem;
}
.ui-progress-top { display: flex; justify-content: space-between; gap: .65rem; align-items: baseline; margin-bottom: .38rem; }
.ui-progress-name { min-width: 0; font-weight: 900; font-size: .72rem; color: var(--color-text, #111827); }
.ui-progress-kind {
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.ui-progress-rate { font-weight: 900; color: var(--color-success, #16a34a); font-size: .74rem; white-space: nowrap; }
.ui-progress-track { height: 9px; border-radius: 999px; background: rgba(148,163,184,.20); overflow: hidden; }
.ui-progress-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--color-primary, #f58220), var(--color-success, #16a34a));
}
.ui-progress-bottom {
    display: flex;
    justify-content: space-between;
    gap: .65rem;
    margin-top: .35rem;
    font-size: .62rem;
    color: var(--color-secondary-text, #6b7280);
    font-weight: 700;
}

/* ── Mini-tuiles métriques (2 colonnes) ──────────────────────────────── */
.ui-metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.ui-metric-tile {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .85rem;
    padding: .62rem;
}
.ui-metric-tile span {
    display: block;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.ui-metric-tile strong {
    display: block;
    margin-top: .16rem;
    font-size: 1.08rem;
    font-weight: 900;
    color: var(--color-text, #111827);
}

/* ── Résumé en tuiles (3-4 colonnes, ex. ardoise, paiements) ─────────── */
.ui-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; margin-bottom: .7rem; }
.ui-summary-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.ui-summary-tile {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .85rem;
    padding: .62rem .7rem;
}
.ui-summary-tile span {
    display: block;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.ui-summary-tile strong {
    display: block;
    margin-top: .18rem;
    font-family: var(--font-display, system-ui);
    font-size: 1.08rem;
    font-weight: 900;
    color: var(--color-text, #111827);
}
.ui-summary-tile.success strong { color: var(--color-success, #16a34a); }
.ui-summary-tile.danger strong { color: var(--color-error, #dc2626); }
.ui-summary-tile.warning strong { color: var(--color-warning, #d97706); }
@media (max-width: 900px) {
    .ui-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ui-summary-grid.cols-3 { grid-template-columns: 1fr; }
}

/* ── Tableaux ────────────────────────────────────────────────────────── */
.ui-table-scroll { overflow: auto; max-height: 420px; }
.ui-timeline-scroll { max-height: 420px; overflow-y: auto; padding-right: .2rem; }
.ui-table { width: 100%; min-width: 660px; border-collapse: collapse; }
.ui-table th, .ui-table td {
    border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);
    padding: .55rem .45rem;
    text-align: left;
    white-space: nowrap;
}
.ui-table th {
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 900;
}
.ui-table td { font-size: .69rem; }
.ui-row-title { font-weight: 900; }
.ui-row-muted { display: block; margin-top: .08rem; font-size: .58rem; color: var(--color-secondary-text, #6b7280); }
.ui-amount-success { color: var(--color-success, #16a34a); font-weight: 900; }
.ui-amount-danger { color: var(--color-error, #dc2626); font-weight: 900; }

/* ── Badges de statut ────────────────────────────────────────────────── */
.ui-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: .22rem .48rem;
    font-size: .6rem;
    font-weight: 900;
    white-space: nowrap;
}
.ui-badge.success { background: rgba(22,163,74,.12); color: #16a34a; }
.ui-badge.danger { background: rgba(220,38,38,.12); color: #dc2626; }
.ui-badge.warning { background: rgba(217,119,6,.14); color: #d97706; }
.ui-badge.info { background: rgba(37,99,235,.12); color: #2563eb; }
.ui-badge.primary { background: rgba(245,130,32,.13); color: #f58220; }
.ui-badge.muted { background: rgba(107,114,128,.12); color: #6b7280; }

/* ── Timeline (fil d'actions/évènements) ─────────────────────────────── */
.ui-timeline { display: grid; gap: .5rem; }
.ui-timeline-item {
    display: grid;
    grid-template-columns: 34px 1fr auto;
    gap: .55rem;
    align-items: center;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .8rem;
    padding: .55rem;
}
.ui-timeline-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: .75rem;
    background: rgba(245,130,32,.13);
    color: var(--color-primary, #f58220);
}
.ui-timeline-title { margin: 0; font-size: .72rem; font-weight: 900; }
.ui-timeline-desc { margin: .1rem 0 0; font-size: .62rem; color: var(--color-secondary-text, #6b7280); }

/* ── État vide ───────────────────────────────────────────────────────── */
.ui-empty-state {
    border: 1px dashed var(--color-border-subtle, #e5e7eb);
    border-radius: .85rem;
    padding: .85rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .7rem;
    text-align: center;
}

/* ── Carte accentuée (ex. ardoise globale, bloc mis en avant) ────────── */
.ui-accent-card { border-left: 3px solid var(--color-primary, #f58220); margin-bottom: .75rem; }
.ui-accent-head-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .65rem;
    flex-wrap: wrap;
    margin-bottom: .7rem;
}
.ui-accent-pill {
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

/* ── Boutons d'action génériques ─────────────────────────────────────── */
.ui-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    height: 38px;
    padding: 0 1rem;
    border-radius: .75rem;
    border: 1px solid transparent;
    font-size: .74rem;
    font-weight: 850;
    cursor: pointer;
    white-space: nowrap;
}
.ui-btn-primary { background: var(--color-primary, #f58220); color: #fff; }
.ui-btn-primary:hover { background: var(--color-primary-hover, #e07318); }
.ui-btn-outline {
    background: var(--color-card, #fff);
    border-color: var(--color-border-subtle, #e5e7eb);
    color: var(--color-text, #111827);
}
.ui-btn-outline:hover { border-color: var(--color-primary, #f58220); color: var(--color-primary, #f58220); }
.ui-btn-danger { background: var(--color-error, #dc2626); color: #fff; }

/* ── Répartitions génériques ─────────────────────────────────────────── */
@media (max-width: 1600px) {
    .ui-analysis-grid { grid-template-columns: 1fr; }
}
@media (max-width: 1100px) {
    .ui-table-grid, .ui-ops-grid { grid-template-columns: 1fr; }
}
@media (max-width: 760px) {
    .ui-page { padding: .55rem; }
    .ui-kpi-grid { grid-template-columns: 1fr; }
    .ui-search-card { align-items: stretch; flex-direction: column; }
    .ui-metric-grid { grid-template-columns: 1fr; }
}
</style>
