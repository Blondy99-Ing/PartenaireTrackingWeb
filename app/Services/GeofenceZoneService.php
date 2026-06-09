<?php

namespace App\Services;

use App\Models\AssociationUserVoiture;
use App\Models\GeofenceZone;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeofenceZoneService
{
    public function tenantPartnerId(User $user): int
    {
        return (int) $user->tenantPartnerId();
    }

    public function canManage(User $user): bool
    {
        if (is_null($user->partner_id)) {
            return true;
        }

        return optional($user->role)->slug === 'partner_admin';
    }

    public function geofencesForUser(User $user): Collection
    {
        return GeofenceZone::query()
            ->where('partner_id', $this->tenantPartnerId($user))
            ->latest()
            ->get();
    }

    public function vehicleIdsForUser(User $user): Collection
    {
        $tenantPartnerId = $this->tenantPartnerId($user);

        return AssociationUserVoiture::query()
            ->where('user_id', $tenantPartnerId)
            ->pluck('voiture_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function vehiclesForUser(User $user): Collection
    {
        $vehicleIds = $this->vehicleIdsForUser($user);

        if ($vehicleIds->isEmpty()) {
            return collect();
        }

        return Voiture::query()
            ->whereIn('id', $vehicleIds)
            ->orderBy('immatriculation')
            ->get();
    }

    public function normalizeZone(string|array $zone): string
    {
        $decoded = is_string($zone) ? json_decode($zone, true) : $zone;

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'zone' => 'Le format du geofence est invalide.',
            ]);
        }

        $points = [];

        foreach ($decoded as $point) {
            if (is_array($point) && isset($point['lat'], $point['lng'])) {
                $points[] = [
                    'lat' => (float) $point['lat'],
                    'lng' => (float) $point['lng'],
                ];
            } elseif (is_array($point) && isset($point[0], $point[1])) {
                $points[] = [
                    'lat' => (float) $point[1],
                    'lng' => (float) $point[0],
                ];
            }
        }

        if (count($points) < 3) {
            throw ValidationException::withMessages([
                'zone' => 'Le geofence doit contenir au moins 3 points.',
            ]);
        }

        return json_encode($points, JSON_UNESCAPED_UNICODE);
    }

    public function create(User $user, array $data): GeofenceZone
    {
        abort_unless($this->canManage($user), 403);

        $geofence = GeofenceZone::create([
            'partner_id' => $this->tenantPartnerId($user),
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'zone' => $this->normalizeZone($data['zone']),
            'created_by' => $user->id,
        ]);

        $this->refreshDashboard($this->tenantPartnerId($user));

        return $geofence;
    }

    public function update(User $user, GeofenceZone $geofence, array $data): GeofenceZone
    {
        abort_unless($this->canManage($user), 403);
        abort_unless((int) $geofence->partner_id === $this->tenantPartnerId($user), 403);

        $geofence->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'zone' => $this->normalizeZone($data['zone']),
        ]);

        $this->refreshDashboard($this->tenantPartnerId($user));

        return $geofence;
    }

    public function delete(User $user, GeofenceZone $geofence): void
    {
        abort_unless($this->canManage($user), 403);
        abort_unless((int) $geofence->partner_id === $this->tenantPartnerId($user), 403);

        $tenantPartnerId = $this->tenantPartnerId($user);

        DB::transaction(function () use ($user, $geofence) {
            $vehicleIds = $this->vehicleIdsForUser($user);

            Voiture::query()
                ->whereIn('id', $vehicleIds)
                ->where('geofence_zone', (string) $geofence->id)
                ->update(['geofence_zone' => null]);

            $geofence->delete();
        });

        $this->refreshDashboard($tenantPartnerId);
    }

    public function assign(User $user, GeofenceZone $geofence, bool $allVehicles, array $vehicleIds = []): int
    {
        abort_unless($this->canManage($user), 403);
        abort_unless((int) $geofence->partner_id === $this->tenantPartnerId($user), 403);

        $allowedVehicleIds = $this->vehicleIdsForUser($user);

        if ($allowedVehicleIds->isEmpty()) {
            return 0;
        }

        $query = Voiture::query()->whereIn('id', $allowedVehicleIds);

        if (! $allVehicles) {
            $selectedVehicleIds = collect($vehicleIds)
                ->map(fn ($id) => (int) $id)
                ->intersect($allowedVehicleIds)
                ->values();

            if ($selectedVehicleIds->isEmpty()) {
                return 0;
            }

            $query->whereIn('id', $selectedVehicleIds);
        }

        $updated = $query->update([
            'geofence_zone' => (string) $geofence->id,
        ]);

        $this->refreshDashboard($this->tenantPartnerId($user));

        return $updated;
    }

    private function refreshDashboard(int $partnerId): void
    {
        try {
            app(DashboardCacheService::class)->rebuildFleet($partnerId);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}