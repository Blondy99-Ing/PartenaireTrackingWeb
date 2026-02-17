<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    /**
     * Types utilisés par la vue (tes cartes + filtre)
     * (tu peux en ajouter si besoin)
     */
    private array $statsTypes = ['stolen','geofence','speed','safe_zone','time_zone'];

    private function typeLabel(?string $type): string
    {
        if (!$type) return 'Unknown';

        return match ($type) {
            'geofence'       => 'GeoFence Breach',
            'safe_zone'      => 'Safe Zone',
            'speed'          => 'Speeding',
            'engine'         => 'Engine Alert',
            'general'        => 'General',
            'stolen'         => 'Stolen Vehicle',
            'low_battery'    => 'Low Battery',
            'power_failure'  => 'Power Failure',
            'offline'        => 'Offline',
            'device_removal' => 'Device Removal',
            'time_zone'      => 'Time Zone',
            default          => ucfirst(str_replace('_', ' ', (string)$type)),
        };
    }

    /**
     * ✅ Partenaire = user.
     * Flotte partenaire = table association_user_voitures (ownership),
     * même si aucune affectation chauffeur.
     */
    private function partnerVehicleIds(int $partnerId): array
    {
        return DB::table('association_user_voitures')
            ->where('user_id', $partnerId)
            ->pluck('voiture_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * ✅ Dernière affectation chauffeur par voiture
     * On suppose acvp.id auto-incrément; c’est le plus fiable.
     */
    private function latestAssignSubquery(array $vehicleIds)
    {
        return DB::table('association_chauffeur_voiture_partner as acvp')
            ->selectRaw('MAX(acvp.id) as max_id, acvp.voiture_id')
            ->whereIn('acvp.voiture_id', $vehicleIds)
            ->groupBy('acvp.voiture_id');
    }

    /**
     * ✅ Normalise les types (au cas où la DB stocke des variantes)
     */
    private function normalizeType(?string $t): string
    {
        $t = strtolower(trim((string)$t));
        if ($t === '') return 'unknown';

        return match ($t) {
            'overspeed', 'speeding' => 'speed',
            'safezone', 'safe-zone' => 'safe_zone',
            'geo_fence'             => 'geofence',
            'timezone', 'time-zone' => 'time_zone',
            default                 => $t,
        };
    }

    /**
     * ✅ Filtre "OUVERTES du jour"
     * - OUVERT = processed = 0 OU NULL
     * - date = alerted_at (sinon created_at) entre startOfDay et endOfDay
     */
    private function applyOpenToday($q): void
    {
        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $q->where(function ($qq) {
            $qq->where('a.processed', 0)->orWhereNull('a.processed');
        });

        $q->where(function ($qq) use ($start, $end) {
            $qq->whereBetween('a.alerted_at', [$start, $end])
               ->orWhere(function ($qq2) use ($start, $end) {
                   $qq2->whereNull('a.alerted_at')
                       ->whereBetween('a.created_at', [$start, $end]);
               });
        });
    }

    /**
     * GET /alerts (JSON)
     * - data paginé (historique), tri récent
     * - stats.by_type = OUVERTES du jour uniquement
     */
    public function index(Request $request)
    {
        $partnerId = (int) Auth::id();

        // pagination
        $perPage = (int) $request->query('per_page', 50);
        if ($perPage < 10) $perPage = 50;
        if ($perPage > 200) $perPage = 200;

        $page = (int) $request->query('page', 1);
        if ($page < 1) $page = 1;

        // flotte
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
                'meta'   => [
                    'current_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'last_page' => 1,
                ],
                'stats' => [
                    'by_type' => array_fill_keys($this->statsTypes, 0),
                ],
            ]);
        }

        // last assignment subquery (chauffeur optionnel)
        $latestAssign = $this->latestAssignSubquery($vehicleIds);

        // base query (SCOPE + joins)
        $base = DB::table('alerts as a')
            ->leftJoin('voitures as v', 'v.id', '=', 'a.voiture_id')

            ->leftJoinSub($latestAssign, 'last_acvp', function ($join) {
                $join->on('last_acvp.voiture_id', '=', 'a.voiture_id');
            })
            ->leftJoin('association_chauffeur_voiture_partner as acvp2', 'acvp2.id', '=', 'last_acvp.max_id')
            ->leftJoin('users as u', 'u.id', '=', 'acvp2.chauffeur_id')

            ->whereIn('a.voiture_id', $vehicleIds);

        // -----------------------
        // ✅ STATS OUVERTES DU JOUR
        // -----------------------
        $statsQ = clone $base;
        $this->applyOpenToday($statsQ);

        $statsRows = $statsQ
            ->selectRaw("COALESCE(a.alert_type,'unknown') as t, COUNT(*) as c")
            ->groupBy('t')
            ->get();

        $byType = array_fill_keys($this->statsTypes, 0);
        foreach ($statsRows as $r) {
            $t = $this->normalizeType($r->t);
            if (array_key_exists($t, $byType)) {
                $byType[$t] += (int) $r->c;
            }
        }

        // -----------------------
        // FILTRES tableau (optionnels)
        // -----------------------
        if ($request->filled('alert_type')) {
            $type = (string) $request->input('alert_type');
            if ($type !== 'all' && $type !== '') {
                $base->where('a.alert_type', $type);
            }
        }

        if ($request->filled('vehicle_id')) {
            $vid = (int) $request->input('vehicle_id');
            if ($vid > 0) $base->where('a.voiture_id', $vid);
        }

        if ($request->filled('user_id')) {
            $uid = (int) $request->input('user_id');
            if ($uid > 0) $base->where('acvp2.chauffeur_id', $uid);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            if ($q !== '') {
                $base->where(function ($qq) use ($q) {
                    $qq->where('v.immatriculation', 'like', "%{$q}%")
                       ->orWhere('a.message', 'like', "%{$q}%")
                       ->orWhere('u.nom', 'like', "%{$q}%")
                       ->orWhere('u.prenom', 'like', "%{$q}%");
                });
            }
        }

        // -----------------------
        // ✅ TABLE PAGINÉE + TRI RECENT
        // -----------------------
        $p = $base
            ->orderByRaw("COALESCE(a.alerted_at, a.created_at) DESC")
            ->select([
                'a.id','a.voiture_id','a.alert_type','a.message','a.read','a.processed','a.processed_by',
                'a.alerted_at','a.created_at',

                'v.id as v_id','v.immatriculation','v.marque','v.model',

                'u.id as driver_id','u.nom as driver_nom','u.prenom as driver_prenom',
            ])
            ->paginate($perPage, ['*'], 'page', $page);

        $items = collect($p->items());

        $data = $items->map(function ($r) {
            $driverLabel = trim(($r->driver_nom ?? '') . ' ' . ($r->driver_prenom ?? ''));
            if ($driverLabel === '') $driverLabel = 'Non associé';

            $ts = $r->alerted_at ?? $r->created_at ?? null;

            $typeNorm = $this->normalizeType($r->alert_type);

            return [
                'id' => (int)$r->id,
                'voiture_id' => (int)$r->voiture_id,

                'alert_type' => $typeNorm,
                'type' => $typeNorm,
                'type_label' => $this->typeLabel($typeNorm),

                'message' => $r->message,
                'location' => $r->message,

                'read' => (bool)$r->read,
                'processed' => (bool)($r->processed ?? false),
                'processed_by' => $r->processed_by ? (int)$r->processed_by : null,

                'alerted_at_human' => $ts ? date('d/m/Y H:i:s', strtotime($ts)) : '-',

                'voiture' => $r->v_id ? [
                    'id' => (int)$r->v_id,
                    'immatriculation' => $r->immatriculation,
                    'marque' => $r->marque,
                    'model' => $r->model,
                ] : null,

                'user_id' => !empty($r->driver_id) ? (int)$r->driver_id : null,
                'driver_label' => $driverLabel,
                'users_labels' => $driverLabel,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'total'        => $p->total(),
                'last_page'    => $p->lastPage(),
            ],
            'stats' => [
                'by_type' => $byType, // ✅ ouvertes du jour
            ],
        ]);
    }

    /**
     * GET /alerts/poll?after_id=123&limit=20
     * -> nouvelles alertes uniquement
     */
    public function poll(Request $request)
    {
        $partnerId = (int) Auth::id();
        $afterId = (int) $request->query('after_id', 0);
        $limit = (int) $request->query('limit', 20);
        if ($limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        $vehicleIds = $this->partnerVehicleIds($partnerId);
        if (empty($vehicleIds)) {
            return response()->json(['status' => 'success', 'data' => [], 'meta' => ['max_id' => $afterId]]);
        }

        $latestAssign = $this->latestAssignSubquery($vehicleIds);

        $rows = DB::table('alerts as a')
            ->leftJoin('voitures as v', 'v.id', '=', 'a.voiture_id')
            ->leftJoinSub($latestAssign, 'last_acvp', function ($join) {
                $join->on('last_acvp.voiture_id', '=', 'a.voiture_id');
            })
            ->leftJoin('association_chauffeur_voiture_partner as acvp2', 'acvp2.id', '=', 'last_acvp.max_id')
            ->leftJoin('users as u', 'u.id', '=', 'acvp2.chauffeur_id')
            ->whereIn('a.voiture_id', $vehicleIds)
            ->where('a.id', '>', $afterId)
            ->orderByDesc('a.id')
            ->limit($limit)
            ->select([
                'a.id','a.voiture_id','a.alert_type','a.message','a.read','a.processed','a.processed_by',
                'a.alerted_at','a.created_at',

                'v.id as v_id','v.immatriculation','v.marque','v.model',

                'u.id as driver_id','u.nom as driver_nom','u.prenom as driver_prenom',
            ])
            ->get();

        // older -> newer pour affichage agréable
        $rows = $rows->reverse()->values();

        $maxId = $afterId;

        $data = $rows->map(function ($r) use (&$maxId) {
            $maxId = max($maxId, (int)$r->id);

            $driverLabel = trim(($r->driver_nom ?? '') . ' ' . ($r->driver_prenom ?? ''));
            if ($driverLabel === '') $driverLabel = 'Non associé';

            $ts = $r->alerted_at ?? $r->created_at ?? null;

            $typeNorm = $this->normalizeType($r->alert_type);

            return [
                'id' => (int)$r->id,
                'voiture_id' => (int)$r->voiture_id,
                'alert_type' => $typeNorm,
                'type' => $typeNorm,
                'type_label' => $this->typeLabel($typeNorm),
                'message' => $r->message,
                'location' => $r->message,
                'read' => (bool)$r->read,
                'processed' => (bool)($r->processed ?? false),
                'processed_by' => $r->processed_by ? (int)$r->processed_by : null,
                'alerted_at_human' => $ts ? date('d/m/Y H:i:s', strtotime($ts)) : '-',
                'voiture' => $r->v_id ? [
                    'id' => (int)$r->v_id,
                    'immatriculation' => $r->immatriculation,
                    'marque' => $r->marque,
                    'model' => $r->model,
                ] : null,
                'user_id' => !empty($r->driver_id) ? (int)$r->driver_id : null,
                'driver_label' => $driverLabel,
                'users_labels' => $driverLabel,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => ['max_id' => $maxId],
        ]);
    }

    /**
     * PATCH /alerts/{id}/read
     */
    public function markReadApi($id)
    {
        $partnerId = (int) Auth::id();
        $allowedVehicleIds = $this->partnerVehicleIds($partnerId);

        $alertVehicleId = DB::table('alerts')->where('id', (int)$id)->value('voiture_id');
        if (!$alertVehicleId || !in_array((int)$alertVehicleId, $allowedVehicleIds, true)) {
            return response()->json(['status' => 'error', 'message' => 'Accès non autorisé.'], 403);
        }

        DB::table('alerts')
            ->where('id', (int)$id)
            ->update(['read' => 1, 'updated_at' => now()]);

        return response()->json(['status' => 'success', 'message' => 'Alerte ignorée.']);
    }

    /**
     * PATCH /alerts/{id}/processed
     */
    public function markProcessedApi($id)
    {
        $partnerId = (int) Auth::id();
        $allowedVehicleIds = $this->partnerVehicleIds($partnerId);

        $alertVehicleId = DB::table('alerts')->where('id', (int)$id)->value('voiture_id');
        if (!$alertVehicleId || !in_array((int)$alertVehicleId, $allowedVehicleIds, true)) {
            return response()->json(['status' => 'error', 'message' => 'Accès non autorisé.'], 403);
        }

        DB::table('alerts')
            ->where('id', (int)$id)
            ->update([
                'processed' => 1,
                'processed_by' => $partnerId,
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'success', 'message' => 'Alerte traitée.']);
    }
}