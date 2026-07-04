@extends('layouts.app')

@section('title', 'Paramétrage coupure lease')

@php
    $rows = collect($rows ?? $vehicles ?? []);
    $totalContracts  = $rows->count();
    $activeRules     = $rows->sum(fn ($row) => (int) ($row['enabled_contract_rules_count'] ?? 0));
    $missingTimes    = $rows->sum(fn ($row) => (int) ($row['missing_time_contract_rules_count'] ?? 0));
    $totalRuleLines  = $rows->sum(fn ($row) => count($row['contract_rules'] ?? []));


    /**
     * Options de filtre front-end. Aucune donnée n'est rechargée depuis Recouvrement ici.
     * La page reste alignée avec la logique existante : elle affiche uniquement les contrats
     * et sous-contrats déjà résolus par le contrôleur.
     */
    $filterContractTypes = $rows
        ->flatMap(fn ($row) => collect($row['contract_rules'] ?? [])->map(fn ($rule) => [
            'id' => (int) ($rule['type_contrat_id'] ?? 0),
            'label' => $rule['type_contrat_label'] ?? null,
            'kind' => ($rule['contract_kind'] ?? 'MAIN') === 'SUB' ? 'SUB' : 'MAIN',
        ]))
        ->filter(fn ($type) => trim((string) ($type['label'] ?? '')) !== '' || (int) ($type['id'] ?? 0) > 0)
        ->unique(fn ($type) => ($type['id'] ?: strtolower((string) $type['label'])) . '|' . $type['kind'])
        ->values();

    /**
     * Map des vrais libellés venant de Recouvrement.
     * Cette vue peut recevoir des libellés techniques comme "Type #4"
     * depuis les anciennes règles enregistrées. On les remplace ici par
     * le libellé métier si $contractTypes contient l'id correspondant.
     */
    $contractTypeLabels = collect($contractTypes ?? [])
        ->filter(fn ($type) => is_array($type))
        ->mapWithKeys(function ($type) {
            $id = (int) ($type['id'] ?? $type['type_contrat_id'] ?? $type['type_contrat'] ?? 0);

            $label = trim((string) (
                $type['libelle']
                ?? $type['label']
                ?? $type['nom']
                ?? $type['name']
                ?? ''
            ));

            return $id > 0 && $label !== '' ? [$id => $label] : [];
        });

    $isTechnicalLabel = function (?string $label): bool {
        $label = trim((string) $label);

        return $label === ''
            || (bool) preg_match('/^(type|contrat|sous-contrat)\s*#?\d+$/i', $label)
            || (bool) preg_match('/^#?\d+$/', $label)
            || (bool) preg_match('/^parent\s*#?\d+$/i', $label)
            || (bool) preg_match('/^CTR[-_ ]?\d+$/i', $label);
    };

    $safeTypeLabel = function (?string $label, $typeId = null, bool $isMain = false) use ($contractTypeLabels, $isTechnicalLabel): string {
        $typeId = (int) $typeId;

        if ($typeId > 0 && $contractTypeLabels->has($typeId)) {
            return (string) $contractTypeLabels->get($typeId);
        }

        $label = trim((string) $label);

        if (! $isTechnicalLabel($label)) {
            return $label;
        }

        return $isMain ? 'Contrat principal' : 'Sous-contrat';
    };
@endphp

@push('styles')
<style>
/* ══════════════════════════════════════════════════════════
   TOKENS
══════════════════════════════════════════════════════════ */
:root {
    --lco-r: 16px;
    --lco-r-sm: 10px;
    --lco-r-pill: 100px;
    --lco-gap: 1rem;
    --lco-ease: cubic-bezier(.4,0,.2,1);
    --lco-t: 140ms;
    --lco-primary: var(--color-primary, #f58220);
    --lco-primary-light: rgba(245,130,32,.08);
    --lco-primary-border: rgba(245,130,32,.28);
}

/* ══════════════════════════════════════════════════════════
   PAGE
══════════════════════════════════════════════════════════ */
.lco { display: flex; flex-direction: column; gap: var(--lco-gap); }

/* ══════════════════════════════════════════════════════════
   ALERTS
══════════════════════════════════════════════════════════ */
.lco-alert {
    display: flex;
    align-items: flex-start;
    gap: .6rem;
    padding: .85rem 1rem;
    border-radius: var(--lco-r);
    border: 1px solid transparent;
    font-size: .8rem;
    font-weight: 700;
    line-height: 1.5;
}

.lco-alert i { margin-top: .1rem; flex-shrink: 0; }
.lco-alert ul { margin: .35rem 0 0 1rem; font-weight: 500; }

.lco-alert.success { color: #15803d; background: rgba(22,163,74,.08);  border-color: rgba(22,163,74,.22); }
.lco-alert.error   { color: #b91c1c; background: rgba(220,38,38,.08);  border-color: rgba(220,38,38,.22); }
.lco-alert.warn    { color: #92400e; background: rgba(245,158,11,.08); border-color: rgba(245,158,11,.25); }

/* ══════════════════════════════════════════════════════════
   HERO HEADER
══════════════════════════════════════════════════════════ */
.lco-hero {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--lco-r);
    padding: 1.1rem 1.4rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
}

.lco-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--lco-primary) 0%, transparent 55%);
    opacity: .04;
    pointer-events: none;
}

