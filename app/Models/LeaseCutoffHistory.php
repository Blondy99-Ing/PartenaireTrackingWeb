<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseCutoffHistory extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_histories';

    protected $fillable = [
        // Partenaire propriétaire du véhicule
        'partner_id',

        // Véhicule concerné
        'vehicle_id',

        // Contrat lease lié à cet événement
        'contract_id',

        // Ligne lease / échéance concernée
        'lease_id',

        // Règle de coupure utilisée
        'rule_id',

        // Date/heure théorique de coupure
        'scheduled_for',

        // Date/heure de détection du véhicule à couper
        'detected_at',

        // Date/heure où la commande a été demandée
        'cutoff_requested_at',

        // Date/heure de coupure réelle
        'cutoff_executed_at',

        // Etat global de l'événement
        'status',

        // Raison métier ou technique
        'reason',

        // Vitesse observée lors du contrôle
        'speed_at_check',

        // Etat du moteur/contact
        'ignition_state',

        // Snapshot JSON de l'état de paiement
        'payment_status_snapshot',

        // Réponse JSON de la commande de coupure
        'command_response',

        // Notes complémentaires
        'notes',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'detected_at' => 'datetime',
        'cutoff_requested_at' => 'datetime',
        'cutoff_executed_at' => 'datetime',
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

    
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffRule::class, 'rule_id');
    }
}