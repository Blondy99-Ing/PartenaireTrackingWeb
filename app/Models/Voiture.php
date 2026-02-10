<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\HistoriqueAssociationChauffeurVoiturePartner;




class Voiture extends Model
{
      /** @use HasFactory<\Database\Factories\UserFactory> */
      use HasFactory, Notifiable;

      /**
       * The attributes that are mass assignable.
       *
       * @var list<string>
       */
    protected $fillable = [
        'voiture_unique_id',
        'immatriculation',
        'mac_id_gps',
        'marque',
        'model',
        'couleur',
        'photo',
        'region_id',
        'region_name',
        'geofence_latitude',
        'geofence_longitude',
        'geofence_radius',
        'geofence_zone', 
    ];



    public function utilisateur()
{
    return $this->belongsToMany(User::class, 'association_user_voitures', 'voiture_id', 'user_id');
}


   public function latestLocation()
{
    return $this->hasOne(\App\Models\Location::class, 'mac_id_gps', 'mac_id_gps')
                ->orderByDesc('datetime'); // <- utiliser datetime ici
}


   public function partenaires()
{
    return $this->belongsToMany(User::class, 'association_user_voitures', 'voiture_id', 'user_id');
}

// app/Models/Voiture.php

public function alerts()
{
    return $this->hasMany(\App\Models\Alert::class, 'voiture_id');
}



public function chauffeurActuelPartner(): HasOne
{
    return $this->hasOne(AssociationChauffeurVoiturePartner::class, 'voiture_id');
}



public function trajets()
{
    return $this->hasMany(\App\Models\Trajet::class, 'vehicle_id');
}


public function chauffeurPartnerActuel(): HasOne
{
    return $this->hasOne(AssociationChauffeurVoiturePartner::class, 'voiture_id');
}

public function historiqueChauffeursPartner(): HasMany
{
    return $this->hasMany(HistoriqueAssociationChauffeurVoiturePartner::class, 'voiture_id')
        ->orderByDesc('start_at');
}


}
