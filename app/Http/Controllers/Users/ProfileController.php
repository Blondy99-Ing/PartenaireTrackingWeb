<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\Voiture;
use App\Services\GpsControlService;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(private GpsControlService $gps) {}

    public function show()
    {
        $user = Auth::user();

        $voitures = Voiture::with('latestLocation')
            ->whereHas('utilisateur', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $voitures = $voitures->map(function ($v) {
            $status = $this->gps->getEngineStatusFromCachedLocation((string) $v->mac_id_gps);

            $engineState = $status['decoded']['engineState'] ?? 'UNKNOWN';
            $connectivity = $status['connectivity'] ?? [];

            $v->engine_on = in_array($engineState, ['ON', 'OFF'], true);
            $v->engine_cut = $engineState === 'CUT';
            $v->engine_state = $engineState;
            $v->gps_status = ($connectivity['is_online'] ?? null) === true ? 'Connected' : 'Disconnected';

            return $v;
        });

        return view('users.profile', [
            'user' => $user,
            'voitures' => $voitures,
            'vehiclesCount' => $voitures->count(),
        ]);
    }

    /**
     * Retourne les dernières positions des véhicules de l'utilisateur
     * en JSON, pour le rafraîchissement de la carte.
     */
    public function vehiclePositions()
    {
        $user = Auth::user();

        $voitures = Voiture::with('latestLocation')
            ->whereHas('utilisateur', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        $data = $voitures->map(function ($v) {
            return [
                'id'              => $v->id,
                'immatriculation' => $v->immatriculation,
                'marque'          => $v->marque,
                'model'           => $v->model,
                'lat'             => optional($v->latestLocation)->latitude,
                'lng'             => optional($v->latestLocation)->longitude,
            ];
        });

        return response()->json([
            'success'  => true,
            'vehicles' => $data,
        ]);
    }
}
