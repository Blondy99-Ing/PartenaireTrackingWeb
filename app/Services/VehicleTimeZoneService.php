<?php

namespace App\Services;

use App\Models\AssociationUserVoiture;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleTimeZoneService
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
            ->select([
                'id',
                'immatriculation',
                'marque',
                'model',
                'mac_id_gps',
                'time_zone_start',
                'time_zone_end',
            ])
            ->orderBy('immatriculation')
            ->get();
    }

    public function apply(
        User $user,
        ?string $startTime,
        ?string $endTime,
        bool $allVehicles = false,
        array $vehicleIds = [],
        bool $disable = false
    ): int {
        abort_unless($this->canManage($user), 403);

        $allowedVehicleIds = $this->vehicleIdsForUser($user);

        if ($allowedVehicleIds->isEmpty()) {
            return 0;
        }

        $selectedVehicleIds = $allowedVehicleIds;

        if (! $allVehicles) {
            $selectedVehicleIds = collect($vehicleIds)
                ->map(fn ($id) => (int) $id)
                ->intersect($allowedVehicleIds)
                ->values();

            if ($selectedVehicleIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'vehicle_ids' => 'Sélectionnez au moins un véhicule ou cochez “Tous les véhicules”.',
                ]);
            }
        }

        $mustDisable = $disable || (blank($startTime) && blank($endTime));

        if (! $mustDisable && (blank($startTime) || blank($endTime))) {
            throw ValidationException::withMessages([
                'time_zone_start' => 'L’heure de début et l’heure de fin sont obligatoires.',
            ]);
        }

        $payload = $mustDisable
            ? [
                'time_zone_start' => null,
                'time_zone_end' => null,
            ]
            : [
                'time_zone_start' => $this->normalizeTime($startTime),
                'time_zone_end' => $this->normalizeTime($endTime),
            ];

        $updated = DB::transaction(function () use ($selectedVehicleIds, $payload) {
            return Voiture::query()
                ->whereIn('id', $selectedVehicleIds)
                ->update($payload);
        });

        $this->refreshDashboard($this->tenantPartnerId($user));

        return $updated;
    }

    private function normalizeTime(?string $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        return substr($time, 0, 5);
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