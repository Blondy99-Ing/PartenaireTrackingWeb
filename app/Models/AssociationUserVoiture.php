<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssociationUserVoiture extends Model
{
    protected $table = 'association_user_voitures';
    protected $fillable = ['user_id', 'voiture_id'];
    public $timestamps = true;
}
