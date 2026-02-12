<?php

namespace App\Http\Controllers;

use App\Services\DashboardCacheService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function index()
    {
        $partnerId = (int) auth()->id();

        // ✅ OK de rebuild ici (page load)
        $stats = $this->cache->getStatsFromRedis($partnerId) ?: $this->cache->rebuildStats($partnerId);

        $vehicles = $this->cache->getFleetFromRedis($partnerId);
        if (empty($vehicles)) $vehicles = $this->cache->rebuildFleet($partnerId);

        $alerts = $this->cache->getAlertsFromRedis($partnerId);
        if (empty($alerts)) $alerts = $this->cache->rebuildAlerts($partnerId, 10);

        return view('dashboards.index', [
            'usersCount'        => (int)($stats['usersCount'] ?? 0),
            'vehiclesCount'     => (int)($stats['vehiclesCount'] ?? 0),
            'associationsCount' => (int)($stats['associationsCount'] ?? 0),
            'alertsCount'       => (int)($stats['alertsCount'] ?? 0),
            'alertStats'        => (array)($stats['alertsByType'] ?? []),
            'vehicles'          => $vehicles,
            'alerts'            => $alerts,
        ]);
    }

    public function dashboardStream(): StreamedResponse
    {
        $partnerId = (int) auth()->id();

        return response()->stream(function () use ($partnerId) {

            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            @ini_set('max_execution_time', '0');
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', '1');
            }

            try { session()->save(); } catch (\Throwable $e) {}
            if (function_exists('session_write_close')) @session_write_close();

            echo "event: hello\n";
            echo "data: {\"ok\":true}\n\n";
            $this->flushNow();

            // premier payload (lecture Redis only)
            echo "event: dashboard\n";
            echo "data: " . $this->buildPayloadRedisOnly($partnerId) . "\n\n";
            $this->flushNow();

            $lastVersion = $this->cache->getVersion($partnerId);

            while (!connection_aborted()) {
                $v = $this->cache->getVersion($partnerId);

                if ($v !== $lastVersion) {
                    $lastVersion = $v;

                    echo "event: dashboard\n";
                    echo "data: " . $this->buildPayloadRedisOnly($partnerId) . "\n\n";
                    $this->flushNow();
                } else {
                    echo ": ping\n\n";
                    $this->flushNow();
                }

                usleep(250000);
            }

        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=UTF-8',
            'Cache-Control'     => 'no-cache, no-store, must-revalidate',
            'Pragma'            => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function rebuildCache()
    {
        $partnerId = (int) auth()->id();
        $all = $this->cache->rebuildAll($partnerId);

        return response()->json([
            'ok'      => true,
            'ts'      => now()->toDateTimeString(),
            'version' => $this->cache->getVersion($partnerId),
            ...$all,
        ]);
    }

    /**
     * ✅ IMPORTANT: aucune requête DB ici
     * (sinon tu bloques le stream, et tu as les "parfois ça marche / parfois ça freeze")
     */
    private function buildPayloadRedisOnly(int $partnerId): string
    {
        $stats  = $this->cache->getStatsFromRedis($partnerId);
        $fleet  = $this->cache->getFleetFromRedis($partnerId);
        $alerts = $this->cache->getAlertsFromRedis($partnerId);

        return json_encode([
            'ts'     => now()->toDateTimeString(),
            'stats'  => $stats ?? ['usersCount'=>0,'vehiclesCount'=>0,'associationsCount'=>0,'alertsCount'=>0,'alertsByType'=>[]],
            'fleet'  => is_array($fleet) ? $fleet : [],
            'alerts' => is_array($alerts) ? $alerts : [],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function flushNow(): void
    {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_flush(); }
        }
        @flush();
    }
}