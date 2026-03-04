<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
     * ✅ NORMALISATION SET + GET
     * ========================= */

    protected function immatriculation(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->upperOrNull($value),
            set: fn ($value) => $this->upperOrNull($value),
        );
    }

    protected function marque(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->upperOrNull($value),
            set: fn ($value) => $this->upperOrNull($value),
        );
    }

    protected function model(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    protected function couleur(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    protected function regionName(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    protected function geofenceZone(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    protected function macIdGps(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->trimOrNull($value),
            set: fn ($value) => $this->trimOrNull($value),
        );
    }

    /* =========================
     * Helpers unicode-safe
     * ========================= */

    private function trimOrNull($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function upperOrNull($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : mb_strtoupper($v, 'UTF-8');
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

    public function utilisateurS()
    {
        return $this->belongsToMany(User::class, 'association_user_voitures', 'voiture_id', 'user_id');
    }

    public function utilisateur()
    {
        return $this->belongsToMany(User::class, 'association_user_voitures', 'voiture_id', 'user_id');
    }

    public function partenaires(): BelongsToMany
    {
        return $this->utilisateurS();
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(Location::class, 'mac_id_gps', 'mac_id_gps')
            ->orderByDesc('datetime');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'voiture_id');
    }

    public function chauffeurPartnerActuel(): HasOne
    {
        return $this->hasOne(AssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('assigned_at');
    }

    public function chauffeurActuelPartner(): HasOne
    {
        return $this->chauffeurPartnerActuel();
    }

    public function associationsChauffeurPartner(): HasMany
    {
        return $this->hasMany(AssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('assigned_at');
    }

    public function trajets(): HasMany
    {
        return $this->hasMany(Trajet::class, 'vehicle_id');
    }

    public function historiqueChauffeursPartner(): HasMany
    {
        return $this->hasMany(HistoriqueAssociationChauffeurVoiturePartner::class, 'voiture_id')
            ->orderByDesc('start_at');
    }
}