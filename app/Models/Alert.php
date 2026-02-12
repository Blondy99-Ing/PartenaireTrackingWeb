<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $table = 'alerts';
    public $timestamps = true;

    /**
     * ✅ Only these types should be shown in partner UI/API (your requirement)
     * Must match alerts.alert_type ENUM values.
     */
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
        'latitude'   => 'decimal:8',
        'longitude'  => 'decimal:8',
    ];

    protected $appends = [
        'type',
        'location',
    ];

    // ─────────────────────────────────────────────────────────────
    // (Optional) If you want to auto-hide other types when serializing JSON
    // NOTE: This does NOT filter DB queries, only hides the field from output.
    // ─────────────────────────────────────────────────────────────
    // protected $hidden = ['alert_type']; // (not needed usually)

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    /**
     * ✅ Use this in controller:
     * Alert::query()->partnerVisible()->...
     */
    public function scopePartnerVisible(Builder $query): Builder
    {
        return $query->whereIn('alert_type', self::PARTNER_VISIBLE_TYPES);
    }

    /**
     * ⚠️ NOT recommended unless you want to globally restrict ALL Alert queries everywhere.
     * If you enable it, even admin pages will never see other types.
     */
    /*
    protected static function booted(): void
    {
        static::addGlobalScope('partner_visible_only', function (Builder $query) {
            $query->whereIn('alert_type', self::PARTNER_VISIBLE_TYPES);
        });
    }
    */

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
        return $this->belongsTo(\App\Models\User::class, 'processed_by');
    }
}
