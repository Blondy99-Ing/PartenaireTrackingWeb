<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trajet;
use App\Models\Voiture;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrajetController extends Controller
{
    /**
     * Liste des trajets avec filtres standardisés
     */
    public function index(Request $request)
    {
        $userId = auth()->id();
        $tz = 'Africa/Douala';

        // 1. Query de base avec jointure pour éviter le N+1 sur les voitures
        $query = Trajet::query()
            ->with(['voiture']) // Charger la relation pour le label/immat
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });

        // 2. Filtre Véhicule
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', (int) $request->vehicle_id);
        }

        // 3. Logique de Date (Quick Filters) - Uniformisée
        $quick = $request->query('quick', $request->query('date_quick', 'today'));
        $now = now($tz);

        if ($quick && $quick !== 'range') {
            match ($quick) {
                'today'     => $query->whereDate('start_time', $now->toDateString()),
                'yesterday' => $query->whereDate('start_time', $now->subDay()->toDateString()),
                'this_week' => $query->whereBetween('start_time', [$now->startOfWeek(), $now->endOfWeek()]),
                'this_month'=> $query->whereBetween('start_time', [$now->startOfMonth(), $now->endOfMonth()]),
                default     => null
            };
        } elseif ($request->filled('start_date')) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end   = Carbon::parse($request->end_date ?? $request->start_date)->endOfDay();
            $query->whereBetween('start_time', [$start, $end]);
        }

        // 4. Pagination (20 à 200 max)
        $perPage = (int) $request->query('per_page', 20);
        $trajets = $query->orderByDesc('start_time')->paginate(min($perPage, 200));

        // 5. Formatage JSON manuel (propre pour le dashboard)
        if ($request->expectsJson() || $request->query('format') === 'json') {
            return response()->json([
                'status' => 'success',
                'data' => collect($trajets->items())->map(function ($t) {
                    return [
                        'id'                => $t->id,
                        'vehicle_id'        => $t->vehicle_id,
                        'immatriculation'   => $t->voiture?->immatriculation,
                        'driver_label'      => $t->voiture?->users_labels ?? 'Inconnu',
                        'start_time'        => $t->start_time,
                        'end_time'          => $t->end_time,
                        'duration_minutes'  => (int) $t->duration_minutes,
                        'total_distance_km' => round((float)$t->total_distance_km, 2),
                        'avg_speed_kmh'     => round((float)$t->avg_speed_kmh, 1),
                        'max_speed_kmh'     => round((float)$t->max_speed_kmh, 1),
                    ];
                }),
                'meta' => [
                    'current_page' => $trajets->currentPage(),
                    'total'        => $trajets->total(),
                    'last_page'    => $trajets->lastPage()
                ]
            ]);
        }

        return view('trajets.index', compact('trajets'));
    }

    /**
     * Détail du trajet + Points GPS pour la carte
     */
  public function showTrajet($vehicle_id, $trajet_id, Request $request)
{
    $userId = auth()->id();

    // 1. Sécurité : Vérifier que l'utilisateur possède le véhicule
    $trajet = Trajet::with('voiture')
        ->where('id', $trajet_id)
        ->where('vehicle_id', $vehicle_id)
        ->whereHas('voiture.utilisateur', fn($q) => $q->where('user_id', $userId))
        ->firstOrFail();

    // 2. Récupération des points GPS
    // CORRECTION : Utilisation de 'datetime' au lieu de 'created_at'
    $points = DB::table('locations')
        ->where('mac_id_gps', $trajet->mac_id_gps ?? $trajet->voiture->mac_id_gps)
        ->whereBetween('datetime', [$trajet->start_time, $trajet->end_time ?? now()])
        ->select(['latitude as lat', 'longitude as lng', 'datetime as ts', 'speed'])
        ->orderBy('datetime', 'asc')
        ->get();

    // 3. Fallback : Si aucun point en table locations
    if ($points->isEmpty() && $trajet->start_lat) {
        $points = collect([
            [
                'lat' => (float)$trajet->start_lat, 
                'lng' => (float)$trajet->start_lng, 
                'ts' => $trajet->start_time, 
                'speed' => 0
            ],
            [
                'lat' => (float)$trajet->end_lat, 
                'lng' => (float)$trajet->end_lng, 
                'ts' => $trajet->end_time ?? now(), 
                'speed' => 0
            ],
        ]);
    }

    return response()->json([
        'status' => 'success',
        'data' => [
            'trajet' => [
                'id' => $trajet->id,
                'immatriculation' => $trajet->voiture->immatriculation ?? '—',
                'start_time' => $trajet->start_time,
                'end_time'   => $trajet->end_time,
                'stats' => [
                    'distance' => round($trajet->total_distance_km, 2),
                    'duration' => $trajet->duration_minutes,
                    'max_speed' => $trajet->max_speed_kmh,
                    'avg_speed' => $trajet->avg_speed_kmh,
                ]
            ],
            'track' => [
                'points' => $points,
                'count'  => $points->count()
            ]
        ]
    ]);
}

}