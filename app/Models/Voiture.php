<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Voiture extends Model
{
    use HasFactory;

    protected $table = 'voitures';

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

    /**
     * Users linked to this vehicle via association_user_voitures
     * (partners/admins and/or other linked users depending on your app logic)
     */
     public function utilisateurS()
{
    return $this->belongsToMany(User::class, 'association_user_voitures', 'voiture_id', 'user_id');
}

    public function utilisateur()
{
    return $this->belongsToMany(User::class, 'association_user_voitures', 'voiture_id', 'user_id');
}



    /**
     * Alias (some code may still call partenaires()).
     * Keeps backward compatibility without changing other files.
     */
    public function partenaires(): BelongsToMany
    {
        return $this->utilisateurs();
    }

    /**
     * Latest GPS location by mac_id_gps
     */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(Location::class, 'mac_id_gps', 'mac_id_gps')
            ->orderByDesc('datetime');
    }

    /**
     * Alerts for this vehicle
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'voiture_id');
    }

    /**
     * Current/last chauffeur assignment (partner pivot)
     * IMPORTANT: ordering ensures you get the latest assignment row.
     */
    public function chauffeurPartnerActuel(): HasOne
    {
        return $this->hasOne(AssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('assigned_at'); // or ->orderByDesc('id')
    }

    /**
     * Backward compatibility alias (your error was because some code eager loads this name)
     */
    public function chauffeurActuelPartner(): HasOne
    {
        return $this->chauffeurPartnerActuel();
    }

    /**
     * All partner chauffeur assignments (history in same table)
     */
    public function associationsChauffeurPartner(): HasMany
    {
        return $this->hasMany(AssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('assigned_at');
    }

    /**
     * Trips
     * NOTE: keep vehicle_id only if your trajets table uses vehicle_id.
     * If it uses voiture_id, change this to 'voiture_id'.
     */
    public function trajets(): HasMany
    {
        return $this->hasMany(Trajet::class, 'vehicle_id');
    }

    /**
     * Partner chauffeur history table
     */
    public function historiqueChauffeursPartner(): HasMany
    {
        return $this->hasMany(HistoriqueAssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('start_at');
    }
}
