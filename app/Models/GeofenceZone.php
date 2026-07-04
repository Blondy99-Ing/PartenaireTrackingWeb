<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceZone extends Model
{
    protected $table = 'geofence_zones';

    protected $fillable = [
        'partner_id',
        'name',
        'code',
        'zone',
        'created_by',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getZoneArrayAttribute(): array
    {
        $zone = json_decode($this->zone, true);

        return is_array($zone) ? $zone : [];
    }
}