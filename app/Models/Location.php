<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'locations';
    public $timestamps = false;

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
        'processed',
        'trip_id',
    ];

    protected $casts = [
        'longitude' => 'float',
        'latitude'  => 'float',
        'speed'     => 'float',
        'datetime'  => 'datetime',
        'sys_time'  => 'datetime',
        'heart_time'=> 'datetime',
        'processed' => 'boolean',
    ];
}