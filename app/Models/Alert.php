<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $table = 'alerts';

    protected $fillable = [
        'voiture_id',
        'type',         // support direct
        'alert_type',   // support legacy column name
        'message',
        'location',
        'alerted_at',
        'sent',
        'read',
        'processed',      // corrigé
        'processed_by',   // clé étrangère vers employe
    ];

    protected $casts = [
        'alerted_at' => 'datetime',
        'read' => 'boolean',
        'sent' => 'boolean',
        'processed' => 'boolean', // ajouté pour cohérence
    ];

    /**
     * Accessor : expose ->type en priorité, sinon ->alert_type
     */
    public function getTypeAttribute($value)
    {
        return $value ?: ($this->attributes['alert_type'] ?? null);
    }

    /**
     * Accessor pour message/location
     */
    public function getLocationAttribute($value)
    {
        return $value ?: ($this->attributes['message'] ?? null);
    }

    /**
     * Relation vers la voiture
     */
    public function voiture()
    {
        return $this->belongsTo(\App\Models\Voiture::class, 'voiture_id');
    }

    /**
     * Relation vers l'employé qui a traité l'alerte
     */
    public function processedBy()
    {
        return $this->belongsTo(\App\Models\Employe::class, 'processed_by');
    }



    
}
