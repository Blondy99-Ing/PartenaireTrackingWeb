<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use App\Models\Trajet;
use App\Models\Voiture;
use App\Services\GpsControlService;
use App\Services\ManualRoadSnapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TrajetController extends Controller
{
    public function __construct(
        private GpsControlService $gpsControlService,
        private ManualRoadSnapService $roadSnapService
    ) {
    }

    /**
     * Liste des trajets avec filtres standardisés
     */
    public function index(Request $request)
    {
        /**
         * Les trajets sont un module du dashboard, pas une page autonome.
         * Toute navigation HTML (lien menu, redirection post-connexion d'un staff
         * n'ayant que la permission trajets) est renvoyée vers le dashboard, qui
         * consomme ensuite cet endpoint en AJAX (?format=json) pour ses données.
         */
        if (! $request->expectsJson() && $request->query('format') !== 'json') {
            return redirect()->route('dashboard');
        }

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

        // Inatteignable en pratique (le HTML est redirigé plus haut), garde-fou.
        return redirect()->route('dashboard');
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
                    $displayPoints = $this->snapAndEnrich($rawPoints);
                    $snappedCount = $displayPoints->count();

                    if ($snappedCount === 0) {
                        $displayPoints = $rawPoints;
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

    /**
     * Replay libre : un véhicule + une période choisie librement (heure de
     * début/fin comprises), indépendant de tout trajet déjà découpé par le
     * système. Même pipeline que showTrajet() (historique provider + collage
     * sur la route via Google Roads), mais la période vient directement du
     * partenaire plutôt que d'une ligne `trips` existante.
     */
    public function replay(Request $request)
    {
        $userId = auth()->id();

        $data = $request->validate([
            'vehicle_id' => ['required', 'integer'],
            'start_at'   => ['required', 'date'],
            'end_at'     => ['required', 'date', 'after:start_at'],
        ]);

        $vehicle = Voiture::query()
            ->whereHas('utilisateur', fn ($q) => $q->where('user_id', $userId))
            ->find($data['vehicle_id']);

        if (! $vehicle) {
            return response()->json(['status' => 'error', 'message' => 'Véhicule introuvable.'], 404);
        }

        $macId = trim((string) $vehicle->mac_id_gps);

        if ($macId === '') {
            return response()->json(['status' => 'error', 'message' => 'Ce véhicule n’a pas de boîtier GPS associé.'], 422);
        }

        $tz = 'Africa/Douala';
        $start = Carbon::parse($data['start_at'], $tz);
        $end = Carbon::parse($data['end_at'], $tz);

        /**
         * Garde-fou : une fenêtre trop large peut représenter des dizaines de
         * milliers de points (chaque relance provider = 1000 points max) et
         * autant d'appels Google Roads. 31 jours reste largement suffisant
         * pour un usage "je veux revoir ce trajet précis", pas un export
         * massif — qui mériterait un traitement asynchrone séparé.
         */
        if ($start->diffInDays($end) > 31) {
            return response()->json(['status' => 'error', 'message' => 'La période ne peut pas dépasser 31 jours.'], 422);
        }

        $snapEnabled = filter_var($request->query('snap_to_road', true), FILTER_VALIDATE_BOOL);

        $history = $this->gpsControlService->getTripHistoryPayloadByMacId(
            $macId,
            $start,
            $end,
            $request->query('mapType', 'BAIDU'),
            filter_var($request->query('playLBS', true), FILTER_VALIDATE_BOOL),
            30,
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

        $rawPoints = collect();
        $displayPoints = collect();
        $snappedCount = 0;

        if (($history['success'] ?? false) === true) {
            $rawPoints = collect($history['points'] ?? [])
                ->map(fn ($p) => [
                    'lat'       => isset($p['lat']) ? (float) $p['lat'] : null,
                    'lng'       => isset($p['lng']) ? (float) $p['lng'] : null,
                    'ts'        => $p['ts'] ?? null,
                    'ts_ms'     => isset($p['ts_ms']) && is_numeric($p['ts_ms']) ? (int) $p['ts_ms'] : null,
                    'speed'     => isset($p['speed']) && is_numeric($p['speed']) ? (float) $p['speed'] : 0.0,
                    'direction' => isset($p['direction']) && is_numeric($p['direction']) ? (float) $p['direction'] : null,
                ])
                ->filter(fn ($p) => $p['lat'] !== null && $p['lng'] !== null)
                ->values();

            $displayPoints = $rawPoints;

            if ($snapEnabled && $rawPoints->count() >= 2) {
                $displayPoints = $this->snapAndEnrich($rawPoints);
                $snappedCount = $displayPoints->count();

                if ($snappedCount === 0) {
                    $displayPoints = $rawPoints;
                }
            }
        }

        $stats = $this->computeTrackStats($rawPoints);

        return response()->json([
            'status' => 'success',
            'data' => [
                'trajet' => [
                    'id'              => null,
                    'vehicle_id'      => (int) $vehicle->id,
                    'immatriculation' => $vehicle->immatriculation ?? '—',
                    'start_time'      => $start->toDateTimeString(),
                    'end_time'        => $end->toDateTimeString(),
                    'stats'           => $stats,
                ],
                'track' => [
                    'points'        => $displayPoints->values(),
                    'count'         => $displayPoints->count(),
                    'meta'          => $historyMeta,
                    'raw_points'    => $rawPoints->values(),
                    'raw_count'     => $rawPoints->count(),
                    'snapped_count' => $snappedCount,
                    'snap_enabled'  => $snapEnabled,
                ],
            ],
        ]);
    }

    /**
     * Statistiques calculées à la volée à partir des points bruts (pas de
     * ligne `trips` pré-calculée pour une période choisie librement).
     */
    /**
     * Colle les points bruts sur la route réelle (ManualRoadSnapService) et
     * reporte la vitesse/heure/direction du point d'origine sur chaque point
     * collé — snapTrack() ne renvoie que des coordonnées, pas ces valeurs, on
     * les retrouve via original_index sur notre propre passage par
     * cleanRawTrack() (pur, sans effet de bord : produit exactement la même
     * liste que celle nettoyée en interne par snapTrack()). Sans ça, le
     * replay aurait des positions correctes mais un HUD vitesse/heure vide.
     * Retourne une collection vide si aucune route locale n'a pu être
     * chargée pour cette zone (l'appelant retombe alors sur les points bruts).
     */
    private function snapAndEnrich(Collection $rawPoints): Collection
    {
        $cleaned = $this->roadSnapService->cleanRawTrack($rawPoints->all());
        $snapped = $this->roadSnapService->snapTrack($rawPoints->all());

        if (empty($snapped)) {
            return collect();
        }

        return collect($snapped)
            ->map(function ($p) use ($cleaned) {
                $origin = isset($p['original_index'])
                    ? ($cleaned[(int) $p['original_index']] ?? null)
                    : null;

                return [
                    'lat'       => isset($p['lat']) ? (float) $p['lat'] : null,
                    'lng'       => isset($p['lng']) ? (float) $p['lng'] : null,
                    'ts'        => $origin['ts'] ?? null,
                    'ts_ms'     => $origin['ts_ms'] ?? null,
                    'speed'     => $origin['speed'] ?? 0.0,
                    'direction' => $origin['direction'] ?? null,
                ];
            })
            ->filter(fn ($p) => $p['lat'] !== null && $p['lng'] !== null)
            ->values();
    }

    private function computeTrackStats(Collection $rawPoints): array
    {
        if ($rawPoints->count() < 2) {
            return ['distance' => 0.0, 'duration' => 0, 'max_speed' => 0.0, 'avg_speed' => 0.0];
        }

        $distanceMeters = 0.0;
        $maxSpeed = 0.0;
        $speedSum = 0.0;
        $prev = null;

        foreach ($rawPoints as $p) {
            if ($prev) {
                $distanceMeters += $this->haversineMeters($prev['lat'], $prev['lng'], $p['lat'], $p['lng']);
            }

            $maxSpeed = max($maxSpeed, (float) $p['speed']);
            $speedSum += (float) $p['speed'];
            $prev = $p;
        }

        $first = $rawPoints->first();
        $last = $rawPoints->last();
        $durationMinutes = 0;

        if (($first['ts_ms'] ?? null) && ($last['ts_ms'] ?? null)) {
            $durationMinutes = max(0, (int) round(($last['ts_ms'] - $first['ts_ms']) / 60000));
        }

        return [
            'distance'  => round($distanceMeters / 1000, 2),
            'duration'  => $durationMinutes,
            'max_speed' => round($maxSpeed, 1),
            'avg_speed' => round($speedSum / max(1, $rawPoints->count()), 1),
        ];
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}