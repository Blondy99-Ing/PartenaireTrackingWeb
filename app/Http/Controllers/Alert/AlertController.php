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

    private array $visibleAlertTypes = [
        'stolen',
        'geofence',
        'geo_fence',
        'speed',
        'overspeed',
        'speeding',
        'safe_zone',
        'time_zone',
        'timezone',
    ];

    public function index(Request $request)
    {
        $partnerId = (int) Auth::id();
        $tz = 'Africa/Douala';

        $vehicleIds = DB::table('association_user_voitures')
            ->where('user_id', $partnerId)
            ->pluck('voiture_id')
            ->toArray();

        if (empty($vehicleIds)) {
            return $this->emptyResponse($request);
        }

        $query = DB::table('alerts as a')
            ->join('voitures as v', 'v.id', '=', 'a.voiture_id')
            ->leftJoin(DB::raw('(SELECT MAX(id) as max_id, voiture_id FROM association_chauffeur_voiture_partner GROUP BY voiture_id) as last_assign'), 'last_assign.voiture_id', '=', 'a.voiture_id')
            ->leftJoin('association_chauffeur_voiture_partner as acvp', 'acvp.id', '=', 'last_assign.max_id')
            ->leftJoin('users as u', 'u.id', '=', 'acvp.chauffeur_id')
            ->whereIn('a.voiture_id', $vehicleIds)
            ->whereIn('a.alert_type', $this->visibleAlertTypes);

        $this->applyFilters($query, $request, $tz);

        $stats = $this->computeStats(clone $query);

        $perPage = (int) $request->query('per_page', 50);
        $alerts = $query->orderByDesc('a.id')
            ->select([
                'a.*',
                'v.immatriculation', 'v.marque', 'v.model',
                'u.nom as driver_nom', 'u.prenom as driver_prenom'
            ])
            ->paginate(min($perPage, 200));

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

    private function applyFilters($query, Request $request, $tz)
    {
        if ($request->filled('q')) {
            $term = "%{$request->q}%";
            $query->where(function($q) use ($term) {
                $q->where('v.immatriculation', 'like', $term)
                  ->orWhere('a.message', 'like', $term)
                  ->orWhere('u.nom', 'like', $term);
            });
        }

        if ($request->filled('alert_type') && $request->alert_type !== 'all') {
            $requestedType = strtolower(trim($request->alert_type));

            match ($requestedType) {
                'speed' => $query->whereIn('a.alert_type', ['speed', 'overspeed', 'speeding']),
                'geofence' => $query->whereIn('a.alert_type', ['geofence', 'geo_fence']),
                'time_zone' => $query->whereIn('a.alert_type', ['time_zone', 'timezone']),
                default => $query->where('a.alert_type', $requestedType),
            };
        }

        $quick = $request->get('quick') ?: $request->get('date_quick', 'today');
        $now = now($tz);

        if ($quick && $quick !== 'range') {
            match ($quick) {
                'today'     => $query->whereDate('a.created_at', $now->copy()->toDateString()),
                'yesterday' => $query->whereDate('a.created_at', $now->copy()->subDay()->toDateString()),
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

    private function computeStats($query)
    {
        $rows = $query->selectRaw("alert_type, COUNT(*) as count")
                      ->groupBy('alert_type')
                      ->get();

        $byType = array_fill_keys($this->statsTypes, 0);

        foreach ($rows as $row) {
            $type = $this->normalizeType($row->alert_type);
            if (isset($byType[$type])) {
                $byType[$type] += (int) $row->count;
            }
        }

        return ['by_type' => $byType];
    }

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