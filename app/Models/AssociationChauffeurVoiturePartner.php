<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssociationChauffeurVoiturePartner extends Model
{
    protected $table = 'association_chauffeur_voiture_partner';

    protected $fillable = [
        'voiture_id',
        'chauffeur_id',
        'assigned_by',
        'assigned_at',
        'note',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
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
