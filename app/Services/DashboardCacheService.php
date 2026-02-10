<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Location;
use App\Models\User;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class DashboardCacheService
{
    private int $ttlStats  = 60;
    private int $ttlFleet  = 120;
    private int $ttlAlerts = 30;
    private int $gpsOfflineMinutes = 10;

    // =========================
    // Keys (scopées partner)
    // =========================
    private function k(int $partnerId, string $suffix): string
    {
        // dash:p:{partnerId}:{suffix}
        return "dash:p:{$partnerId}:{$suffix}";
    }

    // =========================
    // Version
    // =========================
    public function getVersion(int $partnerId): int
    {
        return (int)(Redis::get($this->k($partnerId, 'version')) ?? 0);
    }

    public function bumpVersion(int $partnerId): void
    {
        Redis::incr($this->k($partnerId, 'version'));
    }

    // =========================
    // STATS
    // =========================
    public function getStatsFromRedis(int $partnerId): ?array
    {
        $json = Redis::get($this->k($partnerId, 'stats'));
        return $json ? json_decode($json, true) : null;
    }

    public function rebuildStats(int $partnerId): array
    {
        // ✅ chauffeurs du partner
        $usersCount = User::query()
            ->where('partner_id', $partnerId)
            ->count();

        // ✅ voitures qui ont au moins un user(driver) du partner
        $vehiclesCount = Voiture::query()
            ->whereHas('utilisateur', fn($q) => $q->where('partner_id', $partnerId))
            ->count();

        // ✅ alertes non traitées du partner (sur voitures du partner)
        $alertsCount = Alert::query()
            ->where('processed', false)
            ->whereHas('voiture.utilisateur', fn($q) => $q->where('partner_id', $partnerId))
            ->count();

        // ✅ par type (uniquement non traitées)
        $rows = Alert::query()
            ->where('processed', false)
            ->whereHas('voiture.utilisateur', fn($q) => $q->where('partner_id', $partnerId))
            ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
            ->groupBy('t')
            ->get();

        $alertsByType = [];
        foreach ($rows as $r) {
            $alertsByType[(string)$r->t] = (int)$r->c;
        }

        $payload = [
            'usersCount'    => (int)$usersCount,
            'vehiclesCount' => (int)$vehiclesCount,
            'alertsCount'   => (int)$alertsCount,
            'alertsByType'  => $alertsByType,
        ];

        Redis::setex($this->k($partnerId, 'stats'), $this->ttlStats, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion($partnerId);

        return $payload;
    }

    // =========================
    // FLEET
    // =========================
    public function getFleetFromRedis(int $partnerId): array
    {
        $hashKey = $this->k($partnerId, 'fleet:h');
        $jsonKey = $this->k($partnerId, 'fleet');

        // priorité hash
        try {
            $all = Redis::hgetall($hashKey);
            if (is_array($all) && !empty($all)) {
                $out = [];
                foreach ($all as $vehicleId => $json) {
                    $row = json_decode($json, true);
                    if (is_array($row)) $out[] = $row;
                }
                return $out;
            }
        } catch (\Throwable $e) {}

        $json = Redis::get($jsonKey);
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function rebuildFleet(int $partnerId): array
    {
        // ✅ voitures du partner
        $voitures = Voiture::query()
            ->with(['utilisateur:id,prenom,nom,partner_id'])
            ->whereHas('utilisateur', fn($q) => $q->where('partner_id', $partnerId))
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->get();

        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
            $latest = Location::query()
                ->select(['id','mac_id_gps','latitude','longitude','heart_time','sys_time','datetime','status'])
                ->whereIn('mac_id_gps', $macIds)
                ->orderByDesc('datetime')
                ->get()
                ->groupBy('mac_id_gps')
                ->map(fn($g) => $g->first());

            $latestByMac = $latest->toArray();
        }

        $fleet = [];
        $hashPayload = [];

        foreach ($voitures as $v) {
            $loc = $latestByMac[$v->mac_id_gps] ?? null;
            if (!$loc) continue;

            $lat = $loc['latitude'] ?? null;
            $lon = $loc['longitude'] ?? null;
            if (!$lat || !$lon) continue;

            // drivers affichés
            $drivers = $v->utilisateur
                ? $v->utilisateur->map(fn($u) => trim(($u->prenom ?? '').' '.($u->nom ?? '')))
                    ->filter()->implode(', ')
                : null;

            $lastSeen = $loc['heart_time'] ?? $loc['sys_time'] ?? $loc['datetime'] ?? null;
            $gpsOnline = $this->isGpsOnline($lastSeen);

            $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($loc['status'] ?? null);
            $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

            $row = [
                'id'              => (int)$v->id,
                'immatriculation' => $v->immatriculation,
                'marque'          => $v->marque,
                'model'           => $v->model,
                'users'           => $drivers,
                'lat'             => (float)$lat,
                'lon'             => (float)$lon,
                'engine' => [
                    'cut' => $engineCut,
                    'engineState' => $engineDecoded['engineState'] ?? 'UNKNOWN',
                ],
                'gps' => [
                    'online'    => $gpsOnline,
                    'state'     => $gpsOnline === true ? 'ONLINE' : 'OFFLINE',
                    'last_seen' => (string)$lastSeen,
                ],
            ];

            $fleet[] = $row;
            $hashPayload[(string)$v->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        $hashKey = $this->k($partnerId, 'fleet:h');
        $jsonKey = $this->k($partnerId, 'fleet');

        Redis::del($hashKey);
        if (!empty($hashPayload)) {
            Redis::hmset($hashKey, $hashPayload);
            Redis::expire($hashKey, $this->ttlFleet);
        }

        Redis::setex($jsonKey, $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion($partnerId);

        return $fleet;
    }

    /**
     * ✅ appelé par webhook location.created
     * - doit retrouver partnerId via voiture(mac_id_gps) -> utilisateur.partner_id
     */
    public function updateVehicleFromLocationScoped(Location $location): void
    {
        $macId = trim((string)$location->mac_id_gps);
        if ($macId === '') return;

        $voiture = Voiture::query()
            ->with(['utilisateur:id,prenom,nom,partner_id'])
            ->where('mac_id_gps', $macId)
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->first();

        if (!$voiture) return;

        // ✅ déduire partnerId (B)
        $driver = $voiture->utilisateur?->firstWhere(fn($u) => !empty($u->partner_id));
        $partnerId = $driver?->partner_id ? (int)$driver->partner_id : null;
        if (!$partnerId) return;

        $lat = $location->latitude;
        $lon = $location->longitude;
        if (!$lat || !$lon) return;

        $drivers = $voiture->utilisateur
            ? $voiture->utilisateur->map(fn($u) => trim(($u->prenom ?? '').' '.($u->nom ?? '')))
                ->filter()->implode(', ')
            : null;

        $lastSeen = $location->heart_time ?? $location->sys_time ?? $location->datetime ?? null;
        $gpsOnline = $this->isGpsOnline($lastSeen);

        $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($location->status ?? null);
        $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

        $row = [
            'id'              => (int)$voiture->id,
            'immatriculation' => $voiture->immatriculation,
            'marque'          => $voiture->marque,
            'model'           => $voiture->model,
            'users'           => $drivers,
            'lat'             => (float)$lat,
            'lon'             => (float)$lon,
            'engine' => [
                'cut' => $engineCut,
                'engineState' => $engineDecoded['engineState'] ?? 'UNKNOWN',
            ],
            'gps' => [
                'online'    => $gpsOnline,
                'state'     => $gpsOnline === true ? 'ONLINE' : 'OFFLINE',
                'last_seen' => (string)$lastSeen,
            ],
        ];

        Redis::hset($this->k($partnerId, 'fleet:h'), (string)$voiture->id, json_encode($row, JSON_UNESCAPED_UNICODE));
        Redis::expire($this->k($partnerId, 'fleet:h'), $this->ttlFleet);

        $this->bumpVersion($partnerId);
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
        $json = Redis::get($this->k($partnerId, 'alerts'));
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function rebuildAlerts(int $partnerId, int $limit = 10): array
    {
        $alerts = Alert::query()
            ->whereHas('voiture.utilisateur', fn($q) => $q->where('partner_id', $partnerId))
            ->orderBy('processed', 'asc')
            ->orderBy('alerted_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Alert $a) {
                $v = $a->voiture;

                $drivers = $v?->utilisateur
                    ?->map(fn($u) => trim(($u->prenom ?? '').' '.($u->nom ?? '')))
                    ->filter()
                    ->implode(', ');

                $type = $a->alert_type ?? 'unknown';

                return [
                    'id'           => $a->id,
                    'vehicle'      => $v?->immatriculation ?? 'N/A',
                    'type'         => $type,
                    'users'        => $drivers ?: null,
                    'time'         => optional($a->alerted_at)->format('d/m/Y H:i:s'),
                    'processed'    => (bool)$a->processed,
                    'status'       => $a->processed ? 'Résolu' : 'Ouvert',
                    'status_color' => $a->processed ? 'bg-green-500' : 'bg-red-500',
                ];
            })
            ->values()
            ->toArray();

        Redis::setex($this->k($partnerId, 'alerts'), $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));
        $this->bumpVersion($partnerId);

        return $alerts;
    }
}