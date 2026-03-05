<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlertController extends Controller
{
    private array $statsTypes = ['stolen','geofence','speed','safe_zone','time_zone'];

    /**
     * GET /alerts
     * Liste principale avec statistiques synchronisées
     */
    public function index(Request $request)
    {
        $partnerId = (int) Auth::id();
        $tz = 'Africa/Douala';
        
        // 1. Récupérer les véhicules autorisés (Pluck simple)
        $vehicleIds = DB::table('association_user_voitures')
            ->where('user_id', $partnerId)
            ->pluck('voiture_id')
            ->toArray();

        if (empty($vehicleIds)) {
            return $this->emptyResponse($request);
        }

        // 2. Construction de la requête de base
        // On utilise DB::table pour éviter la surcharge mémoire d'Eloquent sur les gros listings
        $query = DB::table('alerts as a')
            ->join('voitures as v', 'v.id', '=', 'a.voiture_id')
            // Jointure pour le chauffeur actuel (dernière affectation)
            ->leftJoin(DB::raw('(SELECT MAX(id) as max_id, voiture_id FROM association_chauffeur_voiture_partner GROUP BY voiture_id) as last_assign'), 'last_assign.voiture_id', '=', 'a.voiture_id')
            ->leftJoin('association_chauffeur_voiture_partner as acvp', 'acvp.id', '=', 'last_assign.max_id')
            ->leftJoin('users as u', 'u.id', '=', 'acvp.chauffeur_id')
            ->whereIn('a.voiture_id', $vehicleIds);

        // 3. Application des filtres (Dates, Heures, Recherche)
        $this->applyFilters($query, $request, $tz);

        // 4. Calcul des Stats (sur la requête filtrée mais avant pagination)
        $stats = $this->computeStats(clone $query);

        // 5. Pagination et exécution
        $perPage = (int) $request->query('per_page', 50);
        $alerts = $query->orderByDesc('a.id')
            ->select([
                'a.*', 
                'v.immatriculation', 'v.marque', 'v.model',
                'u.nom as driver_nom', 'u.prenom as driver_prenom'
            ])
            ->paginate(min($perPage, 200));

        // 6. Formatage de la réponse
        return response()->json([
            'status' => 'success',
            'data'   => collect($alerts->items())->map(fn($r) => $this->formatAlert($r)),
            'meta'   => [
                'current_page' => $alerts->currentPage(),
                'total'        => $alerts->total(),
                'last_page'    => $alerts->lastPage(),
            ],
            'stats'  => $stats
        ]);
    }

    /**
     * Filtres de recherche et de date
     */
    private function applyFilters($query, Request $request, $tz)
    {
        // Filtre de texte (Immat, Message, Chauffeur)
        if ($request->filled('q')) {
            $term = "%{$request->q}%";
            $query->where(function($q) use ($term) {
                $q->where('v.immatriculation', 'like', $term)
                  ->orWhere('a.message', 'like', $term)
                  ->orWhere('u.nom', 'like', $term);
            });
        }

        // Filtre Type
        if ($request->filled('alert_type') && $request->alert_type !== 'all') {
            $query->where('a.alert_type', $request->alert_type);
        }

        // Logique de Date (Utilise created_at qui est indexé)
        $quick = $request->get('quick') ?: $request->get('date_quick', 'today');
        $now = now($tz);

        if ($quick && $quick !== 'range') {
            match ($quick) {
                'today'     => $query->whereDate('a.created_at', $now->toDateString()),
                'yesterday' => $query->whereDate('a.created_at', $now->subDay()->toDateString()),
                'this_week' => $query->whereBetween('a.created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]),
                'this_month'=> $query->whereBetween('a.created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]),
                default     => null
            };
        } elseif ($request->filled('date_from')) {
            $query->whereBetween('a.created_at', [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to ?? $request->date_from)->endOfDay()
            ]);
        }
    }

    /**
     * Calcul des statistiques par type
     */
    private function computeStats($query)
    {
        $rows = $query->selectRaw("alert_type, COUNT(*) as count")
                      ->groupBy('alert_type')
                      ->get();

        $byType = array_fill_keys($this->statsTypes, 0);
        foreach ($rows as $row) {
            $type = $this->normalizeType($row->alert_type);
            if (isset($byType[$type])) {
                $byType[$type] = (int) $row->count;
            }
        }
        return ['by_type' => $byType];
    }

    /**
     * Formate une ligne de résultat pour le JSON
     */
    private function formatAlert($r): array
    {
        return [
            'id'           => (int) $r->id,
            'type'         => $this->normalizeType($r->alert_type),
            'message'      => $r->message,
            'is_read'      => (bool) $r->read,
            'is_processed' => (bool) ($r->processed ?? false),
            'created_at'   => Carbon::parse($r->created_at)->format('d/m/Y H:i:s'),
            'vehicle'      => [
                'id'    => $r->voiture_id,
                'label' => $r->immatriculation . " (" . $r->marque . ")",
            ],
            'driver'       => trim(($r->driver_nom ?? '') . ' ' . ($r->driver_prenom ?? '')) ?: 'Non assigné'
        ];
    }

    private function normalizeType(?string $t): string
    {
        return match (strtolower(trim((string)$t))) {
            'overspeed', 'speeding' => 'speed',
            'geo_fence'             => 'geofence',
            'timezone'              => 'time_zone',
            default                 => $t ?: 'unknown',
        };
    }

    private function emptyResponse($request) {
        return response()->json([
            'status' => 'success', 'data' => [], 
            'meta' => ['total' => 0, 'current_page' => 1],
            'stats' => ['by_type' => array_fill_keys($this->statsTypes, 0)]
        ]);
    }
}