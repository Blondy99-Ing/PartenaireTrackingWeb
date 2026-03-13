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
    private float $movingThreshold = 5.0;

    private function kStats(int $partnerId): string      { return "dash:p:$partnerId:stats"; }
    private function kFleet(int $partnerId): string      { return "dash:p:$partnerId:fleet"; }
    private function kFleetH(int $partnerId): string     { return "dash:p:$partnerId:fleet:h"; }
    private function kAlerts(int $partnerId): string     { return "dash:p:$partnerId:alerts"; }
    private function kVersion(int $partnerId): string    { return "dash:p:$partnerId:version"; }
    private function kDebounce(int $partnerId): string   { return "dash:p:$partnerId:debounce"; }
    private function kAlertsLock(int $partnerId): string { return "dash:p:$partnerId:alerts:lock"; }
    private function kVehicleIds(int $partnerId): string { return "dash:p:$partnerId:vehicle_ids"; }
    private function kFleetReset(int $partnerId): string { return "dash:p:$partnerId:fleet:reset"; }

    private function kDirtyVehicles(int $partnerId): string { return "dash:p:$partnerId:dirty:vehicles"; }
    private function kDirtyAlerts(int $partnerId): string   { return "dash:p:$partnerId:dirty:alerts"; }
    private function kDirtyStats(int $partnerId): string    { return "dash:p:$partnerId:dirty:stats"; }

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
        if ($ok) {
            $this->bumpVersion($partnerId);
        }
    }

    public function shouldRefreshAlertsNow(int $partnerId, int $seconds = 2): bool
    {
        return (bool) Redis::set($this->kAlertsLock($partnerId), '1', 'EX', $seconds, 'NX');
    }

    public function partnerVehicleIds(int $partnerId): array
    {
        $cached = Redis::get($this->kVehicleIds($partnerId));
        if ($cached) {
            $arr = json_decode($cached, true);
            if (is_array($arr)) {
                return array_values(array_unique(array_map('intval', $arr)));
            }
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

    public function getStatsFromRedis(int $partnerId): ?array
    {
        $json = Redis::get($this->kStats($partnerId));
        return $json ? json_decode($json, true) : null;
    }

    public function getAlertsFromRedis(int $partnerId): array
    {
        $json = Redis::get($this->kAlerts($partnerId));
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function getFleetFromRedis(int $partnerId): array
    {
        try {
            $all = Redis::hgetall($this->kFleetH($partnerId));
            if (is_array($all) && !empty($all)) {
                $out = [];
                foreach ($all as $vehicleId => $json) {
                    $row = json_decode($json, true);
                    if (is_array($row)) {
                        $out[] = $this->applyDynamicLiveStatusOnRow($row);
                    }
                }
                return $out;
            }
        } catch (\Throwable) {
        }

        $json = Redis::get($this->kFleet($partnerId));
        $fleet = $json ? (json_decode($json, true) ?: []) : [];

        if (!is_array($fleet)) {
            return [];
        }

        return array_map(fn ($row) => is_array($row) ? $this->applyDynamicLiveStatusOnRow($row) : $row, $fleet);
    }

    public function getFleetVehicleRowFromRedis(int $partnerId, int $vehicleId): ?array
    {
        try {
            $json = Redis::hget($this->kFleetH($partnerId), (string) $vehicleId);
            if (!$json) {
                return null;
            }

            $row = json_decode($json, true);
            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function consumeDirtyVehicleRows(int $partnerId): array
    {
        $key = $this->kDirtyVehicles($partnerId);
        $ids = Redis::smembers($key);

        if (empty($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            Redis::del($key);
            return [];
        }

        $rows = Redis::pipeline(function ($pipe) use ($partnerId, $ids) {
            foreach ($ids as $id) {
                $pipe->hget($this->kFleetH($partnerId), (string) $id);
            }
            $pipe->del($this->kDirtyVehicles($partnerId));
        });

        $out = [];
        $countRows = count($ids);
        for ($i = 0; $i < $countRows; $i++) {
            $json = $rows[$i] ?? null;
            if (!$json) {
                continue;
            }

            $row = json_decode($json, true);
            if (is_array($row)) {
                $out[] = $this->applyDynamicLiveStatusOnRow($row);
            }
        }

        return $out;
    }

    public function consumeDirtyAlerts(int $partnerId): ?array
    {
        $flag = Redis::get($this->kDirtyAlerts($partnerId));
        if (!$flag) {
            return null;
        }

        $alerts = $this->getAlertsFromRedis($partnerId);
        Redis::del($this->kDirtyAlerts($partnerId));

        return $alerts;
    }

    public function consumeDirtyStats(int $partnerId): ?array
    {
        $flag = Redis::get($this->kDirtyStats($partnerId));
        if (!$flag) {
            return null;
        }

        $stats = $this->getStatsFromRedis($partnerId);
        Redis::del($this->kDirtyStats($partnerId));

        return $stats;
    }

    public function markFleetResetDirty(int $partnerId): void
    {
        Redis::setex($this->kFleetReset($partnerId), 60, '1');
    }

    public function consumeFleetReset(int $partnerId): bool
    {
        $flag = Redis::get($this->kFleetReset($partnerId));
        if (!$flag) {
            return false;
        }

        Redis::del($this->kFleetReset($partnerId));

        return true;
    }

    private function markVehiclesDirty(int $partnerId, array $vehicleIds): void
    {
        $vehicleIds = array_values(array_unique(array_map('intval', $vehicleIds)));
        if (empty($vehicleIds)) {
            return;
        }

        Redis::pipeline(function ($pipe) use ($partnerId, $vehicleIds) {
            foreach ($vehicleIds as $id) {
                $pipe->sadd($this->kDirtyVehicles($partnerId), (string) $id);
            }
            $pipe->expire($this->kDirtyVehicles($partnerId), $this->ttlFleet);
        });
    }

    private function markAlertsDirty(int $partnerId): void
    {
        Redis::setex($this->kDirtyAlerts($partnerId), 60, '1');
    }

    private function markStatsDirty(int $partnerId): void
    {
        Redis::setex($this->kDirtyStats($partnerId), 60, '1');
    }

    public function rebuildStats(int $partnerId): array
    {
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
        $this->markStatsDirty($partnerId);
        $this->bumpVersionDebounced($partnerId, 1);

        return $payload;
    }

    public function rebuildFleet(int $partnerId): array
    {
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            Redis::pipeline(function ($pipe) use ($partnerId) {
                $pipe->del($this->kFleetH($partnerId));
                $pipe->setex($this->kFleet($partnerId), $this->ttlFleet, json_encode([], JSON_UNESCAPED_UNICODE));
                $pipe->del($this->kDirtyVehicles($partnerId));
            });

            $this->markFleetResetDirty($partnerId);
            $this->bumpVersionDebounced($partnerId, 1);

            return [];
        }

        $voitures = Voiture::query()
            ->whereIn('id', $vehicleIds)
            ->with(['chauffeurActuelPartner.chauffeur:id,prenom,nom,partner_id'])
            ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps'])
            ->get();

        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
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
                $latestByMac[(string) $loc->mac_id_gps] = $loc->toArray();
            }
        }

        $fleet = [];
        $hashPayload = [];
        $dirtyIds = [];

        foreach ($voitures as $v) {
            $loc = $latestByMac[(string) $v->mac_id_gps] ?? null;

            if (!$loc) {
                continue;
            }

            $row = $this->buildVehicleRow($v, $loc, null);
            if (!$row) {
                continue;
            }

            $fleet[] = $row;
            $hashPayload[(string) $v->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
            $dirtyIds[] = (int) $v->id;
        }

        Redis::pipeline(function ($pipe) use ($partnerId, $hashPayload, $fleet) {
            $pipe->del($this->kFleetH($partnerId));

            if (!empty($hashPayload)) {
                $pipe->hMSet($this->kFleetH($partnerId), $hashPayload);
                $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
            }

            $pipe->setex($this->kFleet($partnerId), $this->ttlFleet, json_encode($fleet, JSON_UNESCAPED_UNICODE));
        });

        $this->markVehiclesDirty($partnerId, $dirtyIds);
        $this->markFleetResetDirty($partnerId);
        $this->bumpVersionDebounced($partnerId, 1);

        return $fleet;
    }

    public function rebuildFleetForVehicleAssociations(Voiture $voiture): void
    {
        $partnerIds = AssociationUserVoiture::query()
            ->where('voiture_id', (int) $voiture->id)
            ->pluck('user_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        foreach ($partnerIds as $partnerId) {
            $this->rebuildFleet((int) $partnerId);
            $this->markFleetResetDirty((int) $partnerId);
            $this->bumpVersionDebounced((int) $partnerId, 1);
        }
    }

    public function updateVehicleFromLocation(int|Location $partnerIdOrLocation, ?Location $location = null, bool $bump = true): void
    {
        /**
         * Compatibilité double :
         * - ancien webhook : updateVehicleFromLocation(int $partnerId, Location $location, bool $bump = true)
         * - autre usage éventuel : updateVehicleFromLocation(Location $location)
         */

        if ($partnerIdOrLocation instanceof Location) {
            $incomingLocation = $partnerIdOrLocation;
            $mac = trim((string) ($incomingLocation->mac_id_gps ?? ''));

            if ($mac === '') {
                return;
            }

            $vehicles = Voiture::query()
                ->where('mac_id_gps', $mac)
                ->get();

            if ($vehicles->isEmpty()) {
                return;
            }

            $vehicleIds = $vehicles->pluck('id')->map(fn ($x) => (int) $x)->all();

            $partnerIds = AssociationUserVoiture::query()
                ->whereIn('voiture_id', $vehicleIds)
                ->pluck('user_id')
                ->map(fn ($x) => (int) $x)
                ->unique()
                ->values()
                ->all();

            foreach ($partnerIds as $partnerId) {
                $this->updateFleetBatchFromLocations((int) $partnerId, [$incomingLocation->toArray()], $bump);
            }

            return;
        }

        $partnerId = (int) $partnerIdOrLocation;

        if (!$location) {
            return;
        }

        $this->updateFleetBatchFromLocations($partnerId, [$location->toArray()], $bump);
    }

    public function updateFleetBatchFromLocations(int|iterable $partnerIdOrLocations, array $items = [], bool $bump = true): void
    {
        /**
         * Compatibilité double :
         * - contrat réel webhook :
         *   updateFleetBatchFromLocations(int $partnerId, array $items, bool $bump = true)
         *
         * - usage éventuel :
         *   updateFleetBatchFromLocations(iterable $locations)
         */

        if (is_iterable($partnerIdOrLocations) && !is_int($partnerIdOrLocations)) {
            $latestByMac = [];

            foreach ($partnerIdOrLocations as $location) {
                if (!$location instanceof Location) {
                    continue;
                }

                $mac = trim((string) ($location->mac_id_gps ?? ''));
                if ($mac === '') {
                    continue;
                }

                $current = $latestByMac[$mac] ?? null;
                if (!$current || (int) $location->id > (int) $current->id) {
                    $latestByMac[$mac] = $location;
                }
            }

            if (empty($latestByMac)) {
                return;
            }

            $macs = array_keys($latestByMac);

            $vehicles = Voiture::query()
                ->whereIn('mac_id_gps', $macs)
                ->with(['chauffeurActuelPartner.chauffeur:id,prenom,nom,partner_id'])
                ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps'])
                ->get();

            if ($vehicles->isEmpty()) {
                return;
            }

            $vehicleIds = $vehicles->pluck('id')->map(fn ($x) => (int) $x)->all();

            $associations = AssociationUserVoiture::query()
                ->whereIn('voiture_id', $vehicleIds)
                ->get(['user_id', 'voiture_id']);

            if ($associations->isEmpty()) {
                return;
            }

            $vehicleToPartners = [];
            foreach ($associations as $assoc) {
                $vehicleToPartners[(int) $assoc->voiture_id][] = (int) $assoc->user_id;
            }

            $dirtyByPartner = [];

            foreach ($vehicles as $vehicle) {
                $mac = trim((string) ($vehicle->mac_id_gps ?? ''));
                $location = $latestByMac[$mac] ?? null;
                if (!$location) {
                    continue;
                }

                $existingPartners = array_values(array_unique(array_map('intval', $vehicleToPartners[(int) $vehicle->id] ?? [])));
                if (empty($existingPartners)) {
                    continue;
                }

                foreach ($existingPartners as $partnerId) {
                    $existingRow = $this->getFleetVehicleRowFromRedis((int) $partnerId, (int) $vehicle->id);
                    $row = $this->buildVehicleRow($vehicle, $location->toArray(), $existingRow);

                    if (!$row) {
                        continue;
                    }

                    Redis::hset(
                        $this->kFleetH((int) $partnerId),
                        (string) $vehicle->id,
                        json_encode($row, JSON_UNESCAPED_UNICODE)
                    );
                    Redis::expire($this->kFleetH((int) $partnerId), $this->ttlFleet);

                    $dirtyByPartner[(int) $partnerId][] = (int) $vehicle->id;
                }
            }

            foreach ($dirtyByPartner as $partnerId => $vehicleIds) {
                $this->markVehiclesDirty((int) $partnerId, $vehicleIds);
                $this->bumpVersionDebounced((int) $partnerId, 1);
            }

            return;
        }

        $partnerId = (int) $partnerIdOrLocations;

        if (empty($items)) {
            return;
        }

        $latestItems = $this->pickLatestPerMacByLocId($items);
        if (empty($latestItems)) {
            return;
        }

        $latestByMac = [];
        $macs = [];

        foreach ($latestItems as $it) {
            $mac = trim((string) ($it['mac_id_gps'] ?? ''));
            if ($mac === '') {
                continue;
            }

            $latestByMac[$mac] = $it;
            $macs[] = $mac;
        }

        $macs = array_values(array_unique($macs));
        if (empty($macs)) {
            return;
        }

        $partnerVehicleIds = $this->partnerVehicleIds($partnerId);
        if (empty($partnerVehicleIds)) {
            return;
        }

        $voitures = Voiture::query()
            ->whereIn('id', $partnerVehicleIds)
            ->whereIn('mac_id_gps', $macs)
            ->with(['chauffeurActuelPartner.chauffeur:id,prenom,nom,partner_id'])
            ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps'])
            ->get();

        if ($voitures->isEmpty()) {
            return;
        }

        $hashPayload = [];
        $dirtyIds = [];

        foreach ($voitures as $voiture) {
            $mac = trim((string) $voiture->mac_id_gps);
            $data = $latestByMac[$mac] ?? null;
            if (!$data) {
                continue;
            }

            $incomingLocId = (int) ($data['id'] ?? 0);
            if ($incomingLocId > 0 && !$this->isNewerLocIdThanCached($partnerId, (int) $voiture->id, $incomingLocId)) {
                continue;
            }

            $existingRow = $this->getFleetVehicleRowFromRedis($partnerId, (int) $voiture->id);
            $row = $this->buildVehicleRow($voiture, $data, $existingRow);
            if (!$row) {
                continue;
            }

            $hashPayload[(string) $voiture->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
            $dirtyIds[] = (int) $voiture->id;
        }

        if (empty($hashPayload)) {
            return;
        }

        Redis::pipeline(function ($pipe) use ($partnerId, $hashPayload) {
            $pipe->hMSet($this->kFleetH($partnerId), $hashPayload);
            $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
        });

        $this->markVehiclesDirty($partnerId, $dirtyIds);

        if ($bump) {
            $this->bumpVersionDebounced($partnerId, 1);
        }
    }

    public function rebuildAlerts(int $partnerId, int $limit = 10): array
    {
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            Redis::setex($this->kAlerts($partnerId), $this->ttlAlerts, json_encode([], JSON_UNESCAPED_UNICODE));
            $this->markAlertsDirty($partnerId);
            $this->bumpVersionDebounced($partnerId, 1);
            return [];
        }

        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $alerts = Alert::query()
            ->with(['voiture'])
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
                    'time'         => optional($a->alerted_at ?? $a->created_at)->format('d/m/Y H:i:s'),
                    'processed'    => (bool) ($a->processed ?? false),
                    'status'       => 'Ouvert',
                    'status_color' => 'bg-red-500',
                ];
            })
            ->values()
            ->toArray();

        Redis::setex($this->kAlerts($partnerId), $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));
        $this->markAlertsDirty($partnerId);
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

    public function refreshOfflineStatusesFromRedis(int $partnerId): array
    {
        $fleet = $this->getFleetFromRedis($partnerId);
        if (!is_array($fleet) || empty($fleet)) {
            return ['partner_id' => $partnerId, 'updated' => 0, 'changed' => 0];
        }

        $changed = 0;
        $hashPayload = [];
        $dirtyIds = [];

        foreach ($fleet as $vehicle) {
            if (!is_array($vehicle)) {
                continue;
            }

            $oldVehicle = $vehicle;
            $vehicle = $this->applyDynamicLiveStatusOnRow($vehicle);

            if ($vehicle !== $oldVehicle) {
                $changed++;

                if (isset($vehicle['id'])) {
                    $hashPayload[(string) $vehicle['id']] = json_encode($vehicle, JSON_UNESCAPED_UNICODE);
                    $dirtyIds[] = (int) $vehicle['id'];
                }
            }
        }

        if ($changed > 0) {
            Redis::pipeline(function ($pipe) use ($partnerId, $hashPayload) {
                $pipe->hMSet($this->kFleetH($partnerId), $hashPayload);
                $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
            });

            $this->markVehiclesDirty($partnerId, $dirtyIds);
            $this->bumpVersionDebounced($partnerId, 1);
        }

        return ['partner_id' => $partnerId, 'updated' => count($fleet), 'changed' => $changed];
    }

    private function pickLatestPerMacByLocId(array $items): array
    {
        $best = [];

        foreach ($items as $it) {
            $mac = trim((string) ($it['mac_id_gps'] ?? ''));
            if ($mac === '') {
                continue;
            }

            $id = (int) ($it['id'] ?? 0);

            if (!isset($best[$mac])) {
                $best[$mac] = $it;
                $best[$mac]['__id'] = $id;
                continue;
            }

            $prev = (int) ($best[$mac]['__id'] ?? 0);
            if ($id >= $prev) {
                $best[$mac] = $it;
                $best[$mac]['__id'] = $id;
            }
        }

        foreach ($best as &$b) {
            unset($b['__id']);
        }

        return array_values($best);
    }

    private function buildVehicleRow(Voiture $voiture, array $locationData, ?array $existingRow = null): ?array
    {
        $lat = $locationData['latitude'] ?? null;
        $lon = $locationData['longitude'] ?? null;

        if ($lat === null || $lon === null) {
            return null;
        }

        $chauffeur = $voiture->chauffeurActuelPartner?->chauffeur;
        $driverLabel = $chauffeur
            ? trim(($chauffeur->prenom ?? '') . ' ' . ($chauffeur->nom ?? ''))
            : 'Non associé';

        $lastSeen = $locationData['heart_time'] ?? $locationData['sys_time'] ?? $locationData['datetime'] ?? null;
        $gpsOnline = $this->isGpsOnline($lastSeen);

        $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($locationData['status'] ?? null);
        $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

        $previousLiveStatus = (array) ($existingRow['live_status'] ?? []);
        $liveStatus = $this->buildLiveStatusFromLocation($locationData, $previousLiveStatus);

        return [
            'id'              => (int) $voiture->id,
            'immatriculation' => $voiture->immatriculation,
            'marque'          => $voiture->marque,
            'model'           => $voiture->model,
            'mac_id_gps'      => $voiture->mac_id_gps,
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
                'last_seen' => $lastSeen ? (string) $lastSeen : null,
            ],
            'live_status' => $liveStatus,
            'loc_id' => (int) ($locationData['id'] ?? 0),
        ];
    }

    private function isNewerLocIdThanCached(int $partnerId, int $vehicleId, int $incomingLocId): bool
    {
        try {
            $json = Redis::hget($this->kFleetH($partnerId), (string) $vehicleId);
            if (!$json) {
                return true;
            }

            $row = json_decode($json, true);
            $cachedLocId = (int) ($row['loc_id'] ?? 0);

            return $incomingLocId >= $cachedLocId;
        } catch (\Throwable) {
            return true;
        }
    }

    private function applyDynamicLiveStatusOnRow(array $vehicle): array
    {
        $oldLiveStatus = (array) ($vehicle['live_status'] ?? []);
        if (!empty($oldLiveStatus)) {
            $newLiveStatus = $this->recomputeOfflineLiveStatusFromRedis($oldLiveStatus);
            $vehicle['live_status'] = $newLiveStatus;

            $vehicle['gps']['online'] = $newLiveStatus['is_online'] ?? null;
            $vehicle['gps']['state'] = ($newLiveStatus['is_online'] ?? null) === true ? 'ONLINE' : 'OFFLINE';
            $vehicle['gps']['last_seen'] = (string) (
                $newLiveStatus['heart_time']
                ?? $newLiveStatus['datetime']
                ?? $newLiveStatus['sys_time']
                ?? ($vehicle['gps']['last_seen'] ?? '')
            );
        }

        return $vehicle;
    }

    private function isGpsOnline($lastSeen): ?bool
    {
        $ms = $this->toMs($lastSeen);
        if (!$ms) {
            return null;
        }

        $diffMs = now()->getTimestampMs() - $ms;
        return $diffMs <= ($this->gpsOfflineMinutes * 60 * 1000);
    }

    private function durationHuman(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($days > 0) return "{$days}j {$hours}h {$minutes}min";
        if ($hours > 0) return "{$hours}h {$minutes}min";
        if ($minutes > 0) return "{$minutes}min" . ($secs > 0 ? " {$secs}s" : '');
        return "{$secs}s";
    }

    private function toMs($value): ?int
    {
        if ($value === null) return null;

        if (is_numeric($value)) {
            $n = (int) $value;
            if ($n <= 0) return null;
            if ($n >= 1000000000000) return $n;
            if ($n >= 1000000000) return $n * 1000;
        }

        if (is_string($value)) {
            $s = trim((string) $value);
            if ($s === '') return null;
            if (is_numeric($s)) return $this->toMs((int) $s);

            try {
                return Carbon::parse($s)->getTimestampMs();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function msToDateTime(?int $ms): ?string
    {
        if (!$ms || $ms <= 0) return null;

        try {
            return Carbon::createFromTimestampMs($ms)
                ->setTimezone(config('app.timezone'))
                ->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildLiveStatusFromLocation(array $location, ?array $previousLiveStatus = null): array
    {
        $offlineThresholdMinutes = $this->gpsOfflineMinutes;
        $offlineThresholdMs = $offlineThresholdMinutes * 60 * 1000;

        $speedRaw = $location['speed'] ?? $location['su'] ?? null;
        $speed = is_numeric($speedRaw) ? (float) $speedRaw : null;

        $heartMs = $this->toMs($location['heart_time'] ?? null);
        $gpsMs   = $this->toMs($location['datetime'] ?? null);
        $sysMs   = $this->toMs($location['sys_time'] ?? null);

        $nowMs = now()->getTimestampMs();
        $onlineRefMs = $heartMs ?: $gpsMs ?: $sysMs;
        $isOnline = $onlineRefMs ? (($nowMs - $onlineRefMs) < $offlineThresholdMs) : false;

        $prevMovementState = (string) ($previousLiveStatus['movement_state'] ?? '');
        $prevStoppedSinceMs = isset($previousLiveStatus['stopped_since_ms']) ? (int) $previousLiveStatus['stopped_since_ms'] : null;
        $prevOfflineSinceMs = isset($previousLiveStatus['offline_since_ms']) ? (int) $previousLiveStatus['offline_since_ms'] : null;

        $movementState = 'UNKNOWN';
        $connectivityState = 'UNKNOWN';
        $uiStatus = 'UNKNOWN';
        $isMoving = null;

        $stoppedSinceMs = $prevStoppedSinceMs;
        $offlineSinceMs = $prevOfflineSinceMs;

        if ($isOnline === false) {
            $movementState = 'OFFLINE';
            $connectivityState = 'OFFLINE';
            $uiStatus = 'OFFLINE';
            $isMoving = null;

            if (!$offlineSinceMs) {
                $offlineSinceMs = $onlineRefMs ?: $nowMs;
            }
        } else {
            $offlineSinceMs = null;

            if ($speed !== null && $speed >= $this->movingThreshold) {
                $movementState = 'MOVING';
                $connectivityState = 'ONLINE_MOVING';
                $uiStatus = 'ONLINE_MOVING';
                $isMoving = true;
                $stoppedSinceMs = null;
            } elseif ($speed !== null && $speed >= 0) {
                $movementState = 'STOPPED';
                $connectivityState = 'ONLINE_STATIONARY';
                $uiStatus = 'ONLINE_STOPPED';
                $isMoving = false;

                if (!$stoppedSinceMs || $prevMovementState !== 'STOPPED') {
                    $stoppedSinceMs = $gpsMs ?: $sysMs ?: $onlineRefMs ?: $nowMs;
                }
            }
        }

        $stoppedSinceSeconds = $stoppedSinceMs ? max(0, (int) floor(($nowMs - $stoppedSinceMs) / 1000)) : null;
        $offlineSinceSeconds = $offlineSinceMs ? max(0, (int) floor(($nowMs - $offlineSinceMs) / 1000)) : null;

        return [
            'ui_status' => $uiStatus,
            'movement_state' => $movementState,
            'connectivity_state' => $connectivityState,
            'is_online' => $isOnline,
            'is_moving' => $isMoving,
            'speed' => $speed,
            'speed_raw' => $speedRaw,
            'moving_threshold' => $this->movingThreshold,
            'stopped_since_ms' => $stoppedSinceMs,
            'stopped_since_seconds' => $stoppedSinceSeconds,
            'stopped_since_human' => $this->durationHuman($stoppedSinceSeconds),
            'offline_since_ms' => $offlineSinceMs,
            'offline_since_seconds' => $offlineSinceSeconds,
            'offline_since_human' => $this->durationHuman($offlineSinceSeconds),
            'datetime' => $this->msToDateTime($gpsMs),
            'heart_time' => $this->msToDateTime($heartMs),
            'sys_time' => $this->msToDateTime($sysMs),
            'heart_time_ms' => $heartMs,
            'datetime_ms' => $gpsMs,
            'sys_time_ms' => $sysMs,
            'updated_at_ms' => $nowMs,
            'offline_threshold_minutes' => $offlineThresholdMinutes,
        ];
    }

    private function recomputeOfflineLiveStatusFromRedis(array $liveStatus): array
    {
        $offlineThresholdMinutes = (int) ($liveStatus['offline_threshold_minutes'] ?? $this->gpsOfflineMinutes);
        $offlineThresholdMs = $offlineThresholdMinutes * 60 * 1000;
        $nowMs = now()->getTimestampMs();

        $heartMs = isset($liveStatus['heart_time_ms']) ? (int) $liveStatus['heart_time_ms'] : null;
        $datetimeMs = isset($liveStatus['datetime_ms']) ? (int) $liveStatus['datetime_ms'] : null;
        $sysMs = isset($liveStatus['sys_time_ms']) ? (int) $liveStatus['sys_time_ms'] : null;

        $onlineRefMs = $heartMs ?: $datetimeMs ?: $sysMs;
        $isOnline = $onlineRefMs ? (($nowMs - $onlineRefMs) < $offlineThresholdMs) : false;

        $offlineSinceMs = isset($liveStatus['offline_since_ms']) ? (int) $liveStatus['offline_since_ms'] : null;
        $movementState = (string) ($liveStatus['movement_state'] ?? 'UNKNOWN');

        if ($isOnline === false) {
            if (!$offlineSinceMs) {
                $offlineSinceMs = $onlineRefMs ?: $nowMs;
            }

            $offlineSinceSeconds = max(0, (int) floor(($nowMs - $offlineSinceMs) / 1000));
            $liveStatus['ui_status'] = 'OFFLINE';
            $liveStatus['movement_state'] = 'OFFLINE';
            $liveStatus['connectivity_state'] = 'OFFLINE';
            $liveStatus['is_online'] = false;
            $liveStatus['is_moving'] = null;
            $liveStatus['offline_since_ms'] = $offlineSinceMs;
            $liveStatus['offline_since_seconds'] = $offlineSinceSeconds;
            $liveStatus['offline_since_human'] = $this->durationHuman($offlineSinceSeconds);
        } else {
            $liveStatus['is_online'] = true;
            $liveStatus['offline_since_ms'] = null;
            $liveStatus['offline_since_seconds'] = null;
            $liveStatus['offline_since_human'] = null;

            if ($movementState === 'STOPPED') {
                $liveStatus['ui_status'] = 'ONLINE_STOPPED';
                $liveStatus['connectivity_state'] = 'ONLINE_STATIONARY';
                $liveStatus['is_moving'] = false;
            } elseif ($movementState === 'MOVING') {
                $liveStatus['ui_status'] = 'ONLINE_MOVING';
                $liveStatus['connectivity_state'] = 'ONLINE_MOVING';
                $liveStatus['is_moving'] = true;
            }
        }

        $stoppedSinceMs = isset($liveStatus['stopped_since_ms']) ? (int) $liveStatus['stopped_since_ms'] : null;
        if ($stoppedSinceMs && ($liveStatus['movement_state'] ?? null) === 'STOPPED') {
            $stoppedSinceSeconds = max(0, (int) floor(($nowMs - $stoppedSinceMs) / 1000));
            $liveStatus['stopped_since_seconds'] = $stoppedSinceSeconds;
            $liveStatus['stopped_since_human'] = $this->durationHuman($stoppedSinceSeconds);
        }

        $liveStatus['updated_at_ms'] = $nowMs;

        return $liveStatus;
    }

    private function normalizeAlertType(?string $t): string
    {
        $t = strtolower(trim((string) $t));
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
            'geofence'  => 'GeoFence Breach',
            'safe_zone' => 'Safe Zone',
            'speed'     => 'Speeding',
            'stolen'    => 'Stolen Vehicle',
            'time_zone' => 'Time Zone',
            default     => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}