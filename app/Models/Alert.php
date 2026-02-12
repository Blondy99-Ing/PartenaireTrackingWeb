<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $table = 'alerts';

    protected $fillable = [
        'voiture_id',
        'alert_type',
        'message',
        'alerted_at',
        'sent',
        'read',
        'processed',
        'processed_by',
        'latitude',
        'longitude',
        'alert_status',
    ];

    protected $casts = [
        'alerted_at' => 'datetime',
        'read'       => 'boolean',
        'sent'       => 'boolean',
        'processed'  => 'boolean',
        'latitude'   => 'float',
        'longitude'  => 'float',
    ];

    public function voiture()
    {
        return $this->belongsTo(\App\Models\Voiture::class, 'voiture_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(\App\Models\Employe::class, 'processed_by');
    }




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
}