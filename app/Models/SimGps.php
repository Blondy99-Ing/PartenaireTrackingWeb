<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimGps extends Model
{
    protected $table = 'sim_gps';

    protected $fillable = [
        'objectid',
        'mac_id',
        'sim_number',
        'account_name',
    ];
}
