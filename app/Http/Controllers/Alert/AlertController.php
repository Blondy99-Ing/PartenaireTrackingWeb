<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AlertController extends Controller
{
    /**
     * Types utilisés par la vue (tes cartes + filtre)
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

    private function latestAssignSubquery(array $vehicleIds)
    {
        return DB::table('association_chauffeur_voiture_partner as acvp')
            ->selectRaw('MAX(acvp.id) as max_id, acvp.voiture_id')
            ->whereIn('acvp.voiture_id', $vehicleIds)
            ->groupBy('acvp.voiture_id');
    }

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
     * Base query partner (scope + joins)
     */
    private function baseQueryForPartner(array $vehicleIds)
    {
        $latestAssign = $this->latestAssignSubquery($vehicleIds);

        return DB::table('alerts as a')
            ->leftJoin('voitures as v', 'v.id', '=', 'a.voiture_id')

            ->leftJoinSub($latestAssign, 'last_acvp', function ($join) {
                $join->on('last_acvp.voiture_id', '=', 'a.voiture_id');
            })
            ->leftJoin('association_chauffeur_voiture_partner as acvp2', 'acvp2.id', '=', 'last_acvp.max_id')
            ->leftJoin('users as u', 'u.id', '=', 'acvp2.chauffeur_id')

            ->whereIn('a.voiture_id', $vehicleIds);
    }

    private function readPagination(Request $request, int $defaultPerPage = 50, int $maxPerPage = 200): array
    {
        $perPage = (int) $request->query('per_page', $defaultPerPage);
        if ($perPage < 1) $perPage = $defaultPerPage;
        if ($perPage > $maxPerPage) $perPage = $maxPerPage;

        $page = (int) $request->query('page', 1);
        if ($page < 1) $page = 1;

        return [$perPage, $page];
    }

    /**
     * Parse date filters:
     * - date_quick: today|yesterday|this_week|this_month
     * - date: YYYY-MM-DD (jour exact)
     * - date_from / date_to: YYYY-MM-DD (plage)
     */
    private function applyDateFilters($q, Request $request): void
    {
        $col = DB::raw("COALESCE(a.alerted_at, a.created_at)");

        if ($request->filled('date_quick')) {
            $quick = strtolower(trim((string)$request->input('date_quick')));

            if ($quick === 'today') {
                $start = now()->startOfDay();
                $end   = now()->endOfDay();
                $q->whereBetween($col, [$start, $end]);
                return;
            }

            if ($quick === 'yesterday') {
                $start = now()->subDay()->startOfDay();
                $end   = now()->subDay()->endOfDay();
                $q->whereBetween($col, [$start, $end]);
                return;
            }

            if ($quick === 'this_week') {
                // Laravel: startOfWeek dépend de config('app.week_start') / Carbon
                $start = now()->startOfWeek()->startOfDay();
                $end   = now()->endOfWeek()->endOfDay();
                $q->whereBetween($col, [$start, $end]);
                return;
            }

            if ($quick === 'this_month') {
                $start = now()->startOfMonth()->startOfDay();
                $end   = now()->endOfMonth()->endOfDay();
                $q->whereBetween($col, [$start, $end]);
                return;
            }
        }

        if ($request->filled('date')) {
            try {
                $d = Carbon::parse((string)$request->input('date'));
                $q->whereBetween($col, [$d->startOfDay(), $d->endOfDay()]);
            } catch (\Throwable $e) {
                // ignore invalid date
            }
            return;
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            try {
                $from = $request->filled('date_from')
                    ? Carbon::parse((string)$request->input('date_from'))->startOfDay()
                    : Carbon::minValue();

                $to = $request->filled('date_to')
                    ? Carbon::parse((string)$request->input('date_to'))->endOfDay()
                    : Carbon::maxValue();

                $q->whereBetween($col, [$from, $to]);
            } catch (\Throwable $e) {
                // ignore invalid
            }
        }
    }

    /**
     * Hour filters:
     * - hour_from=HH:MM or HH:MM:SS
     * - hour_to=HH:MM or HH:MM:SS
     * Filtre sur l'heure de COALESCE(alerted_at, created_at)
     */
    private function applyHourFilters($q, Request $request): void
    {
        $col = DB::raw("TIME(COALESCE(a.alerted_at, a.created_at))");

        $from = $request->filled('hour_from') ? trim((string)$request->input('hour_from')) : null;
        $to   = $request->filled('hour_to') ? trim((string)$request->input('hour_to')) : null;

        if ($from) {
            // normalise HH:MM -> HH:MM:SS
            if (strlen($from) === 5) $from .= ':00';
            $q->where($col, '>=', $from);
        }
        if ($to) {
            if (strlen($to) === 5) $to .= ':00';
            $q->where($col, '<=', $to);
        }
    }

    /**
     * Filtres communs
     */
    private function applyCommonFilters($q, Request $request): void
    {
        if ($request->filled('alert_type')) {
            $type = (string) $request->input('alert_type');
            if ($type !== 'all' && $type !== '') {
                $q->where('a.alert_type', $type);
            }
        }

        if ($request->filled('vehicle_id')) {
            $vid = (int) $request->input('vehicle_id');
            if ($vid > 0) $q->where('a.voiture_id', $vid);
        }

        if ($request->filled('user_id')) {
            $uid = (int) $request->input('user_id');
            if ($uid > 0) $q->where('acvp2.chauffeur_id', $uid);
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            if ($term !== '') {
                $q->where(function ($qq) use ($term) {
                    $qq->where('v.immatriculation', 'like', "%{$term}%")
                       ->orWhere('a.message', 'like', "%{$term}%")
                       ->orWhere('u.nom', 'like', "%{$term}%")
                       ->orWhere('u.prenom', 'like', "%{$term}%");
                });
            }
        }

        // date + heure (optionnels)
        $this->applyDateFilters($q, $request);
        $this->applyHourFilters($q, $request);
    }

    private function mapAlertRow($r): array
    {
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
    }

    /**
     * GET /alerts  ✅ TOUTES les alertes (historique) paginé + stats selon filtres
     */
    public function index(Request $request)
    {
        $partnerId = (int) Auth::id();
        [$perPage, $page] = $this->readPagination($request, 50, 200);

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

        $base = $this->baseQueryForPartner($vehicleIds);

        // ✅ Appliquer filtres (y compris date/heure) si fournis
        $this->applyCommonFilters($base, $request);

        // ✅ Stats cohérentes avec les filtres
        $statsRows = (clone $base)
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

        $p = (clone $base)
            ->orderByRaw("COALESCE(a.alerted_at, a.created_at) DESC")
            ->select([
                'a.id','a.voiture_id','a.alert_type','a.message','a.read','a.processed','a.processed_by',
                'a.alerted_at','a.created_at',
                'v.id as v_id','v.immatriculation','v.marque','v.model',
                'u.id as driver_id','u.nom as driver_nom','u.prenom as driver_prenom',
            ])
            ->paginate($perPage, ['*'], 'page', $page);

        $data = collect($p->items())->map(fn($r) => $this->mapAlertRow($r))->values();

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
                'by_type' => $byType,
            ],
        ]);
    }

    /**
     * GET /alerts/day ✅ JOUR EN COURS (par défaut)
     *
     * Par défaut: today strict.
     * Options:
     * - date_quick=today|yesterday|this_week|this_month
     * - date=YYYY-MM-DD
     * - date_from=YYYY-MM-DD&date_to=YYYY-MM-DD
     * - hour_from=HH:MM&hour_to=HH:MM
     * - alert_type, vehicle_id, user_id, q
     */
    public function day(Request $request)
    {
        $partnerId = (int) Auth::id();
        [$perPage, $page] = $this->readPagination($request, 20, 100);

        $vehicleIds = $this->partnerVehicleIds($partnerId);
        if (empty($vehicleIds)) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
                'meta'   => ['current_page'=>1,'per_page'=>$perPage,'total'=>0,'last_page'=>1],
                'stats'  => ['by_type' => array_fill_keys($this->statsTypes, 0)],
            ]);
        }

        $base = $this->baseQueryForPartner($vehicleIds);

        // ✅ Default strict: today
        $hasDateParam = $request->filled('date_quick') || $request->filled('date') || $request->filled('date_from') || $request->filled('date_to');
        if (!$hasDateParam) {
            $start = now()->startOfDay();
            $end   = now()->endOfDay();
            $base->whereBetween(DB::raw("COALESCE(a.alerted_at, a.created_at)"), [$start, $end]);
        }

        // ✅ Puis appliquer les filtres (type, q, vehicule, driver, date/heure si fournis)
        $this->applyCommonFilters($base, $request);

        // ✅ Stats cohérentes avec les filtres
        $statsRows = (clone $base)
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

        // ✅ Pagination
        $p = (clone $base)
            ->orderByRaw("COALESCE(a.alerted_at, a.created_at) DESC")
            ->select([
                'a.id','a.voiture_id','a.alert_type','a.message','a.read','a.processed','a.processed_by',
                'a.alerted_at','a.created_at',
                'v.id as v_id','v.immatriculation','v.marque','v.model',
                'u.id as driver_id','u.nom as driver_nom','u.prenom as driver_prenom',
            ])
            ->paginate($perPage, ['*'], 'page', $page);

        $data = collect($p->items())->map(fn($r) => $this->mapAlertRow($r))->values();

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
                'by_type' => $byType,
            ],
        ]);
    }

    /**
     * GET /alerts/poll?after_id=123&limit=20
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

        $rows = $rows->reverse()->values();

        $maxId = $afterId;

        $data = $rows->map(function ($r) use (&$maxId) {
            $maxId = max($maxId, (int)$r->id);
            return $this->mapAlertRow($r);
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