<?php

namespace App\Observers;

use App\Models\Voiture;
use App\Services\DashboardCacheService;

class VoitureObserver
{
    public function updated(Voiture $voiture): void
    {
        if (!$voiture->wasChanged('mac_id_gps')) {
            return;
        }

        app(DashboardCacheService::class)->rebuildFleetForVehicleAssociations($voiture);
    }
}