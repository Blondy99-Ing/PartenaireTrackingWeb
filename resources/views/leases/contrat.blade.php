{{-- resources/views/leases/contrat.blade.php --}}

@extends('layouts.app')

@section('title', 'Contrats & sous-contrats')

@php
    /*
    |--------------------------------------------------------------------------
    | Vue dynamique contrats / sous-contrats / coupure
    |--------------------------------------------------------------------------
    |
    | Données attendues du contrôleur :
    | - $contracts              : contrats venant de recouvrement
    | - $chauffeurs_list        : chauffeurs venant de recouvrement
    | - $vehicules_list         : véhicules venant de Tracking
    | - $contractTypesFromApi   : types de contrats venant de recouvrement
    |
    | Règle importante :
    | On ne fait plus de DELETE direct depuis l’interface.
    | Si un contrat est déjà lié à une échéance ou à un sous-contrat,
    | recouvrement bloque la suppression définitive.
    |
    | Donc la vue propose une action métier :
    | - Clôturer => PUT contrat avec statut = SOLDE.
    */

    $rawContractTypes = collect($contractTypesFromApi ?? []);

    $contractTypes = $rawContractTypes
        ->map(function ($type) {
            $id = (int) (
                $type['id']
                ?? $type['type_contrat']
                ?? $type['type_contrat_id']
                ?? 0
            );

            $label = $type['nom']
                ?? $type['label']
                ?? $type['libelle']
                ?? $type['name']
                ?? $type['titre']
                ?? null;

            $code = strtoupper((string) ($type['code'] ?? $type['slug'] ?? ''));

            $description = $type['description']
                ?? $type['details']
                ?? '';

            $isMain = (bool) (
                $type['is_main']
                ?? $type['est_principal']
                ?? $type['principal']
                ?? false
            );

            if (! $isMain && $code !== '') {
                $isMain = in_array($code, ['VEHICULE', 'VEHICLE', 'MOTO', 'VOITURE', 'MT'], true);
            }

            if (! $isMain && $label) {
                $lowerLabel = mb_strtolower($label, 'UTF-8');

                $isMain = str_contains($lowerLabel, 'véhicule')
                    || str_contains($lowerLabel, 'vehicule')
                    || str_contains($lowerLabel, 'vehicle')
                    || str_contains($lowerLabel, 'moto')
                    || str_contains($lowerLabel, 'voiture');
            }

            return [
                'id' => $id,
                'label' => $label ?: ('Type #' . $id),
                'description' => $description,
                'code' => $code,
                'is_main' => $isMain,
            ];
        })
        ->filter(fn ($type) => ! empty($type['id']))
        ->values();

    $mainType = $contractTypes->firstWhere('is_main', true) ?? $contractTypes->first();
    $mainTypeId = $mainType['id'] ?? null;

    $subContractTypes = $contractTypes
        ->filter(fn ($type) => (int) $type['id'] !== (int) $mainTypeId)
        ->values();

    $typeLabels = $contractTypes->keyBy('id');

    $vehiclesByImmat = collect($vehicules_list ?? [])
        ->filter(fn ($vehicle) => ! empty($vehicle['immatriculation']))
        ->keyBy(function ($vehicle) {
            return mb_strtoupper(
                preg_replace('/\s+/', '', trim((string) $vehicle['immatriculation'])),
                'UTF-8'
            );
        });

    $normalizeStatus = function ($status) {
        return mb_strtolower((string) ($status ?: 'ACTIF'), 'UTF-8');
    };

    $extractSubContracts = function (array $contract) {
        $raw = $contract['raw'] ?? $contract;

        return $raw['sous_contrats']
            ?? $raw['sousContrats']
            ?? $raw['sub_contracts']
            ?? $contract['sous_contrats']
            ?? $contract['sousContrats']
            ?? [];
    };

    $contractsPayload = collect($contracts ?? [])
        ->filter(fn ($contract) => is_array($contract))
        ->map(function ($contract) use ($typeLabels, $normalizeStatus, $extractSubContracts, $contractTypes, $vehiclesByImmat) {
            $raw = $contract['raw'] ?? $contract;

            $contractId = (int) (
                $contract['source_contrat_id']
                ?? $contract['source_contract_id']
                ?? $contract['id']
                ?? $raw['id']
                ?? 0
            );

            $typeId = (int) (
                $raw['type_contrat']
                ?? $contract['type_contrat']
                ?? $contract['type_contrat_id']
                ?? 0
            );

            $type = $typeLabels->get($typeId);

            $total = (float) ($contract['montant_total'] ?? $raw['montant_total'] ?? 0);
            $paid = (float) (
                $contract['total_paye']
                ?? $contract['montant_paye']
                ?? $raw['montant_paye']
                ?? $raw['montant_verse']
                ?? 0
            );

            $remaining = (float) (
                $contract['montant_restant']
                ?? $raw['montant_restant']
                ?? max(0, $total - $paid)
            );

            $progress = $total > 0 ? round(($paid / $total) * 100) : 0;

            $immatriculation = $contract['vehicule']
                ?? $contract['immatriculation']
                ?? $raw['immatriculation']
                ?? null;

            $vehicleKey = mb_strtoupper(
                preg_replace('/\s+/', '', trim((string) $immatriculation)),
                'UTF-8'
            );

            $trackingVehicle = $vehiclesByImmat->get($vehicleKey);

            $vehicleId = $contract['vehicle_id']
                ?? $contract['vehicule_id']
                ?? ($trackingVehicle['id'] ?? null)
                ?? ($trackingVehicle['vehicle_id'] ?? null)
                ?? null;

            $vin = $raw['vin']
                ?? $contract['vin']
                ?? ($trackingVehicle['vin'] ?? '')
                ?? '';

            $subContracts = collect($extractSubContracts($contract))
                ->filter(fn ($row) => is_array($row))
                ->map(function ($sub) use ($typeLabels, $normalizeStatus, $contractId, $immatriculation, $vin, $vehicleId, $contract) {
                    $subTypeId = (int) ($sub['type_contrat'] ?? $sub['type_contrat_id'] ?? 0);
                    $subType = $typeLabels->get($subTypeId);

                    $subTotal = (float) ($sub['montant_total'] ?? 0);
                    $subPaid = (float) ($sub['montant_paye'] ?? $sub['total_paye'] ?? $sub['montant_verse'] ?? 0);
                    $subRemaining = (float) ($sub['montant_restant'] ?? max(0, $subTotal - $subPaid));

                    return [
                        'id' => (int) ($sub['id'] ?? $sub['source_contract_id'] ?? 0),
                        'parent' => $sub['parent'] ?? $sub['parent_id'] ?? $sub['source_parent_contract_id'] ?? $contractId,
                        'chauffeur_id' => $sub['chauffeur'] ?? $contract['chauffeur_id'] ?? $contract['chauffeur'] ?? null,
                        'vehicle_id' => $vehicleId,
                        'vehicule' => $sub['immatriculation'] ?? $immatriculation,
                        'vin' => $sub['vin'] ?? $vin,
                        'type_contrat' => $subTypeId,
                        'type_label' => $subType['label'] ?? ($sub['type_contrat_label'] ?? 'Sous-contrat'),
                        'montant_total' => $subTotal,
                        'montant_restant' => $subRemaining,
                        'montant_par_paiement' => (float) ($sub['montant_par_paiement'] ?? 0),
                        'frequence' => $sub['frequence'] ?? '',
                        'date_debut' => $sub['date_debut'] ?? null,
                        'date_fin' => $sub['date_fin'] ?? null,
                        'prochaine_echeance' => $sub['prochaine_echeance'] ?? null,
                        'statut' => $normalizeStatus($sub['statut'] ?? $sub['status'] ?? 'ACTIF'),
                        'specificites' => $sub['specificites'] ?? null,
                        'contract_link_id' => $sub['contract_link_id'] ?? null,
                        'cutoff' => [
                            'rule_id' => $sub['cutoff']['rule_id'] ?? null,
                            'enabled' => (bool) ($sub['cutoff']['enabled'] ?? $sub['cutoff']['is_enabled'] ?? false),
                            'grace_days' => (int) ($sub['cutoff']['grace_days'] ?? 0),
                            'cutoff_time' => $sub['cutoff']['cutoff_time'] ?? null,
                            'active_days' => $sub['cutoff']['active_days'] ?? [],
                            'only_when_stopped' => (bool) ($sub['cutoff']['only_when_stopped'] ?? true),
                            'notify_before_cutoff' => (bool) ($sub['cutoff']['notify_before_cutoff'] ?? false),
                        ],
                    ];
                })
                ->values()
                ->all();

            $chauffeurId = $contract['chauffeur_id']
                ?? $contract['chauffeur']
                ?? $raw['chauffeur']
                ?? null;

            $cutoff = $contract['cutoff'] ?? $raw['cutoff'] ?? [];

            $contractTypeRules = collect($cutoff['contract_types'] ?? [])
                ->map(function ($rule) {
                    return [
                        'type_contrat' => (int) ($rule['type_contrat'] ?? $rule['type_contrat_id'] ?? 0),
                        'label' => $rule['label'] ?? $rule['type_contrat_label'] ?? '',
                        'enabled' => (bool) ($rule['enabled'] ?? $rule['is_enabled'] ?? false),
                        'grace_days' => (int) ($rule['grace_days'] ?? 0),
                    ];
                })
                ->filter(fn ($rule) => ! empty($rule['type_contrat']))
                ->values();

            if ($contractTypeRules->isEmpty()) {
                $contractTypeRules = $contractTypes->map(fn ($type) => [
                    'type_contrat' => (int) $type['id'],
                    'label' => $type['label'],
                    'enabled' => false,
                    'grace_days' => 0,
                ]);
            }

            return [
                'id' => $contractId,
                'ref' => $contract['ref']
                    ?? $contract['reference']
                    ?? $raw['reference']
                    ?? ($contractId ? 'CTR-' . str_pad((string) $contractId, 5, '0', STR_PAD_LEFT) : 'Contrat'),

                'chauffeur' => $contract['chauffeur_nom']
                    ?? $contract['chauffeur_label']
                    ?? $contract['chauffeur_nom_complet']
                    ?? $contract['nom_complet']
                    ?? $contract['chauffeur']
                    ?? $contract['nom_chauffeur']
                    ?? $raw['chauffeur_nom']
                    ?? $raw['chauffeur_nom_complet']
                    ?? $raw['nom_complet']
                    ?? '—',

                'chauffeur_id' => $chauffeurId,
                'phone_ch' => $contract['phone_ch'] ?? $contract['telephone'] ?? '',
                'vehicule' => $immatriculation ?: '—',
                'vehicle_id' => $vehicleId,
                'vin' => $vin,

                'type_contrat' => $typeId,
                'type_label' => $type['label'] ?? 'Contrat',

                'montant_total' => $total,
                'montant_restant' => $remaining,
                'total_paye' => $paid,
                'versement' => (float) (
                    $contract['versement']
                    ?? $contract['montant_par_paiement']
                    ?? $raw['montant_par_paiement']
                    ?? 0
                ),

                'frequence' => $contract['frequence'] ?? $raw['frequence'] ?? '',
                'date_debut' => $contract['date_debut'] ?? $raw['date_debut'] ?? null,
                'date_fin' => $contract['date_fin_prevue'] ?? $contract['date_fin'] ?? $raw['date_fin'] ?? null,
                'prochaine_echeance' => $contract['prochaine_echeance'] ?? $raw['prochaine_echeance'] ?? null,

                'statut' => $normalizeStatus($contract['statut'] ?? $contract['status'] ?? $raw['statut'] ?? 'ACTIF'),
                'progress' => min(100, max(0, $progress)),
                'specificites' => $raw['specificites'] ?? $contract['specificites'] ?? null,
                'sub_contracts' => $subContracts,

                'cutoff' => [
                    'enabled' => (bool) ($cutoff['enabled'] ?? $cutoff['is_enabled'] ?? false),
                    'grace_days' => (int) ($cutoff['grace_days'] ?? 0),
                    'cutoff_time' => $cutoff['cutoff_time'] ?? null,
                    'active_days' => $cutoff['active_days'] ?? [],
                    'only_when_stopped' => (bool) ($cutoff['only_when_stopped'] ?? true),
                    'notify_before_cutoff' => (bool) ($cutoff['notify_before_cutoff'] ?? false),
                    'contract_types' => $contractTypeRules->values()->all(),
                ],
            ];
        })
        ->values()
        ->all();

    $stats = [
        'total' => count($contractsPayload),
        'active' => collect($contractsPayload)->where('statut', 'actif')->count(),
        'late' => collect($contractsPayload)->where('statut', 'retard')->count()
            + collect($contractsPayload)->filter(fn ($c) => collect($c['sub_contracts'])->contains(fn ($s) => $s['statut'] === 'retard'))->count(),
        'subs' => collect($contractsPayload)->sum(fn ($c) => count($c['sub_contracts'])),
    ];
@endphp

