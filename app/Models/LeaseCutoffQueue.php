<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseCutoffQueue extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_queue';

    protected $fillable = [
        // Partenaire propriétaire du véhicule
        'partner_id',

        // Véhicule à couper
        'vehicle_id',

        // Contrat lease concerné
        'contract_id',

        // Ligne de lease / échéance concernée
        'lease_id',

        // Règle ayant déclenché la mise en queue
        'rule_id',

        // Historique lié à cette demande de coupure
        'history_id',

        // Date/heure théorique de coupure
        'scheduled_for',

        // Etat courant de la ligne dans la queue
        'status',

        // Dernière vérification du cron
        'last_checked_at',

        // Nombre de tentatives / vérifications
        'retry_count',

        // Prochaine vérification prévue
        'next_check_at',
    ];

    protected $casts = [
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

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffRule::class, 'rule_id');
    }

    public function history(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffHistory::class, 'history_id');
    }
}