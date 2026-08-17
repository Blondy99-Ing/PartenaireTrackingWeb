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
     * Liste des véhicules du partenaire (flotte complète du tenant).
     *
     * Bug corrigé : utilisait Auth::id() directement, qui n'est le
     * propriétaire réel des véhicules (côté association_user_voitures) que
     * pour le partenaire principal lui-même. Un membre du staff a
     * partner_id = l'id du partenaire réel, pas de véhicules associés à son
     * propre id — résultat : la page "Véhicules" était vide pour tout le
     * staff (confirmé sur ROYAL HOLDING). Même résolution que
     * AffectationChauffeurVoitureController::tenantPartner().
     */
    public function index()
    {
        $tenantPartnerId = Auth::user()->tenantPartnerId();

        // Récupérer les voitures via la table pivot association_user_voitures
        $voitures = Voiture::with('simGps')
            ->whereHas('utilisateur', function ($q) use ($tenantPartnerId) {
                $q->where('user_id', $tenantPartnerId);
            })->get();

        return view('voitures.index', compact('voitures'));
    }

  
}
