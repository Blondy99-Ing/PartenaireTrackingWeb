<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Voiture;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    public function store(Request $request)
    {
        // Validate the form
        $request->validate([
            'immatriculation' => 'required|string|max:255',
            'mac_id_gps' => 'required|string|max:255',
            'marque' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'couleur' => 'required|string|max:255',
            'region_name' => 'required|string|max:255',
            'region_polygon' => 'required',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('photos', 'public');
        }

        // Save the vehicle
        Voiture::create([
            'voiture_unique_id' => 'VHC' . now()->format('YmdHis') . rand(1000, 9999),
            'immatriculation' => $request->immatriculation,
            'mac_id_gps' => $request->mac_id_gps,
            'marque' => $request->marque,
            'model' => $request->model,
            'couleur' => $request->couleur,
            'photo' => $photoPath,
            'region_name' => $request->region_name,
            'region_polygon' => $request->region_polygon,
            'geofence_latitude' => null,
            'geofence_longitude' => null,
            'geofence_radius' => null,
            'region_id' => null,
        ]);

        return redirect()->back()->with('success', 'Vehicle created successfully!');
    }
}
