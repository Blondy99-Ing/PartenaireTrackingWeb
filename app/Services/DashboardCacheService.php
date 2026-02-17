<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AssociationUserVoiture;
use App\Models\Location;
use App\Models\User;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class DashboardCacheService
{
    private int $ttlStats  = 900;
    private int $ttlFleet  = 600;
    private int $ttlAlerts = 600;

    private int $gpsOfflineMinutes = 10;

    private function kStats(int $partnerId): string      { return "dash:p:$partnerId:stats"; }
    private function kFleet(int $partnerId): string      { return "dash:p:$partnerId:fleet"; }
    private function kFleetH(int $partnerId): string     { return "dash:p:$partnerId:fleet:h"; }
    private function kAlerts(int $partnerId): string     { return "dash:p:$partnerId:alerts"; }
    private function kVersion(int $partnerId): string    { return "dash:p:$partnerId:version"; }

    private function kDebounce(int $partnerId): string   { return "dash:p:$partnerId:debounce"; }
    private function kAlertsLock(int $partnerId): string { return "dash:p:$partnerId:alerts:lock"; }
    private function kVehicleIds(int $partnerId): string { return "dash:p:$partnerId:vehicle_ids"; }

    // =========================
    // Version
    // =========================
    public function getVersion(int $partnerId): int
    {
        return (int) (Redis::get($this->kVersion($partnerId)) ?? 0);
    }

    public function bumpVersion(int $partnerId): void
    {
        Redis::incr($this->kVersion($partnerId));
    }

    public function bumpVersionDebounced(int $partnerId, int $seconds = 1): void
    {
        $ok = Redis::set($this->kDebounce($partnerId), '1', 'EX', $seconds, 'NX');
        if ($ok) $this->bumpVersion($partnerId);
    }

    public function shouldRefreshAlertsNow(int $partnerId, int $seconds = 1): bool
    {
        return (bool) Redis::set($this->kAlertsLock($partnerId), '1', 'EX', $seconds, 'NX');
    }

    // =========================
    // Véhicules du partenaire (pivot)
    // =========================
    public function partnerVehicleIds(int $partnerId): array
    {
        $cached = Redis::get($this->kVehicleIds($partnerId));
        if ($cached) {
            $arr = json_decode($cached, true);
            if (is_array($arr)) return array_values(array_unique(array_map('intval', $arr)));
        }

        $ids = AssociationUserVoiture::query()
            ->where('user_id', $partnerId)
            ->pluck('voiture_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        Redis::setex($this->kVehicleIds($partnerId), $this->ttlFleet, json_encode($ids, JSON_UNESCAPED_UNICODE));
        return $ids;
    }

    // =========================
    // STATS
    // =========================
    public function getStatsFromRedis(int $partnerId): ?array
    {
        $json = Redis::get($this->kStats($partnerId));
        return $json ? json_decode($json, true) : null;
    }

    public function rebuildStats(int $partnerId): array
    {
        // NOTE: si tes “chauffeurs” ne sont pas dans users.partner_id, adapte ici.
        $driversCount = User::query()
            ->where('partner_id', $partnerId)
            ->count();

        $vehicleIds = $this->partnerVehicleIds($partnerId);
        $vehiclesCount = count($vehicleIds);

        $associationsCount = AssociationUserVoiture::query()
            ->where('user_id', $partnerId)
            ->count();

        $alertsCount = 0;
        $alertsByType = [
            'stolen'    => 0,
            'geofence'  => 0,
            'safe_zone' => 0,
            'speed'     => 0,
            'time_zone' => 0,
            'unknown'   => 0,
        ];

        if (!empty($vehicleIds)) {
            $start = now()->startOfDay();
            $end   = now()->endOfDay();

            // ✅ OUVERT = processed = 0 OU NULL + ✅ UNIQUEMENT AUJOURD’HUI
            // Fallback: si alerted_at NULL => created_at
            $baseOpenToday = Alert::query()
                ->whereIn('voiture_id', $vehicleIds)
                ->where(function ($q) {
                    $q->where('processed', 0)->orWhereNull('processed');
                })
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('alerted_at', [$start, $end])
                      ->orWhere(function ($qq) use ($start, $end) {
                          $qq->whereNull('alerted_at')
                             ->whereBetween('created_at', [$start, $end]);
                      });
                });

            $alertsCount = (clone $baseOpenToday)->count();

            $rows = (clone $baseOpenToday)
                ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
                ->groupBy('t')
                ->get();

            foreach ($rows as $r) {
                $norm = $this->normalizeAlertType((string) $r->t);
                $alertsByType[$norm] = ($alertsByType[$norm] ?? 0) + (int) $r->c;
            }
        }

        $payload = [
            'usersCount'        => (int) $driversCount,
            'vehiclesCount'     => (int) $vehiclesCount,
            'associationsCount' => (int) $associationsCount,
            'alertsCount'       => (int) $alertsCount,
            'alertsByType'      => $alertsByType,
        ];

        Redis::setex($this->kStats($partnerId), $this->ttlStats, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->bumpVersionDebounced($partnerId, 1);

        return $payload;
    }

    // =========================
    // FLEET
    // =========================
    public function getFleetFromRedis(int $partnerId): array
    {
        try {
            $all = Redis::hgetall($this->kFleetH($partnerId));
            if (is_array($all) && !empty($all)) {
                $out = [];
                foreach ($all as $vehicleId => $json) {
                    $row = json_decode($json, true);
                    if (is_array($row)) $out[] = $row;
                }
                return $out;
            }
        } catch (\Throwable) {
            // fallback JSON
        }

        $json = Redis::get($this->kFleet($partnerId));
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    /**
     * ✅ Rebuild fleet fiable : dernier location.id par mac_id_gps
     * (évite datetime NULL / désordre)
     */
    public function rebuildFleet(int $partnerId): array
    {
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            Redis::pipeline(function ($pipe) use ($partnerId) {
                $pipe->del($this->kFleetH($partnerId));
                $pipe->setex($this->kFleet($partnerId), $this->ttlFleet, json_encode([], JSON_UNESCAPED_UNICODE));
            });
            $this->bumpVersionDebounced($partnerId, 1);
            return [];
        }

        $voitures = Voiture::query()
            ->whereIn('id', $vehicleIds)
            ->with(['chauffeurActuelPartner.chauffeur:id,prenom,nom,partner_id'])
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->get();

        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
            // subquery: max(id) par mac
            $sub = Location::query()
                ->selectRaw('MAX(id) as max_id, mac_id_gps')
                ->whereIn('mac_id_gps', $macIds)
                ->groupBy('mac_id_gps');

            $latestRows = Location::query()
                ->joinSub($sub, 't', function ($join) {
                    $join->on('locations.id', '=', 't.max_id');
                })
                ->select('locations.*')
                ->get();

            foreach ($latestRows as $loc) {
                $latestByMac[(string) $loc->mac_id_gps] = $loc;
            }
        }

        $fleet = [];
        $hashPayload = [];

        foreach ($voitures as $v) {
            $loc = $latestByMac[(string)$v->mac_id_gps] ?? null;
            if (!$loc) continue;

            $lat = $loc->latitude;
            $lon = $loc->longitude;
            if ($lat === null || $lon === null) continue;

            $chauffeur = $v->chauffeurActuelPartner?->chauffeur;
            $driverLabel = $chauffeur ? trim(($chauffeur->prenom ?? '').' '.($chauffeur->nom ?? '')) : 'Non associé';

            $lastSeen = $loc->heart_time ?? $loc->sys_time ?? $loc->datetime ?? null;
            $gpsOnline = $this->isGpsOnline($lastSeen);

            $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($loc->status ?? null);
            $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

            $row = [
                'id'              => (int) $v->id,
                'immatriculation' => $v->immatriculation,
                'marque'          => $v->marque,
                'model'           => $v->model,
                'driver' => [
                    'label' => $driverLabel,
                    'id'    => $chauffeur?->id,
                ],
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'engine' => [
                    'cut'         => $engineCut,
                    'engineState' => $engineDecoded['engineState'] ?? 'UNKNOWN',
                ],
                'gps' => [
                    'online'    => $gpsOnline,
                    'state'     => $gpsOnline === true ? 'ONLINE' : 'OFFLINE',
                    'last_seen' => (string) $lastSeen,
                ],
                // ✅ anti-retour arrière (critique)
                'loc_id' => (int) $loc->id,
            ];

            $fleet[] = $row;
            $hashPayload[(string) $v->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        Redis::pipeline(function ($pipe) use ($partnerId, $hashPayload, $fleet) {
            $pipe->del($this->kFleetH($partnerId));

            if (!empty($hashPayload)) {
                $pipe->hMSet($this->kFleetH($partnerId), $hashPayload);
                $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
            }

            $pipe->setex($this->kFleet($partnerId), $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));
        });

        $this->bumpVersionDebounced($partnerId, 1);
        return $fleet;
    }

    /**
     * ✅ Update 1 véhicule : on ignore les points “plus vieux” via loc_id
     */
    public function updateVehicleFromLocation(int $partnerId, Location $location, bool $bump = true): void
    {
        $macId = trim((string) $location->mac_id_gps);
        if ($macId === '') return;

        $voiture = Voiture::query()
            ->where('mac_id_gps', $macId)
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->first();
        if (!$voiture) return;

        $owned = AssociationUserVoiture::query()
            ->where('user_id', $partnerId)
            ->where('voiture_id', $voiture->id)
            ->exists();
        if (!$owned) return;

        $lat = $location->latitude;
        $lon = $location->longitude;
        if ($lat === null || $lon === null) return;

        $incomingLocId = (int) ($location->id ?? 0);

        if ($incomingLocId > 0 && !$this->isNewerLocIdThanCached($partnerId, (int)$voiture->id, $incomingLocId)) {
            return;
        }

        $voiture->load(['chauffeurActuelPartner.chauffeur:id,prenom,nom,partner_id']);
        $chauffeur = $voiture->chauffeurActuelPartner?->chauffeur;
        $driverLabel = $chauffeur ? trim(($chauffeur->prenom ?? '').' '.($chauffeur->nom ?? '')) : 'Non associé';

        $lastSeen = $location->heart_time ?? $location->sys_time ?? $location->datetime ?? null;
        $gpsOnline = $this->isGpsOnline($lastSeen);

        $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($location->status ?? null);
        $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

        $row = [
            'id'              => (int) $voiture->id,
            'immatriculation' => $voiture->immatriculation,
            'marque'          => $voiture->marque,
            'model'           => $voiture->model,
            'driver' => [
                'label' => $driverLabel,
                'id'    => $chauffeur?->id,
            ],
            'lat' => (float) $lat,
            'lon' => (float) $lon,
            'engine' => [
                'cut'         => $engineCut,
                'engineState' => $engineDecoded['engineState'] ?? 'UNKNOWN',
            ],
            'gps' => [
                'online'    => $gpsOnline,
                'state'     => $gpsOnline === true ? 'ONLINE' : 'OFFLINE',
                'last_seen' => (string) $lastSeen,
            ],
            'loc_id' => $incomingLocId,
        ];

        Redis::pipeline(function ($pipe) use ($partnerId, $voiture, $row) {
            $pipe->hset($this->kFleetH($partnerId), (string) $voiture->id, json_encode($row, JSON_UNESCAPED_UNICODE));
            $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
        });

        if ($bump) $this->bumpVersionDebounced($partnerId, 1);
    }

    /**
     * ✅ Batch: on garde le plus grand location.id par mac
     */
    public function updateFleetBatchFromLocations(int $partnerId, array $items): void
    {
        if (empty($items)) return;

        $latest = $this->pickLatestPerMacByLocId($items);

        foreach ($latest as $data) {
            $loc = new Location();
            foreach ((array) $data as $k => $v) $loc->setAttribute($k, $v);
            $this->updateVehicleFromLocation($partnerId, $loc, false);
        }

        $this->bumpVersionDebounced($partnerId, 1);
    }

    private function pickLatestPerMacByLocId(array $items): array
    {
        $best = [];

        foreach ($items as $it) {
            $mac = trim((string)($it['mac_id_gps'] ?? ''));
            if ($mac === '') continue;

            $id = (int)($it['id'] ?? 0);

            if (!isset($best[$mac])) {
                $best[$mac] = $it;
                $best[$mac]['__id'] = $id;
                continue;
            }

            $prev = (int)($best[$mac]['__id'] ?? 0);
            if ($id >= $prev) {
                $best[$mac] = $it;
                $best[$mac]['__id'] = $id;
            }
        }

        foreach ($best as &$b) unset($b['__id']);
        return array_values($best);
    }

    private function isNewerLocIdThanCached(int $partnerId, int $vehicleId, int $incomingLocId): bool
    {
        try {
            $json = Redis::hget($this->kFleetH($partnerId), (string)$vehicleId);
            if (!$json) return true;

            $row = json_decode($json, true);
            $cachedLocId = (int)($row['loc_id'] ?? 0);

            return $incomingLocId >= $cachedLocId;
        } catch (\Throwable) {
            return true;
        }
    }

    private function isGpsOnline($lastSeen): ?bool
    {
        if (!$lastSeen) return null;

        try {
            $dt = Carbon::parse($lastSeen);
            return $dt->diffInMinutes(now()) <= $this->gpsOfflineMinutes;
        } catch (\Throwable) {
            return null;
        }
    }

    // =========================
    // ALERTS
    // =========================
    public function getAlertsFromRedis(int $partnerId): array
    {
        $json = Redis::get($this->kAlerts($partnerId));
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function rebuildAlerts(int $partnerId, int $limit = 10): array
    {
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            Redis::setex($this->kAlerts($partnerId), $this->ttlAlerts, json_encode([], JSON_UNESCAPED_UNICODE));
            $this->bumpVersionDebounced($partnerId, 1);
            return [];
        }

        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $alerts = Alert::query()
            ->with(['voiture'])
            ->whereIn('voiture_id', $vehicleIds)
            ->where(function ($q) { // ✅ ouverts = 0 OU NULL
                $q->where('processed', 0)->orWhereNull('processed');
            })
            // ✅ uniquement alertes du jour (fallback created_at si alerted_at NULL)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('alerted_at', [$start, $end])
                  ->orWhere(function ($qq) use ($start, $end) {
                      $qq->whereNull('alerted_at')
                         ->whereBetween('created_at', [$start, $end]);
                  });
            })
            ->orderBy('alerted_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Alert $a) {
                $v = $a->voiture;
                $typeNorm = $this->normalizeAlertType($a->alert_type);

                return [
                    'id'           => $a->id,
                    'vehicle'      => $v?->immatriculation ?? 'N/A',
                    'type'         => $typeNorm,
                    'type_label'   => $this->alertTypeLabel($typeNorm),
                    // fallback si alerted_at NULL
                    'time'         => optional($a->alerted_at ?? $a->created_at)->format('d/m/Y H:i:s'),
                    'processed'    => (bool) ($a->processed ?? false),
                    'status'       => 'Ouvert',
                    'status_color' => 'bg-red-500',
                ];
            })
            ->values()
            ->toArray();

        Redis::setex($this->kAlerts($partnerId), $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));
        $this->bumpVersionDebounced($partnerId, 1);

        return $alerts;
    }

    public function rebuildAll(int $partnerId): array
    {
        $stats  = $this->rebuildStats($partnerId);
        $fleet  = $this->rebuildFleet($partnerId);
        $alerts = $this->rebuildAlerts($partnerId, 10);

        return compact('stats', 'fleet', 'alerts');
    }

    // =========================
    // Normalisation alert types
    // =========================
    private function normalizeAlertType(?string $t): string
    {
        $t = strtolower(trim((string)$t));
        if ($t === '') return 'unknown';

        return match ($t) {
            'overspeed', 'speeding', 'speed' => 'speed',
            'safezone', 'safe-zone', 'safe_zone' => 'safe_zone',
            'geo_fence', 'geofence', 'geofence_enter', 'geofence_exit', 'geofence_breach' => 'geofence',
            'stolen', 'theft', 'stolen_vehicle' => 'stolen',
            'timezone', 'time_zone', 'time-zone' => 'time_zone',
            default => $t,
        };
    }

    private function alertTypeLabel(string $type): string
    {
        return match ($type) {
            'geofence'      => 'GeoFence Breach',
            'safe_zone'     => 'Safe Zone',
            'speed'         => 'Speeding',
            'stolen'        => 'Stolen Vehicle',
            'time_zone'     => 'Time Zone',
            default         => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}