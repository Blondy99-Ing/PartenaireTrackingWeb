<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Règle de coupure spécifique à un contrat ou sous-contrat réel.
 *
 * Point métier fondamental :
 * cette table remplace la décision dangereuse "véhicule + type général".
 * Une coupure automatique ne peut être planifiée que si la ligne exacte
 * lease_contract_links possède une règle active ici.
 */
class LeaseCutoffContractRule extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_contract_rules';

    protected $fillable = [
        'partner_id',
        'vehicle_id',
        'driver_id',
        'contract_link_id',
        'source_contract_id',
        'source_parent_contract_id',
        'contract_kind',
        'type_contrat_id',
        'type_contrat_label',
        'is_enabled',
        'cutoff_time',
        'timezone',
        'grace_days',
        'only_when_stopped',
        'notify_before_cutoff',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'vehicle_id' => 'integer',
        'driver_id' => 'integer',
        'contract_link_id' => 'integer',
        'source_contract_id' => 'integer',
        'source_parent_contract_id' => 'integer',
        'type_contrat_id' => 'integer',
        'is_enabled' => 'boolean',
        'grace_days' => 'integer',
        'only_when_stopped' => 'boolean',
        'notify_before_cutoff' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
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

    public function contractLink(): BelongsTo
    {
        return $this->belongsTo(LeaseContractLink::class, 'contract_link_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function effectiveCutoffTime(): ?string
    {
        return $this->cutoff_time ? substr((string) $this->cutoff_time, 0, 5) : null;
    }

    public function effectiveTimezone(): string
    {
        return $this->timezone ?: config('app.timezone', 'Africa/Douala');
    }

    public function isMainContractRule(): bool
    {
        return $this->contract_kind === LeaseContractLink::KIND_MAIN;
    }

    public function isSubContractRule(): bool
    {
        return $this->contract_kind === LeaseContractLink::KIND_SUB;
    }
}
