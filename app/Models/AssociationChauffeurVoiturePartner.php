<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssociationChauffeurVoiturePartner extends Model
{
    use HasFactory;

    protected $table = 'association_chauffeur_voiture_partner';

    /**
     * If your table does NOT have created_at / updated_at, set this to false.
     * (From your phpMyAdmin screenshot, it DOES have created_at & updated_at,
     * so we keep timestamps enabled.)
     */
    public $timestamps = true;

    protected $fillable = [
        'voiture_id',
        'chauffeur_id',
        'assigned_by',
        'assigned_at',
        'note',
    ];

    protected $casts = [
        'voiture_id'   => 'integer',
        'chauffeur_id' => 'integer',
        'assigned_by'  => 'integer',
        'assigned_at'  => 'datetime',
    ];

    /**
     * Vehicle linked to this assignment
     */
    public function voiture(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'voiture_id');
    }

    /**
     * Driver (user) assigned to the vehicle
     */
    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    /**
     * User who made the assignment (usually partner/admin)
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
