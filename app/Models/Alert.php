<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Voiture;

class Alert extends Model
{
    protected $fillable = [
        'voiture_id',
        'message',
        'alerted_at',
        'sent',
    ];

    protected $casts = [
        'alerted_at' => 'datetime',
        'sent' => 'boolean',
    ];

    public function voiture()
    {
        return $this->belongsTo(Voiture::class, 'voiture_id');
    }
}
