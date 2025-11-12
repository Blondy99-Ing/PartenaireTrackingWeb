<?php

namespace App\Http\Controllers\Voitures;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voiture;
use Illuminate\Support\Str;

class VoitureController extends Controller
{

    // affichage
    public function index()
    {
        $voitures = Voiture::all();
        return view('voitures.index', compact('voitures'));
    }


    public function store(Request $request)
    {
        // Validation
        $validatedData = $request->validate([
            'immatriculation' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'couleur' => 'required|string|max:255',
            'marque' => 'required|string|max:255',
            'mac_id_gps' => 'required|string|max:255|unique:voitures,mac_id_gps',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:8048',
            'geofence_latitude' => 'required|numeric',
            'geofence_longitude' => 'required|numeric',
            'geofence_radius' => 'required|integer|min:100',
        ]);

        // Upload photo
        if ($request->hasFile('photo')) {
            $validatedData['photo'] = $request->file('photo')->store('photos');
        }

        // Generate unique ID
        $validatedData['voiture_unique_id'] = 'VH-' . now()->format('Ym') . '-' . Str::random(6);

        // Create the vehicles
        Voiture::create($validatedData);

        return redirect()->route('tracking.vehicles')->with('success', 'Vehicle added successfully.');
    }


    public function destroy(Voiture $voiture)
    {
        $voiture->delete();
        return redirect()->route('tracking.vehicles')->with('success', 'Véhicule supprimé avec succès.');
    }
}
