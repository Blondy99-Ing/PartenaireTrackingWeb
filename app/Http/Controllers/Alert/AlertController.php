<?php

namespace App\Http\Controllers\Alert;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AlertController extends Controller
{
    /**
     * Label lisible pour chaque type d’alerte
     */
    protected function typeLabel(?string $type): string
    {
        if (!$type) return 'Unknown';

        return match ($type) {
            'geofence'      => 'GeoFence Breach',
            'safe_zone'     => 'Safe Zone',
            'speed'         => 'Speeding',
            'engine'        => 'Engine Alert',
            'unauthorized'  => 'Unauthorized Time',
            'stolen'        => 'Stolen Vehicle',
            'low_battery'   => 'Low Battery',
            'time_zone'     => 'Time Zone',
            default         => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * ✔ Version PARTENAIRE
     *   - Alertes UNIQUEMENT des véhicules du user connecté
     *   - AUCUNE alerte batterie (low_battery)
     *   - Même structure JSON que la plateforme interne
     *   - Priorité : stolen -> geofence -> autres non traitées -> traitées
     */
    public function index()
    {
        $userId = Auth::id();

        $alerts = Alert::with(['voiture.utilisateur', 'processedBy'])

            // 🔒 1) Alertes uniquement des véhicules du partenaire connecté
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })

            // 🚫 2) Exclure les alertes batterie
            ->where('alerts.alert_type', '!=', 'low_battery')
            // Si tu as un autre type lié à la batterie :
            // ->whereNotIn('alerts.alert_type', ['low_battery', 'battery'])

            // ⭐ 3) Priorité : stolen -> geofence -> autres non traitées -> traitées
            ->select('alerts.*')
            ->selectRaw("
                CASE
                    WHEN alerts.processed = 0 AND alerts.alert_type = 'stolen'   THEN 0
                    WHEN alerts.processed = 0 AND alerts.alert_type = 'geofence' THEN 1
                    WHEN alerts.processed = 0                                    THEN 2
                    ELSE 3
                END AS priority
            ")
            ->orderBy('priority', 'asc')
            ->orderBy('alerted_at', 'desc')

            ->get()
            ->map(function (Alert $a) {
                $voiture = $a->voiture;
                $users = collect();

                if ($voiture && $voiture->utilisateur) {
                    $users = $voiture->utilisateur
                        ->map(fn($u) => trim(($u->prenom ?? '') . ' ' . ($u->nom ?? '')))
                        ->filter()
                        ->values();
                }

                return [
                    'id'           => $a->id,
                    'voiture_id'   => $a->voiture_id,
                    'type'         => $a->type, // accessor ->type (type/alert_type)
                    'type_label'   => $this->typeLabel($a->type),
                    'message'      => $a->message,
                    'location'     => $a->location ?? $a->message,
                    'read'         => (bool) $a->read,
                    'processed'    => (bool) $a->processed,
                    'processed_by' => $a->processed_by,
                    'processed_by_name' => optional($a->processedBy)->name ?? null,

                    'alerted_at_human' => $a->alerted_at
                        ? $a->alerted_at->format('d/m/Y H:i:s')
                        : '-',

                    'voiture' => $voiture ? [
                        'id'              => $voiture->id,
                        'immatriculation' => $voiture->immatriculation,
                        'marque'          => $voiture->marque,
                        'model'           => $voiture->model,
                        'couleur'         => $voiture->couleur,
                        'photo'           => $voiture->photo,
                    ] : null,

                    'users_labels' => $users->isEmpty() ? null : $users->implode(', '),
                    'user_id'      => $voiture?->utilisateur?->first()?->id ?? null,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $alerts,
        ]);
    }

    /**
     * ✔ Logue un polygon envoyé depuis le frontend (debug)
     */
    public function receivePolygon(Request $request)
    {
        $polygon = $request->all();

        Log::info('Polygon reçu depuis le frontend (partenaire) : ', $polygon);

        return response()->json([
            'status'           => 'success',
            'message'          => 'Polygon reçu et logué',
            'polygon_received' => $polygon,
        ]);
    }

    /**
     * ✔ Marquer une alerte comme traitée (optionnel côté partenaire)
     *   On sécurise : seulement ses propres alertes
     */
    public function markAsProcessed(Request $request, $id)
    {
        $userId = Auth::id();

        $alert = Alert::where('id', $id)
            ->whereHas('voiture.utilisateur', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->firstOrFail();

        $alert->processed    = true;
        $alert->processed_by = $userId;
        $alert->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Alerte marquée comme traitée',
            'data'    => [
                'id'           => $alert->id,
                'processed'    => true,
                'processed_by' => $alert->processed_by,
            ],
        ]);
    }
}
