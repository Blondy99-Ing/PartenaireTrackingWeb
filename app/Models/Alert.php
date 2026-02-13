<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $table = 'alerts';
    public $timestamps = true;

    public const PARTNER_VISIBLE_TYPES = [
        'geofence',
        'speed',
        'time_zone',
        'safe_zone',
    ];

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
        'sent'       => 'boolean',
        'read'       => 'boolean',
        'processed'  => 'boolean',
        'latitude'   => 'float',
        'longitude'  => 'float',
    ];

    protected $appends = [
        'type',
        'location',
    ];

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopePartnerVisible(Builder $query): Builder
    {
        return $query->whereIn('alert_type', self::PARTNER_VISIBLE_TYPES);
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors / Mutators
    // ─────────────────────────────────────────────────────────────

    public function getTypeAttribute(): ?string
    {
        return $this->attributes['alert_type'] ?? null;
    }

    public function setTypeAttribute($value): void
    {
        $this->attributes['alert_type'] = $value;
    }

    public function getLocationAttribute(): ?string
    {
        $lat = $this->attributes['latitude'] ?? null;
        $lng = $this->attributes['longitude'] ?? null;

        if ($lat !== null && $lng !== null) {
            return $lat . ',' . $lng;
        }

        return $this->attributes['message'] ?? null;
    }

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function voiture(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Voiture::class, 'voiture_id');
    }

    public function processedBy(): BelongsTo
    {
        // ✅ par défaut: User
        return $this->belongsTo(\App\Models\User::class, 'processed_by');

        // Si ta clé processed_by pointe vers employes.id, remplace par :
        // return $this->belongsTo(\App\Models\Employe::class, 'processed_by');
    }
}