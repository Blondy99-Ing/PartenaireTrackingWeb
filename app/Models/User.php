<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

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
     * ✅ NORMALISATION AUTO (Mutators)
     * ========================= */

    public function setNomAttribute($value): void
    {
        $v = trim((string) $value);
        $this->attributes['nom'] = $v === '' ? null : mb_strtoupper($v, 'UTF-8');
    }

    public function setPrenomAttribute($value): void
    {
        $this->attributes['prenom'] = $this->titleWords($value);
    }

    public function setVilleAttribute($value): void
    {
        $this->attributes['ville'] = $this->titleWords($value);
    }

    public function setQuartierAttribute($value): void
    {
        $this->attributes['quartier'] = $this->titleWords($value);
    }

    public function setEmailAttribute($value): void
    {
        $v = trim((string) $value);
        $this->attributes['email'] = $v === '' ? null : mb_strtolower($v, 'UTF-8');
    }

    private function titleWords($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') return null;

        // minuscules d'abord -> puis Title Case (gère les espaces multiples)
        $v = preg_replace('/\s+/', ' ', $v);
        $v = mb_strtolower($v, 'UTF-8');

        // Str::title gère assez bien les accents (et met la 1ère lettre de chaque mot en majuscule)
        return Str::title($v);
    }

    /* =========================
     * Relations (inchangées)
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
}