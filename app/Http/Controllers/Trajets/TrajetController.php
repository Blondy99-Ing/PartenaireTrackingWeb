<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trajet;
use App\Models\Voiture;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrajetController extends Controller
{
    /**
     * LISTE DES TRAJETS (partenaire)
     * - uniquement les trajets des véhicules du user connecté via voiture.utilisateur
     */
    public function index(Request $request)
    {
        $userId = (int) auth()->id();

        $query = Trajet::query()
            ->with('voiture')
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });

        // Dates (priorité range puis quick)
        $this->applyIndexDateFilters($query, $request, 'start_time');

        // Recherche véhicule (immat)
        if ($request->filled('vehicule')) {
            $term = trim($request->vehicule);
            $query->whereHas('voiture', function ($q) use ($term) {
                $q->where('immatriculation', 'LIKE', '%' . $term . '%');
            });
        }

        // Heures robuste
        $this->applyTimeOfDayFilter($query, $request, 'start_time');

        $trajets = $query
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->paginate(20)
            ->appends($request->query());

        return view('trajets.index', compact('trajets'));
    }

    /**
     * TRAJETS D’UN VÉHICULE (partenaire) + TRACKS + focus click
     */
    public function byVoiture($vehicle_id, Request $request)
    {
        $userId = (int) auth()->id();

        // ✅ Sécurité: véhicule doit appartenir au partenaire
        $voiture = Voiture::query()
            ->whereHas('utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->findOrFail($vehicle_id);

        $focusId = $request->query('focus_trajet_id'); // string/int

        $query = Trajet::query()->where('vehicle_id', $vehicle_id);

        // ✅ Dates (default today)
        $this->applyByVoitureDateFilters($query, $request, 'start_time');

        // ✅ Heures
        $this->applyTimeOfDayFilter($query, $request, 'start_time');

        // ✅ Liste des trajets
        // IMPORTANT: pour replay cohérent on garde ASC (chronologique)
        // si tu veux tableau “dernier en haut”, tu trieras côté vue
        $trajets = $query->orderBy('start_time', 'asc')->orderBy('id', 'asc')->get();

        [$totalDistance, $totalDuration, $maxSpeed, $avgSpeed] = $this->statsFromDbFields($trajets);

        // ✅ Tracks (focus = continu, pas de découpe agressive)
        $tracks = $this->buildTracks($trajets, $voiture, $focusId);

        return view('trajets.byVoiture', [
            'voiture'       => $voiture,
            'trajets'       => $trajets,
            'tracks'        => $tracks,
            'filters'       => $request->all(),
            'totalDistance' => $totalDistance,
            'totalDuration' => $totalDuration,
            'maxSpeed'      => $maxSpeed,
            'avgSpeed'      => $avgSpeed,
            'focusId'       => $focusId,
        ]);
    }

    /**
     * AFFICHER UN TRAJET SEUL (partenaire)
     */
    public function showTrajet($vehicle_id, $trajet_id, Request $request)
    {
        $userId = (int) auth()->id();

        $voiture = Voiture::query()
            ->whereHas('utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->findOrFail($vehicle_id);

        $trajet = Trajet::query()
            ->where('vehicle_id', $vehicle_id)
            ->where('id', $trajet_id)
            ->firstOrFail();

        $trajets = collect([$trajet]);

        [$totalDistance, $totalDuration, $maxSpeed, $avgSpeed] = $this->statsFromDbFields($trajets);

        // focus forcé = trajet_id
        $tracks = $this->buildTracks($trajets, $voiture, (string)$trajet_id);

        return view('trajets.byVoiture', [
            'voiture'       => $voiture,
            'trajets'       => $trajets,
            'tracks'        => $tracks,
            'filters'       => $request->all(),
            'totalDistance' => $totalDistance,
            'totalDuration' => $totalDuration,
            'maxSpeed'      => $maxSpeed,
            'avgSpeed'      => $avgSpeed,
            'focusId'       => (string) $trajet_id,
        ]);
    }

    // ---------------------------------------------------------------------
    // Dates
    // ---------------------------------------------------------------------

    private function applyIndexDateFilters($query, Request $request, string $column): void
    {
        $hasRange = $request->filled('start_date') || $request->filled('end_date');
        if ($hasRange) {
            if ($request->filled('start_date')) $query->whereDate($column, '>=', $request->start_date);
            if ($request->filled('end_date'))   $query->whereDate($column, '<=', $request->end_date);
            return;
        }

        if ($request->filled('quick')) {
            switch ($request->quick) {
                case 'today':     $query->whereDate($column, now()); break;
                case 'yesterday': $query->whereDate($column, now()->subDay()); break;
                case 'week':      $query->whereBetween($column, [now()->startOfWeek(), now()->endOfWeek()]); break;
                case 'month':     $query->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()]); break;
                case 'year':      $query->whereYear($column, now()->year); break;
            }
        }
    }

    private function applyByVoitureDateFilters($query, Request $request, string $column): void
    {
        $quick = $request->input('quick');
        $quick = is_string($quick) ? trim($quick) : $quick;
        if ($quick === '') $quick = null;

        if (!$quick) {
            if ($request->filled('date')) $quick = 'date';
            elseif ($request->filled('start_date') || $request->filled('end_date')) $quick = 'range';
            else $quick = 'today';
        }

        switch ($quick) {
            case 'today':     $query->whereDate($column, now()); break;
            case 'yesterday': $query->whereDate($column, now()->subDay()); break;
            case 'week':      $query->whereBetween($column, [now()->startOfWeek(), now()->endOfWeek()]); break;
            case 'month':     $query->whereBetween($column, [now()->startOfMonth(), now()->endOfMonth()]); break;
            case 'year':      $query->whereYear($column, now()->year); break;

            case 'date':
                if ($request->filled('date')) $query->whereDate($column, $request->date);
                break;

            case 'range':
                if ($request->filled('start_date')) $query->whereDate($column, '>=', $request->start_date);
                if ($request->filled('end_date'))   $query->whereDate($column, '<=', $request->end_date);
                break;
        }
    }

    /**
     * Filtre heures robuste (gère 22:00->06:00)
     */
    private function applyTimeOfDayFilter($query, Request $request, string $column): void
    {
        $startT = $request->input('start_time');
        $endT   = $request->input('end_time');

        if ($startT && $endT) {
            if ($startT <= $endT) {
                $query->whereTime($column, '>=', $startT)
                      ->whereTime($column, '<=', $endT);
            } else {
                $query->where(function ($q) use ($column, $startT, $endT) {
                    $q->whereTime($column, '>=', $startT)
                      ->orWhereTime($column, '<=', $endT);
                });
            }
            return;
        }

        if ($startT) $query->whereTime($column, '>=', $startT);
        if ($endT)   $query->whereTime($column, '<=', $endT);
    }

    // ---------------------------------------------------------------------
    // Stats
    // ---------------------------------------------------------------------

    private function statsFromDbFields(Collection $trajetsCollection): array
    {
        $totalDistance = (float) $trajetsCollection->sum(fn($x) => (float) ($x->total_distance_km ?? 0));
        $totalDuration = (int)   $trajetsCollection->sum(fn($x) => (int)   ($x->duration_minutes ?? 0));
        $maxSpeed      = (float) ($trajetsCollection->max('max_speed_kmh') ?? 0);

        // moyenne pondérée par durée
        $sumWeighted = 0.0;
        $sumWeight   = 0.0;
        foreach ($trajetsCollection as $tr) {
            $dur = (float) ($tr->duration_minutes ?? 0);
            $spd = (float) ($tr->avg_speed_kmh ?? 0);
            if ($dur > 0) { $sumWeighted += ($spd * $dur); $sumWeight += $dur; }
        }
        $avgSpeed = ($sumWeight > 0)
            ? ($sumWeighted / $sumWeight)
            : (float) ($trajetsCollection->avg('avg_speed_kmh') ?? 0);

        return [round($totalDistance, 1), $totalDuration, round($maxSpeed, 1), round($avgSpeed, 1)];
    }

    // ---------------------------------------------------------------------
    // Tracks (locations) + correction points optimale
    // ---------------------------------------------------------------------

    private function buildTracks(Collection $trajetsCollection, Voiture $voiture, $focusId = null): array
    {
        $tracks = [];

        $windowBeforeMin = (int) env('TRACKS_WINDOW_BEFORE_MIN', 2);
        $windowAfterMin  = (int) env('TRACKS_WINDOW_AFTER_MIN', 2);
        $fallbackEndH    = (int) env('TRACKS_FALLBACK_END_HOURS', 3);

        $maxPointsDefault = (int) env('TRACK_MAX_POINTS', 1500);
        $maxDbRowsDefault = (int) env('TRACK_MAX_DB_ROWS', 20000);

        $maxPointsFocus = (int) env('TRACK_MAX_POINTS_FOCUS', 9999999);
        $maxDbRowsFocus = (int) env('TRACK_MAX_DB_ROWS_FOCUS', 300000);

        $disableReductionFocus = filter_var(env('TRACK_DISABLE_REDUCTION_FOCUS', true), FILTER_VALIDATE_BOOLEAN);

        $filterOutliers = filter_var(env('TRACK_FILTER_OUTLIERS', true), FILTER_VALIDATE_BOOLEAN);
        $maxJumpKmh     = (float) env('TRACK_MAX_JUMP_KMH', 140);
        $maxJumpMeters  = (float) env('TRACK_MAX_JUMP_METERS', 250);
        $maxJumpSeconds = (float) env('TRACK_MAX_JUMP_SECONDS', 10);

        foreach ($trajetsCollection as $t) {

            $isFocusTrip = $focusId && ((string)$t->id === (string)$focusId);

            $maxPoints = $isFocusTrip ? $maxPointsFocus : $maxPointsDefault;
            $maxDbRows = $isFocusTrip ? $maxDbRowsFocus : $maxDbRowsDefault;

            $mac = $t->mac_id_gps ?: $voiture->mac_id_gps;
            if (empty($mac)) continue;

            $start = Carbon::parse($t->start_time);
            $end   = $t->end_time ? Carbon::parse($t->end_time) : (clone $start)->addHours($fallbackEndH);

            $startQ = (clone $start)->subMinutes($windowBeforeMin);
            $endQ   = (clone $end)->addMinutes($windowAfterMin);

            $locs = Location::query()
                ->select(['latitude','longitude','datetime','speed'])
                ->where('mac_id_gps', $mac)
                ->whereBetween('datetime', [$startQ, $endQ])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('latitude', '!=', 0)
                ->where('longitude','!=', 0)
                ->orderBy('datetime', 'asc')
                ->limit($maxDbRows)
                ->get();

            if ($locs->count() < 2) {
                $tracks[] = [
                    'trajet_id'  => $t->id,
                    'start_time' => $start->format('Y-m-d H:i:s'),
                    'end_time'   => $end->format('Y-m-d H:i:s'),
                    'points'     => [],
                    'segments'   => [],
                ];
                continue;
            }

            // 1) raw points + dédup
            $raw = [];
            $prevKey = null;

            foreach ($locs as $l) {
                $lat = (float) $l->latitude;
                $lng = (float) $l->longitude;

                $key = number_format($lat, 6) . ',' . number_format($lng, 6);
                if ($key === $prevKey) continue;
                $prevKey = $key;

                $raw[] = [
                    'lat'   => $lat,
                    'lng'   => $lng,
                    't'     => $l->datetime ? Carbon::parse($l->datetime)->format('Y-m-d H:i:s') : null,
                    'speed' => (float) ($l->speed ?? 0),
                ];
            }

            // 2) outliers
            $points = $filterOutliers
                ? $this->filterOutliers($raw, $maxJumpKmh, $maxJumpMeters, $maxJumpSeconds)
                : $raw;

            // 3) réduction points (PAS en focus si disableReductionFocus)
            if (!$isFocusTrip || !$disableReductionFocus) {
                if (count($points) > $maxPoints) {
                    $points = $this->reducePoints($points, $maxPoints);
                }
            }

            // 4) segmentation :
            // - en focus => on garde 1 seul segment (pas de coupure) pour éviter "ça coupé le trajet"
            // - en liste => on segmente pour éviter les traits absurdes entre points cassés
            $segments = $isFocusTrip
                ? [$points]
                : $this->splitSegments($points, 180); // seuil mètres

            $tracks[] = [
                'trajet_id'  => $t->id,
                'start_time' => $start->format('Y-m-d H:i:s'),
                'end_time'   => $end->format('Y-m-d H:i:s'),
                'points'     => $points,     // replay
                'segments'   => $segments,   // dessin
            ];
        }

        return $tracks;
    }

    private function reducePoints(array $points, int $maxPoints): array
    {
        $n = count($points);
        if ($n <= $maxPoints) return $points;

        $step = (int) ceil($n / $maxPoints);
        $reduced = [];

        for ($i = 0; $i < $n; $i += $step) $reduced[] = $points[$i];

        $last = $points[$n - 1] ?? null;
        $end  = $reduced[count($reduced) - 1] ?? null;

        if ($last && (!$end || $end['lat'] != $last['lat'] || $end['lng'] != $last['lng'])) {
            $reduced[] = $last;
        }

        return $reduced;
    }

    private function splitSegments(array $points, float $breakMeters): array
    {
        $segs = [];
        if (count($points) < 2) return $segs;

        $seg = [$points[0]];
        for ($i = 1; $i < count($points); $i++) {
            $a = $points[$i - 1];
            $b = $points[$i];
            $d = $this->haversineMeters($a['lat'], $a['lng'], $b['lat'], $b['lng']);

            if ($d > $breakMeters) {
                if (count($seg) >= 2) $segs[] = $seg;
                $seg = [$b];
            } else {
                $seg[] = $b;
            }
        }
        if (count($seg) >= 2) $segs[] = $seg;

        return $segs;
    }

    private function filterOutliers(array $points, float $maxKmh, float $maxJumpMeters, float $maxJumpSeconds): array
    {
        if (count($points) < 2) return $points;

        $out = [];
        $prev = null;

        foreach ($points as $p) {
            if (!$prev) { $out[] = $p; $prev = $p; continue; }

            $d = $this->haversineMeters($prev['lat'], $prev['lng'], $p['lat'], $p['lng']);
            if ($d < 3) continue; // micro jitter

            $t1 = $prev['t'] ? strtotime(str_replace(' ', 'T', $prev['t'])) : null;
            $t2 = $p['t']    ? strtotime(str_replace(' ', 'T', $p['t']))    : null;

            if ($t1 && $t2) {
                $dt = abs($t2 - $t1);
                if ($dt > 0) {
                    $v = ($d / $dt) * 3.6;
                    if ($v > $maxKmh) continue;
                    if ($d > $maxJumpMeters && $dt <= $maxJumpSeconds) continue;
                } else {
                    if ($d > $maxJumpMeters) continue;
                }
            } else {
                if ($d > $maxJumpMeters * 3) continue;
            }

            $out[] = $p;
            $prev = $p;
        }

        return $out;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000.0;
        $toRad = fn($x) => $x * M_PI / 180.0;

        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos($toRad($lat1)) * cos($toRad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $R * $c;
    }
}