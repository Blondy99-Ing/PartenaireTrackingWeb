<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modèle LeaseCutoffQueue.
 *
 * Rôle :
 * Représente une demande de coupure en attente de traitement.
 *
 * Pourquoi une queue ?
 * On ne coupe pas immédiatement un véhicule dès qu’un impayé est détecté.
 * Tracking doit d’abord vérifier :
 * - si le véhicule est arrêté ;
 * - si la règle autorise la coupure ;
 * - si une commande n’est pas déjà en cours ;
 * - si l’heure de coupure est atteinte.
 *
 * Avec les sous-contrats :
 * La queue garde maintenant le détail du contrat ou sous-contrat déclencheur.
 */
class LeaseCutoffQueue extends Model
{
    use HasFactory;

    protected $table = 'lease_cutoff_queue';

    protected $fillable = [
        'partner_id',
        'vehicle_id',

        /**
         * contract_id reste l’ID recouvrement historique.
         * Il est conservé pour compatibilité avec ton code actuel.
         */
        'contract_id',

        'lease_id',
        'contract_link_id',
        'parent_contract_id',
        'type_contrat_id',
        'type_contrat_label',
        'contract_kind',
        'trigger_label',
        'trigger_payload',

        'rule_id',
        'history_id',
        'scheduled_for',
        'status',
        'last_checked_at',
        'retry_count',
        'next_check_at',
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'vehicle_id' => 'integer',
        'contract_id' => 'integer',
        'lease_id' => 'integer',
        'contract_link_id' => 'integer',
        'parent_contract_id' => 'integer',
        'type_contrat_id' => 'integer',
        'trigger_payload' => 'array',
        'scheduled_for' => 'datetime',
        'last_checked_at' => 'datetime',
        'next_check_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    /**
     * Partenaire propriétaire du véhicule.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /**
     * Véhicule qui sera coupé.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Voiture::class, 'vehicle_id');
    }

    /**
     * Règle véhicule ayant autorisé la mise en queue.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffRule::class, 'rule_id');
    }

    /**
     * Historique associé à cette demande de coupure.
     */
    public function history(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffHistory::class, 'history_id');
    }

    /**
     * Lien local vers le contrat ou sous-contrat recouvrement.
     */
    public function contractLink(): BelongsTo
    {
        return $this->belongsTo(LeaseContractLink::class, 'contract_link_id');
    }
}