.lco-hero-icon {
    width: 46px; height: 46px;
    border-radius: 14px;
    background: linear-gradient(135deg, var(--lco-primary), var(--lco-primary-border));
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.15rem;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(245,130,32,.28);
}

.lco-hero-body { flex: 1; min-width: 0; }

.lco-hero-title {
    font-size: .98rem;
    font-weight: 900;
    color: var(--color-text);
    margin: 0 0 .2rem;
    letter-spacing: -.015em;
}

.lco-hero-sub {
    font-size: .76rem;
    color: var(--color-text-muted);
    margin: 0;
    line-height: 1.55;
    max-width: 760px;
}

.lco-hero-chips {
    display: flex;
    gap: .45rem;
    flex-wrap: wrap;
    flex-shrink: 0;
    justify-content: flex-end;
}

.lco-hero-chip {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .32rem .7rem;
    border-radius: var(--lco-r-pill);
    border: 1px solid var(--color-border-subtle);
    background: var(--color-bg, #f8fafc);
    font-size: .7rem;
    font-weight: 700;
    color: var(--color-text-muted);
    white-space: nowrap;
}

/* ══════════════════════════════════════════════════════════
   KPI STRIP
══════════════════════════════════════════════════════════ */
.lco-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .55rem;
}

@media (max-width: 900px) { .lco-kpis { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 520px) { .lco-kpis { grid-template-columns: 1fr; } }

.lco-kpi {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--lco-r);
    padding: .9rem 1rem;
    box-shadow: var(--shadow-xs);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    position: relative;
    overflow: hidden;
    transition: box-shadow var(--lco-t), transform var(--lco-t);
}

.lco-kpi::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--lco-kpi-accent, var(--lco-primary));
    border-radius: var(--lco-r) var(--lco-r) 0 0;
}

.lco-kpi:hover { box-shadow: var(--shadow-sm); transform: translateY(-1px); }

.lco-kpi-label {
    font-size: .6rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: .3rem;
}

.lco-kpi-val {
    font-size: 1.55rem;
    font-weight: 900;
    color: var(--color-text);
    letter-spacing: -.03em;
    line-height: 1;
}

.lco-kpi-icon {
    width: 38px; height: 38px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    flex-shrink: 0;
    background: var(--lco-kpi-icon-bg, var(--lco-primary-light));
    color: var(--lco-kpi-icon-color, var(--lco-primary));
}

/* ══════════════════════════════════════════════════════════
   TOOLBAR (bulk actions)
══════════════════════════════════════════════════════════ */
.lco-toolbar {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--lco-r);
    overflow: hidden;
    box-shadow: var(--shadow-xs);
}

.lco-toolbar-header {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .7rem 1.1rem;
    border-bottom: 1px solid var(--color-border-subtle);
    background: var(--color-bg-subtle, #f9fafb);
    flex-wrap: wrap;
    justify-content: space-between;
}

.dark-mode .lco-toolbar-header { background: rgba(255,255,255,.03); }

.lco-toolbar-title {
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    display: flex;
    align-items: center;
    gap: .4rem;
    white-space: nowrap;
}

.lco-toolbar-body {
    padding: .85rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: .65rem;
}

.lco-toolbar-row {
    display: flex;
    align-items: center;
    gap: .6rem;
    flex-wrap: wrap;
}

/* Inputs */
.lco-search-wrap { position: relative; flex: 1; min-width: 220px; }

.lco-search-icon {
    position: absolute;
    left: .85rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted);
    font-size: .75rem;
    pointer-events: none;
}

.lco-input {
    height: 38px;
    border: 1px solid var(--color-input-border);
    border-radius: var(--lco-r-sm);
    background: var(--color-input-bg);
    color: var(--color-text);
    font-size: .82rem;
    transition: border-color var(--lco-t), box-shadow var(--lco-t);
}

.lco-input:focus {
    outline: none;
    border-color: var(--lco-primary);
    box-shadow: 0 0 0 3px var(--lco-primary-light);
}

.lco-search-input {
    width: 100%;
    padding: 0 .85rem 0 2.25rem;
    font-weight: 500;
}

.lco-time-input { padding: 0 .75rem; min-width: 130px; }
.lco-select { padding: 0 .75rem; min-width: 160px; }

/* Buttons */
.lco-btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    height: 38px;
    padding: 0 .9rem;
    border-radius: var(--lco-r-sm);
    border: 1px solid var(--color-border);
    background: var(--color-card);
    color: var(--color-text);
    font-size: .73rem;
    font-weight: 700;
    cursor: pointer;
    transition: background var(--lco-t), border-color var(--lco-t), color var(--lco-t), transform var(--lco-t);
    text-decoration: none;
    white-space: nowrap;
}

.lco-btn:hover { border-color: var(--lco-primary); color: var(--lco-primary); transform: translateY(-1px); }

.lco-btn.primary {
    background: var(--lco-primary);
    border-color: var(--lco-primary);
    color: #fff;
}

.lco-btn.primary:hover { opacity: .88; transform: translateY(-1px); }

.lco-btn.soft {
    background: var(--lco-primary-light);
    border-color: var(--lco-primary-border);
    color: var(--lco-primary);
}

