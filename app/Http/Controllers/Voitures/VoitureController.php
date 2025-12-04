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

        // Ajouter statut moteur + GPS online
        foreach ($voitures as $v) {
            $status = $this->gps->getEngineStatus($v->mac_id_gps);

            $v->engine_on = $status['engine_on'] ?? false;
            $v->gps_status = ($status['online'] ?? false) ? 'Connected' : 'Disconnected';
        }

        return view('voitures.index', compact('voitures'));
    }

    /**
     * API : statut moteur
     */
    public function getEngineStatus($id)
    {
        $userId = Auth::id();

        $voiture = Voiture::where('id', $id)
            ->whereHas('utilisateur', fn($q) => $q->where('user_id', $userId))
            ->firstOrFail();

        $status = $this->gps->getEngineStatus($voiture->mac_id_gps);

        return response()->json([
            "success" => $status["success"] ?? false,
            "engine_on" => $status["engine_on"] ?? false,
        ]);
    }

    /**
     * API : on/off moteur
     */
    public function toggleEngine($id)
    {
        $userId = Auth::id();

        $voiture = Voiture::where('id', $id)
            ->whereHas('utilisateur', fn($q) => $q->where('user_id', $userId))
            ->firstOrFail();

        $status = $this->gps->getEngineStatus($voiture->mac_id_gps);

        if (!($status["success"] ?? false)) {
            return response()->json([
                "success" => false,
                "message" => "Impossible d’obtenir le statut moteur."
            ], 500);
        }

        $engineOn = $status["engine_on"];

        $response = $engineOn
            ? $this->gps->cutEngine($voiture->mac_id_gps)   // moteur ON → couper
            : $this->gps->startEngine($voiture->mac_id_gps); // moteur OFF → allumer

        return response()->json([
            "success" => true,
            "engine_on" => !$engineOn,
            "gps_response" => $response
        ]);
    }
}
