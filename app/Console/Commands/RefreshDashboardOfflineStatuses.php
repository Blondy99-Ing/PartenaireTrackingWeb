<?php

namespace App\Console\Commands;

use App\Services\DashboardCacheService;
use Illuminate\Console\Command;

class RefreshDashboardOfflineStatuses extends Command
{
    protected $signature = 'dashboard:refresh-offline-statuses';
    protected $description = 'Recalcule depuis Redis les statuts OFFLINE des véhicules et bump la version si changement';

    public function handle(DashboardCacheService $cache): int
    {
        $startedAt = now();

        $result = $cache->refreshAllPartnersOfflineStatusesFromRedis();

        $this->info('Offline statuses refreshed.');
        $this->line('Partners: ' . ($result['partners'] ?? 0));
        $this->line('Changed: ' . ($result['changed'] ?? 0));
        $this->line('Started at: ' . $startedAt->toDateTimeString());
        $this->line('Finished at: ' . now()->toDateTimeString());

        return self::SUCCESS;
    }
}