<?php

namespace App\Http\Controllers\Voitures;

use App\Http\Controllers\Controller;
use App\Models\Voiture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\GpsControlService;

class VoitureController extends Controller
{
    private GpsControlService $gps;

    public function __construct(GpsControlService $gps)
    {
        $this->gps = $gps;
    }

    /**
     * Liste des véhicules appartenant à l'utilisateur connecté
     */
    public function index()
    {
        $userId = Auth::id();

        // Récupérer les voitures via la table pivot association_user_voitures
        $voitures = Voiture::whereHas('utilisateur', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->get();

      

        return view('voitures.index', compact('voitures'));
    }

  
}
