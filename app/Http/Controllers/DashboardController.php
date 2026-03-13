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

        $stats = $this->cache->getStatsFromRedis($partnerId) ?: $this->cache->rebuildStats($partnerId);

        $vehicles = $this->cache->getFleetFromRedis($partnerId);
        if (empty($vehicles)) {
            $vehicles = $this->cache->rebuildFleet($partnerId);
        }

        $alerts = $this->cache->getAlertsFromRedis($partnerId);
        if (empty($alerts)) {
            $alerts = $this->cache->rebuildAlerts($partnerId, 10);
        }

        return view('dashboards.index', [
            'usersCount'        => (int) ($stats['usersCount'] ?? 0),
            'vehiclesCount'     => (int) ($stats['vehiclesCount'] ?? 0),
            'associationsCount' => (int) ($stats['associationsCount'] ?? 0),
            'alertsCount'       => (int) ($stats['alertsCount'] ?? 0),
            'alertStats'        => (array) ($stats['alertsByType'] ?? []),
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

            try {
                session()->save();
            } catch (\Throwable $e) {
            }

            if (function_exists('session_write_close')) {
                @session_write_close();
            }

            echo "event: hello\n";
            echo "data: {\"ok\":true}\n\n";
            $this->flushNow();

            echo "event: dashboard.init\n";
            echo "data: " . $this->buildInitPayload($partnerId) . "\n\n";
            $this->flushNow();

            $lastVersion = $this->cache->getVersion($partnerId);

            while (!connection_aborted()) {
                $version = $this->cache->getVersion($partnerId);

                if ($version !== $lastVersion) {
                    $lastVersion = $version;

                    if ($this->cache->consumeFleetReset($partnerId)) {
                        echo "event: fleet.reset\n";
                        echo "data: " . json_encode([
                            'fleet' => $this->cache->getFleetFromRedis($partnerId),
                        ], JSON_UNESCAPED_UNICODE) . "\n\n";
                    } else {
                        $vehicles = $this->cache->consumeDirtyVehicleRows($partnerId);
                        foreach ($vehicles as $row) {
                            echo "event: vehicle.updated\n";
                            echo "data: " . json_encode(['vehicle' => $row], JSON_UNESCAPED_UNICODE) . "\n\n";
                        }
                    }

                    $alerts = $this->cache->consumeDirtyAlerts($partnerId);
                    if ($alerts !== null) {
                        echo "event: alerts.updated\n";
                        echo "data: " . json_encode(['alerts' => $alerts], JSON_UNESCAPED_UNICODE) . "\n\n";
                    }

                    $stats = $this->cache->consumeDirtyStats($partnerId);
                    if ($stats !== null) {
                        echo "event: stats.updated\n";
                        echo "data: " . json_encode(['stats' => $stats], JSON_UNESCAPED_UNICODE) . "\n\n";
                    }

                    $this->flushNow();
                } else {
                    echo "event: heartbeat\n";
                    echo "data: " . json_encode(['ts' => now()->toDateTimeString()], JSON_UNESCAPED_UNICODE) . "\n\n";
                    $this->flushNow();
                }

                usleep(2000000);
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

    private function buildInitPayload(int $partnerId): string
    {
        $stats  = $this->cache->getStatsFromRedis($partnerId) ?: [];
        $fleet  = $this->cache->getFleetFromRedis($partnerId);
        $alerts = $this->cache->getAlertsFromRedis($partnerId);

        return json_encode([
            'ts'     => now()->toDateTimeString(),
            'stats'  => $stats,
            'fleet'  => is_array($fleet) ? $fleet : [],
            'alerts' => is_array($alerts) ? $alerts : [],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function flushNow(): void
    {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
        }
        @flush();
    }
}