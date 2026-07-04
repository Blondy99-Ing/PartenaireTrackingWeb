<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use App\Enums\PartnerPermission;
use App\Models\Permission;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\HistoriqueAssociationChauffeurVoiturePartner;
use Illuminate\Database\Eloquent\Builder;

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
    'keycloak_id',
    'keycloak_username',
    'keycloak_sync_status',
    'recouvrement_driver_id',
    'recouvrement_sync_status',
    'sync_error',
    'last_synced_at',
    'type_partner',
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
            'last_synced_at' => 'datetime',
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

    /**
     * Permissions explicitly granted to this staff member.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withPivot('granted_by')
            ->withTimestamps();
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
            ->orderByDesc('started_at');
    }




    // relation avec le partenaire 
public function partner(): BelongsTo
{
    return $this->belongsTo(User::class, 'partner_id');
}



/**
 * Alias explicite pour éviter la confusion avec la table partners.
 */
public function partnerUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'partner_id');
}

/**
 * Chauffeurs / utilisateurs secondaires rattachés à ce partenaire.
 */
public function drivers(): HasMany
{
    return $this->hasMany(User::class, 'partner_id');
}

/**
 * Vérifie si ce compte est un vrai partenaire.
 */
public function isPartnerAccount(): bool
{
    return is_null($this->partner_id);
}

/**
 * Vérifie si ce compte est un chauffeur / utilisateur secondaire.
 */
public function isDriverAccount(): bool
{
    return !is_null($this->partner_id);
}

/**
 * Retourne l'identifiant tenant effectif.
 *
 * - Partenaire : son propre id
 * - Chauffeur : son partner_id
 */
public function tenantPartnerId(): int
{
    return (int) ($this->partner_id ?: $this->id);
}

/**
 * Per-request cache of granted permission keys (avoids re-querying the
 * pivot on every @can / gate check during a single request).
 */
private ?Collection $cachedPermissionKeys = null;

/**
 * Keys of the permissions granted to this user.
 */
public function grantedPermissionKeys(): Collection
{
    return $this->cachedPermissionKeys ??= $this->permissions()->pluck('key');
}

/**
 * Whether this user is allowed to perform the given permission.
 *
 * The main partner (no partner_id) implicitly has every permission;
 * staff members only have what was explicitly granted to them.
 */
public function hasPermission(PartnerPermission|string $permission): bool
{
    if (is_null($this->partner_id)) {
        return true;
    }

    $key = $permission instanceof PartnerPermission
        ? $permission->value
        : $permission;

    return $this->grantedPermissionKeys()->contains($key);
}

/**
 * Drop the cached permission keys (call after syncing permissions).
 */
public function forgetCachedPermissions(): void
{
    $this->cachedPermissionKeys = null;
}

/**
 * Name of the route this user should land on — the first feature they are
 * allowed to access. The main partner always lands on the dashboard.
 */
public function homeRouteName(): string
{
    if (is_null($this->partner_id)) {
        return 'dashboard';
    }

    // Each route MUST be gated by the very permission that maps to it, so the
    // landing page is always reachable (no 403 redirect loop).
    $map = [
        PartnerPermission::DashboardView->value        => 'dashboard',
        PartnerPermission::TrackingView->value         => 'tracking.vehicles',
        PartnerPermission::LeaseView->value            => 'lease.index',
        PartnerPermission::LeaseContractsManage->value => 'lease.contrat',
        PartnerPermission::DriversManage->value        => 'partner.drivers.index',
        PartnerPermission::AffectationsManage->value   => 'partner.affectations.index',
        PartnerPermission::EngineControl->value        => 'engine.action.index',
        // Trajets et alertes sont des modules du dashboard : on y redirige, la
        // vue n'affichera que l'onglet autorisé (pas de page dédiée).
        PartnerPermission::AlertsView->value           => 'dashboard',
        PartnerPermission::TrajetsView->value          => 'dashboard',
        PartnerPermission::SettingsManage->value       => 'settings.lease.index',
    ];

    foreach ($map as $key => $routeName) {
        if ($this->hasPermission($key)) {
            return $routeName;
        }
    }

    // Fallback: profile is always reachable for an authenticated user.
    return 'profile.edit';
}

/**
 * Nom affichable propre.
 */
public function displayName(): string
{
    return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? '')) ?: ($this->phone ?? 'Utilisateur');
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