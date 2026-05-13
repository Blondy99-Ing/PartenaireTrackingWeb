<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique complet des décisions de coupure lease.
 *
 * Cette table est l'audit métier : elle doit dire qui/quoi a déclenché, quelle
 * règle spécifique a autorisé, pourquoi la coupure a été planifiée, annulée,
 * envoyée ou confirmée.
 */
class LeaseCutoffHistory extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_histories';

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
        'rule_id',
        'contract_rule_id',
        'scheduled_for',
        'detected_at',
        'cutoff_requested_at',
        'cutoff_executed_at',
        'status',
        'reason',
        'forgiven_by_user_id',
        'forgiven_by_name',
        'forgiven_at',
        'speed_at_check',
        'ignition_state',
        'payment_status_snapshot',
        'command_response',
        'notes',
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
        'rule_id' => 'integer',
        'contract_rule_id' => 'integer',
        'scheduled_for' => 'datetime',
        'detected_at' => 'datetime',
        'cutoff_requested_at' => 'datetime',
        'cutoff_executed_at' => 'datetime',
        'forgiven_at' => 'datetime',
        'speed_at_check' => 'decimal:2',
        'payment_status_snapshot' => 'array',
        'command_response' => 'array',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }

    /** Ancienne règle véhicule, conservée pour lecture historique. */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffRule::class, 'rule_id');
    }

    /** Nouvelle règle spécifique ayant autorisé la décision. */
    public function contractRule(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffContractRule::class, 'contract_rule_id');
    }

    public function contractLink(): BelongsTo
    {
        return $this->belongsTo(LeaseContractLink::class, 'contract_link_id');
    }
}
