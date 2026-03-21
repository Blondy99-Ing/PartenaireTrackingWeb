<?php

namespace App\Observers;

use App\Models\Voiture;
use App\Services\DashboardCacheService;

/**
 * VoitureObserver — version corrigée
 *
 * CORRECTION [FIX-3] :
 * Les doubles appels à markFleetResetDirty() et bumpVersionDebounced()
 * ont été supprimés. rebuildFleet() (appelé via rebuildFleetForVehicleAssociations)
 * les gère déjà en interne. Les appels supplémentaires ici causaient
 * un double bump de version garantissant des notifications parasites côté SSE.
 */
class VoitureObserver
{
    public function updated(Voiture $voiture): void
    {
        if (!$voiture->wasChanged('mac_id_gps')) {
            return;
        }

        // rebuildFleet() interne appelle déjà :
        //   - markFleetResetDirty()
        //   - bumpVersionDebounced()
        // Ne pas les rappeler ici.
        app(DashboardCacheService::class)->rebuildFleetForVehicleAssociations($voiture);
    }
}