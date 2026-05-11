<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle LeaseContractLink.
 *
 * Rôle :
 * Cette table fait le lien entre les contrats/sous-contrats du recouvrement
 * et les entités locales de Tracking.
 *
 * Pourquoi cette table est nécessaire :
 * Recouvrement connaît les contrats, les paiements et les impayés.
 * Tracking connaît les véhicules, le GPS et la coupure.
 *
 * Lorsqu’un sous-contrat est impayé côté recouvrement,
 * Tracking doit pouvoir retrouver :
 * - le véhicule local concerné ;
 * - le partenaire ;
 * - le chauffeur ;
 * - le type de contrat ;
 * - le contrat parent si c’est un sous-contrat.
 */
class LeaseContractLink extends Model
{
    public const KIND_MAIN = 'MAIN';
    public const KIND_SUB = 'SUB';

    protected $table = 'lease_contract_links';

    protected $fillable = [
        'partner_id',
        'vehicle_id',
        'driver_id',
        'recouvrement_driver_id',
        'source_contract_id',
        'source_parent_contract_id',
        'contract_kind',
        'type_contrat_id',
        'type_contrat_label',
        'immatriculation',
        'vin',
        'status',
        'last_payment_status',
        'last_snapshot',
        'last_payload',
        'last_synced_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'vehicle_id' => 'integer',
        'driver_id' => 'integer',
        'recouvrement_driver_id' => 'integer',
        'source_contract_id' => 'integer',
        'source_parent_contract_id' => 'integer',
        'type_contrat_id' => 'integer',
        'last_snapshot' => 'array',
        'last_payload' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Partenaire propriétaire du contrat.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * Véhicule Tracking concerné par le contrat ou sous-contrat.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }

    /**
     * Chauffeur local associé au contrat.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Contrat parent local, quand la ligne représente un sous-contrat.
     */
    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'source_parent_contract_id',
            'source_contract_id'
        );
    }

    /**
     * Indique si cette ligne représente le contrat principal véhicule.
     */
    public function isMainContract(): bool
    {
        return $this->contract_kind === self::KIND_MAIN;
    }

    /**
     * Indique si cette ligne représente un sous-contrat.
     */
    public function isSubContract(): bool
    {
        return $this->contract_kind === self::KIND_SUB;
    }
}