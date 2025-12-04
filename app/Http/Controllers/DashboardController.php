<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Voiture;
use App\Models\Alert;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        // COUNT uniquement les véhicules du user connecté
        $vehiclesCount = Voiture::whereHas('utilisateur', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->count();

        $associationsCount = $vehiclesCount;

        // COUNT alertes du user connecté
        $alertsCount = Alert::where('processed', false)
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->count();

        // Charger uniquement les alertes des véhicules du user connecté
        $alerts = Alert::with(['voiture.utilisateur'])
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->orderBy('processed', 'asc')
            ->orderBy('alerted_at', 'desc')
            ->take(10)
            ->get()
            ->map(function($a) {
                $voiture = $a->voiture;

                $users = $voiture?->utilisateur
                    ?->map(fn($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')))
                    ->implode(', ');

                return [
                    'vehicle' => $voiture?->immatriculation ?? 'N/A',
                    'type'    => $a->type,
                    'time'    => $a->alerted_at?->format('d/m/Y H:i:s'),
                    'status'  => $a->processed ? 'Résolu' : 'Ouvert',
                    'status_color' => $a->processed ? 'bg-green-500' : 'bg-red-500',
                    'users'   => $users,
                ];
            });

        // Géolocalisation dynamique : uniquement les véhicules du user connecté
        $vehicles = Voiture::with(['latestLocation', 'utilisateur'])
            ->whereHas('utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->get()
            ->filter(function($v) {
                return $v->latestLocation && $v->latestLocation->latitude && $v->latestLocation->longitude;
            })
            ->map(function($v) {
                return [
                    'id' => $v->id,
                    'immatriculation' => $v->immatriculation,
                    'lat' => floatval($v->latestLocation->latitude),
                    'lon' => floatval($v->latestLocation->longitude),
                    'status' => 'En mouvement',
                ];
            });

        return view('dashboards.index', [
            'usersCount' => 1, // inutile pour un user connecté
            'vehiclesCount' => $vehiclesCount,
            'associationsCount' => $associationsCount,
            'alertsCount' => $alertsCount,
            'alerts' => $alerts,
            'vehicles' => $vehicles,
        ]);
    }
}
