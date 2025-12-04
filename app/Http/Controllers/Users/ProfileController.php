<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Voiture;
use App\Services\GpsControlService;
use Illuminate\Support\Facades\Route;


class ProfileController extends Controller
{
    private GpsControlService $gps;

    public function __construct(GpsControlService $gps)
    {
        $this->gps = $gps;
    }

    public function show()
{
    $user = Auth::user();

    // Charger ses véhicules + dernière position
    $voitures = Voiture::with('latestLocation')
        ->whereHas('utilisateur', fn($q) => $q->where('user_id', $user->id))
        ->get();

    // Ajouter statut moteur et online/offline
    $voitures = $voitures->map(function($v) {

        // Nouvelle méthode correcte
        $status = $this->gps->getEngineStatus($v->mac_id_gps);

        $v->engine_on = $status['engine_on'] ?? false;

        // Attention : gps_status = offline / online
        $v->gps_status = ($status['online'] ?? false) ? 'Connected' : 'Disconnected';

        return $v;
    });

    return view('users.profile', [
        'user' => $user,
        'voitures' => $voitures,
        'vehiclesCount' => $voitures->count(),
    ]);
}

}