.lco-btn-icon-only {
    width: 38px;
    padding: 0;
    justify-content: center;
}

/* Divider between bulk groups */
.lco-toolbar-divider {
    width: 1px;
    height: 28px;
    background: var(--color-border-subtle);
    flex-shrink: 0;
}

/* ══════════════════════════════════════════════════════════
   SELECTION BAR
══════════════════════════════════════════════════════════ */
.lco-sel-bar {
    display: none;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
    padding: .7rem 1.1rem;
    background: var(--lco-primary-light);
    border: 1px solid var(--lco-primary-border);
    border-radius: var(--lco-r);
    font-size: .8rem;
    font-weight: 700;
    color: var(--lco-primary);
    box-shadow: var(--shadow-xs);
}

.lco-sel-bar.show { display: flex; }

.lco-sel-count { display: flex; align-items: center; gap: .4rem; }

.lco-sel-actions { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }

/* ══════════════════════════════════════════════════════════
   MAIN FORM CARD
══════════════════════════════════════════════════════════ */
.lco-form-card {
    background: var(--color-card);
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--lco-r);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

/* Table */
.lco-table-wrap { overflow-x: auto; }

.lco-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1080px;
    font-size: .82rem;
}

.lco-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: var(--color-bg-subtle, #f8fafc);
    text-align: left;
    font-size: .62rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    padding: .65rem .9rem;
    border-bottom: 2px solid var(--lco-primary);
}

.dark-mode .lco-table thead th { background: #161b22; }

.lco-table tbody td {
    padding: .9rem .9rem;
    border-bottom: 1px solid var(--color-border-subtle);
    vertical-align: top;
}

.lco-table tbody tr:last-child td { border-bottom: none; }

.lco-row:hover td { background: var(--color-sidebar-active); }
.lco-row.selected td { background: var(--lco-primary-light); }
.lco-row.hidden { display: none; }

/* Row check */
.lco-row-check {
    width: 16px; height: 16px;
    accent-color: var(--lco-primary);
    cursor: pointer;
}

/* ── Vehicle cell ── */
.lco-vehicle {
    display: flex;
    align-items: flex-start;
    gap: .6rem;
}

.lco-vehicle-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: var(--lco-primary-light);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--lco-primary);
    font-size: .8rem;
    flex-shrink: 0;
    margin-top: .1rem;
}

.lco-vehicle-plate {
    font-weight: 900;
    font-size: .9rem;
    color: var(--color-text);
    letter-spacing: -.01em;
}

.lco-vehicle-meta {
    font-size: .71rem;
    color: var(--color-text-muted);
    margin-top: .22rem;
    line-height: 1.55;
}

.lco-vehicle-meta code {
    font-size: .68rem;
    background: var(--color-border-subtle);
    padding: .1rem .3rem;
    border-radius: 5px;
    color: var(--color-text-muted);
}

/* ── Modified indicator ── */
.lco-row.dirty .lco-vehicle-plate::after {
    content: ' ·';
    color: #c2410c;
    font-size: .9rem;
}

/* ── Driver cell ── */
.lco-driver {
    font-size: .8rem;
    color: var(--color-text);
    font-weight: 600;
}

.lco-driver-empty { color: var(--color-text-muted); font-style: italic; }

/* ── Status cell ── */
.lco-status-cell { white-space: nowrap; }

/* Tags */
.lco-tag {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    border-radius: var(--lco-r-pill);
    padding: .25rem .55rem;
    font-size: .64rem;
    font-weight: 700;
    white-space: nowrap;
    border: 1px solid transparent;
}

