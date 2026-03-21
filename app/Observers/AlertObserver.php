<?php

namespace App\Observers;

use App\Models\Alert;
use App\Services\DashboardCacheService;

/**
 * AlertObserver
 *
 * Rebuild automatique du cache Redis alertes + stats
 * dès qu'une alerte est créée ou modifiée (processed / read).
 */
class AlertObserver
{
    public function __construct(private DashboardCacheService $cache) {}

    public function created(Alert $alert): void
    {
        $this->refresh($alert);
    }

    public function updated(Alert $alert): void
    {
        if ($alert->wasChanged(['processed', 'read'])) {
            $this->refresh($alert);
        }
    }

    private function refresh(Alert $alert): void
    {
        $partnerIds = $this->cache->partnerIdsForVehicle((int) $alert->voiture_id);

        foreach ($partnerIds as $partnerId) {
            $this->cache->rebuildAlerts($partnerId, 10);
            $this->cache->rebuildStats($partnerId);
        }
    }
}