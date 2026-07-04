<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trajet extends Model
{
    use HasFactory;

    protected $table = 'trips';

    protected $fillable = [
        'vehicle_id',
        'mac_id_gps',
        'start_time',
        'end_time',
        'duration_minutes',
        'start_latitude',
        'start_longitude',
        'start_address',
        'end_latitude',
        'end_longitude',
        'end_address',
        'total_distance_km',
        'avg_speed_kmh',
        'max_speed_kmh',
        'waypoint_count',
    ];

    public function voiture()
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }
}
