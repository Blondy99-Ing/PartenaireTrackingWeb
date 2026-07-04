<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modèle LeaseContractLink.
 *
 * Rôle : relier les contrats/sous-contrats Recouvrement aux véhicules Tracking.
 *
 * Règle de coupure clarifiée :
 * le système ne coupe pas parce qu'un type général existe. Il coupe seulement
 * si cette ligne précise, contrat MAIN ou sous-contrat SUB, possède une règle
 * active dans lease_cutoff_contract_rules.
 */
class LeaseContractLink extends Model
{
    public const KIND_MAIN = 'MAIN';
    public const KIND_SUB = 'SUB';

    protected $table = 'lease_contract_links';

    protected $fillable = [
        'partner_id',
        'vehicle_id',
        'driver_id',
        'recouvrement_driver_id',
        'source_contract_id',
        'source_parent_contract_id',
        'contract_kind',
        'type_contrat_id',
        'type_contrat_label',
        'immatriculation',
        'vin',
        'status',
        'last_payment_status',
        'last_snapshot',
        'last_payload',
        'last_synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'vehicle_id' => 'integer',
        'driver_id' => 'integer',
        'recouvrement_driver_id' => 'integer',
        'source_contract_id' => 'integer',
        'source_parent_contract_id' => 'integer',
        'type_contrat_id' => 'integer',
        'last_snapshot' => 'array',
        'last_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_parent_contract_id', 'source_contract_id')
            ->whereColumn('lease_contract_links.partner_id', 'lease_contract_links.partner_id');
    }

    public function subContracts(): HasMany
    {
        return $this->hasMany(self::class, 'source_parent_contract_id', 'source_contract_id')
            ->where('contract_kind', self::KIND_SUB)
            ->where('status', '!=', 'DELETED');
    }

    public function cutoffRule(): HasOne
    {
        return $this->hasOne(LeaseCutoffContractRule::class, 'contract_link_id');
    }

    public function activeCutoffRule(): HasOne
    {
        return $this->hasOne(LeaseCutoffContractRule::class, 'contract_link_id')
            ->where('is_enabled', true);
    }

    public function isMainContract(): bool
    {
        return $this->contract_kind === self::KIND_MAIN;
    }

    public function isSubContract(): bool
    {
        return $this->contract_kind === self::KIND_SUB;
    }

    public function displayTypeLabel(): string
    {
        return trim((string) $this->type_contrat_label) !== ''
            ? trim((string) $this->type_contrat_label)
            : 'Contrat #' . $this->source_contract_id;
    }
}
