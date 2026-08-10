<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne = une vérification réelle effectuée pendant un cycle de
 * coupure/rallumage automatique (offline, en mouvement, commande envoyée,
 * confirmation en attente...). Jamais mise à jour après coup : c'est
 * l'historique complet et chronologique du cycle, contrairement à
 * LeaseCutoffHistory qui ne garde que le dernier statut.
 */
class LeaseCutoffEvent extends Model
{
    protected $table = 'lease_cutoff_events';

    protected $fillable = [
        'history_id',
        'queue_id',
        'event_type',
        'message',
        'speed_at_check',
        'ignition_state',
        'retry_count',
        'occurred_at',
    ];

    protected $casts = [
        'history_id' => 'integer',
        'queue_id' => 'integer',
        'speed_at_check' => 'decimal:2',
        'retry_count' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function history(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffHistory::class, 'history_id');
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(LeaseCutoffQueue::class, 'queue_id');
    }
}
