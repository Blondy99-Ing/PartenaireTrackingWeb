<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modèle LeaseCutoffRule.
 *
 * Rôle :
 * Représente la règle principale de coupure pour un véhicule.
 *
 * Avant :
 * La règle disait seulement :
 * - ce véhicule peut-il être coupé ?
 * - à quelle heure ?
 *
 * Maintenant :
 * Elle sert de règle générale véhicule, et ses enfants
 * lease_cutoff_rule_contract_types précisent quels types de contrats
 * peuvent réellement déclencher la coupure.
 */
class LeaseCutoffRule extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_rules';

    protected $fillable = [
        'partner_id',
        'vehicle_id',
        'is_enabled',
        'cutoff_time',
        'timezone',
        'grace_days',
        'only_when_stopped',
        'notify_before_cutoff',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'vehicle_id' => 'integer',
        'is_enabled' => 'boolean',
        'grace_days' => 'integer',
        'only_when_stopped' => 'boolean',
        'notify_before_cutoff' => 'boolean',
    ];

    /**
     * Partenaire propriétaire de la règle.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * Véhicule concerné par la règle.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }

    /**
     * Types de contrats configurés pour cette règle véhicule.
     *
     * Exemple :
     * - Véhicule : activé
     * - Téléphone : activé
     * - Kit sécurité : désactivé
     */
    public function contractTypeRules(): HasMany
    {
        return $this->hasMany(LeaseCutoffRuleContractType::class, 'rule_id');
    }

    /**
     * Utilisateur ayant créé la règle.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Utilisateur ayant modifié la règle.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}