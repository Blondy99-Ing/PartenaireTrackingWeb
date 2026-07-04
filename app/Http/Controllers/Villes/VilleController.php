<?php

namespace App\Http\Controllers\Villes;

use App\Http\Controllers\Controller;
use App\Models\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VilleController extends Controller
{
    public function index()
    {
        // On ne charge pas le geojson ici (front fera map.data.loadGeoJson avec URL)
        return view('villes.index');
    }

    public function store(Request $request)
    {
        // Log de la requête brute
        Log::info('[VilleController@store] Requête reçue', ['all' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'code_ville' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'geom' => 'required|json', // on exige un GeoJSON valide côté client
        ]);

        if ($validator->fails()) {
            Log::warning('[VilleController@store] Validation échouée', ['errors' => $validator->errors()->all()]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Sauvegarde
        $ville = Ville::create([
            'code_ville' => $request->input('code_ville'),
            'name' => $request->input('name'),
            'geom' => $request->input('geom'),
        ]);

        Log::info('[VilleController@store] Ville créée', ['id' => $ville->id, 'name' => $ville->name]);

        return redirect()->route('villes.index')->with('success', 'Ville enregistrée avec succès');
    }
}
