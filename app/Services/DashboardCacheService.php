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
    // TTLs (stables)
    private int $ttlStats  = 60;
    private int $ttlFleet  = 600; // ✅ 10 min (évite les “trous”)
    private int $ttlAlerts = 60;

    // OFFLINE si last seen > X minutes
    private int $gpsOfflineMinutes = 10;

    // =========================
    // Keys (scopées partner)
    // =========================
    private function kStats(int $partnerId): string   { return "dash:p:$partnerId:stats"; }
    private function kFleet(int $partnerId): string   { return "dash:p:$partnerId:fleet"; }     // JSON fallback
    private function kFleetH(int $partnerId): string  { return "dash:p:$partnerId:fleet:h"; }   // HASH vehicle_id => JSON
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
     * ✅ Debounce : bumpVersion au max 1 fois / seconde (stabilise SSE)
     */
    public function bumpVersionDebounced(int $partnerId, int $seconds = 1): void
    {
        $ok = Redis::set($this->kDebounce($partnerId), '1', 'EX', $seconds, 'NX');
        if ($ok) {
            $this->bumpVersion($partnerId);
        }
    }

    // =========================
    // Helpers: véhicules du partenaire (pivot)
    // =========================
    public function partnerVehicleIds(int $partnerId): array
    {
        return AssociationUserVoiture::query()
            ->where('user_id', $partnerId) // user_id = partenaire
            ->pluck('voiture_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
    }

    // =========================
    // STATS (scopées partner)
    // =========================
    public function getStatsFromRedis(int $partnerId): ?array
    {
        $json = Redis::get($this->kStats($partnerId));
        return $json ? json_decode($json, true) : null;
    }

    public function rebuildStats(int $partnerId): array
    {
        // ✅ Chauffeurs = users dont partner_id = partner connecté
        $driversCount = User::query()
            ->where('partner_id', $partnerId)
            ->count();

        // ✅ Véhicules = pivot association_user_voitures (user_id = partnerId)
        $vehicleIds = $this->partnerVehicleIds($partnerId);
        $vehiclesCount = count($vehicleIds);

        // ✅ Associations = lignes pivot
        $associationsCount = AssociationUserVoiture::query()
            ->where('user_id', $partnerId)
            ->count();

        // ✅ Alertes ouvertes du partenaire = alertes sur SES véhicules
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
    // FLEET (scopée partner)
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
        } catch (\Throwable $e) {
            // fallback JSON
        }

        $json = Redis::get($this->kFleet($partnerId));
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    /**
     * ✅ rebuild complet fleet du partenaire
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
            $driverLabel = $chauffeur ? trim(($chauffeur->prenom ?? '').' '.($chauffeur->nom ?? '')) : 'Non associé';

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
     * ✅ Update 1 véhicule (temps réel)
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

        $owned = AssociationUserVoiture::query()
            ->where('user_id', $partnerId)
            ->where('voiture_id', $voiture->id)
            ->exists();
        if (!$owned) return;

        $lat = $location->latitude;
        $lon = $location->longitude;
        if (!$lat || !$lon) return;

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
        ];

        Redis::hset($this->kFleetH($partnerId), (string) $voiture->id, json_encode($row, JSON_UNESCAPED_UNICODE));
        Redis::expire($this->kFleetH($partnerId), $this->ttlFleet);

        // ✅ IMPORTANT : debounce
        $this->bumpVersionDebounced($partnerId, 1);
    }

    /**
     * ✅ Update en batch (très recommandé si Node envoie “beaucoup”)
     * $items = [ ['mac_id_gps'=>..., 'latitude'=>..., ...], ... ]
     */
    public function updateFleetBatchFromLocations(int $partnerId, array $items): void
    {
        if (empty($items)) return;

        // On update véhicule par véhicule (simple & fiable),
        // mais on ne bump qu’une seule fois à la fin.
        foreach ($items as $data) {
            $loc = new Location();
            foreach ((array)$data as $k => $v) {
                $loc->setAttribute($k, $v);
            }
            $this->updateVehicleFromLocation($partnerId, $loc);
        }

        // updateVehicleFromLocation() bump déjà debounce,
        // donc pas besoin de re-bump ici.
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
    // ALERTS (scopées partner)
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