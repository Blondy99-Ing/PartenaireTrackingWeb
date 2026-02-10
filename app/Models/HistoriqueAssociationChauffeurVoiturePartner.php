<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueAssociationChauffeurVoiturePartner extends Model
{
    protected $table = 'historique_association_chauffeur_voiture_partner';

    protected $fillable = [
         'partner_id',
        'voiture_id',
        'chauffeur_id',
        'assigned_by',
        'started_at',
        'ended_at',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    public function voiture(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'voiture_id');
    }

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