@push('styles')
<style>
    .lease-console { display:flex; flex-direction:column; gap:1rem; }

    .lc-hero {
        background:linear-gradient(135deg, rgba(245,130,32,.12), rgba(37,99,235,.08));
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:18px;
        padding:1rem 1.15rem;
        display:flex;
        justify-content:space-between;
        gap:1rem;
        align-items:flex-start;
        box-shadow:var(--shadow-sm,0 8px 24px rgba(15,23,42,.06));
    }

    @media(max-width:800px){ .lc-hero{flex-direction:column;} }

    .lc-hero h1 {
        margin:0;
        font-size:1.15rem;
        font-weight:900;
        color:var(--color-text,#111827);
        display:flex;
        gap:.55rem;
        align-items:center;
    }

    .lc-hero h1 i { color:var(--color-primary,#f58220); }

    .lc-hero p {
        margin:.3rem 0 0;
        color:var(--color-secondary-text,#6b7280);
        font-size:.78rem;
        line-height:1.45;
        max-width:850px;
    }

    .lc-actions { display:flex; flex-wrap:wrap; gap:.5rem; justify-content:flex-end; }

    .lc-btn {
        border:1px solid var(--color-border-subtle,#e5e7eb);
        background:var(--color-card,#fff);
        color:var(--color-text,#111827);
        border-radius:12px;
        padding:.62rem .82rem;
        font-size:.72rem;
        font-weight:900;
        cursor:pointer;
        display:inline-flex;
        align-items:center;
        gap:.42rem;
        transition:.16s ease;
        text-decoration:none;
    }

    .lc-btn:hover {
        border-color:var(--color-primary,#f58220);
        color:var(--color-primary,#f58220);
        transform:translateY(-1px);
    }

    .lc-btn.primary {
        background:var(--color-primary,#f58220);
        border-color:var(--color-primary,#f58220);
        color:white;
    }

    .lc-btn.soft {
        background:rgba(245,130,32,.1);
        border-color:rgba(245,130,32,.25);
        color:var(--color-primary,#f58220);
    }

    .lc-btn.danger {
        color:#dc2626;
        border-color:rgba(220,38,38,.25);
    }

    .lc-grid-stats {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:.8rem;
    }

    @media(max-width:900px){
        .lc-grid-stats{grid-template-columns:repeat(2,minmax(0,1fr));}
    }

    .lc-stat {
        background:var(--color-card,#fff);
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:16px;
        padding:.85rem;
        box-shadow:var(--shadow-sm,0 6px 18px rgba(15,23,42,.05));
    }

    .lc-stat span {
        display:block;
        font-size:.62rem;
        text-transform:uppercase;
        letter-spacing:.07em;
        color:var(--color-secondary-text,#6b7280);
        font-weight:900;
    }

    .lc-stat strong {
        display:block;
        margin-top:.15rem;
        font-size:1.35rem;
        font-weight:900;
        color:var(--color-primary,#f58220);
    }

    .lc-layout {
        display:grid;
        grid-template-columns:minmax(420px,.9fr) minmax(0,1.2fr);
        gap:1rem;
        align-items:start;
    }

    @media(max-width:1100px){
        .lc-layout{grid-template-columns:1fr;}
    }

    .lc-panel {
        background:var(--color-card,#fff);
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:18px;
        overflow:hidden;
        box-shadow:var(--shadow-sm,0 8px 24px rgba(15,23,42,.06));
    }

    .lc-panel-head {
        padding:.9rem 1rem;
        border-bottom:1px solid var(--color-border-subtle,#e5e7eb);
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:.75rem;
    }

    .lc-panel-head h2 {
        margin:0;
        font-size:.92rem;
        font-weight:900;
        color:var(--color-text,#111827);
        display:flex;
        align-items:center;
        gap:.45rem;
    }

    .lc-toolbar-advanced {
        padding:.75rem 1rem;
        border-bottom:1px solid var(--color-border-subtle,#e5e7eb);
        display:grid;
        grid-template-columns:minmax(220px,1.2fr) 150px 190px 170px;
        gap:.55rem;
    }

    @media(max-width:980px){
        .lc-toolbar-advanced{grid-template-columns:1fr 1fr;}
    }

    @media(max-width:640px){
        .lc-toolbar-advanced{grid-template-columns:1fr;}
    }

    .lc-input,.lc-select,.lc-textarea {
        width:100%;
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:12px;
        padding:.62rem .75rem;
        background:var(--color-card,#fff);
        color:var(--color-text,#111827);
        font-size:.76rem;
        outline:none;
    }

    .lc-input:focus,.lc-select:focus,.lc-textarea:focus {
        border-color:var(--color-primary,#f58220);
        box-shadow:0 0 0 3px rgba(245,130,32,.12);
    }

    .lc-textarea { resize:vertical; min-height:90px; }

    .lc-selection-bar {
        padding:.7rem 1rem;
        border-bottom:1px solid var(--color-border-subtle,#e5e7eb);
        background:rgba(245,130,32,.07);
        display:none;
        justify-content:space-between;
        align-items:center;
        gap:.75rem;
        flex-wrap:wrap;
    }

    .lc-selection-bar.open { display:flex; }

    .lc-selection-count {
        font-size:.72rem;
        font-weight:900;
        color:var(--color-primary,#f58220);
        display:inline-flex;
        align-items:center;
        gap:.4rem;
    }

    .lc-list { max-height:calc(100vh - 390px); overflow:auto; }

    .lc-contract-item {
        padding:.85rem 1rem;
        border-bottom:1px solid var(--color-border-subtle,#e5e7eb);
        cursor:pointer;
        transition:.15s ease;
        display:grid;
        grid-template-columns:28px minmax(0,1fr);
        gap:.7rem;
        align-items:start;
    }

    .lc-contract-item:hover,.lc-contract-item.active {
        background:rgba(245,130,32,.07);
    }

    .lc-select-contract {
        width:18px;
        height:18px;
        accent-color:var(--color-primary,#f58220);
        cursor:pointer;
        margin-top:.2rem;
    }

    .lc-contract-content { min-width:0; }

    .lc-contract-top {
        display:flex;
        justify-content:space-between;
        gap:.8rem;
        align-items:flex-start;
    }

    .lc-contract-ref {
        font-size:.78rem;
        font-weight:900;
        color:var(--color-text,#111827);
        margin:0;
    }

    .lc-contract-meta {
        margin:.15rem 0 0;
        color:var(--color-secondary-text,#6b7280);
        font-size:.68rem;
        line-height:1.4;
    }

    .lc-status {
        display:inline-flex;
        align-items:center;
        gap:.28rem;
        padding:.25rem .55rem;
        border-radius:999px;
        font-size:.58rem;
        font-weight:900;
        white-space:nowrap;
        border:1px solid transparent;
    }

    .lc-status.actif { background:rgba(22,163,74,.1); color:#15803d; border-color:rgba(22,163,74,.22); }
    .lc-status.retard { background:rgba(220,38,38,.1); color:#b91c1c; border-color:rgba(220,38,38,.22); }
    .lc-status.termine { background:rgba(37,99,235,.1); color:#2563eb; border-color:rgba(37,99,235,.22); }
    .lc-status.suspendu { background:rgba(107,114,128,.1); color:#4b5563; border-color:rgba(107,114,128,.22); }
    .lc-status.solde { background:rgba(37,99,235,.1); color:#1d4ed8; border-color:rgba(37,99,235,.22); }
    .lc-status.contentieux { background:rgba(124,45,18,.1); color:#9a3412; border-color:rgba(124,45,18,.22); }

    .lc-sub-badges {
        margin-top:.55rem;
        display:flex;
        flex-wrap:wrap;
        gap:.35rem;
    }

    .lc-type-badge {
        display:inline-flex;
        align-items:center;
        gap:.3rem;
        padding:.2rem .45rem;
        border-radius:999px;
        font-size:.58rem;
        font-weight:900;
        background:rgba(37,99,235,.08);
        color:#2563eb;
        border:1px solid rgba(37,99,235,.16);
    }

    .lc-type-badge.late {
        background:rgba(220,38,38,.08);
        color:#b91c1c;
        border-color:rgba(220,38,38,.18);
    }

    .lc-money-row {
        margin-top:.65rem;
        display:flex;
        align-items:center;
        gap:.55rem;
    }

    .lc-progress {
        flex:1;
        height:7px;
        border-radius:999px;
        background:var(--color-border-subtle,#e5e7eb);
        overflow:hidden;
    }

    .lc-progress > span {
        display:block;
        height:100%;
        border-radius:999px;
        background:var(--color-primary,#f58220);
    }

    .lc-progress-text {
        font-size:.65rem;
        font-weight:900;
        color:var(--color-secondary-text,#6b7280);
        white-space:nowrap;
    }

    .lc-empty {
        padding:2rem;
        text-align:center;
        color:var(--color-secondary-text,#6b7280);
        font-weight:800;
        font-size:.82rem;
    }

    .lc-detail { min-height:600px; }

    .lc-detail-body {
        padding:1rem;
        display:flex;
        flex-direction:column;
        gap:1rem;
    }

    .lc-detail-title {
        display:flex;
        justify-content:space-between;
        gap:1rem;
        align-items:flex-start;
    }

    @media(max-width:720px){
        .lc-detail-title{flex-direction:column;}
    }

    .lc-detail-title h3 {
        margin:0;
        font-size:1.05rem;
        font-weight:900;
        color:var(--color-text,#111827);
    }

    .lc-detail-title p {
        margin:.25rem 0 0;
        font-size:.72rem;
        color:var(--color-secondary-text,#6b7280);
    }

    .lc-detail-actions {
        display:flex;
        flex-wrap:wrap;
        gap:.45rem;
        align-items:center;
        justify-content:flex-end;
    }

    .lc-info-grid {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:.65rem;
    }

    @media(max-width:900px){
        .lc-info-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
    }

    .lc-info {
        background:rgba(148,163,184,.06);
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:14px;
        padding:.75rem;
    }

    .lc-info span {
        font-size:.58rem;
        text-transform:uppercase;
        letter-spacing:.06em;
        color:var(--color-secondary-text,#6b7280);
        font-weight:900;
    }

    .lc-info strong {
        display:block;
        margin-top:.18rem;
        color:var(--color-text,#111827);
        font-size:.8rem;
        font-weight:900;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .lc-json-field {
        min-height:92px;
        resize:vertical;
        font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        line-height:1.45;
    }

    .lc-specificites-editor {
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:16px;
        background:rgba(148,163,184,.05);
        padding:.75rem;
    }

    .lc-specificites-editor-head {
        display:flex;
        justify-content:space-between;
        gap:.75rem;
        align-items:flex-start;
        margin-bottom:.65rem;
    }

    .lc-specificites-editor-head strong {
        display:block;
        color:var(--color-text,#111827);
        font-size:.82rem;
        font-weight:900;
    }

    .lc-specificites-editor-head span {
        display:block;
        margin-top:.18rem;
        color:var(--color-secondary-text,#6b7280);
        font-size:.7rem;
        line-height:1.35;
    }

    .lc-specificites-rows {
        display:flex;
        flex-direction:column;
        gap:.5rem;
    }

    .lc-specificite-row {
        display:grid;
        grid-template-columns:minmax(130px,.8fr) minmax(150px,1.2fr) auto;
        gap:.45rem;
        align-items:center;
    }

    .lc-specificite-row .lc-input {
        min-height:38px;
    }

    .lc-specificite-remove {
        min-width:38px;
        height:38px;
        border-radius:12px;
        border:1px solid rgba(239,68,68,.22);
        background:rgba(239,68,68,.07);
        color:#b91c1c;
        cursor:pointer;
        font-weight:900;
    }

    .lc-specificites-actions {
        display:flex;
        justify-content:space-between;
        gap:.65rem;
        align-items:center;
        margin-top:.65rem;
        flex-wrap:wrap;
    }

    .lc-specificites-examples {
        color:var(--color-secondary-text,#6b7280);
        font-size:.68rem;
        line-height:1.35;
    }

    @media(max-width:700px){
        .lc-specificite-row {
            grid-template-columns:1fr;
        }
        .lc-specificite-remove {
            width:100%;
        }
    }

    .lc-specificites-box {
        background:rgba(148,163,184,.06);
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:14px;
        padding:.75rem;
    }

    .lc-specificites-title {
        font-size:.62rem;
        text-transform:uppercase;
        letter-spacing:.06em;
        color:var(--color-secondary-text,#6b7280);
        font-weight:900;
        margin-bottom:.45rem;
    }

    .lc-specificites-list {
        display:flex;
        flex-wrap:wrap;
        gap:.4rem;
    }

    .lc-specificite-chip {
        display:inline-flex;
        gap:.28rem;
        align-items:center;
        max-width:100%;
        border-radius:999px;
        padding:.25rem .5rem;
        background:rgba(37,99,235,.07);
        color:#1d4ed8;
        border:1px solid rgba(37,99,235,.14);
        font-size:.68rem;
        font-weight:800;
    }

    .lc-specificite-chip b { color:var(--color-text,#111827); }

    .lc-section-title {
        display:flex;
        justify-content:space-between;
        gap:.7rem;
        align-items:center;
        margin-bottom:.65rem;
    }

    .lc-section-title h4 {
        margin:0;
        font-size:.85rem;
        font-weight:900;
        color:var(--color-text,#111827);
        display:flex;
        align-items:center;
        gap:.4rem;
    }

    .lc-sub-list {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.7rem;
    }

    @media(max-width:850px){
        .lc-sub-list{grid-template-columns:1fr;}
    }

    .lc-sub-card {
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:15px;
        padding:.85rem;
        background:var(--color-card,#fff);
        display:flex;
        flex-direction:column;
        gap:.65rem;
        position:relative;
    }

    .lc-sub-head {
        display:flex;
        justify-content:space-between;
        gap:.7rem;
        align-items:flex-start;
    }

    .lc-sub-title {
        margin:0;
        font-size:.82rem;
        font-weight:900;
        color:var(--color-text,#111827);
        display:flex;
        gap:.42rem;
        align-items:center;
    }

    .lc-sub-title i { color:var(--color-primary,#f58220); }

    .lc-sub-meta {
        margin:.18rem 0 0;
        font-size:.66rem;
        color:var(--color-secondary-text,#6b7280);
    }

    .lc-sub-actions {
        display:flex;
        flex-wrap:wrap;
        gap:.35rem;
        margin-top:.2rem;
    }

    .lc-sub-action {
        border:1px solid var(--color-border-subtle,#e5e7eb);
        background:transparent;
        color:var(--color-secondary-text,#6b7280);
        border-radius:9px;
        padding:.35rem .5rem;
        font-size:.62rem;
        font-weight:900;
        cursor:pointer;
    }

    .lc-sub-action:hover {
        color:var(--color-primary,#f58220);
        border-color:var(--color-primary,#f58220);
    }

    .lc-policy-box {
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:15px;
        background:rgba(245,130,32,.04);
        padding:.85rem;
    }

    .lc-policy-row,.lc-type-row {
        display:grid;
        grid-template-columns:minmax(0,1.2fr) 110px 110px;
        gap:.55rem;
        align-items:center;
        padding:.55rem 0;
        border-bottom:1px dashed var(--color-border-subtle,#e5e7eb);
    }

    .lc-policy-row:last-child,.lc-type-row:last-child {
        border-bottom:none;
    }

    @media(max-width:760px){
        .lc-policy-row,.lc-type-row{grid-template-columns:1fr;}
    }

    .lc-policy-name {
        font-size:.76rem;
        font-weight:900;
        color:var(--color-text,#111827);
    }

    .lc-policy-help {
        display:block;
        margin-top:.12rem;
        color:var(--color-secondary-text,#6b7280);
        font-size:.63rem;
        font-weight:600;
    }

    .lc-switch {
        position:relative;
        width:44px;
        height:24px;
        display:inline-block;
    }

    .lc-switch input {
        opacity:0;
        width:0;
        height:0;
    }

    .lc-slider {
        position:absolute;
        inset:0;
        border-radius:999px;
        background:#cbd5e1;
        transition:.16s;
        cursor:pointer;
    }

    .lc-slider:before {
        content:"";
        position:absolute;
        width:16px;
        height:16px;
        top:4px;
        left:4px;
        border-radius:999px;
        background:white;
        transition:.16s;
        box-shadow:0 2px 6px rgba(0,0,0,.2);
    }

    .lc-switch input:checked + .lc-slider {
        background:#16a34a;
    }

    .lc-switch input:checked + .lc-slider:before {
        transform:translateX(20px);
    }

    .lc-inline-edit {
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:14px;
        padding:.85rem;
        background:rgba(148,163,184,.05);
    }

    .lc-form-help {
        font-size:.64rem;
        color:var(--color-secondary-text,#6b7280);
        line-height:1.4;
        margin-top:.35rem;
    }

    .lc-danger-zone {
        border:1px solid rgba(220,38,38,.22);
        background:rgba(220,38,38,.06);
        color:#991b1b;
        padding:.8rem;
        border-radius:14px;
        font-size:.72rem;
        font-weight:800;
    }

    .lc-modal-backdrop {
        position:fixed;
        inset:0;
        background:rgba(15,23,42,.55);
        z-index:9998;
        opacity:0;
        pointer-events:none;
        transition:.18s;
    }

    .lc-modal-backdrop.open {
        opacity:1;
        pointer-events:auto;
    }

    .lc-drawer {
        position:fixed;
        top:0;
        right:0;
        width:min(780px,100%);
        height:100vh;
        background:var(--color-card,#fff);
        z-index:9999;
        transform:translateX(100%);
        transition:.22s;
        box-shadow:-12px 0 36px rgba(15,23,42,.22);
        display:flex;
        flex-direction:column;
    }

    .lc-drawer.open { transform:translateX(0); }

    .lc-drawer form {
        height:100%;
        display:flex;
        flex-direction:column;
    }

    .lc-drawer-head {
        padding:1rem;
        border-bottom:1px solid var(--color-border-subtle,#e5e7eb);
        display:flex;
        justify-content:space-between;
        gap:1rem;
        align-items:flex-start;
    }

    .lc-drawer-head h3 {
        margin:0;
        font-size:1rem;
        font-weight:900;
        color:var(--color-text,#111827);
    }

    .lc-drawer-head p {
        margin:.2rem 0 0;
        font-size:.72rem;
        color:var(--color-secondary-text,#6b7280);
    }

    .lc-drawer-body {
        padding:1rem;
        overflow:auto;
        flex:1;
        display:flex;
        flex-direction:column;
        gap:1rem;
    }

    .lc-drawer-foot {
        padding:.85rem 1rem;
        border-top:1px solid var(--color-border-subtle,#e5e7eb);
        display:flex;
        justify-content:flex-end;
        gap:.55rem;
        flex-wrap:wrap;
    }

    .lc-form-grid {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.75rem;
    }

    @media(max-width:700px){
        .lc-form-grid{grid-template-columns:1fr;}
    }

    .lc-field {
        display:flex;
        flex-direction:column;
        gap:.28rem;
    }

    .lc-field.full {
        grid-column:1 / -1;
    }

    .lc-rule-card {
        grid-column: 1 / -1;
        border: 1px solid rgba(245,130,32,.18);
        background: rgba(245,130,32,.06);
        border-radius: 1rem;
        padding: .85rem;
    }

    .lc-rule-title {
        display:flex;
        align-items:center;
        gap:.45rem;
        font-weight:900;
        font-size:.78rem;
        color:var(--color-text,#111827);
        margin-bottom:.35rem;
    }

    .lc-rule-help {
        margin:0 0 .7rem;
        color:var(--color-secondary-text,#6b7280);
        font-size:.68rem;
        line-height:1.45;
    }

    .lc-checkbox-line {
        display:flex;
        align-items:center;
        gap:.45rem;
        color:var(--color-text,#111827);
        font-size:.72rem;
        font-weight:800;
    }

    .lc-days-list {
        display:flex;
        flex-wrap:wrap;
        gap:.4rem;
    }

    .lc-day-pill {
        display:inline-flex;
        align-items:center;
        gap:.3rem;
        border-radius:999px;
        background:var(--color-card,#fff);
        border:1px solid var(--color-border-subtle,#e5e7eb);
        padding:.35rem .55rem;
        font-size:.67rem;
        font-weight:850;
        color:var(--color-text,#111827);
    }

    .lc-rule-custom-box {
        display:none;
        grid-column: 1 / -1;
        margin-top:.65rem;
        border-top:1px dashed rgba(245,130,32,.25);
        padding-top:.75rem;
    }

    .lc-rule-custom-box.open {
        display:block;
    }

    .lc-field label {
        font-size:.62rem;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.06em;
        color:var(--color-secondary-text,#6b7280);
    }

    .lc-subform-card {
        border:1px solid var(--color-border-subtle,#e5e7eb);
        border-radius:15px;
        overflow:hidden;
        background:rgba(148,163,184,.05);
    }

    .lc-subform-head {
        padding:.7rem .8rem;
        border-bottom:1px solid var(--color-border-subtle,#e5e7eb);
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:.6rem;
    }

    .lc-subform-head strong {
        font-size:.78rem;
        color:var(--color-text,#111827);
        font-weight:900;
    }

    .lc-subform-body { padding:.8rem; }




    /* =========================================================
   Correction responsive formulaires / drawers
   Objectif :
   - tous les formulaires restent accessibles sur petit écran ;
   - le contenu scrolle à l'intérieur du drawer ;
   - le bouton Enregistrer reste toujours visible ;
   - aucun input ne reste caché en bas de page.
   ========================================================= */

.lc-drawer {
    height: 100vh;
    height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
}

.lc-drawer form {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.lc-drawer-head {
    flex: 0 0 auto;
}

.lc-drawer-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    overscroll-behavior: contain;
    padding-bottom: 1.25rem;
}

.lc-drawer-foot {
    flex: 0 0 auto;
    position: sticky;
    bottom: 0;
    z-index: 4;
    background: var(--color-card, #fff);
    box-shadow: 0 -8px 20px rgba(15, 23, 42, .06);
}

/* Les blocs internes du formulaire peuvent scroller sans casser le drawer */
.lc-drawer .lc-panel {
    max-height: none;
    overflow: visible;
}

.lc-drawer .lc-panel > div:not(.lc-panel-head) {
    min-height: 0;
}

/* Meilleure lisibilité des formulaires longs */
.lc-inline-edit,
.lc-subform-card {
    max-width: 100%;
    overflow: visible;
}

.lc-form-grid {
    min-width: 0;
}

.lc-field {
    min-width: 0;
}

.lc-input,
.lc-select,
.lc-textarea {
    max-width: 100%;
}

/* Le textarea ne doit pas pousser toute la fenêtre */
.lc-textarea {
    max-height: 180px;
    overflow-y: auto;
}

/* Sous-formulaires : scroll propre si plusieurs sous-contrats */
#createSubList {
    max-height: 55vh;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: .25rem;
}

/* Scrollbar discrète */
.lc-drawer-body::-webkit-scrollbar,
#createSubList::-webkit-scrollbar {
    width: 7px;
}

.lc-drawer-body::-webkit-scrollbar-thumb,
#createSubList::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, .55);
    border-radius: 999px;
}

.lc-drawer-body::-webkit-scrollbar-track,
#createSubList::-webkit-scrollbar-track {
    background: transparent;
}

/* =========================================================
   Petit écran
   ========================================================= */

@media (max-width: 700px) {
    .lc-drawer {
        width: 100%;
        height: 100dvh;
        max-height: 100dvh;
    }

    .lc-drawer-head {
        padding: .85rem;
    }

    .lc-drawer-head h3 {
        font-size: .95rem;
    }

    .lc-drawer-head p {
        font-size: .68rem;
        line-height: 1.35;
    }

    .lc-drawer-body {
        padding: .75rem;
        gap: .75rem;
    }

    .lc-drawer-foot {
        padding: .7rem .75rem;
        justify-content: stretch;
    }

    .lc-drawer-foot .lc-btn {
        flex: 1 1 0;
        justify-content: center;
    }

    .lc-form-grid {
        grid-template-columns: 1fr !important;
        gap: .65rem;
    }

    .lc-panel-head {
        padding: .75rem;
    }

    .lc-panel-head h2 {
        font-size: .8rem;
    }

    .lc-inline-edit {
        padding: .7rem;
    }

    .lc-subform-body {
        padding: .7rem;
    }

    #createSubList {
        max-height: 42vh;
    }

    .lc-input,
    .lc-select,
    .lc-textarea {
        min-height: 42px;
        font-size: .78rem;
    }
}

/* =========================================================
   Très petit écran
   ========================================================= */

@media (max-width: 420px) {
    .lc-drawer-head {
        gap: .5rem;
    }

    .lc-drawer-head .lc-btn {
        padding: .5rem .6rem;
    }

    .lc-drawer-body {
        padding: .6rem;
    }

    .lc-drawer-foot {
        flex-direction: column-reverse;
    }

    .lc-drawer-foot .lc-btn {
        width: 100%;
    }

    #createSubList {
        max-height: 38vh;
    }
}
</style>
@endpush

@section('content')
<div class="lease-console">
 

   



 
    @if($contractTypes->isEmpty())
        <div class="lc-panel" style="padding:.85rem 1rem;color:#b91c1c;background:rgba(220,38,38,.08);border-color:rgba(220,38,38,.22);">
            <strong><i class="fas fa-ban"></i> Types de contrats indisponibles :</strong>
            vous devez creer les types de contrat pour pouvoir créer des contrats ou des sous contrats.
        </div>
    @endif

    <section class="lc-hero">
        <div>
            <h1>
                <i class="fas fa-file-contract"></i>
                Contrats, sous-contrats et règles de coupure
            </h1>
            
        </div>

        <div class="lc-actions">
            <button type="button" class="lc-btn soft" id="openPolicyBtn">
                <i class="fas fa-bolt"></i>
                Paramètres du contrat
            </button>

            <button type="button"
                    class="lc-btn primary"
                    id="openCreateBtn"
                    @disabled($contractTypes->isEmpty() || empty($chauffeurs_list) || empty($vehicules_list))>
                <i class="fas fa-plus"></i>
                Nouveau contrat
            </button>
        </div>
    </section>

    <section class="lc-grid-stats">
        <div class="lc-stat">
            <span>Contrats</span>
            <strong>{{ $stats['total'] }}</strong>
        </div>
        <div class="lc-stat">
            <span>Actifs</span>
            <strong>{{ $stats['active'] }}</strong>
        </div>
        <div class="lc-stat">
            <span>Alertes / retards</span>
            <strong>{{ $stats['late'] }}</strong>
        </div>
        <div class="lc-stat">
            <span>Sous-contrats</span>
            <strong>{{ $stats['subs'] }}</strong>
        </div>
    </section>

    <section class="lc-layout">
        <aside class="lc-panel">
            <div class="lc-panel-head">
                <h2><i class="fas fa-list"></i> Liste des contrats</h2>
                <span class="lc-status actif">{{ count($contractsPayload) }} ligne(s)</span>
            </div>

            <div class="lc-toolbar-advanced">
                <input type="search"
                       class="lc-input"
                       id="contractSearch"
                       placeholder="Rechercher chauffeur, véhicule, référence, VIN...">

                <select class="lc-select" id="statusFilter">
                    <option value="all">Tous les statuts</option>
                    <option value="actif">Actifs</option>
                    
                    <option value="solde">Soldés</option>
                    <option value="suspendu">Suspendus</option>
                    
                </select>

                <select class="lc-select" id="subTypeFilter">
                    <option value="all">Tous les types</option>
                    <option value="main">Contrat sans sous-contrat</option>
                    @foreach($subContractTypes as $type)
                        <option value="{{ $type['id'] }}">Avec sous-contrat {{ $type['label'] }}</option>
                    @endforeach
                </select>

                <select class="lc-select" id="cutoffFilter">
                    <option value="all">Toutes les coupures</option>
                    <option value="cutoff_enabled">Coupure activée</option>
                    <option value="cutoff_disabled">Coupure désactivée</option>
                    
                </select>
            </div>

            <div class="lc-selection-bar" id="selectionBar">
                <div class="lc-selection-count">
                    <i class="fas fa-check-square"></i>
                    <span id="selectionCount">0 contrat sélectionné</span>
                </div>

                <div class="lc-actions">
                    <button type="button" class="lc-btn soft" id="bulkPolicyBtn">
                        <i class="fas fa-bolt"></i>
                        Appliquer règles
                    </button>

                    <button type="button" class="lc-btn danger" id="clearSelectionBtn">
                        <i class="fas fa-times"></i>
                        Annuler
                    </button>
                </div>
            </div>

            <div class="lc-list" id="contractList"></div>
        </aside>

        <main class="lc-panel lc-detail">
            <div class="lc-panel-head">
                <h2><i class="fas fa-eye"></i> Détail du contrat</h2>
                <div class="lc-actions">
                    <button type="button" class="lc-btn soft" id="openContractPolicyBtn">
                        <i class="fas fa-bolt"></i>
                        Coupure du contrat
                    </button>
                    <button type="button" class="lc-btn" id="addSubToCurrentBtn">
                        <i class="fas fa-layer-group"></i>
                        Ajouter sous-contrat
                    </button>
                </div>
            </div>

            <div class="lc-detail-body" id="contractDetail"></div>
        </main>
    </section>
</div>

<div class="lc-modal-backdrop" id="drawerBackdrop"></div>

{{-- DRAWER : création contrat principal --}}
<aside class="lc-drawer" id="createDrawer">
    <form method="POST" action="{{ route('lease.contrat.store') }}" id="createContractForm">
        @csrf

        <div class="lc-drawer-head">
            <div>
                <h3>Créer un contrat</h3>
                <p>Chauffeur recouvrement + véhicule Tracking. L’immatriculation et le VIN sont automatiques.</p>
            </div>
            <button type="button" class="lc-btn" data-close-drawer>
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="lc-drawer-body">
            <div class="lc-panel" style="box-shadow:none;">
                <div class="lc-panel-head">
                    <h2><i class="fas fa-user"></i> Chauffeur et véhicule</h2>
                </div>

                <div style="padding:1rem;">
                    <div class="lc-form-grid">
                        <div class="lc-field">
                            <label>Chauffeur recouvrement</label>
                            <select class="lc-select" name="chauffeur" id="createChauffeur" required>
                                <option value="">Sélectionner un chauffeur</option>
                                @foreach($chauffeurs_list ?? [] as $chauffeur)
                                    <option value="{{ $chauffeur['id'] }}">
                                        {{ $chauffeur['label'] }}
                                        @if(!empty($chauffeur['phone']))
                                            — {{ $chauffeur['phone'] }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="lc-form-help">Ce champ envoie l’ID chauffeur recouvrement.</small>
                        </div>

                        <div class="lc-field">
                            <label>Véhicule Tracking</label>
                            <select class="lc-select" name="vehicle_id" id="createVehicle" required>
                                <option value="">Sélectionner un véhicule</option>
                                @foreach($vehicules_list ?? [] as $vehicle)
                                    @php
                                        $vehicleId = $vehicle['id'] ?? $vehicle['vehicle_id'] ?? '';
                                        $immat = $vehicle['immatriculation'] ?? '';
                                        $vin = $vehicle['vin'] ?? '';
                                        $marque = $vehicle['marque'] ?? '';
                                        $model = $vehicle['model'] ?? '';
                                        $label = $vehicle['label'] ?? trim($immat . ' — ' . trim($marque . ' ' . $model));
                                    @endphp

                                    <option value="{{ $vehicleId }}"
                                            data-immat="{{ $immat }}"
                                            data-vin="{{ $vin }}">
                                        {{ $label ?: 'Véhicule #' . $vehicleId }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="lc-form-help">Ce champ vient de Tracking.</small>
                        </div>

                        <div class="lc-field">
                            <label>Immatriculation</label>
                            <input class="lc-input" name="immatriculation" id="createImmatriculation" readonly required>
                        </div>

                        <div class="lc-field">
                            <label>VIN</label>
                            <input class="lc-input" name="vin" id="createVin" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lc-panel" style="box-shadow:none;">
                <div class="lc-panel-head">
                    <h2><i class="fas fa-file-contract"></i> Contrat principal</h2>
                </div>

                <div style="padding:1rem;">
                    <div class="lc-form-grid">
                        <div class="lc-field">
                            <label>Type de contrat principal</label>
                            <select class="lc-select" name="type_contrat" required>
                                @foreach($contractTypes as $type)
                                    <option value="{{ $type['id'] }}" @selected((int) $type['id'] === (int) $mainTypeId)>
                                        {{ $type['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="lc-field">
                            <label>Montant total</label>
                            <input type="number" class="lc-input" name="montant_total" step="0.01" required>
                        </div>

                        <div class="lc-field">
                            <label>Montant payé</label>
                            <input type="number" class="lc-input" name="montant_paye" step="0.01" min="0" value="0">
                        </div>

                        <div class="lc-field">
                            <label>Montant par paiement</label>
                            <input type="number" class="lc-input" name="montant_par_paiement" step="0.01" required>
                        </div>

                        <div class="lc-field">
                            <label>Fréquence</label>
                            <select class="lc-select" name="frequence" required>
                                <option value="JOURNALIER">Journalier</option>
                                <option value="HEBDOMADAIRE">Hebdomadaire</option>
                                <option value="MENSUEL">Mensuel</option>
                            </select>
                        </div>

                        <div class="lc-field">
                            <label>Date début</label>
                            <input type="date" class="lc-input" name="date_debut" id="createDateDebut" required>
                        </div>

                        <div class="lc-field">
                            <label>Jours d'activité / semaine <small>(journalier)</small></label>
                            <input type="number" class="lc-input" name="jours_actifs_semaine" id="createActiveDaysPerWeek" min="1" max="7" step="1" value="6">
                        </div>

                        <div class="lc-field">
                            <label>Date fin</label>
                            <input type="date" class="lc-input" name="date_fin" id="createDateFin" readonly required>

                        </div>

                        <div class="lc-field">
                            <label>Prochaine échéance</label>
                            <input type="date" class="lc-input" name="prochaine_echeance" id="createProchaineEcheance" required>
                        </div>

                        <div class="lc-field full">
                            <label>Informations spécifiques du contrat principal <small>(optionnel)</small></label>
                            <div class="lc-specificites-editor" data-specificites-editor>
                                <textarea class="lc-json-field" name="specificites" data-specificites-payload hidden></textarea>
                                <div class="lc-specificites-editor-head">
                                    <div>
                                        <strong>Informations complémentaires</strong>
                                        <span></span>
                                    </div>
                                </div>
                                <div class="lc-specificites-rows" data-specificites-rows></div>
                                <div class="lc-specificites-actions">
                                    <button type="button" class="lc-btn soft" data-add-specificite>
                                        <i class="fas fa-plus"></i> Ajouter une information
                                    </button>
                                    <span class="lc-specificites-examples">Exemples : marque, modèle, couleur, numéro de série.</span>
                                </div>
                            </div>
                        </div>

                        <div class="lc-rule-card">
                            <div class="lc-rule-title"><i class="fas fa-power-off"></i> Règle de coupure du contrat</div>
                            <p class="lc-rule-help"></p>

                            <div class="lc-form-grid">
                                <input type="hidden" name="apply_default_cutoff_rule" value="1" data-apply-default-cutoff-rule>
                                <input type="hidden" name="customize_cutoff_rule" value="0" data-customize-cutoff-rule>

                                <div class="lc-field full">
                                    <label class="lc-checkbox-line">
                                        <input type="radio" name="cutoff_rule_mode" value="default" data-cutoff-rule-mode checked>
                                        Appliquer la règle de coupure par défaut
                                    </label>
                                </div>

                                <div class="lc-field full">
                                    <label class="lc-checkbox-line">
                                        <input type="radio" name="cutoff_rule_mode" value="custom" data-cutoff-rule-mode data-toggle-custom-rule>
                                        Personnaliser la règle de coupure pour ce contrat
                                    </label>
                                </div>

                                <div class="lc-rule-custom-box" data-custom-rule-box>
                                    <div class="lc-form-grid">
                                        <div class="lc-field">
                                            <label>Règle active</label>
                                            <select class="lc-select" name="custom_rule_is_enabled">
                                                <option value="1" selected>Oui</option>
                                                <option value="0">Non</option>
                                            </select>
                                        </div>

                                        <div class="lc-field">
                                            <label>Heure de coupure</label>
                                            <input type="time" class="lc-input" name="custom_rule_cutoff_time">
                                        </div>

                                        <div class="lc-field">
                                            <label>Jours de grâce</label>
                                            <input type="number" class="lc-input" name="custom_rule_grace_days" min="0" max="365" value="0">
                                        </div>

                                        <div class="lc-field">
                                            <label>Seulement à l'arrêt</label>
                                            <select class="lc-select" name="custom_rule_only_when_stopped">
                                                <option value="1" selected>Oui</option>
                                                <option value="0">Non</option>
                                            </select>
                                        </div>

                                        <div class="lc-field full">
                                            <label>Jours actifs</label>
                                            <div class="lc-days-list">
                                                @foreach([
                                                    'monday' => 'Lun',
                                                    'tuesday' => 'Mar',
                                                    'wednesday' => 'Mer',
                                                    'thursday' => 'Jeu',
                                                    'friday' => 'Ven',
                                                    'saturday' => 'Sam',
                                                    'sunday' => 'Dim',
                                                ] as $dayValue => $dayLabel)
                                                    <label class="lc-day-pill">
                                                        <input type="checkbox" name="custom_rule_active_days[]" value="{{ $dayValue }}" data-custom-rule-active-day @checked($dayValue !== 'sunday')>
                                                        {{ $dayLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        
                                        
                                    </div>
                                </div>
                            </div>
                        </div>

                        
                    </div>
                </div>
            </div>

            <div class="lc-panel" style="box-shadow:none;">
                <div class="lc-panel-head">
                    <h2><i class="fas fa-layer-group"></i> Sous-contrats</h2>
                    <button type="button" class="lc-btn soft" id="createAddSubBtn">
                        <i class="fas fa-plus"></i>
                        Ajouter
                    </button>
                </div>

                <div style="padding:1rem;display:flex;flex-direction:column;gap:.75rem;" id="createSubList"></div>
            </div>
        </div>

        <div class="lc-drawer-foot">
            <button type="button" class="lc-btn" data-close-drawer>Annuler</button>
            <button type="submit" class="lc-btn primary">
                <i class="fas fa-save"></i>
                Créer
            </button>
        </div>
    </form>
</aside>

{{-- DRAWER : ajouter sous-contrat --}}
<aside class="lc-drawer" id="addSubDrawer">
    <form method="POST" action="{{ route('lease.contrat.store') }}" id="addSubForm">
        @csrf

        <input type="hidden" name="parent" id="addSubParent">
        <input type="hidden" name="chauffeur" id="addSubChauffeur">
        <input type="hidden" name="vehicle_id" id="addSubVehicleId">
        <input type="hidden" name="immatriculation" id="addSubImmatriculation">
        <input type="hidden" name="vin" id="addSubVin">

        <div class="lc-drawer-head">
            <div>
                <h3>Ajouter un sous-contrat</h3>
                <p id="addSubSubtitle"></p>
            </div>
            <button type="button" class="lc-btn" data-close-drawer>
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="lc-drawer-body">
            <div class="lc-inline-edit">
                <div class="lc-form-grid">
                    <div class="lc-field">
                        <label>Type de sous-contrat</label>
                        <select class="lc-select" name="type_contrat" required>
                            @foreach($subContractTypes as $type)
                                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Montant total</label>
                        <input type="number" class="lc-input" name="montant_total" step="0.01" required>
                    </div>

                    <div class="lc-field">
                        <label>Montant payé</label>
                        <input type="number" class="lc-input" name="montant_paye" step="0.01" min="0" value="0">
                    </div>

                    <div class="lc-field">
                        <label>Montant par paiement</label>
                        <input type="number" class="lc-input" name="montant_par_paiement" step="0.01" required>
                    </div>

                    <div class="lc-field">
                        <label>Fréquence</label>
                        <select class="lc-select" name="frequence" required>
                            <option value="JOURNALIER">Journalier</option>
                            <option value="HEBDOMADAIRE">Hebdomadaire</option>
                            <option value="MENSUEL">Mensuel</option>
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Date début</label>
                        <input type="date" class="lc-input" name="date_debut" id="addSubDateDebut" required>
                    </div>

                    <div class="lc-field">
                        <label>Jours d'activité / semaine <small>(journalier)</small></label>
                        <input type="number" class="lc-input" name="jours_actifs_semaine" id="addSubActiveDaysPerWeek" min="1" max="7" step="1" value="6">
                    </div>

                    <div class="lc-field">
                        <label>Date fin</label>
                        <input type="date" class="lc-input" name="date_fin" id="addSubDateFin" readonly required>

                    </div>

                    <div class="lc-field">
                        <label>Prochaine échéance</label>
                        <input type="date" class="lc-input" name="prochaine_echeance" id="addSubProchaineEcheance" required>
                    </div>

                    <div class="lc-field full">
                        <label>Informations spécifiques du sous-contrat <small>(optionnel)</small></label>
                        <div class="lc-specificites-editor" data-specificites-editor>
                                <textarea class="lc-json-field" name="specificites" id="addSubSpecificites" data-specificites-payload hidden></textarea>
                                <div class="lc-specificites-editor-head">
                                    <div>
                                        <strong>Détails du sous-contrat</strong>
                                        
                                    </div>
                                </div>
                                <div class="lc-specificites-rows" data-specificites-rows></div>
                                <div class="lc-specificites-actions">
                                    <button type="button" class="lc-btn soft" data-add-specificite>
                                        <i class="fas fa-plus"></i> Ajouter une information
                                    </button>
                                    <span class="lc-specificites-examples">Exemples : IMEI, marque, modèle, police assurance, accessoire.</span>
                                </div>
                            </div>
                    </div>

                        <div class="lc-rule-card">
                            <div class="lc-rule-title"><i class="fas fa-power-off"></i> Règle de coupure du contrat</div>
                            <p class="lc-rule-help"></p>

                            <div class="lc-form-grid">
                                <input type="hidden" name="apply_default_cutoff_rule" value="1" data-apply-default-cutoff-rule>
                                <input type="hidden" name="customize_cutoff_rule" value="0" data-customize-cutoff-rule>

                                <div class="lc-field full">
                                    <label class="lc-checkbox-line">
                                        <input type="radio" name="cutoff_rule_mode" value="default" data-cutoff-rule-mode checked>
                                        Appliquer la règle de coupure par défaut
                                    </label>
                                </div>

                                <div class="lc-field full">
                                    <label class="lc-checkbox-line">
                                        <input type="radio" name="cutoff_rule_mode" value="custom" data-cutoff-rule-mode data-toggle-custom-rule>
                                        Personnaliser la règle de coupure pour ce contrat
                                    </label>
                                </div>

                                <div class="lc-rule-custom-box" data-custom-rule-box>
                                    <div class="lc-form-grid">
                                        <div class="lc-field">
                                            <label>Règle active</label>
                                            <select class="lc-select" name="custom_rule_is_enabled">
                                                <option value="1" selected>Oui</option>
                                                <option value="0">Non</option>
                                            </select>
                                        </div>

                                        <div class="lc-field">
                                            <label>Heure de coupure</label>
                                            <input type="time" class="lc-input" name="custom_rule_cutoff_time">
                                        </div>

                                        <div class="lc-field">
                                            <label>Jours de grâce</label>
                                            <input type="number" class="lc-input" name="custom_rule_grace_days" min="0" max="365" value="0">
                                        </div>

                                        <div class="lc-field">
                                            <label>Seulement à l'arrêt</label>
                                            <select class="lc-select" name="custom_rule_only_when_stopped">
                                                <option value="1" selected>Oui</option>
                                                <option value="0">Non</option>
                                            </select>
                                        </div>

                                        <div class="lc-field full">
                                            <label>Jours actifs</label>
                                            <div class="lc-days-list">
                                                @foreach([
                                                    'monday' => 'Lun',
                                                    'tuesday' => 'Mar',
                                                    'wednesday' => 'Mer',
                                                    'thursday' => 'Jeu',
                                                    'friday' => 'Ven',
                                                    'saturday' => 'Sam',
                                                    'sunday' => 'Dim',
                                                ] as $dayValue => $dayLabel)
                                                    <label class="lc-day-pill">
                                                        <input type="checkbox" name="custom_rule_active_days[]" value="{{ $dayValue }}" data-custom-rule-active-day @checked($dayValue !== 'sunday')>
                                                        {{ $dayLabel }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        

                                        
                                    </div>
                                </div>
                            </div>
                        </div>

                        
                </div>
            </div>
        </div>

        <div class="lc-drawer-foot">
            <button type="button" class="lc-btn" data-close-drawer>Annuler</button>
            <button type="submit" class="lc-btn primary">
                <i class="fas fa-save"></i>
                Ajouter
            </button>
        </div>
    </form>
</aside>

{{-- DRAWER : modifier contrat / sous-contrat --}}
<aside class="lc-drawer" id="editContractDrawer">
    <form method="POST" action="#" id="editContractForm">
        @csrf
        @method('PUT')

        <input type="hidden" name="vehicle_id" id="editVehicleId">
        <input type="hidden" name="parent" id="editParent">

        <div class="lc-drawer-head">
            <div>
                <h3 id="editDrawerTitle">Modifier</h3>
                <p>Modification envoyée au backend Laravel, puis vers recouvrement.</p>
            </div>
            <button type="button" class="lc-btn" data-close-drawer>
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="lc-drawer-body">
            <div class="lc-inline-edit">
                <div class="lc-form-grid">
                    <div class="lc-field">
                        <label>Type</label>
                        <select class="lc-select" name="type_contrat" id="editType" required>
                            @foreach($contractTypes as $type)
                                <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Statut</label>
                        <select class="lc-select" name="statut" id="editStatus">
                            <option value="ACTIF">Actif</option>
                            <option value="SUSPENDU">Suspendu</option>
                            <option value="SOLDE">Soldé</option>
                            
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Chauffeur recouvrement</label>
                        <select class="lc-select" name="chauffeur" id="editChauffeur" required>
                            @foreach($chauffeurs_list ?? [] as $chauffeur)
                                <option value="{{ $chauffeur['id'] }}">{{ $chauffeur['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Immatriculation</label>
                        <input class="lc-input" name="immatriculation" id="editImmatriculation" readonly required>
                    </div>

                    <div class="lc-field">
                        <label>VIN</label>
                        <input class="lc-input" name="vin" id="editVin" readonly>
                    </div>

                    <div class="lc-field">
                        <label>Montant total</label>
                        <input type="number" class="lc-input" name="montant_total" id="editTotal" step="0.01" required>
                    </div>

                    <div class="lc-field">
                        <label>Montant payé</label>
                        <input type="number" class="lc-input" name="montant_paye" id="editPaid" step="0.01" min="0">
                    </div>

                    <div class="lc-field">
                        <label>Montant restant</label>
                        <input type="number" class="lc-input" name="montant_restant" id="editRemaining" step="0.01" readonly>
                    </div>

                    <div class="lc-field">
                        <label>Montant par paiement</label>
                        <input type="number" class="lc-input" name="montant_par_paiement" id="editInstallment" step="0.01" required>
                    </div>

                    <div class="lc-field">
                        <label>Fréquence</label>
                        <select class="lc-select" name="frequence" id="editFrequency" required>
                            <option value="JOURNALIER">Journalier</option>
                            <option value="HEBDOMADAIRE">Hebdomadaire</option>
                            <option value="MENSUEL">Mensuel</option>
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Date début</label>
                        <input type="date" class="lc-input" name="date_debut" id="editStartDate" required>
                    </div>

                    <div class="lc-field">
                        <label>Jours d'activité / semaine <small>(journalier)</small></label>
                        <input type="number" class="lc-input" name="jours_actifs_semaine" id="editActiveDaysPerWeek" min="1" max="7" step="1" value="6">
                    </div>

                    <div class="lc-field">
                        <label>Date fin</label>
                        <input type="date" class="lc-input" name="date_fin" id="editEndDate" readonly required>
                    </div>

                    <div class="lc-field">
                        <label>Prochaine échéance</label>
                        <input type="date" class="lc-input" name="prochaine_echeance" id="editDueDate" required>
                    </div>

                    <div class="lc-field full">
                        <label>Informations spécifiques <small>(optionnel)</small></label>
                        <div class="lc-specificites-editor" data-specificites-editor>
                                <textarea class="lc-json-field" name="specificites" id="editSpecificites" data-specificites-payload hidden></textarea>
                                <div class="lc-specificites-editor-head">
                                    <div>
                                        <strong>Détails enregistrés</strong>
                                        <span>Modifiez les informations lisibles; elles seront sauvegardées en JSON automatiquement.</span>
                                    </div>
                                </div>
                                <div class="lc-specificites-rows" data-specificites-rows></div>
                                <div class="lc-specificites-actions">
                                    <button type="button" class="lc-btn soft" data-add-specificite>
                                        <i class="fas fa-plus"></i> Ajouter une information
                                    </button>
                                    <span class="lc-specificites-examples">Exemples : IMEI, marque, modèle, police assurance.</span>
                                </div>
                            </div>
                    </div>

                    <div class="lc-rule-card">
                        <div class="lc-rule-title"><i class="fas fa-power-off"></i> Règle de coupure</div>
                        <p class="lc-rule-help">Choisissez la règle à appliquer à ce contrat.</p>

                        <div class="lc-form-grid">
                            <input type="hidden" name="apply_default_cutoff_rule" value="1" data-apply-default-cutoff-rule>
                            <input type="hidden" name="customize_cutoff_rule" value="0" data-customize-cutoff-rule>

                            <div class="lc-field full">
                                <label class="lc-checkbox-line">
                                    <input type="radio" name="edit_cutoff_rule_mode" value="default" data-cutoff-rule-mode checked>
                                    Appliquer la règle de coupure par défaut
                                </label>
                            </div>

                            <div class="lc-field full">
                                <label class="lc-checkbox-line">
                                    <input type="radio" name="edit_cutoff_rule_mode" value="custom" data-cutoff-rule-mode data-toggle-custom-rule>
                                    Personnaliser la règle de coupure
                                </label>
                            </div>

                            <div class="lc-rule-custom-box" data-custom-rule-box>
                                <div class="lc-form-grid">
                                    <div class="lc-field">
                                        <label>Règle active</label>
                                        <select class="lc-select" name="custom_rule_is_enabled" id="editCustomRuleEnabled">
                                            <option value="1">Oui</option>
                                            <option value="0">Non</option>
                                        </select>
                                    </div>

                                    <div class="lc-field">
                                        <label>Heure de coupure</label>
                                        <input type="time" class="lc-input" name="custom_rule_cutoff_time" id="editCustomRuleTime">
                                    </div>

                                    <div class="lc-field">
                                        <label>Jours de grâce</label>
                                        <input type="number" class="lc-input" name="custom_rule_grace_days" id="editCustomRuleGrace" min="0" max="365" value="0">
                                    </div>

                                    <div class="lc-field">
                                        <label>Seulement à l'arrêt</label>
                                        <select class="lc-select" name="custom_rule_only_when_stopped" id="editCustomRuleOnlyStopped">
                                            <option value="1" selected>Oui</option>
                                            <option value="0">Non</option>
                                        </select>
                                    </div>

                                    <div class="lc-field full">
                                        <label>Jours actifs</label>
                                        <div class="lc-days-list" id="editCustomRuleDays">
                                            @foreach([
                                                'monday' => 'Lun',
                                                'tuesday' => 'Mar',
                                                'wednesday' => 'Mer',
                                                'thursday' => 'Jeu',
                                                'friday' => 'Ven',
                                                'saturday' => 'Sam',
                                                'sunday' => 'Dim',
                                            ] as $dayValue => $dayLabel)
                                                <label class="lc-day-pill">
                                                    <input type="checkbox" name="custom_rule_active_days[]" value="{{ $dayValue }}" data-custom-rule-active-day @checked($dayValue !== 'sunday')>
                                                    {{ $dayLabel }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                </div>
            </div>
        </div>

        <div class="lc-drawer-foot">
            <button type="button" class="lc-btn" data-close-drawer>Annuler</button>
            <button type="submit" class="lc-btn primary">
                <i class="fas fa-save"></i>
                Enregistrer
            </button>
        </div>
    </form>
</aside>

{{-- DRAWER : règles de coupure --}}
<aside class="lc-drawer" id="policyDrawer">
    <form method="POST" action="{{ route('lease.contrat.cutoff-policy') }}" id="policyForm">
        @csrf

        <input type="hidden" name="contract_id" id="policyContractId">
        <input type="hidden" name="vehicle_id" id="policyVehicleId">

        <div class="lc-drawer-head">
            <div>
                <h3>Paramétrage de coupure</h3>
                <p id="policyDrawerSubtitle"></p>
            </div>
            <button type="button" class="lc-btn" data-close-drawer>
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="lc-drawer-body">
            <div class="lc-inline-edit">
                <div class="lc-form-grid">
                    <div class="lc-field">
                        <label>Coupure automatique</label>
                        <select class="lc-select" name="cutoff[is_enabled]" id="policyEnabled">
                            <option value="1">Activée</option>
                            <option value="0">Désactivée</option>
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Heure de coupure</label>
                        <input type="time" class="lc-input" name="cutoff[cutoff_time]" id="policyTime">
                    </div>

                    <div class="lc-field">
                        <label>Délai de grâce</label>
                        <input type="number" min="0" max="365" class="lc-input" name="cutoff[grace_days]" id="policyGrace">
                    </div>

                    <div class="lc-field full">
                        <label>Jours actifs</label>
                        <div class="lc-days-list" id="policyActiveDays">
                            @foreach([
                                'monday' => 'Lun', 'tuesday' => 'Mar', 'wednesday' => 'Mer', 'thursday' => 'Jeu',
                                'friday' => 'Ven', 'saturday' => 'Sam', 'sunday' => 'Dim',
                            ] as $dayValue => $dayLabel)
                                <label class="lc-day-pill">
                                    <input type="checkbox" name="cutoff[active_days][]" value="{{ $dayValue }}">
                                    {{ $dayLabel }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="lc-field">
                        <label>Condition sécurité</label>
                        <select class="lc-select" name="cutoff[only_when_stopped]" id="policyOnlyStopped">
                            <option value="1">Couper seulement si arrêté</option>
                            
                        </select>
                    </div>
                </div>
            </div>

        </div>

        <div class="lc-drawer-foot">
            <button type="button" class="lc-btn" data-close-drawer>Fermer</button>
            <button type="submit" class="lc-btn primary">
                <i class="fas fa-save"></i>
                Enregistrer
            </button>
        </div>
    </form>
</aside>

{{-- DRAWER : règles de coupure en lot --}}
<aside class="lc-drawer" id="bulkPolicyDrawer">
    <form method="POST" action="{{ route('lease.contrat.bulk-cutoff-policy') }}" id="bulkPolicyForm">
        @csrf

        <div id="bulkSelectedContracts"></div>

        <div class="lc-drawer-head">
            <div>
                <h3>Appliquer des règles à plusieurs contrats</h3>
                <p id="bulkPolicySubtitle"></p>
            </div>
            <button type="button" class="lc-btn" data-close-drawer>
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="lc-drawer-body">
            <div class="lc-inline-edit">
                <div class="lc-form-grid">
                    <div class="lc-field">
                        <label>Coupure automatique</label>
                        <select class="lc-select" name="cutoff[is_enabled]">
                            <option value="1">Activer</option>
                            <option value="0">Désactiver</option>
                        </select>
                    </div>

                    <div class="lc-field">
                        <label>Heure de coupure</label>
                        <input type="time" class="lc-input" name="cutoff[cutoff_time]">
                    </div>

                    <div class="lc-field">
                        <label>Délai de grâce</label>
                        <input type="number" min="0" max="60" class="lc-input" name="cutoff[grace_days]">
                    </div>

                    <div class="lc-field">
                        <label>Sécurité</label>
                        <select class="lc-select" name="cutoff[only_when_stopped]">
                            <option value="1">Couper seulement si arrêté</option>
                            <option value="0">Ne pas imposer l’arrêt</option>
                        </select>
                    </div>
                </div>
            </div>

        </div>

        <div class="lc-drawer-foot">
            <button type="button" class="lc-btn" data-close-drawer>Annuler</button>
            <button type="submit" class="lc-btn primary">
                <i class="fas fa-check"></i>
                Appliquer
            </button>
        </div>
    </form>
</aside>

<template id="subFormTemplate">
    <div class="lc-subform-card" data-subform>
        <div class="lc-subform-head">
            <strong data-subform-title>Sous-contrat</strong>
            <button type="button" class="lc-btn danger" data-remove-subform>
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <div class="lc-subform-body">
            <div class="lc-form-grid">
                <div class="lc-field">
                    <label>Type</label>
                    <select class="lc-select" data-sub-type>
                        @foreach($subContractTypes as $type)
                            <option value="{{ $type['id'] }}">{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="lc-field">
                    <label>Montant total</label>
                    <input type="number" class="lc-input" data-sub-total step="0.01">
                </div>

                <div class="lc-field">
                    <label>Montant payé</label>
                    <input type="number" class="lc-input" data-sub-paid step="0.01" min="0" value="0">
                </div>

                <div class="lc-field">
                    <label>Montant par paiement</label>
                    <input type="number" class="lc-input" data-sub-installment step="0.01">
                </div>

                <div class="lc-field">
                    <label>Fréquence</label>
                    <select class="lc-select" data-sub-frequency>
                        <option value="JOURNALIER">Journalier</option>
                        <option value="HEBDOMADAIRE">Hebdomadaire</option>
                        <option value="MENSUEL">Mensuel</option>
                    </select>
                </div>

                <div class="lc-field">
                    <label>Date début</label>
                    <input type="date" class="lc-input" data-sub-start>
                </div>

                <div class="lc-field">
                    <label>Date fin</label>
                    <input type="date" class="lc-input" data-sub-end readonly>
                    
                </div>

                <div class="lc-field">
                    <label>Prochaine échéance</label>
                    <input type="date" class="lc-input" data-sub-due>
                </div>

                <div class="lc-field full">
                    <label>Informations spécifiques <small>(optionnel)</small></label>
                    <div class="lc-specificites-editor" data-specificites-editor>
                        <textarea class="lc-json-field" data-sub-specificites data-specificites-payload hidden></textarea>
                        <div class="lc-specificites-editor-head">
                            <div>
                                <strong>Détails du sous-contrat</strong>
                                <span></span>
                            </div>
                        </div>
                        <div class="lc-specificites-rows" data-specificites-rows></div>
                        <div class="lc-specificites-actions">
                            <button type="button" class="lc-btn soft" data-add-specificite>
                                <i class="fas fa-plus"></i> Ajouter une information
                            </button>
                            <span class="lc-specificites-examples">Exemples : IMEI, marque, modèle, police assurance.</span>
                        </div>
                    </div>
                </div>

                <div class="lc-rule-card" data-sub-rule-card>
                    <div class="lc-rule-title"><i class="fas fa-power-off"></i> Règle de coupure</div>
                    <p class="lc-rule-help"></p>

                    <div class="lc-form-grid">
                        <input type="hidden" data-sub-apply-default-cutoff-rule value="1">
                        <input type="hidden" data-sub-customize-cutoff-rule value="0">

                        <div class="lc-field full">
                            <label class="lc-checkbox-line">
                                <input type="radio" data-sub-cutoff-rule-mode value="default" checked>
                                Appliquer la règle de coupure par défaut
                            </label>
                        </div>

                        <div class="lc-field full">
                            <label class="lc-checkbox-line">
                                <input type="radio" data-sub-cutoff-rule-mode data-toggle-custom-rule value="custom">
                                Personnaliser la règle de coupure
                            </label>
                        </div>

                        <div class="lc-rule-custom-box" data-custom-rule-box>
                            <div class="lc-form-grid">
                                <div class="lc-field">
                                    <label>Règle active</label>
                                    <select class="lc-select" data-sub-custom-rule-enabled>
                                        <option value="1" selected>Oui</option>
                                        <option value="0">Non</option>
                                    </select>
                                </div>

                                <div class="lc-field">
                                    <label>Heure de coupure</label>
                                    <input type="time" class="lc-input" data-sub-custom-rule-time>
                                </div>

                                <div class="lc-field">
                                    <label>Jours de grâce</label>
                                    <input type="number" class="lc-input" data-sub-custom-rule-grace min="0" max="365" value="0">
                                </div>

                                <div class="lc-field">
                                    <label>Seulement à l'arrêt</label>
                                    <select class="lc-select" data-sub-custom-rule-only-stopped>
                                        <option value="1" selected>Oui</option>
                                        <option value="0">Non</option>
                                    </select>
                                </div>

                                <div class="lc-field full">
                                    <label>Jours actifs</label>
                                    <div class="lc-days-list">
                                        @foreach([
                                            'monday' => 'Lun',
                                            'tuesday' => 'Mar',
                                            'wednesday' => 'Mer',
                                            'thursday' => 'Jeu',
                                            'friday' => 'Ven',
                                            'saturday' => 'Sam',
                                            'sunday' => 'Dim',
                                        ] as $dayValue => $dayLabel)
                                            <label class="lc-day-pill">
                                                <input type="checkbox" data-sub-custom-rule-active-day value="{{ $dayValue }}" @checked($dayValue !== 'sunday')>
                                                {{ $dayLabel }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const contracts = @json($contractsPayload);
    const contractTypes = @json($contractTypes->values());
    const defaultCutoffRules = @json($defaultCutoffRulesForView ?? []);
    const updateUrlTemplate = @json(route('lease.contrat.update', ['id' => '__ID__']));
    const csrfToken = @json(csrf_token());

    const typeById = Object.fromEntries(contractTypes.map(type => [Number(type.id), type]));

    let selectedContractId = contracts[0]?.id || null;
    let selectedContractIds = new Set();
    let subFormIndex = 0;

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const money = value => {
        const amount = Number(value || 0);
        return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(amount) + ' FCFA';
    };

    const pct = value => Math.max(0, Math.min(100, Number(value || 0)));
    const buildUrl = (template, id) => template.replace('__ID__', id);
    const escapeHtml = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function formatSpecificiteLabel(value) {
        return String(value || '')
            .trim()
            .replace(/[_-]+/g, ' ')
            .split(/\s+/)
            .filter(Boolean)
            .map(word => {
                if (word === word.toUpperCase() && word.length <= 8) return word;
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            })
            .join(' ');
    }

    function formatSpecificiteValue(value) {
        if (value === null || value === undefined) return '';
        if (typeof value === 'object') return JSON.stringify(value);
        const raw = String(value).trim();
        if (!raw) return '';
        if (/^[A-Z0-9_+.-]{2,}$/i.test(raw) && !raw.includes(' ')) return raw;
        return raw
            .split(/\s+/)
            .map(word => word ? word.charAt(0).toUpperCase() + word.slice(1).toLowerCase() : '')
            .join(' ');
    }

    function normalizeSpecificitesForDisplay(value) {
        if (!value || (Array.isArray(value) && value.length === 0)) return [];

        if (typeof value === 'string') {
            try {
                value = JSON.parse(value);
            } catch (e) {
                return value.trim() ? [['info', value.trim()]] : [];
            }
        }

        if (Array.isArray(value)) {
            return value.map((item, index) => [String(index + 1), typeof item === 'object' ? JSON.stringify(item) : String(item)]);
        }

        if (typeof value === 'object') {
            return Object.entries(value).filter(([, v]) => v !== null && v !== '');
        }

        return [];
    }

    function renderSpecificites(value, title = 'Spécificités') {
        const rows = normalizeSpecificitesForDisplay(value);

        if (!rows.length) return '';

        return `
            <div class="lc-specificites-box">
                <div class="lc-specificites-title">${escapeHtml(title)}</div>
                <div class="lc-specificites-list">
                    ${rows.map(([key, val]) => `
                        <span class="lc-specificite-chip"><b>${escapeHtml(formatSpecificiteLabel(key))}</b> ${escapeHtml(formatSpecificiteValue(val))}</span>
                    `).join('')}
                </div>
            </div>
        `;
    }


    function parseSpecificitesValue(value) {
        if (!value || (Array.isArray(value) && value.length === 0)) return {};

        if (typeof value === 'string') {
            try {
                value = JSON.parse(value);
            } catch (e) {
                return value.trim() ? { info: value.trim() } : {};
            }
        }

        if (Array.isArray(value)) {
            return value.reduce((acc, item, index) => {
                if (item && typeof item === 'object' && !Array.isArray(item)) {
                    Object.entries(item).forEach(([key, val]) => {
                        if (key && val !== null && val !== '') acc[String(key)] = val;
                    });
                } else if (item !== null && item !== '') {
                    acc[String(index + 1)] = item;
                }
                return acc;
            }, {});
        }

        if (typeof value === 'object') {
            return Object.entries(value).reduce((acc, [key, val]) => {
                if (key && val !== null && val !== '') acc[String(key)] = val;
                return acc;
            }, {});
        }

        return {};
    }

    function addSpecificiteRow(editor, key = '', value = '') {
        const rows = $('[data-specificites-rows]', editor);
        if (!rows) return;

        const row = document.createElement('div');
        row.className = 'lc-specificite-row';
        row.innerHTML = `
            <input type="text" class="lc-input" data-specificite-key placeholder="Nom : IMEI, marque, police..." value="${escapeHtml(formatSpecificiteLabel(key))}">
            <input type="text" class="lc-input" data-specificite-value placeholder="Valeur" value="${escapeHtml(typeof value === 'object' ? JSON.stringify(value) : value)}">
            <button type="button" class="lc-specificite-remove" data-remove-specificite title="Retirer cette information">
                <i class="fas fa-times"></i>
            </button>
        `;
        rows.appendChild(row);
    }

    function syncSpecificitesEditor(editor) {
        const payload = $('[data-specificites-payload]', editor);
        if (!payload) return;

        const data = {};
        $$('[data-specificite-key]', editor).forEach(keyInput => {
            const row = keyInput.closest('.lc-specificite-row');
            const key = formatSpecificiteLabel(keyInput.value);
            const value = $('[data-specificite-value]', row)?.value.trim() || '';

            if (key && value !== '') {
                data[key] = value;
            }
        });

        payload.value = Object.keys(data).length ? JSON.stringify(data) : '';
    }

    function fillSpecificitesEditor(editor, value = {}) {
        if (!editor) return;

        const rows = $('[data-specificites-rows]', editor);
        if (rows) rows.innerHTML = '';

        const data = parseSpecificitesValue(value);
        Object.entries(data).forEach(([key, val]) => addSpecificiteRow(editor, key, val));

        if (!Object.keys(data).length) {
            addSpecificiteRow(editor);
        }

        syncSpecificitesEditor(editor);
    }

    function initSpecificitesEditor(editor) {
        if (!editor || editor.dataset.specificitesReady === '1') return;
        editor.dataset.specificitesReady = '1';

        const payload = $('[data-specificites-payload]', editor);
        fillSpecificitesEditor(editor, payload?.value || {});
    }

    function bootSpecificitesEditors(root = document) {
        $$('[data-specificites-editor]', root).forEach(initSpecificitesEditor);
    }

    function syncSpecificitesInForm(form) {
        $$('[data-specificites-editor]', form).forEach(syncSpecificitesEditor);
    }

    function today() {
        return new Date().toISOString().slice(0, 10);
    }

    function addMonths(dateString, months) {
        const date = new Date(dateString);
        date.setMonth(date.getMonth() + months);
        return date.toISOString().slice(0, 10);
    }

    function addMonthsNoOverflow(date, months) {
        const result = new Date(date.getTime());
        const originalDay = result.getDate();

        result.setDate(1);
        result.setMonth(result.getMonth() + Number(months || 0));

        const lastDayOfTargetMonth = new Date(result.getFullYear(), result.getMonth() + 1, 0).getDate();
        result.setDate(Math.min(originalDay, lastDayOfTargetMonth));

        return result;
    }

    function statusLabel(status) {
        const value = String(status || 'actif').toLowerCase();

        if (value === 'retard') return 'En retard';
        if (value === 'termine') return 'Terminé';
        if (value === 'suspendu') return 'Suspendu';
        if (value === 'solde') return 'Soldé';
        if (value === 'contentieux') return 'Contentieux';

        return 'Actif';
    }

    function apiStatus(status) {
        const value = String(status || 'ACTIF').toUpperCase();

        if (value === 'SOLDE') return 'SOLDE';
        if (value === 'SUSPENDU') return 'SUSPENDU';
        if (value === 'CONTENTIEUX') return 'CONTENTIEUX';

        return 'ACTIF';
    }

    function currentContract() {
        return contracts.find(contract => Number(contract.id) === Number(selectedContractId)) || contracts[0] || null;
    }

    function hasSubType(contract, typeId) {
        return (contract.sub_contracts || []).some(sub => Number(sub.type_contrat) === Number(typeId));
    }

    function hasLateSub(contract) {
        return (contract.sub_contracts || []).some(sub => sub.statut === 'retard');
    }

    function hasCuttableLate(contract) {
        if (!contract.cutoff?.enabled) return false;

        if (contract.statut === 'retard') return true;

        const lateSub = (contract.sub_contracts || []).find(sub => sub.statut === 'retard');
        if (!lateSub) return false;

        const rule = (contract.cutoff.contract_types || []).find(
            item => Number(item.type_contrat) === Number(lateSub.type_contrat)
        );

        return Boolean(rule?.enabled);
    }

    function passesFilters(contract) {
        const search = ($('#contractSearch')?.value || '').toLowerCase().trim();
        const status = $('#statusFilter')?.value || 'all';
        const subType = $('#subTypeFilter')?.value || 'all';
        const cutoff = $('#cutoffFilter')?.value || 'all';

        const haystack = [
            contract.ref,
            contract.chauffeur,
            contract.vehicule,
            contract.phone_ch,
            contract.vin,
            contract.type_label,
            ...(contract.sub_contracts || []).map(sub => sub.type_label)
        ].join(' ').toLowerCase();

        if (search && !haystack.includes(search)) return false;

        if (status !== 'all') {
            if (status === 'retard') {
                if (contract.statut !== 'retard' && !hasLateSub(contract)) return false;
            } else if (contract.statut !== status) {
                return false;
            }
        }

        if (subType !== 'all') {
            if (subType === 'main') {
                if ((contract.sub_contracts || []).length > 0) return false;
            } else if (!hasSubType(contract, subType)) {
                return false;
            }
        }

        if (cutoff !== 'all') {
            if (cutoff === 'cutoff_enabled' && !contract.cutoff?.enabled) return false;
            if (cutoff === 'cutoff_disabled' && contract.cutoff?.enabled) return false;
            if (cutoff === 'late_cuttable' && !hasCuttableLate(contract)) return false;
        }

        return true;
    }

    function renderList() {
        const list = $('#contractList');
        const filtered = contracts.filter(passesFilters);

        if (!filtered.length) {
            list.innerHTML = `
                <div class="lc-empty">
                    <i class="fas fa-search"></i><br>
                    Aucun contrat trouvé.
                </div>
            `;
            updateSelectionBar();
            return;
        }

        list.innerHTML = filtered.map(contract => {
            const badges = (contract.sub_contracts || []).map(sub => `
                <span class="lc-type-badge ${sub.statut === 'retard' ? 'late' : ''}">
                    <i class="fas fa-file-contract"></i>
                    ${sub.type_label}
                </span>
            `).join('');

            return `
                <article class="lc-contract-item ${Number(contract.id) === Number(selectedContractId) ? 'active' : ''}">
                    <div>
                        <input type="checkbox"
                               class="lc-select-contract"
                               data-select-contract="${contract.id}"
                               ${selectedContractIds.has(Number(contract.id)) ? 'checked' : ''}>
                    </div>

                    <div class="lc-contract-content" data-open-contract="${contract.id}">
                        <div class="lc-contract-top">
                            <div>
                                <p class="lc-contract-ref">${contract.ref}</p>
                                <p class="lc-contract-meta">${contract.chauffeur || '—'} · ${contract.vehicule || '—'}</p>
                                <p class="lc-contract-meta">
                                    ${(contract.sub_contracts || []).length} sous-contrat(s)
                                    · règle contrat ${contract.cutoff?.enabled ? 'active' : 'inactive'}
                                </p>
                            </div>

                            <span class="lc-status ${contract.statut}">
                                ${statusLabel(contract.statut)}
                            </span>
                        </div>

                        <div class="lc-sub-badges">
                            ${badges || '<span class="lc-type-badge"><i class="fas fa-file-contract"></i> Aucun sous-contrat</span>'}
                        </div>

                        <div class="lc-money-row">
                            <div class="lc-progress">
                                <span style="width:${pct(contract.progress)}%"></span>
                            </div>
                            <span class="lc-progress-text">${pct(contract.progress)}%</span>
                        </div>
                    </div>
                </article>
            `;
        }).join('');

        $$('[data-open-contract]').forEach(area => {
            area.addEventListener('click', () => {
                selectedContractId = Number(area.dataset.openContract);
                renderList();
                renderDetail();
            });
        });

        $$('[data-select-contract]').forEach(check => {
            check.addEventListener('click', event => {
                event.stopPropagation();

                const id = Number(check.dataset.selectContract);

                if (check.checked) selectedContractIds.add(id);
                else selectedContractIds.delete(id);

                updateSelectionBar();
            });
        });

        updateSelectionBar();
    }

    function updateSelectionBar() {
        const bar = $('#selectionBar');
        if (!bar) return;

        const count = selectedContractIds.size;
        bar.classList.toggle('open', count > 0);

        $('#selectionCount').textContent = count <= 1
            ? `${count} contrat sélectionné`
            : `${count} contrats sélectionnés`;
    }

    function renderDetail() {
        const contract = currentContract();
        const detail = $('#contractDetail');

        if (!contract) {
            detail.innerHTML = `<div class="lc-empty">Aucun contrat disponible.</div>`;
            return;
        }

        const subHtml = (contract.sub_contracts || []).length
            ? contract.sub_contracts.map(renderSubContract).join('')
            : `<div class="lc-empty" style="grid-column:1/-1;">Aucun sous-contrat.</div>`;

        detail.innerHTML = `
            <div class="lc-detail-title">
                <div>
                    <h3>${contract.ref} · ${contract.vehicule}</h3>
                    <p>Chauffeur : <strong>${contract.chauffeur}</strong>${contract.phone_ch ? ' · ' + contract.phone_ch : ''}</p>
                </div>

                <div class="lc-detail-actions">
                    <span class="lc-status ${contract.statut}">${statusLabel(contract.statut)}</span>

                    <button type="button" class="lc-btn" id="editMainContractBtn">
                        <i class="fas fa-edit"></i>
                        Modifier
                    </button>

                    <button type="button" class="lc-btn danger" id="closeMainContractBtn">
                        <i class="fas fa-ban"></i>
                        Clôturer
                    </button>
                </div>
            </div>

            <div class="lc-info-grid">
                <div class="lc-info"><span>Type</span><strong>${contract.type_label || '—'}</strong></div>
                <div class="lc-info"><span>Montant total</span><strong>${money(contract.montant_total)}</strong></div>
                <div class="lc-info"><span>Déjà payé</span><strong>${money(contract.total_paye)}</strong></div>
                <div class="lc-info"><span>Restant</span><strong>${money(contract.montant_restant)}</strong></div>
                <div class="lc-info"><span>Versement</span><strong>${money(contract.versement)}</strong></div>
                <div class="lc-info"><span>Fréquence</span><strong>${contract.frequence || '—'}</strong></div>
                <div class="lc-info"><span>Échéance</span><strong>${contract.prochaine_echeance || '—'}</strong></div>
                <div class="lc-info"><span>Fin</span><strong>${contract.date_fin || '—'}</strong></div>
            </div>

            ${renderSpecificites(contract.specificites, 'Spécificités du contrat principal')}

            <div>
                <div class="lc-section-title">
                    <h4><i class="fas fa-layer-group"></i> Sous-contrats</h4>
                    <button type="button" class="lc-btn soft" id="addSubBtn">
                        <i class="fas fa-plus"></i>
                        Ajouter
                    </button>
                </div>

                <div class="lc-sub-list">${subHtml}</div>
            </div>

            <div>
                <div class="lc-section-title">
                    <h4><i class="fas fa-bolt"></i> Règle de coupure du contrat principal</h4>
                    <button type="button" class="lc-btn soft" id="editPolicyBtn">
                        <i class="fas fa-sliders-h"></i>
                        Modifier
                    </button>
                </div>

                ${renderPolicySummary(contract)}
            </div>
        `;

        $('#editMainContractBtn')?.addEventListener('click', () => openEditDrawer(contract));
        $('#closeMainContractBtn')?.addEventListener('click', () => closeContractByStatus(contract, null, 'SOLDE'));
        $('#addSubBtn')?.addEventListener('click', () => openAddSubDrawer(contract));
        $('#editPolicyBtn')?.addEventListener('click', () => openPolicyDrawer(contract));

        $$('[data-edit-sub]').forEach(button => {
            button.addEventListener('click', () => {
                const subId = Number(button.dataset.editSub);
                const found = findSub(subId);
                if (found) openEditDrawer(found.sub, found.contract);
            });
        });

        $$('[data-close-sub]').forEach(button => {
            button.addEventListener('click', () => {
                const subId = Number(button.dataset.closeSub);
                const found = findSub(subId);
                if (found) closeContractByStatus(found.sub, found.contract, 'SOLDE');
            });
        });

        $$('[data-policy-sub]').forEach(button => {
            button.addEventListener('click', () => {
                const subId = Number(button.dataset.policySub);
                const found = findSub(subId);
                if (found) openPolicyDrawer(found.sub, found.contract);
            });
        });
    }

    function cutoffSummaryText(cutoff) {
        if (!cutoff?.enabled) return 'Aucune règle active';

        const action = cutoff.only_when_stopped ? 'Couper à l’arrêt' : 'Couper sans condition d’arrêt';
        const time = cutoff.cutoff_time ? ` à ${cutoff.cutoff_time}` : '';
        const grace = Number(cutoff.grace_days || 0) > 0 ? ` après ${cutoff.grace_days} jour(s) de grâce` : '';

        return `${action}${time}${grace}`;
    }

    function cutoffDaysText(cutoff) {
        const days = {
            monday: 'Lun', tuesday: 'Mar', wednesday: 'Mer', thursday: 'Jeu',
            friday: 'Ven', saturday: 'Sam', sunday: 'Dim'
        };

        return (cutoff?.active_days || []).map(day => days[day] || day).join(', ') || '—';
    }

    function renderSubContract(sub) {
        const subCutoff = sub.cutoff || {};

        return `
            <article class="lc-sub-card">
                <div class="lc-sub-head">
                    <div>
                        <p class="lc-sub-title">
                            <i class="fas fa-file-contract"></i>
                            ${sub.type_label}
                        </p>
                        <p class="lc-sub-meta">${sub.frequence || '—'} · échéance ${sub.prochaine_echeance || '—'}</p>
                    </div>

                    <span class="lc-status ${sub.statut}">${statusLabel(sub.statut)}</span>
                </div>

                <div class="lc-info-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                    <div class="lc-info"><span>Total</span><strong>${money(sub.montant_total)}</strong></div>
                    <div class="lc-info"><span>Payé</span><strong>${money(sub.total_paye || Math.max(0, Number(sub.montant_total || 0) - Number(sub.montant_restant || 0)))}</strong></div>
                    <div class="lc-info"><span>Restant</span><strong>${money(sub.montant_restant)}</strong></div>
                    <div class="lc-info"><span>Versement</span><strong>${money(sub.montant_par_paiement)}</strong></div>
                    <div class="lc-info"><span>Début</span><strong>${sub.date_debut || '—'}</strong></div>
                    <div class="lc-info"><span>Fin</span><strong>${sub.date_fin || '—'}</strong></div>
                    <div class="lc-info"><span>Règle</span><strong>${subCutoff.enabled ? 'Active' : 'Inactive'}</strong></div>
                    <div class="lc-info"><span>Heure</span><strong>${subCutoff.cutoff_time || '—'}</strong></div>
                    <div class="lc-info"><span>Sécurité</span><strong>${subCutoff.only_when_stopped ? 'Couper à l’arrêt' : 'Non imposée'}</strong></div>
                    <div class="lc-info"><span>Jours actifs</span><strong>${cutoffDaysText(subCutoff)}</strong></div>
                </div>

                <div class="lc-policy-box" style="padding:.65rem;">
                    <div class="lc-policy-name">Règle du sous-contrat</div>
                    <span class="lc-policy-help">${cutoffSummaryText(subCutoff)}</span>
                </div>

                ${renderSpecificites(sub.specificites, 'Spécificités')}

                <div class="lc-sub-actions">
                    <button type="button" class="lc-sub-action" data-edit-sub="${sub.id}">
                        <i class="fas fa-edit"></i>
                        Modifier
                    </button>

                    <button type="button" class="lc-sub-action" data-policy-sub="${sub.id}">
                        <i class="fas fa-bolt"></i>
                        Coupure
                    </button>

                    <button type="button" class="lc-sub-action" data-close-sub="${sub.id}">
                        <i class="fas fa-ban"></i>
                        Clôturer
                    </button>
                </div>
            </article>
        `;
    }

    function renderPolicySummary(contract) {
        const cutoff = contract.cutoff || {};
        const activeDays = cutoffDaysText(cutoff);

        return `
            <div class="lc-policy-box">
                <div class="lc-info-grid">
                    <div class="lc-info"><span>Règle</span><strong>${cutoff.enabled ? 'Active' : 'Inactive'}</strong></div>
                    <div class="lc-info"><span>Heure</span><strong>${cutoff.cutoff_time || '—'}</strong></div>
                    <div class="lc-info"><span>Délai grâce</span><strong>${cutoff.grace_days ?? 0} jour(s)</strong></div>
                    <div class="lc-info"><span>Jours actifs</span><strong>${activeDays}</strong></div>
                    <div class="lc-info"><span>Sécurité</span><strong>${cutoff.only_when_stopped ? 'Seulement à l’arrêt' : 'Non imposée'}</strong></div>
                    
                </div>
                <p class="lc-form-help" style="margin-top:.75rem;">
                    Cette règle concerne uniquement ce contrat ou sous-contrat réel. Les sous-contrats ont leurs propres règles indépendantes.
                </p>
            </div>
        `;
    }

    function findSub(subId) {
        for (const contract of contracts) {
            const sub = (contract.sub_contracts || []).find(item => Number(item.id) === Number(subId));
            if (sub) return { contract, sub };
        }

        return null;
    }

    function openDrawer(drawer) {
        $('#drawerBackdrop')?.classList.add('open');
        drawer?.classList.add('open');
    }

    function closeDrawers() {
        $('#drawerBackdrop')?.classList.remove('open');
        $$('.lc-drawer').forEach(drawer => drawer.classList.remove('open'));
    }

    function fillVehicleIdentityFromTracking() {
        const select = $('#createVehicle');
        if (!select) return;

        const option = select.options[select.selectedIndex];

        $('#createImmatriculation').value = option?.dataset?.immat || '';
        $('#createVin').value = option?.dataset?.vin || '';
    }

    function openCreateDrawer() {
        openDrawer($('#createDrawer'));
        fillVehicleIdentityFromTracking();

        const start = today();

        $('#createDateDebut').value = $('#createDateDebut').value || start;
        $('#createProchaineEcheance').value = $('#createProchaineEcheance').value || start;
        $('#createDateFin').value = calculateContractEndDate(start, $('[name="montant_total"]', $('#createContractForm'))?.value, $('[name="montant_paye"]', $('#createContractForm'))?.value, $('[name="montant_par_paiement"]', $('#createContractForm'))?.value, $('[name="frequence"]', $('#createContractForm'))?.value, activePerWeekForForm($('#createContractForm')));
    }

    function openAddSubDrawer(contract) {
        $('#addSubParent').value = contract.id;
        $('#addSubChauffeur').value = contract.chauffeur_id || '';
        $('#addSubVehicleId').value = contract.vehicle_id || '';
        $('#addSubImmatriculation').value = contract.vehicule || '';
        $('#addSubVin').value = contract.vin || '';
        $('#addSubSubtitle').textContent = `${contract.ref} · ${contract.vehicule} · ${contract.chauffeur}`;

        const start = today();

        $('#addSubDateDebut').value = start;
        $('#addSubProchaineEcheance').value = start;
        $('#addSubSpecificites').value = '';
        $('#addSubDateFin').value = calculateContractEndDate(
            start,
            $('[name="montant_total"]', $('#addSubForm'))?.value,
            $('[name="montant_paye"]', $('#addSubForm'))?.value,
            $('[name="montant_par_paiement"]', $('#addSubForm'))?.value,
            $('[name="frequence"]', $('#addSubForm'))?.value,
            activePerWeekForForm($('#addSubForm'))
        );

        console.log('[LEASE_ADD_SUB_CONTEXT]', {
            parent: contract.id,
            chauffeur: contract.chauffeur_id,
            vehicle_id_tracking_local: contract.vehicle_id,
            immatriculation: contract.vehicule,
            vin: contract.vin
        });

        openDrawer($('#addSubDrawer'));
    }

    function setValue(selector, value) {
        const element = $(selector);

        if (!element) {
            return;
        }

        element.value = value ?? '';
    }

    function setText(selector, value) {
        const element = $(selector);

        if (!element) {
            return;
        }

        element.textContent = value ?? '';
    }

    function openEditDrawer(item, parent = null) {
        const isSub = Boolean(parent);

        setText('#editDrawerTitle', isSub ? 'Modifier le sous-contrat' : 'Modifier le contrat');

        const form = $('#editContractForm');
        if (form) {
            form.action = buildUrl(updateUrlTemplate, item.id);
        }

        setValue('#editVehicleId', parent?.vehicle_id || item.vehicle_id || '');
        setValue('#editParent', parent?.id || item.parent || '');
        setValue('#editType', item.type_contrat || '');
        setValue('#editStatus', apiStatus(item.statut));
        setValue('#editChauffeur', parent?.chauffeur_id || item.chauffeur_id || '');
        setValue('#editImmatriculation', parent?.vehicule || item.vehicule || '');
        setValue('#editVin', parent?.vin || item.vin || '');
        setValue('#editTotal', item.montant_total || '');

        const paidAmount = item.total_paye || item.montant_paye || Math.max(
            0,
            Number(item.montant_total || 0) - Number(item.montant_restant || 0)
        );

        setValue('#editPaid', paidAmount);
        setValue('#editRemaining', item.montant_restant || '');
        setValue('#editInstallment', item.versement || item.montant_par_paiement || '');
        setValue('#editFrequency', item.frequence || 'JOURNALIER');

        const startDate = item.date_debut || parent?.date_debut || today();
        setValue('#editStartDate', startDate);

        const endDate = calculateContractEndDate(
            startDate,
            item.montant_total || '',
            paidAmount,
            item.versement || item.montant_par_paiement || '',
            item.frequence || 'JOURNALIER',
            activePerWeekForForm($('#editContractForm'))
        ) || item.date_fin || '';

        setValue('#editEndDate', endDate);
        setValue('#editDueDate', item.prochaine_echeance || today());

        fillSpecificitesEditor($('#editSpecificites')?.closest('[data-specificites-editor]'), item.specificites || {});

        const editForm = $('#editContractForm');
        const hasExistingRule = Boolean(item.cutoff?.rule_id || item.cutoff?.enabled || item.cutoff?.cutoff_time || (item.cutoff?.active_days || []).length);
        const defaultMode = editForm?.querySelector('input[name="edit_cutoff_rule_mode"][value="default"]');
        const customMode = editForm?.querySelector('input[name="edit_cutoff_rule_mode"][value="custom"]');

        if (defaultMode) defaultMode.checked = !hasExistingRule;
        if (customMode) customMode.checked = hasExistingRule;

        setValue('#editCustomRuleEnabled', item.cutoff?.enabled ? '1' : '0');
        setValue('#editCustomRuleTime', item.cutoff?.cutoff_time || '');
        setValue('#editCustomRuleGrace', item.cutoff?.grace_days ?? 0);
        setValue('#editCustomRuleOnlyStopped', item.cutoff?.only_when_stopped ? '1' : '0');

        const editActiveDays = normalizeActiveDays(item.cutoff?.active_days || activeDaysForType(item.type_contrat));
        $$('#editCustomRuleDays input[type="checkbox"]').forEach(input => {
            input.checked = editActiveDays.includes(input.value);
        });

        syncCutoffRuleMode(editForm);

        openDrawer($('#editContractDrawer'));
    }

    function closeContractByStatus(item, parent = null, status = 'SOLDE') {
        const isSub = Boolean(parent);
        const label = isSub ? 'sous-contrat' : 'contrat';

        if (!confirm(`Ce ${label} sera marqué ${status}. Il ne sera pas supprimé définitivement. Continuer ?`)) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = buildUrl(updateUrlTemplate, item.id);
        form.style.display = 'none';

        const payload = {
            _token: csrfToken,
            _method: 'PUT',
            vehicle_id: parent?.vehicle_id || item.vehicle_id || '',
            parent: parent?.id || item.parent || '',
            chauffeur: parent?.chauffeur_id || item.chauffeur_id || '',
            type_contrat: item.type_contrat || '',
            immatriculation: parent?.vehicule || item.vehicule || '',
            vin: parent?.vin || item.vin || '',
            montant_total: item.montant_total || 0,
            montant_paye: item.total_paye || item.montant_paye || Math.max(0, Number(item.montant_total || 0) - Number(item.montant_restant || 0)),
            montant_restant: item.montant_restant || 0,
            montant_par_paiement: item.versement || item.montant_par_paiement || 0,
            frequence: item.frequence || 'JOURNALIER',
            date_debut: item.date_debut || parent?.date_debut || today(),
            date_fin: item.date_fin || parent?.date_fin || today(),
            prochaine_echeance: item.prochaine_echeance || today(),
            specificites: typeof item.specificites === 'object' && item.specificites !== null
                ? JSON.stringify(item.specificites)
                : (item.specificites || ''),
            statut: status,
        };

        Object.entries(payload).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    function openPolicyDrawer(contract, parent = null) {
        $('#policyContractId').value = contract.id;
        $('#policyVehicleId').value = contract.vehicle_id || '';
        $('#policyDrawerSubtitle').textContent = parent ? `${parent.ref} · sous-contrat ${contract.type_label} · ${parent.vehicule}` : `${contract.ref} · ${contract.vehicule} · ${contract.chauffeur}`;

        $('#policyEnabled').value = contract.cutoff?.enabled ? '1' : '0';
        $('#policyTime').value = contract.cutoff?.cutoff_time || '';
        $('#policyGrace').value = contract.cutoff?.grace_days ?? 0;
        $('#policyOnlyStopped').value = contract.cutoff?.only_when_stopped ? '1' : '0';

        const activeDays = contract.cutoff?.active_days || [];
        $$('#policyActiveDays input[type="checkbox"]').forEach(input => {
            input.checked = activeDays.length ? activeDays.includes(input.value) : input.value !== 'sunday';
        });

        openDrawer($('#policyDrawer'));
    }

    function openBulkPolicyDrawer() {
        if (!selectedContractIds.size) return;

        const selectedIds = Array.from(selectedContractIds).map(id => Number(id));

        $('#bulkSelectedContracts').innerHTML = selectedIds
            .map(id => `<input type="hidden" name="contract_ids[]" value="${id}">`)
            .join('');

        $('#bulkPolicySubtitle').textContent = `${selectedIds.length} contrat(s) réel(s) sélectionné(s). La règle sera enregistrée sur chaque contrat, pas sur le véhicule global.`;

        openDrawer($('#bulkPolicyDrawer'));
    }

    function addSubForm() {
        const template = $('#subFormTemplate');
        const node = template.content.cloneNode(true);
        const card = $('[data-subform]', node);
        const index = subFormIndex++;

        $('[data-sub-type]', card).setAttribute('name', `sous_contrats[${index}][type_contrat]`);
        $('[data-sub-total]', card).setAttribute('name', `sous_contrats[${index}][montant_total]`);
        $('[data-sub-paid]', card).setAttribute('name', `sous_contrats[${index}][montant_paye]`);
        $('[data-sub-installment]', card).setAttribute('name', `sous_contrats[${index}][montant_par_paiement]`);
        $('[data-sub-frequency]', card).setAttribute('name', `sous_contrats[${index}][frequence]`);
        $('[data-sub-start]', card).setAttribute('name', `sous_contrats[${index}][date_debut]`);
        $('[data-sub-end]', card).setAttribute('name', `sous_contrats[${index}][date_fin]`);
        $('[data-sub-due]', card).setAttribute('name', `sous_contrats[${index}][prochaine_echeance]`);
        $('[data-sub-specificites]', card).setAttribute('name', `sous_contrats[${index}][specificites]`);
        $('[data-sub-apply-default-cutoff-rule]', card)?.setAttribute('name', `sous_contrats[${index}][apply_default_cutoff_rule]`);
        $('[data-sub-customize-cutoff-rule]', card)?.setAttribute('name', `sous_contrats[${index}][customize_cutoff_rule]`);
        $$('[data-sub-cutoff-rule-mode]', card).forEach(input => input.setAttribute('name', `sous_contrats[${index}][cutoff_rule_mode]`));
        $('[data-sub-custom-rule-enabled]', card)?.setAttribute('name', `sous_contrats[${index}][custom_rule_is_enabled]`);
        $('[data-sub-custom-rule-time]', card)?.setAttribute('name', `sous_contrats[${index}][custom_rule_cutoff_time]`);
        $('[data-sub-custom-rule-grace]', card)?.setAttribute('name', `sous_contrats[${index}][custom_rule_grace_days]`);
        $$('[data-sub-custom-rule-active-day]', card).forEach(input => input.setAttribute('name', `sous_contrats[${index}][custom_rule_active_days][]`));
        $('[data-sub-custom-rule-only-stopped]', card)?.setAttribute('name', `sous_contrats[${index}][custom_rule_only_when_stopped]`);

        const start = today();

        $('[data-sub-start]', card).value = start;
        $('[data-sub-due]', card).value = start;
        $('[data-sub-end]', card).value = calculateContractEndDate(start, $('[data-sub-total]', card).value, $('[data-sub-paid]', card).value, $('[data-sub-installment]', card).value, $('[data-sub-frequency]', card).value, activePerWeekForForm(card));

        bindAutoEndDate(card, {
            start: '[data-sub-start]',
            end: '[data-sub-end]',
            total: '[data-sub-total]',
            paid: '[data-sub-paid]',
            installment: '[data-sub-installment]',
            frequency: '[data-sub-frequency]',
            type: '[data-sub-type]',
        });
        syncCutoffRuleMode(card);

        const select = $('[data-sub-type]', card);
        const title = $('[data-subform-title]', card);

        function refreshTitle() {
            title.textContent = 'Sous-contrat · ' + (select.options[select.selectedIndex]?.textContent || '');
        }

        select.addEventListener('change', refreshTitle);
        refreshTitle();

        $('[data-remove-subform]', card).addEventListener('click', () => card.remove());

        $('#createSubList').appendChild(card);
        bootSpecificitesEditors(card);
    }

    function syncCutoffRuleMode(root) {
        const customRadio = root.querySelector('[data-toggle-custom-rule]');
        const customBox = root.querySelector('[data-custom-rule-box]');
        const applyDefaultInput = root.querySelector('[data-apply-default-cutoff-rule], [data-sub-apply-default-cutoff-rule]');
        const customizeInput = root.querySelector('[data-customize-cutoff-rule], [data-sub-customize-cutoff-rule]');
        const isCustom = Boolean(customRadio?.checked);

        if (applyDefaultInput) applyDefaultInput.value = isCustom ? '0' : '1';
        if (customizeInput) customizeInput.value = isCustom ? '1' : '0';
        if (customBox) customBox.classList.toggle('open', isCustom);
    }


    document.addEventListener('click', function (event) {
        const addButton = event.target.closest('[data-add-specificite]');
        if (addButton) {
            const editor = addButton.closest('[data-specificites-editor]');
            addSpecificiteRow(editor);
            syncSpecificitesEditor(editor);
            return;
        }

        const removeButton = event.target.closest('[data-remove-specificite]');
        if (removeButton) {
            const editor = removeButton.closest('[data-specificites-editor]');
            removeButton.closest('.lc-specificite-row')?.remove();
            if (!$$('[data-specificite-key]', editor).length) addSpecificiteRow(editor);
            syncSpecificitesEditor(editor);
        }
    });

    document.addEventListener('input', function (event) {
        const specificiteInput = event.target.closest('[data-specificite-key], [data-specificite-value]');
        if (!specificiteInput) return;

        const editor = specificiteInput.closest('[data-specificites-editor]');
        syncSpecificitesEditor(editor);
    });

    document.addEventListener('change', function (event) {
        const keyInput = event.target.closest('[data-specificite-key]');
        if (!keyInput) return;
        keyInput.value = formatSpecificiteLabel(keyInput.value);
        syncSpecificitesEditor(keyInput.closest('[data-specificites-editor]'));
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => syncSpecificitesInForm(form));
    });

    document.querySelectorAll('form').forEach(syncCutoffRuleMode);

    document.addEventListener('change', function (event) {
        const modeRadio = event.target.closest('[data-cutoff-rule-mode], [data-sub-cutoff-rule-mode]');
        if (!modeRadio) return;

        const root = modeRadio.closest('[data-subform]') || modeRadio.closest('form') || document;
        syncCutoffRuleMode(root);
    });


    function normalizeActiveDays(days) {
        const allowed = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        const normalized = (days || [])
            .map(day => String(day || '').toLowerCase())
            .filter(day => allowed.includes(day));

        return [...new Set(normalized)].length ? [...new Set(normalized)] : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    }

    function activeDaysForType(typeId) {
        const rule = defaultCutoffRules[String(typeId)] || defaultCutoffRules[Number(typeId)] || null;
        return normalizeActiveDays(rule?.active_days || []);
    }

    function activeDaysForForm(root) {
        if (!root) return normalizeActiveDays([]);

        const customMode = root.querySelector('[data-toggle-custom-rule]')?.checked;

        if (customMode) {
            const checkedDays = Array.from(root.querySelectorAll('[data-custom-rule-active-day]:checked, [data-sub-custom-rule-active-day]:checked, input[name$="[custom_rule_active_days][]"]:checked, input[name="custom_rule_active_days[]"]:checked'))
                .map(input => input.value);

            return normalizeActiveDays(checkedDays);
        }

        const typeValue = root.querySelector('[name="type_contrat"], [data-sub-type]')?.value;
        return activeDaysForType(typeValue);
    }

    function activeDaysForItem(item) {
        return normalizeActiveDays(item?.cutoff?.active_days || activeDaysForType(item?.type_contrat));
    }

    function dayName(date) {
        return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'][date.getDay()];
    }

    function moveToNextActiveDay(date, activeDays) {
        const result = new Date(date.getTime());
        let guard = 0;

        while (!activeDays.includes(dayName(result)) && guard < 14) {
            result.setDate(result.getDate() + 1);
            guard++;
        }

        return result;
    }

    function addActivePaymentDays(start, periodsToAdd, activeDays) {
        const result = moveToNextActiveDay(start, activeDays);
        let added = 0;

        while (added < periodsToAdd) {
            result.setDate(result.getDate() + 1);

            if (activeDays.includes(dayName(result))) {
                added++;
            }
        }

        return result;
    }

    function toDateInputValue(date) {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    // Nombre de jours d'activité dans la semaine (1..7), lu depuis le formulaire.
    function activePerWeekForForm(root) {
        if (!root) return 7;
        const el = root.querySelector('[name="jours_actifs_semaine"], [data-sub-active-per-week]');
        const n = parseInt(el?.value, 10);
        return (Number.isFinite(n) && n >= 1 && n <= 7) ? n : 7;
    }

    /*
     * Calcul de la date de fin.
     *
     * JOURNALIER : jour par jour, en ne comptant que N jours actifs par semaine
     * (les 7-N derniers jours du bloc de 7 sont chômés). date_debut = jour actif #1.
     * Le P-ème (dernier) paiement tombe à :
     *     offset = floor((P-1)/N) * 7 + ((P-1) mod N)   jours après date_debut.
     * Ex : P=30, N=6, début lundi 20/07 -> offset=33 -> 22/08 (samedi).
     *
     * HEBDOMADAIRE / MENSUEL : une échéance par semaine / par mois (N ignoré).
     *
     * P (nombre de paiements) = ceil((montant_total - montant_payé) / montant_par_paiement).
     */
    function calculateContractEndDate(dateDebut, total, paid, installment, frequency, activeDaysPerWeek = 7) {
        if (!dateDebut) return '';

        const amountTotal = Number(total || 0);
        const amountPaid  = Number(paid || 0);
        const perPayment  = Number(installment || 0);
        const remaining   = Math.max(0, amountTotal - amountPaid);
        const start = new Date(`${dateDebut}T00:00:00`);
        if (Number.isNaN(start.getTime())) return '';

        let N = parseInt(activeDaysPerWeek, 10);
        if (!Number.isFinite(N) || N < 1) N = 7;
        if (N > 7) N = 7;

        if (remaining <= 0 || perPayment <= 0) return toDateInputValue(start);

        const payments = Math.ceil(remaining / perPayment);
        const periods  = Math.max(0, payments - 1);
        let result = new Date(start.getTime());

        switch ((frequency || 'JOURNALIER').toUpperCase()) {
            case 'HEBDOMADAIRE':
                result.setDate(result.getDate() + periods * 7);
                break;
            case 'MENSUEL':
                result = addMonthsNoOverflow(result, periods);
                break;
            case 'JOURNALIER':
            default: {
                const offset = Math.floor(periods / N) * 7 + (periods % N);
                result.setDate(result.getDate() + offset);
                break;
            }
        }

        return toDateInputValue(result);
    }

    function bindAutoEndDate(root, selectors) {
        if (!root) return;

        const start = root.querySelector(selectors.start);
        const end = root.querySelector(selectors.end);
        const total = root.querySelector(selectors.total);
        const paid = root.querySelector(selectors.paid);
        const installment = root.querySelector(selectors.installment);
        const frequency = root.querySelector(selectors.frequency);
        const type = selectors.type ? root.querySelector(selectors.type) : root.querySelector('[name="type_contrat"], [data-sub-type]');

        if (!start || !end || !total || !installment || !frequency) return;

        const perWeek = root.querySelector('[name="jours_actifs_semaine"], [data-sub-active-per-week]');

        const refresh = () => {
            end.value = calculateContractEndDate(
                start.value,
                total.value,
                paid?.value || 0,
                installment.value,
                frequency.value,
                activePerWeekForForm(root)
            );
        };

        [start, total, paid, installment, frequency, type, perWeek].filter(Boolean).forEach(input => {
            input.addEventListener('input', refresh);
            input.addEventListener('change', refresh);
        });

        refresh();
    }

    function bootAutoEndDates() {
        bindAutoEndDate($('#createContractForm'), {
            start: '[name="date_debut"]',
            end: '[name="date_fin"]',
            total: '[name="montant_total"]',
            paid: '[name="montant_paye"]',
            installment: '[name="montant_par_paiement"]',
            frequency: '[name="frequence"]',
            type: '[name="type_contrat"]',
        });

        bindAutoEndDate($('#addSubForm'), {
            start: '[name="date_debut"]',
            end: '[name="date_fin"]',
            total: '[name="montant_total"]',
            paid: '[name="montant_paye"]',
            installment: '[name="montant_par_paiement"]',
            frequency: '[name="frequence"]',
            type: '[name="type_contrat"]',
        });

        bindAutoEndDate($('#editContractForm'), {
            start: '[name="date_debut"]',
            end: '[name="date_fin"]',
            total: '[name="montant_total"]',
            paid: '[name="montant_paye"]',
            installment: '[name="montant_par_paiement"]',
            frequency: '[name="frequence"]',
            type: '[name="type_contrat"]',
        });
    }

    function boot() {
        $('#contractSearch')?.addEventListener('input', renderList);
        $('#statusFilter')?.addEventListener('change', renderList);
        $('#subTypeFilter')?.addEventListener('change', renderList);
        $('#cutoffFilter')?.addEventListener('change', renderList);

        $('#openCreateBtn')?.addEventListener('click', openCreateDrawer);
        $('#createVehicle')?.addEventListener('change', fillVehicleIdentityFromTracking);
        $('#createAddSubBtn')?.addEventListener('click', addSubForm);
        bootAutoEndDates();
        bootSpecificitesEditors();

        $('#addSubToCurrentBtn')?.addEventListener('click', () => {
            const contract = currentContract();
            if (contract) openAddSubDrawer(contract);
        });

        $('#openPolicyBtn')?.addEventListener('click', () => {
            const contract = currentContract();
            if (contract) openPolicyDrawer(contract);
        });

        $('#openContractPolicyBtn')?.addEventListener('click', () => {
            const contract = currentContract();
            if (contract) openPolicyDrawer(contract);
        });

        $('#bulkPolicyBtn')?.addEventListener('click', openBulkPolicyDrawer);

        $('#clearSelectionBtn')?.addEventListener('click', () => {
            selectedContractIds.clear();
            renderList();
            updateSelectionBar();
        });

        $('#drawerBackdrop')?.addEventListener('click', closeDrawers);

        $$('[data-close-drawer]').forEach(button => {
            button.addEventListener('click', closeDrawers);
        });

        renderList();
        renderDetail();
    }

    boot();
});
</script>
@endpush