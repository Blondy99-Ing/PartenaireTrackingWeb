{{--
    Système de composants partagé Fleetra — deuxième couche.

    Important : layouts/app.blade.php définit déjà un premier système
    global (.ui-card, .ui-table, .ui-table-container, .btn-primary,
    .btn-secondary, .ui-input-style — section "UI CARDS"/"BOUTONS"/
    "TABLEAUX"). Ce fichier ne le remplace PAS et ne redéfinit AUCUNE de
    ces classes : il ajoute les composants qui manquaient (badges de
    statut, tuiles KPI, timeline, résumés en tuiles, bascules de vue,
    filtres de période) sous un préfixe `.dash-` qui n'entre en collision
    avec rien d'existant. Pour les cartes/tableaux/boutons, les pages
    doivent continuer à utiliser .ui-card/.ui-table/.btn-primary.

    Rôle : avant ce fichier, le dashboard recouvrement (le plus abouti
    visuellement) avait sa propre copie de ces composants dans un bloc
    <style> local, non réutilisable par les autres pages. Ce fichier les
    rend disponibles partout, sans dupliquer le CSS page par page.

    Inclus une seule fois dans layouts/app.blade.php, avant @stack('styles') :
    une page peut toujours surcharger une règle précise via son propre
    @push('styles'), qui se rend après ce bloc dans le <head>.

    Toutes les couleurs référencent les variables --color-* définies dans
    layouts/app.blade.php (clair/sombre) avec une valeur de repli, jamais
    de couleur en dur — c'est ce qui garantit la cohérence avec le thème
    actif.
--}}
<style>
/* ── Bandeau de titre de page ────────────────────────────────────────── */
.dash-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
    margin: 0 0 .55rem;
}
.dash-title h1 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: .45rem;
    font-family: var(--font-display, system-ui);
    font-size: 1.12rem;
    font-weight: 850;
    color: var(--color-text, #111827);
}
.dash-title h1 i { color: var(--color-primary, #f58220); }
.dash-title p {
    margin: .16rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}
.dash-scope-chip {
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
.dash-alert {
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
.dash-alert.error { color: var(--color-error, #dc2626); }

/* ── Recherche globale de page ───────────────────────────────────────── */
.dash-search-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .7rem;
    padding: .55rem .7rem;
    margin-bottom: .6rem;
}
.dash-search-field { position: relative; flex: 1; min-width: 240px; }
.dash-search-field i {
    position: absolute;
    left: .78rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-secondary-text, #6b7280);
    font-size: .72rem;
}
.dash-search-input {
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
.dash-search-input:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.14);
}

/* ── Filtre de période ───────────────────────────────────────────────── */
.dash-period-chip {
    white-space: nowrap;
    border-radius: 999px;
    background: var(--color-bg-subtle, rgba(148,163,184,.10));
    color: var(--color-secondary-text, #6b7280);
    font-size: .68rem;
    font-weight: 800;
    padding: .42rem .65rem;
}
.dash-period-bar {
    display: flex;
    flex-direction: column;
    gap: .55rem;
    padding: .65rem .75rem;
    margin-bottom: .65rem;
}
.dash-period-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .65rem;
    flex-wrap: wrap;
}
.dash-period-select-wrap { display: flex; align-items: center; gap: .4rem; flex: 0 0 auto; }
.dash-period-select {
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
.dash-period-select:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.14);
}
.dash-date-filter-group {
    display: none;
    align-items: center;
    gap: .35rem;
    flex-wrap: wrap;
    justify-content: flex-end;
    width: 100%;
}
.dash-date-filter-group.is-visible { display: flex; }
.dash-date-filter-group input[type="date"] {
    height: 34px;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: .7rem;
    padding: 0 .55rem;
    font-size: .7rem;
    color: var(--color-text, #111827);
    background: var(--color-card, #fff);
}
.dash-date-filter-group .dash-date-label {
    font-size: .64rem;
    font-weight: 850;
    color: var(--color-secondary-text, #6b7280);
}
.dash-filter-submit {
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

/* ── Carte "dashboard" (variante nue de .ui-card, sans padding forcé) ── */
/* Utiliser .ui-card (existant) pour une carte classique avec padding
   automatique. Utiliser .dash-card quand le padding doit être ajouté
   sélectivement via .dash-card-pad (ex. carte contenant un tableau qui
   doit toucher les bords). */
.dash-card {
    background: var(--color-card, #fff);
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    border-radius: 1rem;
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(0,0,0,.06));
}
.dash-card-pad { padding: .75rem; }
.dash-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .65rem;
    margin-bottom: .65rem;
}
.dash-card-title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: .4rem;
    font-family: var(--font-display, system-ui);
    font-size: .88rem;
    font-weight: 850;
}
.dash-card-title i { color: var(--color-primary, #f58220); }
.dash-card-subtitle {
    margin: .15rem 0 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .65rem;
}
.dash-card-actions { display: flex; align-items: center; gap: .45rem; margin-left: auto; }

/* ── Recherche locale d'un bloc ──────────────────────────────────────── */
.dash-block-search-field { position: relative; width: min(260px, 34vw); }
.dash-block-search-field i {
    position: absolute;
    left: .68rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-secondary-text, #6b7280);
    font-size: .68rem;
}
.dash-block-search-input {
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
.dash-block-search-input:focus {
    border-color: var(--color-primary, #f58220);
    box-shadow: 0 0 0 3px rgba(245,130,32,.12);
}

/* ── Tuiles KPI ──────────────────────────────────────────────────────── */
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .5rem;
    margin-bottom: .65rem;
}
.dash-kpi {
    min-height: 82px;
    padding: .58rem .7rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .45rem;
    overflow: hidden;
}
.dash-kpi-highlight {
    grid-column: span 2;
    min-height: auto;
    border-color: rgba(22,163,74,.35);
    background: linear-gradient(135deg, rgba(22,163,74,.07), var(--color-card, #fff) 65%);
}
.dash-kpi-highlight .dash-kpi-note { white-space: normal; }
@media (max-width: 640px) { .dash-kpi-highlight { grid-column: span 1; } }
.dash-kpi-label {
    margin: 0;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    font-weight: 800;
    white-space: nowrap;
}
.dash-kpi-value {
    margin: .1rem 0 0;
    font-family: var(--font-display, system-ui);
    font-size: 1.02rem;
    font-weight: 900;
    white-space: nowrap;
    color: var(--color-text, #111827);
}
.dash-kpi-value.success { color: var(--color-success, #16a34a); }
.dash-kpi-value.danger { color: var(--color-error, #dc2626); }
.dash-kpi-value.warning { color: var(--color-warning, #d97706); }
.dash-kpi-note {
    margin-top: .22rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 700;
}
.dash-kpi-icon {
    width: 35px;
    height: 35px;
    display: grid;
    place-items: center;
    border-radius: .75rem;
    flex-shrink: 0;
}
.dash-kpi-icon.green { background: rgba(22,163,74,.12); color: #16a34a; }
.dash-kpi-icon.red { background: rgba(220,38,38,.12); color: #dc2626; }
.dash-kpi-icon.orange { background: rgba(245,130,32,.13); color: #f58220; }
.dash-kpi-icon.blue { background: rgba(37,99,235,.12); color: #2563eb; }
.dash-kpi-icon.grey { background: rgba(107,114,128,.12); color: #6b7280; }

/* ── Grilles de mise en page ─────────────────────────────────────────── */
.dash-page-grid, .dash-analysis-grid, .dash-table-grid, .dash-ops-grid { display: grid; gap: .75rem; }
.dash-analysis-grid { grid-template-columns: 1.45fr 1.05fr .8fr; }
.dash-table-grid { grid-template-columns: 1.15fr 1fr; }
.dash-ops-grid { grid-template-columns: .85fr 1.15fr; }

/* ── Bascule de vue (Barres/Courbe, Liste/Par chauffeur, etc.) ──────────── */
.dash-switch-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    margin-bottom: .45rem;
}
.dash-switch-title {
    display: flex;
    flex-direction: column;
    gap: .08rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .64rem;
    font-weight: 800;
}
.dash-switch-title strong { color: var(--color-text, #0f172a); font-size: .78rem; }
.dash-mode-toggle {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    background: var(--color-bg-subtle, #f8fafc);
    border: 1px solid var(--color-border-subtle, #e2e8f0);
    border-radius: 999px;
    padding: .2rem;
    flex-shrink: 0;
}
.dash-mode-btn {
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
.dash-mode-btn.active {
    background: var(--color-card, #ffffff);
    color: var(--color-primary, #f58220);
    box-shadow: var(--shadow-sm, 0 1px 2px rgba(15,23,42,.08));
}
.dash-chart-wrap { height: 245px; position: relative; }

/* ── Progression par type (barres de collecte, etc.) ─────────────────── */
.dash-progress-list { display: grid; gap: .55rem; max-height: 300px; overflow-y: auto; padding-right: .2rem; }
.dash-progress-row {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .8rem;
    padding: .55rem;
}
.dash-progress-top { display: flex; justify-content: space-between; gap: .65rem; align-items: baseline; margin-bottom: .38rem; }
.dash-progress-name { min-width: 0; font-weight: 900; font-size: .72rem; color: var(--color-text, #111827); }
.dash-progress-kind {
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.dash-progress-rate { font-weight: 900; color: var(--color-success, #16a34a); font-size: .74rem; white-space: nowrap; }
.dash-progress-track { height: 9px; border-radius: 999px; background: rgba(148,163,184,.20); overflow: hidden; }
.dash-progress-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--color-primary, #f58220), var(--color-success, #16a34a));
}
.dash-progress-bottom {
    display: flex;
    justify-content: space-between;
    gap: .65rem;
    margin-top: .35rem;
    font-size: .62rem;
    color: var(--color-secondary-text, #6b7280);
    font-weight: 700;
}

/* ── Mini-tuiles métriques (2 colonnes) ──────────────────────────────── */
.dash-metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.dash-metric-tile {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .85rem;
    padding: .62rem;
}
.dash-metric-tile span {
    display: block;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.dash-metric-tile strong {
    display: block;
    margin-top: .16rem;
    font-size: 1.08rem;
    font-weight: 900;
    color: var(--color-text, #111827);
}

/* ── Résumé en tuiles (3-4 colonnes, ex. ardoise, paiements) ─────────── */
.dash-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .5rem; margin-bottom: .7rem; }
.dash-summary-grid.cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.dash-summary-tile {
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .85rem;
    padding: .62rem .7rem;
}
.dash-summary-tile span {
    display: block;
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.dash-summary-tile strong {
    display: block;
    margin-top: .18rem;
    font-family: var(--font-display, system-ui);
    font-size: 1.08rem;
    font-weight: 900;
    color: var(--color-text, #111827);
}
.dash-summary-tile.success strong { color: var(--color-success, #16a34a); }
.dash-summary-tile.danger strong { color: var(--color-error, #dc2626); }
.dash-summary-tile.warning strong { color: var(--color-warning, #d97706); }
@media (max-width: 900px) {
    .dash-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dash-summary-grid.cols-3 { grid-template-columns: 1fr; }
}

/* ── Tableaux "dashboard" (variante compacte de .ui-table) ──────────── */
/* Utiliser .ui-table (existant) pour un tableau de gestion classique.
   Utiliser .dash-table quand une densité plus compacte est voulue (ex.
   listes longues avec beaucoup de colonnes numériques). */
.dash-table-scroll { overflow: auto; max-height: 420px; }
.dash-timeline-scroll { max-height: 420px; overflow-y: auto; padding-right: .2rem; }
.dash-table { width: 100%; min-width: 660px; border-collapse: collapse; }
.dash-table th, .dash-table td {
    border-bottom: 1px solid var(--color-border-subtle, #e5e7eb);
    padding: .55rem .45rem;
    text-align: left;
    white-space: nowrap;
}
.dash-table th {
    color: var(--color-secondary-text, #6b7280);
    font-size: .58rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 900;
}
.dash-table td { font-size: .69rem; }
.dash-row-title { font-weight: 900; }
.dash-row-muted { display: block; margin-top: .08rem; font-size: .58rem; color: var(--color-secondary-text, #6b7280); }
.dash-amount-success { color: var(--color-success, #16a34a); font-weight: 900; }
.dash-amount-danger { color: var(--color-error, #dc2626); font-weight: 900; }

/* ── Badges de statut ────────────────────────────────────────────────── */
.dash-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: .22rem .48rem;
    font-size: .6rem;
    font-weight: 900;
    white-space: nowrap;
}
.dash-badge.success { background: rgba(22,163,74,.12); color: #16a34a; }
.dash-badge.danger { background: rgba(220,38,38,.12); color: #dc2626; }
.dash-badge.warning { background: rgba(217,119,6,.14); color: #d97706; }
.dash-badge.info { background: rgba(37,99,235,.12); color: #2563eb; }
.dash-badge.primary { background: rgba(245,130,32,.13); color: #f58220; }
.dash-badge.muted { background: rgba(107,114,128,.12); color: #6b7280; }

/* ── Timeline (fil d'actions/évènements) ─────────────────────────────── */
.dash-timeline { display: grid; gap: .5rem; }
.dash-timeline-item {
    display: grid;
    grid-template-columns: 34px 1fr auto;
    gap: .55rem;
    align-items: center;
    border: 1px solid var(--color-border-subtle, #e5e7eb);
    background: var(--color-bg, #f8fafc);
    border-radius: .8rem;
    padding: .55rem;
}
.dash-timeline-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: .75rem;
    background: rgba(245,130,32,.13);
    color: var(--color-primary, #f58220);
}
.dash-timeline-title { margin: 0; font-size: .72rem; font-weight: 900; }
.dash-timeline-desc { margin: .1rem 0 0; font-size: .62rem; color: var(--color-secondary-text, #6b7280); }

/* ── État vide ───────────────────────────────────────────────────────── */
.dash-empty-state {
    border: 1px dashed var(--color-border-subtle, #e5e7eb);
    border-radius: .85rem;
    padding: .85rem;
    color: var(--color-secondary-text, #6b7280);
    font-size: .7rem;
    text-align: center;
}

/* ── Carte accentuée (ex. ardoise globale, bloc mis en avant) ────────── */
.dash-accent-card { border-left: 3px solid var(--color-primary, #f58220); margin-bottom: .75rem; }
.dash-accent-head-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .65rem;
    flex-wrap: wrap;
    margin-bottom: .7rem;
}
.dash-accent-pill {
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

/* ── Répartitions génériques ─────────────────────────────────────────── */
@media (max-width: 1600px) {
    .dash-analysis-grid { grid-template-columns: 1fr; }
}
@media (max-width: 1100px) {
    .dash-table-grid, .dash-ops-grid { grid-template-columns: 1fr; }
}
@media (max-width: 760px) {
    .dash-kpi-grid { grid-template-columns: 1fr; }
    .dash-search-card { align-items: stretch; flex-direction: column; }
    .dash-metric-grid { grid-template-columns: 1fr; }
}
</style>
