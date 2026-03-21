<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use App\Models\Trajet;
use App\Services\GoogleRoadsService;
use App\Services\GpsControlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TrajetController extends Controller
{
    public function __construct(
        private GpsControlService $gpsControlService,
        private GoogleRoadsService $googleRoadsService
    ) {
    }

    /**
     * Liste des trajets avec filtres standardisés
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        $tz = 'Africa/Douala';

        $query = Trajet::query()
            ->with(['voiture'])
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', (int) $request->vehicle_id);
        }

        $quick = $request->query('quick', $request->query('date_quick', 'today'));
        $now = now($tz);

        if ($quick && $quick !== 'range') {
            match ($quick) {
                'today'      => $query->whereDate('start_time', $now->copy()->toDateString()),
                'yesterday'  => $query->whereDate('start_time', $now->copy()->subDay()->toDateString()),
                'this_week'  => $query->whereBetween('start_time', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]),
                'this_month' => $query->whereBetween('start_time', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]),
                default      => null,
            };
        } elseif ($request->filled('start_date')) {
            $start = Carbon::parse($request->start_date, $tz)->startOfDay();
            $end   = Carbon::parse($request->end_date ?? $request->start_date, $tz)->endOfDay();

            $query->whereBetween('start_time', [$start, $end]);
        }

        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 200);

        $trajets = $query
            ->orderByDesc('start_time')
            ->paginate($perPage);

        if ($request->expectsJson() || $request->query('format') === 'json') {
            return response()->json([
                'status' => 'success',
                'data' => collect($trajets->items())->map(function ($t) {
                    return [
                        'id'                => (int) $t->id,
                        'vehicle_id'        => (int) $t->vehicle_id,
                        'immatriculation'   => $t->voiture?->immatriculation,
                        'driver_label'      => $t->voiture?->users_labels ?? 'Inconnu',
                        'start_time'        => $t->start_time,
                        'end_time'          => $t->end_time,
                        'duration_minutes'  => (int) ($t->duration_minutes ?? 0),
                        'total_distance_km' => round((float) ($t->total_distance_km ?? 0), 2),
                        'avg_speed_kmh'     => round((float) ($t->avg_speed_kmh ?? 0), 1),
                        'max_speed_kmh'     => round((float) ($t->max_speed_kmh ?? 0), 1),
                    ];
                })->values(),
                'meta' => [
                    'current_page' => $trajets->currentPage(),
                    'total'        => $trajets->total(),
                    'last_page'    => $trajets->lastPage(),
                ],
            ]);
        }

        return view('trajets.index', compact('trajets'));
    }

    /**
     * Détail du trajet + points GPS provider + correction Google Roads
     */
    public function showTrajet($vehicle_id, $trajet_id, Request $request)
    {
        $userId = auth()->id();

        $trajet = Trajet::with('voiture')
            ->where('id', $trajet_id)
            ->where('vehicle_id', $vehicle_id)
            ->whereHas('voiture.utilisateur', fn ($q) => $q->where('user_id', $userId))
            ->firstOrFail();

        $macId = trim((string) ($trajet->mac_id_gps ?: $trajet->voiture?->mac_id_gps));

        $rawPoints = collect();
        $displayPoints = collect();
        $historyMeta = null;
        $snappedCount = 0;
        $snapEnabled = filter_var($request->query('snap_to_road', true), FILTER_VALIDATE_BOOL);

        if ($macId !== '') {
            $history = $this->gpsControlService->getTripHistoryPayloadByMacId(
                $macId,
                $trajet->start_time,
                $trajet->end_time ?? now(),
                $request->query('mapType', 'BAIDU'),
                filter_var($request->query('playLBS', true), FILTER_VALIDATE_BOOL),
                20,
                true
            );

            $historyMeta = [
                'success'     => (bool) ($history['success'] ?? false),
                'source'      => $history['source'] ?? 'provider',
                'account'     => $history['account'] ?? null,
                'resolved_by' => $history['resolved_by'] ?? null,
                'user_id'     => $history['user_id'] ?? null,
                'count'       => (int) ($history['count'] ?? 0),
                'loops'       => (int) ($history['loops'] ?? 0),
                'message'     => $history['message'] ?? null,
            ];

            if (($history['success'] ?? false) === true) {
                $rawPoints = collect($history['points'] ?? [])
                    ->map(function ($p) {
                        return [
                            'lat'       => isset($p['lat']) ? (float) $p['lat'] : null,
                            'lng'       => isset($p['lng']) ? (float) $p['lng'] : null,
                            'ts'        => $p['ts'] ?? null,
                            'ts_ms'     => isset($p['ts_ms']) && is_numeric($p['ts_ms']) ? (int) $p['ts_ms'] : null,
                            'speed'     => isset($p['speed']) && is_numeric($p['speed']) ? (float) $p['speed'] : 0.0,
                            'direction' => isset($p['direction']) && is_numeric($p['direction']) ? (float) $p['direction'] : null,
                        ];
                    })
                    ->filter(fn ($p) => $p['lat'] !== null && $p['lng'] !== null)
                    ->values();

                $displayPoints = $rawPoints;

                if ($snapEnabled && $rawPoints->count() >= 2) {
                    $snapped = $this->googleRoadsService->snapTrack($rawPoints->all(), true);
                    $snappedCount = count($snapped);

                    if ($snappedCount > 0) {
                        $displayPoints = collect($snapped)
                            ->map(function ($p) {
                                return [
                                    'lat'       => isset($p['lat']) ? (float) $p['lat'] : null,
                                    'lng'       => isset($p['lng']) ? (float) $p['lng'] : null,
                                    'ts'        => null,
                                    'ts_ms'     => null,
                                    'speed'     => 0.0,
                                    'direction' => null,
                                    'place_id'  => $p['place_id'] ?? null,
                                ];
                            })
                            ->filter(fn ($p) => $p['lat'] !== null && $p['lng'] !== null)
                            ->values();
                    }
                }
            }
        }

        if ($displayPoints->isEmpty()) {
            $startLat = $trajet->start_latitude ?? $trajet->start_lat ?? null;
            $startLng = $trajet->start_longitude ?? $trajet->start_lng ?? null;
            $endLat   = $trajet->end_latitude ?? $trajet->end_lat ?? null;
            $endLng   = $trajet->end_longitude ?? $trajet->end_lng ?? null;

            if ($startLat !== null && $startLng !== null) {
                $fallback = [
                    [
                        'lat'       => (float) $startLat,
                        'lng'       => (float) $startLng,
                        'ts'        => $trajet->start_time,
                        'ts_ms'     => null,
                        'speed'     => 0.0,
                        'direction' => null,
                    ],
                ];

                if ($endLat !== null && $endLng !== null) {
                    $fallback[] = [
                        'lat'       => (float) $endLat,
                        'lng'       => (float) $endLng,
                        'ts'        => $trajet->end_time ?? now()->toDateTimeString(),
                        'ts_ms'     => null,
                        'speed'     => 0.0,
                        'direction' => null,
                    ];
                }

                $displayPoints = collect($fallback);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trajet' => [
                    'id'              => (int) $trajet->id,
                    'vehicle_id'      => (int) $trajet->vehicle_id,
                    'immatriculation' => $trajet->voiture?->immatriculation ?? '—',
                    'start_time'      => $trajet->start_time,
                    'end_time'        => $trajet->end_time,
                    'stats' => [
                        'distance'  => round((float) ($trajet->total_distance_km ?? 0), 2),
                        'duration'  => (int) ($trajet->duration_minutes ?? 0),
                        'max_speed' => round((float) ($trajet->max_speed_kmh ?? 0), 1),
                        'avg_speed' => round((float) ($trajet->avg_speed_kmh ?? 0), 1),
                    ],
                ],
                'track' => [
                    'points'         => $displayPoints->values(),
                    'count'          => $displayPoints->count(),
                    'meta'           => $historyMeta,
                    'raw_points'     => $rawPoints->values(),
                    'raw_count'      => $rawPoints->count(),
                    'snapped_count'  => $snappedCount,
                    'snap_enabled'   => $snapEnabled,
                ],
            ],
        ]);
    }
}