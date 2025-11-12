<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'locations'; // Nom de la table

    public $timestamps = false; // On ne gère pas updated_at automatiquement

    protected $fillable = [
        'sys_time',
        'user_name',
        'longitude',
        'latitude',
        'datetime',
        'heart_time',
        'speed',
        'status',
        'direction',
        'mac_id_gps',
        'created_at',
    ];

    protected $casts = [
        'longitude' => 'float',
        'latitude' => 'float',
        'speed' => 'float',
        'datetime' => 'datetime',
        'created_at' => 'datetime',
    ];
}
