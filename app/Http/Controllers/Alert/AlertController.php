<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    /**
     * Allowed alert types for partner UI (optional filter, keep if your ENUM matches).
     */
    protected const PARTNER_ALERT_TYPES = [
        'geofence'       => 'GeoFence',
        'safe_zone'      => 'Safe Zone',
        'speed'          => 'Speed',
        'time_zone'      => 'Time Zone',
        'power_failure'  => 'Power Failure',
        'stolen'         => 'Stolen',
        'offline'        => 'Offline',
        'engine'         => 'Engine',
        'device_removal' => 'Device Removal',
        'low_battery'    => 'Low Battery',
        'general'        => 'General',
    ];

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
            default          => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * ✅ Filtre commun: uniquement alertes OUVERTES de la JOURNÉE
     * - Ouvertes: processed = 0 ou NULL
     * - Journée: alerted_at entre start/end day (fallback created_at si alerted_at NULL)
     */
    private function applyOpenTodayFilters($q): void
    {
        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        // Ouvertes
        $q->where(function ($qq) {
            $qq->where('a.processed', 0)->orWhereNull('a.processed');
        });

        // Aujourd'hui (fallback created_at)
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
     *
     * ✅ SCOPE RULE (final):
     * A partner sees alerts for vehicles that "belong to him" = vehicles present in
     * association_chauffeur_voiture_partner where assigned_by = partner_id.
     *
     * ✅ New rule:
     * Only OPEN alerts of TODAY.
     */
    public function index(Request $request)
    {
        $partnerId = (int) Auth::id();

        // 1) Vehicles belonging to this partner
        $vehicleIds = DB::table('association_chauffeur_voiture_partner')
            ->where('assigned_by', $partnerId)
            ->pluck('voiture_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        // 2) Latest assignment per vehicle (current driver)
        $latestAssign = DB::table('association_chauffeur_voiture_partner as acvp')
            ->select([
                'acvp.voiture_id',
                DB::raw('MAX(COALESCE(acvp.assigned_at, acvp.created_at, "1970-01-01")) as max_dt'),
            ])
            ->whereIn('acvp.voiture_id', $vehicleIds)
            ->groupBy('acvp.voiture_id');

        // 3) Alerts query with joins
        $q = DB::table('alerts as a')
            ->leftJoin('voitures as v', 'v.id', '=', 'a.voiture_id')

            ->leftJoinSub($latestAssign, 'last_acvp', function ($join) {
                $join->on('last_acvp.voiture_id', '=', 'a.voiture_id');
            })
            ->leftJoin('association_chauffeur_voiture_partner as acvp2', function ($join) {
                $join->on('acvp2.voiture_id', '=', 'a.voiture_id')
                    ->on(
                        DB::raw('COALESCE(acvp2.assigned_at, acvp2.created_at, "1970-01-01")'),
                        '=',
                        'last_acvp.max_dt'
                    );
            })
            ->leftJoin('users as u', 'u.id', '=', 'acvp2.chauffeur_id')

            ->whereIn('a.voiture_id', $vehicleIds);

        // ✅ ONLY OPEN alerts of TODAY
        $this->applyOpenTodayFilters($q);

        // optional filter by alert_type
        if ($request->filled('alert_type')) {
            $type = (string) $request->input('alert_type');
            if ($type !== 'all' && $type !== '') {
                $q->where('a.alert_type', $type);
            }
        }

        $rows = $q->orderByDesc('a.alerted_at')
            ->limit(500)
            ->select([
                'a.id',
                'a.voiture_id',
                'a.alert_type',
                'a.message',
                'a.read',
                'a.processed',
                'a.processed_by',
                'a.alerted_at',
                'a.created_at',

                'v.id as v_id',
                'v.immatriculation',
                'v.marque',
                'v.model',

                'u.id as driver_id',
                'u.nom as driver_nom',
                'u.prenom as driver_prenom',
            ])
            ->get();

        $data = $rows->map(function ($r) {
            $driverLabel = trim(($r->driver_nom ?? '') . ' ' . ($r->driver_prenom ?? ''));
            if ($driverLabel === '') $driverLabel = null;

            $ts = $r->alerted_at ?? $r->created_at ?? null;

            return [
                'id'               => (int) $r->id,
                'voiture_id'       => (int) $r->voiture_id,
                'alert_type'       => $r->alert_type,
                'type'             => $r->alert_type,
                'type_label'       => $this->typeLabel($r->alert_type),

                'message'          => $r->message,
                'location'         => $r->message,

                'read'             => (bool) $r->read,
                // si NULL, on considère ouvert
                'processed'        => (bool) ($r->processed ?? false),
                'processed_by'     => $r->processed_by ? (int) $r->processed_by : null,

                'alerted_at_human' => $ts ? date('d/m/Y H:i:s', strtotime($ts)) : '-',

                'voiture' => $r->v_id ? [
                    'id'              => (int) $r->v_id,
                    'immatriculation' => $r->immatriculation,
                    'marque'          => $r->marque,
                    'model'           => $r->model,
                ] : null,

                'user_id'      => $r->driver_id ? (int) $r->driver_id : null,
                'driver_label' => $driverLabel,
                'users_labels' => $driverLabel,
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function markReadApi($id)
    {
        $partnerId = (int) Auth::id();

        $allowedVehicleIds = DB::table('association_chauffeur_voiture_partner')
            ->where('assigned_by', $partnerId)
            ->pluck('voiture_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $alertVehicleId = DB::table('alerts')->where('id', (int) $id)->value('voiture_id');
        if (!$alertVehicleId || !in_array((int) $alertVehicleId, $allowedVehicleIds, true)) {
            return response()->json(['status' => 'error', 'message' => 'Accès non autorisé.'], 403);
        }

        DB::table('alerts')
            ->where('id', (int) $id)
            ->update([
                'read' => 1,
                'updated_at' => now(),
            ]);

        return response()->json(['status' => 'success', 'message' => 'Alerte ignorée.']);
    }

    public function poll(Request $request)
    {
        $partnerId = (int) Auth::id();
        $afterId = (int) $request->query('after_id', 0);
        $limit = (int) $request->query('limit', 20);
        if ($limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        $vehicleIds = DB::table('association_chauffeur_voiture_partner')
            ->where('assigned_by', $partnerId)
            ->pluck('voiture_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            return response()->json(['status' => 'success', 'data' => [], 'meta' => ['max_id' => $afterId]]);
        }

        $latestAssign = DB::table('association_chauffeur_voiture_partner as acvp')
            ->select([
                'acvp.voiture_id',
                DB::raw('MAX(COALESCE(acvp.assigned_at, acvp.created_at, "1970-01-01")) as max_dt'),
            ])
            ->whereIn('acvp.voiture_id', $vehicleIds)
            ->groupBy('acvp.voiture_id');

        $rowsQuery = DB::table('alerts as a')
            ->leftJoin('voitures as v', 'v.id', '=', 'a.voiture_id')
            ->leftJoinSub($latestAssign, 'last_acvp', function ($join) {
                $join->on('last_acvp.voiture_id', '=', 'a.voiture_id');
            })
            ->leftJoin('association_chauffeur_voiture_partner as acvp2', function ($join) {
                $join->on('acvp2.voiture_id', '=', 'a.voiture_id')
                    ->on(DB::raw('COALESCE(acvp2.assigned_at, acvp2.created_at, "1970-01-01")'), '=', 'last_acvp.max_dt');
            })
            ->leftJoin('users as u', 'u.id', '=', 'acvp2.chauffeur_id')
            ->whereIn('a.voiture_id', $vehicleIds)
            ->where('a.id', '>', $afterId);

        // ✅ ONLY OPEN alerts of TODAY
        $this->applyOpenTodayFilters($rowsQuery);

        $rows = $rowsQuery
            ->orderByDesc('a.id')
            ->limit($limit)
            ->select([
                'a.id','a.voiture_id','a.alert_type','a.message','a.read','a.processed','a.processed_by','a.alerted_at','a.created_at',
                'v.id as v_id','v.immatriculation','v.marque','v.model',
                'u.id as driver_id','u.nom as driver_nom','u.prenom as driver_prenom',
            ])
            ->get();

        // reverse so older -> newer (nice stacking order)
        $rows = $rows->reverse()->values();

        $maxId = $afterId;
        $data = $rows->map(function ($r) use (&$maxId) {
            $maxId = max($maxId, (int)$r->id);

            $driverLabel = trim(($r->driver_nom ?? '') . ' ' . ($r->driver_prenom ?? ''));
            if ($driverLabel === '') $driverLabel = null;

            $ts = $r->alerted_at ?? $r->created_at ?? null;

            return [
                'id' => (int)$r->id,
                'voiture_id' => (int)$r->voiture_id,
                'alert_type' => $r->alert_type,
                'type' => $r->alert_type,
                'type_label' => $this->typeLabel($r->alert_type),
                'message' => $r->message,
                'location' => $r->message,
                'read' => (bool)$r->read,
                'processed' => (bool) ($r->processed ?? false),
                'processed_by' => $r->processed_by ? (int)$r->processed_by : null,
                'alerted_at_human' => $ts ? date('d/m/Y H:i:s', strtotime($ts)) : '-',
                'voiture' => $r->v_id ? [
                    'id' => (int)$r->v_id,
                    'immatriculation' => $r->immatriculation,
                    'marque' => $r->marque,
                    'model' => $r->model,
                ] : null,
                'user_id' => $r->driver_id ? (int)$r->driver_id : null,
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
     * PATCH /alerts/{id}/processed
     */
    public function markProcessedApi($id)
    {
        $partnerId = (int) Auth::id();

        // Optional safety: partner can only process alerts for his vehicles
        $allowedVehicleIds = DB::table('association_chauffeur_voiture_partner')
            ->where('assigned_by', $partnerId)
            ->pluck('voiture_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $alertVehicleId = DB::table('alerts')->where('id', (int) $id)->value('voiture_id');
        if (!$alertVehicleId || !in_array((int) $alertVehicleId, $allowedVehicleIds, true)) {
            return response()->json(['status' => 'error', 'message' => 'Accès non autorisé.'], 403);
        }

        DB::table('alerts')
            ->where('id', (int) $id)
            ->update([
                'processed'    => 1,
                'processed_by' => $partnerId,
                'updated_at'   => now(),
            ]);

        return response()->json(['status' => 'success', 'message' => 'Alerte traitée.']);
    }
}