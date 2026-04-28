<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseCutoffRule extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_rules';

    protected $fillable = [
        // Partenaire auquel appartient cette règle
        'partner_id',

        // Véhicule concerné par la règle
        'vehicle_id',

        // Indique si la coupure auto est active pour ce véhicule
        'is_enabled',

        // Heure de coupure définie pour ce véhicule
        'cutoff_time',

        // Fuseau horaire d'interprétation de l'heure
        'timezone',

        // Utilisateur qui a créé la règle
        'created_by',

        // Utilisateur qui a modifié la règle
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

   
    /**
     * Véhicule concerné par la règle.
     * IMPORTANT : dans votre projet, le modèle est Voiture et non Vehicle.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }
    

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}