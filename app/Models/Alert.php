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
    ];

    protected $casts = [
        'alerted_at' => 'datetime',
        'read' => 'boolean',
        'sent' => 'boolean',
    ];

    /**
     * Accessor : expose ->type en priorité, sinon ->alert_type
     * (évite le null si ton champ s'appelle alert_type en base)
     */
    public function getTypeAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }

        return $this->attributes['alert_type'] ?? null;
    }

    /**
     * Accessor pour message/location (compatibilité si tu utilises l'un ou l'autre)
     */
    public function getLocationAttribute($value)
    {
        if (!empty($value)) {
            return $value;
        }

        return $this->attributes['message'] ?? null;
    }

    /**
     * Relation vers la voiture
     */
    public function voiture()
    {
        return $this->belongsTo(\App\Models\Voiture::class, 'voiture_id');
    }
}
