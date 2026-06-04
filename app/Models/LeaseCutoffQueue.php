<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File d'attente de coupure lease.
 *
 * La queue évite de couper immédiatement : elle permet de revérifier le paiement,
 * d'attendre l'arrêt du véhicule, d'éviter les doublons et de tracer la décision.
 */
class LeaseCutoffQueue extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_queue';

    protected $fillable = [
        'partner_id',
        'vehicle_id',
        'contract_id',
        'lease_id',
        'lease_date_echeance',
        'contract_link_id',
        'parent_contract_id',
        'type_contrat_id',
        'type_contrat_label',
        'contract_kind',
        'trigger_label',
        'trigger_payload',
        'contract_rule_id',
        'history_id',
        'scheduled_for',
        'status',
        'last_checked_at',
        'retry_count',
        'next_check_at',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'vehicle_id' => 'integer',
        'contract_id' => 'integer',
        'lease_id' => 'integer',
        'lease_date_echeance' => 'date',
        'contract_link_id' => 'integer',
        'parent_contract_id' => 'integer',
        'type_contrat_id' => 'integer',
        'trigger_payload' => 'array',
        'contract_rule_id' => 'integer',
        'history_id' => 'integer',
        'scheduled_for' => 'datetime',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }



    /** Nouvelle règle métier spécifique au contrat/sous-contrat réel. */
    public function contractRule(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffContractRule::class, 'contract_rule_id');
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffHistory::class, 'history_id');
    }

    public function contractLink(): BelongsTo
    {
        return $this->belongsTo(LeaseContractLink::class, 'contract_link_id');
    }
}
