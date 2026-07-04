<?php

namespace App\Http\Middleware;

use App\Services\DashboardCacheService;
use Closure;
use Illuminate\Http\Request;

/**
 * RebuildDashboardCache
 *
 * Appliqué sur la route dashboard (GET /dashboard ou équivalent).
 * Rebuild Redis si le cache est vide — couvre :
 *   - Premier chargement
 *   - Actualisation après expiration TTL
 *   - Reconnexion après longue absence
 *
 * NE rebuild PAS à chaque requête — vérifie d'abord si Redis a des données.
 */
class RebuildDashboardCache
{
    public function __construct(private DashboardCacheService $cache) {}

    public function handle(Request $request, Closure $next)
    {
        $partnerId = (int) auth()->id();

        if ($partnerId > 0 && empty($this->cache->getStatsFromRedis($partnerId))) {
            $this->cache->rebuildAll($partnerId);
        }

        return $next($request);
    }
}