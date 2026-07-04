<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\GeofenceZone;
use App\Services\GeofenceZoneService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GeofenceSettingsController extends Controller
{
    public function __construct(
        private readonly GeofenceZoneService $service
    ) {}

    public function store(Request $request)
    {
        abort_unless($this->service->canManage($request->user()), 403);

        $tenantPartnerId = $this->service->tenantPartnerId($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('geofence_zones', 'code')
                    ->where('partner_id', $tenantPartnerId),
            ],
            'zone' => ['required', 'string'],
        ]);

        $this->service->create($request->user(), $validated);

        return redirect()
            ->route('settings.lease.index')
            ->with('success', 'Geofence créé avec succès.')
            ->with('active_section', 'geofence');
    }

    public function update(Request $request, GeofenceZone $geofence)
    {
        abort_unless($this->service->canManage($request->user()), 403);
        abort_unless((int) $geofence->partner_id === $this->service->tenantPartnerId($request->user()), 403);

        $tenantPartnerId = $this->service->tenantPartnerId($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('geofence_zones', 'code')
                    ->where('partner_id', $tenantPartnerId)
                    ->ignore($geofence->id),
            ],
            'zone' => ['required', 'string'],
        ]);

        $this->service->update($request->user(), $geofence, $validated);

        return redirect()
            ->route('settings.lease.index')
            ->with('success', 'Geofence modifié avec succès.')
            ->with('active_section', 'geofence');
    }

    public function destroy(Request $request, GeofenceZone $geofence)
    {
        $this->service->delete($request->user(), $geofence);

        return redirect()
            ->route('settings.lease.index')
            ->with('success', 'Geofence supprimé avec succès.')
            ->with('active_section', 'geofence');
    }

    public function assign(Request $request, GeofenceZone $geofence)
    {
        $validated = $request->validate([
            'all_vehicles' => ['nullable', 'boolean'],
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer'],
        ]);

        $count = $this->service->assign(
            $request->user(),
            $geofence,
            (bool) ($validated['all_vehicles'] ?? false),
            $validated['vehicle_ids'] ?? []
        );

        return redirect()
            ->route('settings.lease.index')
            ->with('success', "{$count} véhicule(s) mis à jour.")
            ->with('active_section', 'geofence');
    }
}