.lco-tag.ok   { background: rgba(22,163,74,.10);  color: #15803d; border-color: rgba(22,163,74,.25); }
.lco-tag.warn { background: rgba(245,158,11,.12); color: #92400e; border-color: rgba(245,158,11,.3); }
.lco-tag.off  { background: rgba(100,116,139,.1); color: #64748b; border-color: rgba(100,116,139,.2); }
.lco-tag.sub  { background: rgba(59,130,246,.1);  color: #1d4ed8; border-color: rgba(59,130,246,.25); }
.lco-tag.main { background: rgba(22,163,74,.10);  color: #15803d; border-color: rgba(22,163,74,.25); }

.dark-mode .lco-tag.ok   { background: rgba(22,163,74,.18);  color: #6ee7b7; }
.dark-mode .lco-tag.warn { background: rgba(245,158,11,.18); color: #fcd34d; }
.dark-mode .lco-tag.off  { background: rgba(100,116,139,.15);color: #9ca3af; }
.dark-mode .lco-tag.sub  { background: rgba(59,130,246,.18); color: #93c5fd; }
.dark-mode .lco-tag.main { background: rgba(22,163,74,.18);  color: #6ee7b7; }

/* ══════════════════════════════════════════════════════════
   RULE GRID & CARDS
══════════════════════════════════════════════════════════ */
.lco-rule-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(260px, 1fr));
    gap: .65rem;
}

@media (max-width: 980px) { .lco-rule-grid { grid-template-columns: 1fr; } }

.lco-rule-card {
    border: 1px solid var(--color-border-subtle);
    border-radius: var(--lco-r);
    padding: .8rem .9rem;
    background: var(--color-bg-subtle, rgba(248,250,252,.7));
    transition: border-color var(--lco-t), background var(--lco-t), box-shadow var(--lco-t);
}

.lco-rule-card:hover {
    box-shadow: var(--shadow-xs);
}

.lco-rule-card.enabled {
    border-color: rgba(22,163,74,.35);
    background: rgba(22,163,74,.04);
}

.dark-mode .lco-rule-card.enabled {
    border-color: rgba(22,163,74,.3);
    background: rgba(22,163,74,.08);
}

/* Card top: label + toggle */
.lco-rule-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .5rem;
    margin-bottom: .65rem;
}

.lco-rule-name {
    font-weight: 700;
    font-size: .83rem;
    color: var(--color-text);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .35rem;
}

.lco-rule-id {
    font-size: .68rem;
    color: var(--color-text-muted);
    margin-top: .18rem;
    font-family: var(--font-mono, monospace);
}

/* Toggle switch */
.lco-toggle-wrap {
    display: flex;
    align-items: center;
    gap: .4rem;
    flex-shrink: 0;
}

.lco-toggle-label {
    font-size: .7rem;
    font-weight: 700;
    color: var(--color-text-muted);
    white-space: nowrap;
}

/* Fields grid: 4 cols */
.lco-rule-fields {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .45rem;
    padding-top: .6rem;
    border-top: 1px solid var(--color-border-subtle);
}

.lco-field label {
    display: block;
    font-size: .57rem;
    text-transform: uppercase;
    letter-spacing: .07em;
    font-weight: 700;
    color: var(--color-text-muted);
    margin-bottom: .22rem;
}

.lco-field-input {
    width: 100%;
    height: 34px;
    border: 1px solid var(--color-input-border);
    border-radius: var(--lco-r-sm);
    padding: 0 .55rem;
    font-size: .76rem;
    background: var(--color-input-bg);
    color: var(--color-text);
    transition: border-color var(--lco-t);
}

.lco-field-input:focus {
    outline: none;
    border-color: var(--lco-primary);
    box-shadow: 0 0 0 2px var(--lco-primary-light);
}

.lco-check-wrap {
    display: flex;
    align-items: center;
    gap: .35rem;
    margin-top: .3rem;
    font-size: .7rem;
    font-weight: 600;
    color: var(--color-text-muted);
}

.lco-check-wrap input { accent-color: var(--lco-primary); }

/* ══════════════════════════════════════════════════════════
   FORM FOOTER
══════════════════════════════════════════════════════════ */
.lco-form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    padding: .9rem 1.1rem;
    border-top: 1px solid var(--color-border-subtle);
    background: var(--color-bg-subtle, #f9fafb);
}

.dark-mode .lco-form-footer { background: rgba(255,255,255,.02); }

.lco-form-footer-hint {
    font-size: .73rem;
    color: var(--color-text-muted);
    display: flex;
    align-items: flex-start;
    gap: .4rem;
    line-height: 1.5;
}

.lco-form-actions { display: flex; gap: .5rem; flex-wrap: wrap; }

/* ══════════════════════════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════════════════════════ */
.lco-empty {
    padding: 3.5rem 2rem;
    text-align: center;
}

.lco-empty-icon {
    width: 56px; height: 56px;
    border-radius: 18px;
    background: var(--color-bg-subtle, #f3f4f6);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: var(--color-border);
    margin: 0 auto .85rem;
}

.lco-empty-text { font-size: .95rem; font-weight: 800; color: var(--color-text); margin-bottom: .35rem; }
.lco-empty-sub { font-size: .78rem; color: var(--color-text-muted); }
</style>
@endpush

@section('content')
<div class="lco">

    <div class="lco-alert warn">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            Cette page ne crée pas de contrat, de lease ou de paiement. Elle paramètre uniquement les règles Tracking
            applicables aux contrats et sous-contrats déjà reçus depuis Recouvrement. La sécurité de coupure reste obligatoire :
            une règle active ne pourra être exécutée que si le véhicule est à l'arrêt.
        </div>
    </div>


    {{-- ── HERO HEADER ─────────────────────────────────────────── --}}
    <div class="lco-hero">
        <div class="lco-hero-icon">
            <i class="fas fa-shield-halved"></i>
        </div>
        <div class="lco-hero-body">
            <h1 class="lco-hero-title">Paramétrage coupure lease</h1>
            <p class="lco-hero-sub">
                Une coupure automatique est toujours décidée à partir d'un lease impayé, du contrat ou sous-contrat concerné,
                d'une règle active et des conditions de sécurité. Cette page ne modifie jamais les données Recouvrement.
            </p>
        </div>
        <div class="lco-hero-chips">
            <span class="lco-hero-chip">
                <i class="fas fa-file-contract" style="font-size:.65rem;color:var(--lco-primary);"></i>
                {{ $totalContracts }} contrat{{ $totalContracts > 1 ? 's' : '' }}
            </span>
            <span class="lco-hero-chip">
                <i class="fas fa-link" style="font-size:.65rem;"></i>
                {{ $totalRuleLines }} ligne{{ $totalRuleLines > 1 ? 's' : '' }}
            </span>
        </div>
    </div>

    {{-- ── KPI STRIP ────────────────────────────────────────────── --}}
    <div class="lco-kpis">
        <div class="lco-kpi" style="--lco-kpi-accent:#f58220;--lco-kpi-icon-bg:rgba(245,130,32,.1);--lco-kpi-icon-color:#f58220;">
            <div>
                <div class="lco-kpi-label">Contrats principaux</div>
                <div class="lco-kpi-val" id="kpiContracts">{{ $totalContracts }}</div>
            </div>
            <div class="lco-kpi-icon"><i class="fas fa-file-contract"></i></div>
        </div>

        <div class="lco-kpi" style="--lco-kpi-accent:#6366f1;--lco-kpi-icon-bg:rgba(99,102,241,.1);--lco-kpi-icon-color:#6366f1;">
            <div>
                <div class="lco-kpi-label">Lignes réelles</div>
                <div class="lco-kpi-val">{{ $totalRuleLines }}</div>
            </div>
            <div class="lco-kpi-icon"><i class="fas fa-link"></i></div>
        </div>

        <div class="lco-kpi" style="--lco-kpi-accent:#10b981;--lco-kpi-icon-bg:rgba(16,185,129,.1);--lco-kpi-icon-color:#10b981;">
            <div>
                <div class="lco-kpi-label">Règles actives</div>
                <div class="lco-kpi-val" id="kpiActiveRules">{{ $activeRules }}</div>
            </div>
            <div class="lco-kpi-icon"><i class="fas fa-power-off"></i></div>
        </div>

        <div class="lco-kpi" style="--lco-kpi-accent:#f59e0b;--lco-kpi-icon-bg:rgba(245,158,11,.1);--lco-kpi-icon-color:#f59e0b;">
            <div>
                <div class="lco-kpi-label">Actives sans heure</div>
                <div class="lco-kpi-val" id="kpiMissingTime">{{ $missingTimes }}</div>
            </div>
            <div class="lco-kpi-icon"><i class="fas fa-clock"></i></div>
        </div>
    </div>

    {{-- ── SELECTION BAR (si sélection active) ─────────────────── --}}
    <div class="lco-sel-bar" id="selectionBar">
        <div class="lco-sel-count">
            <i class="fas fa-check-square"></i>
            <span id="selectedCount">0</span> contrat(s) sélectionné(s)
        </div>
        <div class="lco-sel-actions">
            <input type="time" class="lco-input lco-time-input" id="selectionTime" value="12:00">
            <button type="button" class="lco-btn soft" id="selApplyTimeBtn">
                <i class="fas fa-clock"></i> Appliquer heure
            </button>
            <button type="button" class="lco-btn soft" id="selEnableBtn">
                <i class="fas fa-toggle-on"></i> Activer
            </button>
            <button type="button" class="lco-btn" id="selDisableBtn">
                <i class="fas fa-toggle-off"></i> Désactiver
            </button>
            <button type="button" class="lco-btn lco-btn-icon-only" id="clearSelectionBtn" title="Vider la sélection">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
    </div>

    {{-- ── TOOLBAR (recherche + actions en masse) ───────────────── --}}
    <div class="lco-toolbar">
        <div class="lco-toolbar-header">
            <span class="lco-toolbar-title">
                <i class="fas fa-layer-group"></i>
                Paramétrage en masse
            </span>
            <span style="font-size:.72rem;color:var(--color-text-muted);">
                Les actions s'appliquent aux lignes visibles ou sélectionnées uniquement.
            </span>
        </div>

        <div class="lco-toolbar-body">
            <div class="lco-toolbar-row">
                {{-- Recherche --}}
                <div class="lco-search-wrap">
                    <i class="fas fa-search lco-search-icon"></i>
                    <input
                        type="search"
                        id="contractSearch"
                        class="lco-input lco-search-input"
                        placeholder="Contrat, véhicule, chauffeur, type…"
                    >
                </div>

                <select id="typeFilter" class="lco-input lco-select" title="Filtrer par type de contrat">
                    <option value="">Tous les types</option>
                    @foreach($filterContractTypes as $type)
                        @php
                            $typeLabel = $safeTypeLabel($type['label'] ?? null, $type['id'] ?? null, ($type['kind'] ?? 'MAIN') !== 'SUB');
                            $typeValue = strtolower(trim(($type['kind'] ?? 'MAIN') . '|' . ($type['id'] ?: $typeLabel)));
                        @endphp
                        <option value="{{ $typeValue }}">{{ ($type['kind'] ?? 'MAIN') === 'SUB' ? 'Sous-contrat' : 'Principal' }} — {{ $typeLabel }}</option>
                    @endforeach
                </select>

                <select id="statusFilter" class="lco-input lco-select" title="Filtrer par statut de règle">
                    <option value="">Tous les statuts</option>
                    <option value="active">Règle active</option>
                    <option value="missing_time">Active sans heure</option>
                    <option value="inactive">Aucune règle</option>
                </select>

                <div class="lco-toolbar-divider"></div>

                {{-- Groupe : heure + actions visibles --}}
                <input type="time" class="lco-input lco-time-input" id="bulkTime" value="12:00">

                <button type="button" class="lco-btn soft" id="applyTimeVisibleBtn">
                    <i class="fas fa-clock"></i> Heure aux visibles
                </button>

                <div class="lco-toolbar-divider"></div>

                <button type="button" class="lco-btn soft" id="enableVisibleBtn">
                    <i class="fas fa-toggle-on"></i> Activer visibles
                </button>

                <button type="button" class="lco-btn" id="disableVisibleBtn">
                    <i class="fas fa-toggle-off"></i> Désactiver visibles
                </button>
            </div>
        </div>
    </div>

    {{-- ── FORM CARD ────────────────────────────────────────────── --}}
    <div class="lco-form-card">
        <form method="POST" action="{{ route('lease.cutoff-rules.store') }}" id="cutoffRulesForm">
            @csrf

            <div class="lco-table-wrap">
                <table class="lco-table">
                    <thead>
                        <tr>
                            <th style="width:42px;">
                                <input type="checkbox" id="checkAll" class="lco-row-check">
                            </th>
                            <th>Véhicule / Contrat</th>
                            <th>Chauffeur</th>
                            <th>Statut</th>
                            <th>Règles associées</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse($rows as $rowIndex => $row)
                        @php
                            $contractRules = collect($row['contract_rules'] ?? []);

                            $mainRule = $contractRules
                                ->first(fn ($rule) => ($rule['contract_kind'] ?? 'MAIN') === 'MAIN')
                                ?? $contractRules->first();

                            $mainVisibleLabel = $safeTypeLabel(
                                $row['main_type_label'] ?? ($mainRule['type_contrat_label'] ?? null),
                                $mainRule['type_contrat_id'] ?? null,
                                true
                            );

                            $typeTokens = $contractRules
                                ->map(function ($rule) use ($safeTypeLabel) {
                                    $kind = ($rule['contract_kind'] ?? 'MAIN') === 'SUB' ? 'SUB' : 'MAIN';
                                    $typeId = (int) ($rule['type_contrat_id'] ?? 0);
                                    $label = $safeTypeLabel(
                                        $rule['type_contrat_label'] ?? null,
                                        $typeId,
                                        $kind !== 'SUB'
                                    );

                                    return strtolower(trim($kind . '|' . ($typeId ?: $label)));
                                })
                                ->filter()
                                ->unique()
                                ->implode(' ');

                            $searchText = strtolower(trim(
                                ($row['immatriculation'] ?? '') . ' ' .
                                ($row['driver_name'] ?? '') . ' ' .
                                $mainVisibleLabel . ' ' .
                                $contractRules
                                    ->map(fn ($rule) => $safeTypeLabel(
                                        $rule['type_contrat_label'] ?? null,
                                        $rule['type_contrat_id'] ?? null,
                                        ($rule['contract_kind'] ?? 'MAIN') !== 'SUB'
                                    ))
                                    ->implode(' ')
                            ));
                        @endphp

                        <tr class="lco-row"
                            data-search="{{ $searchText }}"
                            data-type-tokens="{{ $typeTokens }}"
                            data-enabled-count="{{ $row['enabled_contract_rules_count'] ?? 0 }}"
                            data-missing-time="{{ $row['missing_time_contract_rules_count'] ?? 0 }}">

                            {{-- Checkbox --}}
                            <td style="vertical-align:middle;">
                                <input type="checkbox" class="lco-row-check">
                            </td>

                            {{-- Véhicule --}}
                            <td>
                                <input type="hidden" name="rules[{{ $rowIndex }}][main_contract_link_id]" value="{{ $row['main_contract_link_id'] }}">
                                <input type="hidden" name="rules[{{ $rowIndex }}][vehicle_id]" value="{{ $row['vehicle_id'] }}">

                                <div class="lco-vehicle">
                                    <div class="lco-vehicle-icon">
                                        <i class="fas fa-motorcycle"></i>
                                    </div>
                                    <div>
                                        <div class="lco-vehicle-plate">
                                            {{ $row['immatriculation'] ?: 'Sans immatriculation' }}
                                        </div>
                                        <div class="lco-vehicle-meta">
                                            {{ trim(($row['marque'] ?? '') . ' ' . ($row['model'] ?? '')) ?: 'Modèle non renseigné' }}<br>
                                            Contrat principal — {{ $mainVisibleLabel }}<br>
                                            GPS : {{ !empty($row['mac_id_gps']) ? 'renseigné' : 'à compléter' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            {{-- Chauffeur --}}
                            <td style="vertical-align:middle;">
                                @if($row['driver_name'])
                                    <span class="lco-driver">{{ $row['driver_name'] }}</span>
                                @else
                                    <span class="lco-driver lco-driver-empty">Non résolu</span>
                                @endif
                            </td>

                            {{-- Statut (calculé en JS) --}}
                            <td class="row-status lco-status-cell" style="vertical-align:middle;"></td>

                            {{-- Règles --}}
                            <td>
                                <div class="lco-rule-grid">
                                    @foreach(($row['contract_rules'] ?? []) as $ruleIndex => $rule)
                                        @php
                                            $isSubContract = ($rule['contract_kind'] ?? 'MAIN') === 'SUB';

                                            $ruleVisibleLabel = $safeTypeLabel(
                                                $rule['type_contrat_label'] ?? null,
                                                $rule['type_contrat_id'] ?? null,
                                                ! $isSubContract
                                            );

                                            $ruleKindLabel = $isSubContract ? 'Sous-contrat' : 'Principal';
                                            $ruleDescription = $isSubContract
                                                ? 'Règle du sous-contrat associé'
                                                : 'Règle du contrat principal';
                                        @endphp
                                        <div class="lco-rule-card {{ !empty($rule['is_enabled']) ? 'enabled' : '' }}">

                                            {{-- Top : nom + toggle --}}
                                            <div class="lco-rule-top">
                                                <div>
                                                    <div class="lco-rule-name">
                                                        <span class="lco-tag {{ $isSubContract ? 'sub' : 'main' }}">
                                                            {{ $ruleKindLabel }}
                                                        </span>
                                                        {{ $ruleVisibleLabel }}
                                                    </div>
                                                    <div class="lco-rule-id">
                                                        {{ $ruleDescription }}
                                                    </div>
                                                </div>

                                                <div class="lco-toggle-wrap">
                                                    <span class="lco-toggle-label">Activer</span>
                                                    <input type="hidden" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][is_enabled]" value="0">
                                                    <input
                                                        type="checkbox"
                                                        class="rule-enabled lco-row-check"
                                                        name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][is_enabled]"
                                                        value="1"
                                                        @checked($rule['is_enabled'])
                                                    >
                                                </div>
                                            </div>

                                            <input type="hidden" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][contract_link_id]" value="{{ $rule['contract_link_id'] }}">
                                            <input type="hidden" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][timezone]" value="{{ $rule['timezone'] ?? 'Africa/Douala' }}">

                                            {{-- Champs : heure, grâce, sécurité, notif --}}
                                            <div class="lco-rule-fields">
                                                <div class="lco-field">
                                                    <label>Heure</label>
                                                    <input
                                                        type="time"
                                                        class="lco-field-input rule-time"
                                                        name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][cutoff_time]"
                                                        value="{{ $rule['cutoff_time'] ?: '12:00' }}"
                                                    >
                                                </div>

                                                <div class="lco-field">
                                                    <label>Grâce (j)</label>
                                                    <input
                                                        type="number"
                                                        class="lco-field-input"
                                                        min="0" max="365"
                                                        name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][grace_days]"
                                                        value="{{ $rule['grace_days'] ?? 0 }}"
                                                    >
                                                </div>

                                                <div class="lco-field">
                                                    <label>Sécurité</label>
                                                    <input type="hidden" name="rules[{{ $rowIndex }}][contract_rules][{{ $ruleIndex }}][only_when_stopped]" value="1">
                                                    <label class="lco-check-wrap" title="Sécurité obligatoire côté Tracking">
                                                        <input type="checkbox" checked disabled>
                                                        Arrêt obligatoire
                                                    </label>
                                                </div>

                                                
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="lco-empty">
                                    <div class="lco-empty-icon"><i class="fas fa-file-contract"></i></div>
                                    <div class="lco-empty-text">Aucun contrat lié trouvé</div>
                                    <div class="lco-empty-sub">Synchronisez d'abord les contrats Recouvrement avec les véhicules Tracking.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- FOOTER --}}
            <div class="lco-form-footer">
                <div class="lco-form-footer-hint">
                    <i class="fas fa-info-circle" style="margin-top:.1rem;color:var(--lco-primary);flex-shrink:0;"></i>
                    Pas de règle active sur le contrat ou sous-contrat réel = aucune planification de coupure. La dette reste gérée uniquement dans Recouvrement.
                </div>
                <div class="lco-form-actions">
                    <button type="reset" class="lco-btn">
                        <i class="fas fa-rotate-left"></i>
                        Réinitialiser
                    </button>
                    <button type="submit" class="lco-btn primary">
                        <i class="fas fa-save"></i>
                        Enregistrer les règles
                    </button>
                </div>
            </div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows         = Array.from(document.querySelectorAll('.lco-row'));
    const search       = document.getElementById('contractSearch');
    const typeFilter   = document.getElementById('typeFilter');
    const statusFilter = document.getElementById('statusFilter');
    const checkAll     = document.getElementById('checkAll');
    const selectionBar = document.getElementById('selectionBar');
    const selectedCountEl = document.getElementById('selectedCount');

    /* ── helpers ───────────────────────────────────────────── */
    const visibleRows  = () => rows.filter(r => !r.classList.contains('hidden'));
    const selectedRows = () => rows.filter(r => r.querySelector('.lco-row-check')?.checked);

    /* Refresh a single row's status badge and KPI data attributes */
    function refreshRow(row) {
        const cards   = Array.from(row.querySelectorAll('.lco-rule-card'));
        const enabled = cards.filter(c => c.querySelector('.rule-enabled')?.checked).length;
        const missing = cards.filter(c => c.querySelector('.rule-enabled')?.checked && !c.querySelector('.rule-time')?.value).length;

        row.dataset.enabledCount = String(enabled);
        row.dataset.missingTime  = String(missing);

        /* card visual state */
        cards.forEach(c => c.classList.toggle('enabled', !!c.querySelector('.rule-enabled')?.checked));

        /* row status badge */
        const statusEl = row.querySelector('.row-status');
        if (statusEl) {
            if (enabled > 0 && missing === 0)
                statusEl.innerHTML = '<span class="lco-tag ok"><i class="fas fa-check" style="font-size:.55rem;"></i> Règle active</span>';
            else if (enabled > 0 && missing > 0)
                statusEl.innerHTML = '<span class="lco-tag warn"><i class="fas fa-clock" style="font-size:.55rem;"></i> Heure manquante</span>';
            else
                statusEl.innerHTML = '<span class="lco-tag off"><i class="fas fa-minus" style="font-size:.55rem;"></i> Aucune règle</span>';
        }

        row.classList.add('dirty');
        refreshKpis();
    }

    function refreshKpis() {
        const activeRules = rows.reduce((s, r) => s + Number(r.dataset.enabledCount || 0), 0);
        const missing     = rows.reduce((s, r) => s + Number(r.dataset.missingTime  || 0), 0);
        document.getElementById('kpiActiveRules').textContent  = activeRules;
        document.getElementById('kpiMissingTime').textContent  = missing;
    }

    function rowStatus(row) {
        const enabled = Number(row.dataset.enabledCount || 0);
        const missing = Number(row.dataset.missingTime || 0);

        if (enabled > 0 && missing > 0) return 'missing_time';
        if (enabled > 0) return 'active';
        return 'inactive';
    }

    function applyFilters() {
        const q = (search?.value || '').trim().toLowerCase();
        const type = (typeFilter?.value || '').trim().toLowerCase();
        const status = (statusFilter?.value || '').trim().toLowerCase();

        rows.forEach(row => {
            const matchSearch = !q || (row.dataset.search || '').includes(q);
            const matchType = !type || (row.dataset.typeTokens || '').split(/\s+/).includes(type);
            const matchStatus = !status || rowStatus(row) === status;
            row.classList.toggle('hidden', !(matchSearch && matchType && matchStatus));
        });
        refreshSelectionBar();
    }

    function refreshSelectionBar() {
        const selected = selectedRows();
        selectionBar?.classList.toggle('show', selected.length > 0);
        if (selectedCountEl) selectedCountEl.textContent = selected.length;

        const vis        = visibleRows();
        const visChecked = vis.filter(r => r.querySelector('.lco-row-check')?.checked).length;
        if (checkAll) {
            checkAll.checked       = vis.length > 0 && visChecked === vis.length;
            checkAll.indeterminate = visChecked > 0 && visChecked < vis.length;
        }

        rows.forEach(r => r.classList.toggle('selected', !!r.querySelector('.lco-row-check')?.checked));
    }

    /* ── batch mutations ────────────────────────────────────── */
    function mutate(targetRows, callback) {
        targetRows.forEach(row => { callback(row); refreshRow(row); });
        applyFilters();
    }

    const setEnabled = (row, val) => row.querySelectorAll('.rule-enabled').forEach(i => i.checked = val);
    const setTimes   = (row, t)   => { if (t) row.querySelectorAll('.rule-time').forEach(i => i.value = t); };

    /* ── listeners ──────────────────────────────────────────── */
    search?.addEventListener('input', applyFilters);
    typeFilter?.addEventListener('change', applyFilters);
    statusFilter?.addEventListener('change', applyFilters);

    checkAll?.addEventListener('change', () => {
        visibleRows().forEach(row => {
            const cb = row.querySelector('.lco-row-check');
            if (cb) cb.checked = checkAll.checked;
        });
        refreshSelectionBar();
    });

    rows.forEach(row => {
        row.querySelector('.lco-row-check')?.addEventListener('change', refreshSelectionBar);
        row.querySelectorAll('input').forEach(input => {
            if (input.classList.contains('lco-row-check')) return;
            input.addEventListener('change', () => { refreshRow(row); applyFilters(); });
            input.addEventListener('input',  () => { refreshRow(row); applyFilters(); });
        });
    });

    /* Visible buttons */
    document.getElementById('enableVisibleBtn')
        ?.addEventListener('click', () => mutate(visibleRows(), r => setEnabled(r, true)));

    document.getElementById('disableVisibleBtn')
        ?.addEventListener('click', () => mutate(visibleRows(), r => setEnabled(r, false)));

    document.getElementById('applyTimeVisibleBtn')
        ?.addEventListener('click', () => {
            const t = document.getElementById('bulkTime')?.value;
            if (!t) { alert('Choisissez une heure à appliquer.'); return; }
            mutate(visibleRows(), r => setTimes(r, t));
        });

    /* Selection buttons */
    document.getElementById('selEnableBtn')
        ?.addEventListener('click', () => mutate(selectedRows(), r => setEnabled(r, true)));

    document.getElementById('selDisableBtn')
        ?.addEventListener('click', () => mutate(selectedRows(), r => setEnabled(r, false)));

    document.getElementById('selApplyTimeBtn')
        ?.addEventListener('click', () => {
            const t = document.getElementById('selectionTime')?.value;
            if (!t) { alert('Choisissez une heure à appliquer.'); return; }
            mutate(selectedRows(), r => setTimes(r, t));
        });

    document.getElementById('clearSelectionBtn')
        ?.addEventListener('click', () => {
            rows.forEach(r => { const cb = r.querySelector('.lco-row-check'); if (cb) cb.checked = false; });
            refreshSelectionBar();
        });

    /* ── init ───────────────────────────────────────────────── */
    rows.forEach(refreshRow);
    rows.forEach(r => r.classList.remove('dirty'));
    applyFilters();
});
</script>
@endpush