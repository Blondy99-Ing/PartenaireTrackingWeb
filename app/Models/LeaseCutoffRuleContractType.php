<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle LeaseCutoffRuleContractType.
 *
 * Rôle :
 * Il indique si un type de contrat recouvrement peut déclencher une coupure
 * pour un véhicule donné.
 *
 * Exemple :
 * - type_contrat_id = 1, Véhicule      => peut couper
 * - type_contrat_id = 4, Téléphone     => peut couper
 * - type_contrat_id = 3, Kit sécurité  => ne peut pas couper
 *
 * Cette table complète lease_cutoff_rules.
 * Elle ne la remplace pas.
 */
class LeaseCutoffRuleContractType extends Model
{
    protected $table = 'lease_cutoff_rule_contract_types';

    protected $fillable = [
        'rule_id',
        'partner_id',
        'vehicle_id',
        'type_contrat_id',
        'type_contrat_label',
        'is_enabled',
        'grace_days',
        'cutoff_time',
        'only_when_stopped',
        'notify_before_cutoff',
    ];

    protected $casts = [
        'rule_id' => 'integer',
        'partner_id' => 'integer',
        'vehicle_id' => 'integer',
        'type_contrat_id' => 'integer',
        'is_enabled' => 'boolean',
        'grace_days' => 'integer',
        'only_when_stopped' => 'boolean',
        'notify_before_cutoff' => 'boolean',
    ];

    /**
     * Règle véhicule parente.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffRule::class, 'rule_id');
    }

    /**
     * Partenaire propriétaire de la règle.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * Véhicule concerné.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }

    /**
     * Retourne le délai de grâce effectif.
     *
     * Fonctionnement :
     * - si le type de contrat a son propre grace_days, on l’utilise ;
     * - sinon on utilisera plus tard celui de lease_cutoff_rules.
     */
    public function hasSpecificGraceDays(): bool
    {
        return $this->grace_days !== null;
    }
}