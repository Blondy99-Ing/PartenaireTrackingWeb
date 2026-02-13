<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Commande extends Model
{
    use HasFactory;

    protected $table = 'commands';

    protected $fillable = [
        'user_id',
        'employe_id',
        'vehicule_id',
        'CmdNo',
        'status',
        'type_commande',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employe()
    {
        return $this->belongsTo(Employe::class);
    }

    public function vehicule()
    {
        return $this->belongsTo(Voiture::class, 'vehicule_id');
    }
}
