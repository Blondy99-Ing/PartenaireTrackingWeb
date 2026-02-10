<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\HistoriqueAssociationChauffeurVoiturePartner;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;





class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
     use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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
            // Exemple: PxT-202501-M1gU
            $prefix = 'PxT-' . now()->format('Ym') . '-';

            do {
                $candidate = $prefix . Str::random(4);
            } while (self::where('user_unique_id', $candidate)->exists());

            $user->user_unique_id = $candidate;
        }
    });
}

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
