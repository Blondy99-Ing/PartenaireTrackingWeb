<?php

namespace App\Http\Controllers;

use App\Services\DashboardCacheService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function index()
    {
        $userId = (int) auth()->id();

        $stats = $this->cache->getStatsFromRedis($userId) ?: $this->cache->rebuildStats($userId);
        $vehicles = $this->cache->getFleetFromRedis($userId);
        if (empty($vehicles)) $vehicles = $this->cache->rebuildFleet($userId);

        $alerts = $this->cache->getAlertsFromRedis($userId);
        if (empty($alerts)) $alerts = $this->cache->rebuildAlerts($userId, 10);

        if (!isset($stats['alertsCount']) || !isset($stats['alertsByType'])) {
            $stats = $this->cache->rebuildStats($userId);
        }

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
        $userId = (int) auth()->id();

        return response()->stream(function () use ($userId) {

            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);

            try { session()->save(); } catch (\Throwable $e) {}
            if (function_exists('session_write_close')) @session_write_close();

            echo "event: hello\n";
            echo "data: {\"ok\":true}\n\n";
            $this->flushNow();

            echo "event: dashboard\n";
            echo "data: " . $this->buildPayload($userId) . "\n\n";
            $this->flushNow();

            $lastVersion = $this->cache->getVersion($userId);

            while (!connection_aborted()) {
                $v = $this->cache->getVersion($userId);

                if ($v !== $lastVersion) {
                    $lastVersion = $v;

                    echo "event: dashboard\n";
                    echo "data: " . $this->buildPayload($userId) . "\n\n";
                    $this->flushNow();
                } else {
                    echo ": ping\n\n";
                    $this->flushNow();
                }

                usleep(120000);
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
        $userId = (int) auth()->id();
        $all = $this->cache->rebuildAll($userId);

        return response()->json([
            'ok'      => true,
            'ts'      => now()->toDateTimeString(),
            'version' => $this->cache->getVersion($userId),
            ...$all,
        ]);
    }

    private function buildPayload(int $userId): string
    {
        $stats = $this->cache->getStatsFromRedis($userId) ?? $this->cache->rebuildStats($userId);

        $fleet = $this->cache->getFleetFromRedis($userId);
        if (empty($fleet)) $fleet = $this->cache->rebuildFleet($userId);

        $alerts = $this->cache->getAlertsFromRedis($userId);
        if (empty($alerts)) $alerts = $this->cache->rebuildAlerts($userId, 10);

        return json_encode([
            'ts'     => now()->toDateTimeString(),
            'stats'  => $stats,
            'fleet'  => $fleet,
            'alerts' => $alerts,
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