<?php

namespace App\Http\Controllers\Trajets;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Trajet;
use App\Models\Voiture;

class TrajetController extends Controller
{
    /**
     * LISTE DES TRAJETS
     */
    public function index(Request $request)
    {
        $userId = auth()->id();

        // 🔥 NE PRENDRE QUE LES TRAJETS DES VÉHICULES DU USER CONNECTÉ
        $query = Trajet::with('voiture')
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });

        /* ---------------------------
           1. Filtres rapides
        ----------------------------*/
        if ($request->quick) {
            switch ($request->quick) {
                case 'today':
                    $query->whereDate('start_time', now());
                    break;

                case 'yesterday':
                    $query->whereDate('start_time', now()->subDay());
                    break;

                case 'week':
                    $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;

                case 'month':
                    $query->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()]);
                    break;

                case 'year':
                    $query->whereYear('start_time', now()->year);
                    break;
            }
        }

        /* ---------------------------
           2. Dates personnalisées
        ----------------------------*/
        if ($request->start_date) {
            $query->whereDate('start_time', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('start_time', '<=', $request->end_date);
        }

        /* ---------------------------
           3. Recherche véhicule
        ----------------------------*/
        if ($request->vehicule) {
            $query->whereHas('voiture', function ($q) use ($request) {
                $q->where('immatriculation', 'LIKE', '%' . $request->vehicule . '%');
            });
        }

        /* ---------------------------
           4. Heures
        ----------------------------*/
        if ($request->start_time) {
            $query->whereTime('start_time', '>=', $request->start_time);
        }

        if ($request->end_time) {
            $query->whereTime('start_time', '<=', $request->end_time);
        }

        /* ---------------------------
           5. Résultat final
        ----------------------------*/
        $trajets = $query->orderBy('start_time', 'desc')->paginate(20);

        return view('trajets.index', compact('trajets'));
    }



    /**
     * TRAJETS D’UN VÉHICULE SPÉCIFIQUE
     */
    public function byVoiture($vehicle_id, Request $request)
    {
        $userId = auth()->id();

        // 🔥 Vérifier que le véhicule appartient au user connecté
        $voiture = Voiture::whereHas('utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->findOrFail($vehicle_id);

        // Optionnel (sécurité extrême)
        if ($voiture->utilisateur->first()->id !== $userId) {
            abort(403, 'Accès non autorisé');
        }

        $query = Trajet::where('vehicle_id', $vehicle_id);

        /* ---------------------------
            1. Filtres rapides
        ----------------------------*/
        $quick = $request->quick ?? 'today';

        switch ($quick) {
            case 'today':
                $query->whereDate('start_time', now());
                break;

            case 'yesterday':
                $query->whereDate('start_time', now()->subDay());
                break;

            case 'week':
                $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
                break;

            case 'month':
                $query->whereMonth('start_time', now()->month)
                      ->whereYear('start_time', now()->year);
                break;

            case 'year':
                $query->whereYear('start_time', now()->year);
                break;

            case 'date':
                if ($request->date) {
                    $query->whereDate('start_time', $request->date);
                }
                break;

            case 'range':
                if ($request->start_date) {
                    $query->whereDate('start_time', '>=', $request->start_date);
                }
                if ($request->end_date) {
                    $query->whereDate('start_time', '<=', $request->end_date);
                }
                break;
        }

        /* ---------------------------
           2. Heures
        ----------------------------*/
        if ($request->start_time) {
            $query->whereTime('start_time', '>=', $request->start_time);
        }

        if ($request->end_time) {
            $query->whereTime('start_time', '<=', $request->end_time);
        }

        /* ---------------------------
           3. Exécution
        ----------------------------*/
        $trajets = $query->orderBy('start_time', 'asc')->get();

        /* ---------------------------
           4. Statistiques
        ----------------------------*/
        $totalDistance = $trajets->sum('total_distance_km');
        $totalDuration = $trajets->sum('duration_minutes');
        $maxSpeed      = $trajets->max('max_speed_kmh');

        $avgSpeed = $totalDuration > 0
            ? round($totalDistance / ($totalDuration / 60), 1)
            : 0;

        return view('trajets.byVoiture', [
            'voiture'       => $voiture,
            'trajets'       => $trajets,
            'filters'       => $request->all(),
            'totalDistance' => round($totalDistance, 1),
            'totalDuration' => $totalDuration,
            'maxSpeed'      => round($maxSpeed, 1),
            'avgSpeed'      => $avgSpeed,
        ]);
    }





    public function showTrajet($vehicle_id, $trajet_id, Request $request)
{
    $userId = auth()->id();

    // Vérification du véhicule
    $voiture = Voiture::whereHas('utilisateur', function($q) use ($userId){
            $q->where('user_id', $userId);
        })
        ->findOrFail($vehicle_id);

    // Charger le trajet spécifique
    $trajet = Trajet::where('vehicle_id', $vehicle_id)
                    ->where('id', $trajet_id)
                    ->firstOrFail();

    // Mettre le trajet dans une collection pour compatibilité avec la vue
    $trajets = collect([$trajet]);

    // Statistiques
    $totalDistance = $trajet->total_distance_km;
    $totalDuration = $trajet->duration_minutes;
    $maxSpeed      = $trajet->max_speed_kmh;
    $avgSpeed      = $trajet->avg_speed_kmh;

    return view('trajets.byVoiture', [
        'voiture'       => $voiture,
        'trajets'       => $trajets,
        'filters'       => $request->all(),
        'totalDistance' => round($totalDistance, 1),
        'totalDuration' => $totalDuration,
        'maxSpeed'      => round($maxSpeed, 1),
        'avgSpeed'      => round($avgSpeed, 1)
    ]);
}

}
