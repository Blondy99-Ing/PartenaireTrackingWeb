<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\VehicleTimeZoneService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehicleTimeZoneSettingsController extends Controller
{
    public function __construct(
        private readonly VehicleTimeZoneService $service
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'time_zone_start' => ['nullable', 'date_format:H:i'],
            'time_zone_end' => ['nullable', 'date_format:H:i'],

            'disable_timezone' => ['nullable', 'boolean'],

            'all_vehicles' => ['nullable', 'boolean'],
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer'],
        ]);

        $count = $this->service->apply(
            user: $request->user(),
            startTime: $validated['time_zone_start'] ?? null,
            endTime: $validated['time_zone_end'] ?? null,
            allVehicles: (bool) ($validated['all_vehicles'] ?? false),
            vehicleIds: $validated['vehicle_ids'] ?? [],
            disable: (bool) ($validated['disable_timezone'] ?? false),
        );

        return redirect()
            ->route('settings.lease.index')
            ->with('success', "{$count} véhicule(s) mis à jour.")
            ->with('active_section', 'timezone');
    }
}