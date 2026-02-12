<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\Location;
use App\Models\User;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

class DashboardCacheService
{
    // TTLs
    private int $ttlStats  = 60;
    private int $ttlFleet  = 600; // 10 min
    private int $ttlAlerts = 60;

    // OFFLINE if last seen > X minutes
    private int $gpsOfflineMinutes = 10;

    // =========================
    // Keys (scoped by partner)
    // =========================
    private function kStats(int $partnerId): string   { return "dash:p:$partnerId:stats"; }
    private function kFleet(int $partnerId): string   { return "dash:p:$partnerId:fleet"; }
    private function kFleetH(int $partnerId): string  { return "dash:p:$partnerId:fleet:h"; } // HASH vehicle_id => JSON
    private function kAlerts(int $partnerId): string  { return "dash:p:$partnerId:alerts"; }
    private function kVersion(int $partnerId): string { return "dash:p:$partnerId:version"; }
    private function kDebounce(int $partnerId): string { return "dash:p:$partnerId:debounce"; }

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

    /**
     * Debounce: bumpVersion max 1x per second
     */
    public function bumpVersionDebounced(int $partnerId, int $seconds = 1): void
    {
        $ok = Redis::set($this->kDebounce($partnerId), '1', 'EX', $seconds, 'NX');
        if ($ok) $this->bumpVersion($partnerId);
    }

