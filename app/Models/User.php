<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\HistoriqueAssociationChauffeurVoiturePartner;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'user_unique_id',
        'nom',
        'prenom',
        'phone',
        'email',
        'ville',
        'quartier',
        'photo',
        'password',
        'role_id',
        'partner_id',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted()
    {
        static::creating(function (User $user) {
            if (empty($user->user_unique_id)) {
                $prefix = 'PxT-' . now()->format('Ym') . '-';

                do {
                    $candidate = $prefix . Str::random(4);
                } while (self::where('user_unique_id', $candidate)->exists());

                $user->user_unique_id = $candidate;
            }
        });
    }

    /* =========================
     * ✅ NORMALISATION SET + GET (Attributes)
     * ========================= */

    protected function nom(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->upperOrNull($value),
            set: fn ($value) => $this->upperOrNull($value),
        );
    }

    protected function prenom(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    protected function ville(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    protected function quartier(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->titleWords($value),
            set: fn ($value) => $this->titleWords($value),
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->lowerOrNull($value),
            set: fn ($value) => $this->lowerOrNull($value),
        );
    }

    /* =========================
     * Helpers unicode-safe
     * ========================= */

    private function upperOrNull($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : mb_strtoupper($v, 'UTF-8');
    }

    private function lowerOrNull($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : mb_strtolower($v, 'UTF-8');
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Role::class, 'role_id');
    }

    public function voituresPartenaire()
    {
        return $this->belongsToMany(Voiture::class, 'association_user_voitures', 'user_id', 'voiture_id');
    }

    public function voitures()
    {
        return $this->belongsToMany(Voiture::class, 'association_user_voitures', 'user_id', 'voiture_id');
    }

    public function affectationVoitureActuellePartner(): HasMany
    {
        return $this->hasMany(AssociationChauffeurVoiturePartner::class, 'chauffeur_id');
    }

    public function historiqueAffectationsVoituresPartner(): HasMany
    {
        return $this->hasMany(HistoriqueAssociationChauffeurVoiturePartner::class, 'chauffeur_id')
            ->orderByDesc('start_at');
    }




    // relation avec le partenaire 
    public function partner(): BelongsTo
{
    return $this->belongsTo(Partner::class, 'partner_id');
}

public function createdPartners(): HasMany
{
    return $this->hasMany(Partner::class, 'created_by');
}

public function ownedPartners(): HasMany
{
    return $this->hasMany(Partner::class, 'owner_user_id');
}


   //Exemple à ajouter dans User.php (inoffensif maintenant) :
     public function scopePartners(Builder $query): Builder {
         return $query->whereNull('partner_id');
     }
     public function scopeDriversOf(Builder $query, int $partnerId): Builder {
         return $query->where('partner_id', $partnerId);
     }
 
}