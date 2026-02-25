<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

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

    /* =========================
     * ✅ NORMALISATION AUTO (Mutators)
     * ========================= */

    public function setImmatriculationAttribute($value): void
    {
        $v = trim((string) $value);
        $this->attributes['immatriculation'] = $v === '' ? null : mb_strtoupper($v, 'UTF-8');
    }

    public function setMarqueAttribute($value): void
    {
        $v = trim((string) $value);
        $this->attributes['marque'] = $v === '' ? null : mb_strtoupper($v, 'UTF-8');
    }

    public function setModelAttribute($value): void
    {
        $this->attributes['model'] = $this->titleWords($value);
    }

    public function setCouleurAttribute($value): void
    {
        $this->attributes['couleur'] = $this->titleWords($value);
    }

    public function setRegionNameAttribute($value): void
    {
        $this->attributes['region_name'] = $this->titleWords($value);
    }

    public function setGeofenceZoneAttribute($value): void
    {
        $this->attributes['geofence_zone'] = $this->titleWords($value);
    }

    public function setMacIdGpsAttribute($value): void
    {
        $v = trim((string) $value);
        $this->attributes['mac_id_gps'] = $v === '' ? null : $v;
    }

    private function titleWords($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') return null;

        $v = preg_replace('/\s+/', ' ', $v);
        $v = mb_strtolower($v, 'UTF-8');

        return Str::title($v);
    }

    /* =========================
     * Relations (✅ inchangées)
     * ========================= */

    /**
     * Users linked to this vehicle via association_user_voitures
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
        // ⚠️ Ton code avait "utilisateurs()" mais ta relation s'appelle "utilisateurS()"
        return $this->utilisateurS();
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
     */
    public function chauffeurPartnerActuel(): HasOne
    {
        return $this->hasOne(AssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('assigned_at');
    }

    /**
     * Backward compatibility alias
     */
    public function chauffeurActuelPartner(): HasOne
    {
        return $this->chauffeurPartnerActuel();
    }

    /**
     * All partner chauffeur assignments
     */
    public function associationsChauffeurPartner(): HasMany
    {
        return $this->hasMany(AssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('assigned_at');
    }

    /**
     * Trips
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