    // =========================
    // Helpers: partner vehicles (BY chauffeur.partner_id)
    // =========================
    public function partnerVehicleIds(int $partnerId): array
    {
        return AssociationChauffeurVoiturePartner::query()
            ->whereHas('chauffeur', fn ($q) => $q->where('partner_id', $partnerId))
            ->pluck('voiture_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
    }

    // =========================
    // STATS (scoped)
    // =========================
    public function getStatsFromRedis(int $partnerId): ?array
    {
        $json = Redis::get($this->kStats($partnerId));
        return $json ? json_decode($json, true) : null;
    }

    public function rebuildStats(int $partnerId): array
    {
        // Drivers: users whose partner_id = partnerId
        $driversCount = User::query()
            ->where('partner_id', $partnerId)
            ->count();

        // Vehicles: from partner vehicle ids
        $vehicleIds = $this->partnerVehicleIds($partnerId);
        $vehiclesCount = count($vehicleIds);

        // Associations: number of pivot rows for this partner (chauffeurs belonging to partner)
        $associationsCount = AssociationChauffeurVoiturePartner::query()
            ->whereHas('chauffeur', fn ($q) => $q->where('partner_id', $partnerId))
            ->count();

        // Open alerts for partner vehicles
        $alertsCount = 0;
        $alertsByType = [];

        if (!empty($vehicleIds)) {
            $alertsCount = Alert::query()
                ->whereIn('voiture_id', $vehicleIds)
                ->where('processed', false)
                ->count();

            $rows = Alert::query()
                ->whereIn('voiture_id', $vehicleIds)
                ->where('processed', false)
                ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
                ->groupBy('t')
                ->get();

            foreach ($rows as $r) {
                $alertsByType[(string) $r->t] = (int) $r->c;
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
    // FLEET (scoped)
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
     * rebuild full fleet of partner
     */
    public function rebuildFleet(int $partnerId): array
    {
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            Redis::del($this->kFleetH($partnerId));
            Redis::setex($this->kFleet($partnerId), $this->ttlFleet, json_encode([], JSON_UNESCAPED_UNICODE));
            $this->bumpVersionDebounced($partnerId, 1);
            return [];
        }

        $voitures = Voiture::query()
            ->whereIn('id', $vehicleIds)
            // uses the alias you added in Voiture model (chauffeurActuelPartner -> chauffeurPartnerActuel)
            ->with(['chauffeurActuelPartner.chauffeur:id,prenom,nom,partner_id'])
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->get();

        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
            $latest = Location::query()
                ->select(['id','mac_id_gps','latitude','longitude','heart_time','sys_time','datetime','status','speed'])
                ->whereIn('mac_id_gps', $macIds)
                ->orderByDesc('datetime')
                ->get()
                ->groupBy('mac_id_gps')
                ->map(fn ($g) => $g->first());

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

            $chauffeur = $v->chauffeurActuelPartner?->chauffeur;

            // SAFETY: chauffeur must belong to this partner
            if ($chauffeur && (int)($chauffeur->partner_id ?? 0) !== (int)$partnerId) {
                $chauffeur = null;
            }

            $driverLabel = $chauffeur
                ? trim(($chauffeur->prenom ?? '').' '.($chauffeur->nom ?? ''))
                : 'Non associé';

            $lastSeen = $loc['heart_time'] ?? $loc['sys_time'] ?? $loc['datetime'] ?? null;
            $gpsOnline = $this->isGpsOnline($lastSeen);

            $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($loc['status'] ?? null);
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
            ];

            $fleet[] = $row;
            $hashPayload[(string) $v->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        Redis::del($this->kFleetH($partnerId));
        if (!empty($hashPayload)) {
            Redis::hmset($this->kFleetH($partnerId), $hashPayload);
            Redis::expire($this->kFleetH($partnerId), $this->ttlFleet);
        }

        Redis::setex($this->kFleet($partnerId), $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));
        $this->bumpVersionDebounced($partnerId, 1);

        return $fleet;
    }

    /**
     * Update 1 vehicle from a Location (real-time)
     */
    public function updateVehicleFromLocation(int $partnerId, Location $location): void
    {
        $macId = trim((string) $location->mac_id_gps);
        if ($macId === '') return;

        $voiture = Voiture::query()
            ->where('mac_id_gps', $macId)
            ->select(['id','immatriculation','marque','model','mac_id_gps'])
            ->first();
        if (!$voiture) return;

        // Ownership check: voiture must be in partnerVehicleIds
        $owned = AssociationChauffeurVoiturePartner::query()
            ->where('voiture_id', $voiture->id)
            ->whereHas('chauffeur', fn ($q) => $q->where('partner_id', $partnerId))
            ->exists();

        if (!$owned) return;

        $lat = $location->latitude;
        $lon = $location->longitude;
        if (!$lat || !$lon) return;

        $voiture->load(['chauffeurActuelPartner.chauffeur:id,prenom,nom,partner_id']);
        $chauffeur = $voiture->chauffeurActuelPartner?->chauffeur;

        if ($chauffeur && (int)($chauffeur->partner_id ?? 0) !== (int)$partnerId) {
            $chauffeur = null;
        }

        $driverLabel = $chauffeur
            ? trim(($chauffeur->prenom ?? '').' '.($chauffeur->nom ?? ''))
            : 'Non associé';

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
        ];

        Redis::hset($this->kFleetH($partnerId), (string) $voiture->id, json_encode($row, JSON_UNESCAPED_UNICODE));
        Redis::expire($this->kFleetH($partnerId), $this->ttlFleet);

        $this->bumpVersionDebounced($partnerId, 1);
    }

    /**
     * Batch update from locations
     */
    public function updateFleetBatchFromLocations(int $partnerId, array $items): void
    {
        if (empty($items)) return;

        foreach ($items as $data) {
            $loc = new Location();
            foreach ((array) $data as $k => $v) {
                $loc->setAttribute($k, $v);
            }
            $this->updateVehicleFromLocation($partnerId, $loc);
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
    // ALERTS (scoped)
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

        $alerts = Alert::query()
            ->with(['voiture'])
            ->whereIn('voiture_id', $vehicleIds)
            ->orderBy('processed', 'asc')
            ->orderBy('alerted_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Alert $a) {
                $v = $a->voiture;
                $type = $a->alert_type ?? 'unknown';

                return [
                    'id'           => $a->id,
                    'vehicle'      => $v?->immatriculation ?? 'N/A',
                    'type'         => $type,
                    'time'         => optional($a->alerted_at)->format('d/m/Y H:i:s'),
                    'processed'    => (bool) $a->processed,
                    'status'       => $a->processed ? 'Résolu' : 'Ouvert',
                    'status_color' => $a->processed ? 'bg-green-500' : 'bg-red-500',
                ];
            })
            ->values()
            ->toArray();

        Redis::setex($this->kAlerts($partnerId), $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));
        $this->bumpVersionDebounced($partnerId, 1);

        return $alerts;
    }

    // =========================
    // ALL
    // =========================
    public function rebuildAll(int $partnerId): array
    {
        $stats  = $this->rebuildStats($partnerId);
        $fleet  = $this->rebuildFleet($partnerId);
        $alerts = $this->rebuildAlerts($partnerId, 10);

        return compact('stats', 'fleet', 'alerts');
    }
}
