<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Alert;

class AlertController extends Controller
{
    protected function typeLabel(?string $type): string
    {
        if (!$type) {
            return 'Unknown';
        }

        return match($type) {
            'geofence'      => 'GeoFence Breach',
            'safe_zone'     => 'Safe Zone',
            'speed'         => 'Speeding',
            'engine'        => 'Engine Alert',
            'unauthorized'  => 'Unauthorized Time',
            default         => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function index()
    {
        $alerts = Alert::with(['voiture.utilisateur'])
            ->orderBy('read', 'asc')        // Non lus en premier
            ->orderBy('alerted_at', 'desc') // Puis tri par date décroissante
            ->get()
            ->map(function (Alert $a) {
                $voiture = $a->voiture;
                $users = collect();

                if ($voiture && $voiture->utilisateur) {
                    $users = $voiture->utilisateur->map(function($u){
                        return trim(($u->prenom ?? '') . ' ' . ($u->nom ?? ''));
                    })->filter()->values();
                }

                $type = $a->type ?? null;
                $typeLabel = $this->typeLabel($type);

                return [
                    'id' => $a->id,
                    'voiture_id' => $a->voiture_id,
                    'type' => $type,
                    'type_label' => $typeLabel,
                    'message' => $a->message ?? null,
                    'location' => $a->location ?? $a->message ?? null,
                    'read' => (bool) $a->read,
                    'alerted_at_iso' => $a->alerted_at ? $a->alerted_at->toIso8601String() : null,
                    'alerted_at_human' => $a->alerted_at ? $a->alerted_at->format('d/m/Y H:i:s') : null,
                    'created_at' => $a->created_at ? $a->created_at->toIso8601String() : null,
                    'voiture' => $voiture ? [
                        'id' => $voiture->id,
                        'immatriculation' => $voiture->immatriculation,
                        'marque' => $voiture->marque,
                        'model' => $voiture->model,
                        'couleur' => $voiture->couleur,
                        'photo' => $voiture->photo,
                    ] : null,
                    'users' => $users->values()->all(),
                    'users_labels' => $users->isEmpty() ? null : $users->implode(', '),
                    'user_id' => $voiture && $voiture->utilisateur && $voiture->utilisateur->first() ? $voiture->utilisateur->first()->id : null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $alerts
        ]);
    }

    public function markAsRead($id)
    {
        $alert = Alert::findOrFail($id);
        $alert->read = true;
        $alert->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Alerte marquée comme lue',
            'data' => [
                'id' => $alert->id,
                'read' => true
            ]
        ]);
    }
}
