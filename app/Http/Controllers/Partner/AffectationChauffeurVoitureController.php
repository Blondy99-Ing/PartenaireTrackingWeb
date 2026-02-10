<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voiture;
use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\HistoriqueAssociationChauffeurVoiturePartner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AffectationChauffeurVoitureController extends Controller
{
    /**
     * PAGE: Liste des associations actives du partner
     */
    public function index(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();

        $items = AssociationChauffeurVoiturePartner::query()
            ->whereHas('voiture.partenaires', fn($q) => $q->where('users.id', $partner->id))
            ->with([
                'voiture:id,immatriculation,marque,model,mac_id_gps',
                'chauffeur:id,nom,prenom,phone,email',
                'assigner:id,nom,prenom',
            ])
            ->orderByDesc('assigned_at')
            ->get();

        return view('associations.index', compact('items'));
    }

    // ============ JSON LIST VEHICLES ============
    public function vehicles(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();
        $q = trim((string) $request->query('q', ''));

        $query = Voiture::query()
            ->whereHas('partenaires', fn($x) => $x->where('users.id', $partner->id))
            ->with(['chauffeurPartnerActuel.chauffeur:id,nom,prenom,phone']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('immatriculation', 'like', "%{$q}%")
                    ->orWhere('mac_id_gps', 'like', "%{$q}%")
                    ->orWhere('marque', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%");
            });
        }

        $items = $query->orderByDesc('id')->limit(80)->get()->map(function (Voiture $v) {
            $cur = $v->chauffeurPartnerActuel?->chauffeur;
            return [
                'id' => $v->id,
                'immatriculation' => $v->immatriculation,
                'marque' => $v->marque,
                'model' => $v->model,
                'mac_id_gps' => $v->mac_id_gps,
                'current_driver' => $cur ? [
                    'id' => $cur->id,
                    'nom' => $cur->nom,
                    'prenom' => $cur->prenom,
                    'phone' => $cur->phone,
                ] : null,
            ];
        });

        return response()->json(['ok' => true, 'items' => $items]);
    }

    // ============ JSON LIST DRIVERS ============
    public function drivers(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();
        $q = trim((string) $request->query('q', ''));

        $query = User::query()
            ->where('partner_id', $partner->id)
            ->with(['affectationVoitureActuellePartner.voiture:id,immatriculation,marque,model']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nom', 'like', "%{$q}%")
                    ->orWhere('prenom', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('ville', 'like', "%{$q}%")
                    ->orWhere('quartier', 'like', "%{$q}%");
            });
        }

        $items = $query->orderByDesc('id')->limit(80)->get()->map(function (User $u) {
            $cur = $u->affectationVoitureActuellePartner->first()?->voiture;
            return [
                'id' => $u->id,
                'nom' => $u->nom,
                'prenom' => $u->prenom,
                'phone' => $u->phone,
                'email' => $u->email,
                'current_vehicle' => $cur ? [
                    'id' => $cur->id,
                    'immatriculation' => $cur->immatriculation,
                    'marque' => $cur->marque,
                    'model' => $cur->model,
                ] : null,
            ];
        });

        return response()->json(['ok' => true, 'items' => $items]);
    }

    // ============ ASSIGN ============
    public function assign(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();

        $data = $request->validate([
            'chauffeur_id' => ['required', 'integer'],
            'voiture_id'   => ['required', 'integer'],
            'note'         => ['nullable', 'string'],
            'force'        => ['nullable', 'boolean'],
        ]);

        $chauffeurId = (int) $data['chauffeur_id'];
        $voitureId   = (int) $data['voiture_id'];
        $force       = (bool) ($data['force'] ?? false);
        $note        = $data['note'] ?? null;

        $chauffeur = User::where('partner_id', $partner->id)->findOrFail($chauffeurId);

        $voiture = Voiture::whereHas('partenaires', fn($q) => $q->where('users.id', $partner->id))
            ->findOrFail($voitureId);

        $actorId = (int) $partner->id;

        return DB::transaction(function () use ($partner, $chauffeur, $voiture, $force, $note, $actorId) {

            $currentByVehicle = AssociationChauffeurVoiturePartner::with('chauffeur:id,nom,prenom,phone')
                ->where('voiture_id', $voiture->id)->first();

            if ($currentByVehicle && $currentByVehicle->chauffeur_id !== $chauffeur->id) {
                if (!$force) {
                    return response()->json([
                        'ok' => false,
                        'type' => 'conflict_vehicle',
                        'existing' => [
                            'chauffeur_id' => $currentByVehicle->chauffeur_id,
                            'nom' => $currentByVehicle->chauffeur?->nom,
                            'prenom' => $currentByVehicle->chauffeur?->prenom,
                            'phone' => $currentByVehicle->chauffeur?->phone,
                        ]
                    ], 409);
                }
                $this->endCurrentAssignment($currentByVehicle, (int)$partner->id, $actorId, 'Réaffectation véhicule');
            }

            $currentByDriver = AssociationChauffeurVoiturePartner::with('voiture:id,immatriculation,marque,model')
                ->where('chauffeur_id', $chauffeur->id)->first();

            if ($currentByDriver && $currentByDriver->voiture_id !== $voiture->id) {
                if (!$force) {
                    return response()->json([
                        'ok' => false,
                        'type' => 'conflict_driver',
                        'existing' => [
                            'voiture_id' => $currentByDriver->voiture_id,
                            'immatriculation' => $currentByDriver->voiture?->immatriculation,
                            'marque' => $currentByDriver->voiture?->marque,
                            'model' => $currentByDriver->voiture?->model,
                        ]
                    ], 409);
                }
                $this->endCurrentAssignment($currentByDriver, (int)$partner->id, $actorId, 'Réaffectation chauffeur');
            }

            $already = AssociationChauffeurVoiturePartner::where('chauffeur_id', $chauffeur->id)
                ->where('voiture_id', $voiture->id)->first();

            if ($already) {
                return response()->json(['ok' => true, 'message' => 'Affectation déjà en place.']);
            }

            AssociationChauffeurVoiturePartner::create([
                'voiture_id'   => $voiture->id,
                'chauffeur_id' => $chauffeur->id,
                'assigned_by'  => $actorId,
                'assigned_at'  => now(),
                'note'         => $note,
            ]);

            HistoriqueAssociationChauffeurVoiturePartner::create([
                'partner_id'   => $partner->id, // OBLIGATOIRE
                'voiture_id'   => $voiture->id,
                'chauffeur_id' => $chauffeur->id,
                'assigned_by'  => $actorId,
                'created_by'   => $actorId,
                'started_at'   => now(),
                'ended_at'     => null,
                'note'         => $note,
            ]);

            return response()->json(['ok' => true, 'message' => 'Affectation effectuée.']);
        });
    }

    // ============ UNASSIGN ============
    public function unassign(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();

        $data = $request->validate([
            'voiture_id'   => ['nullable', 'integer'],
            'chauffeur_id' => ['nullable', 'integer'],
            'note'         => ['nullable', 'string'],
        ]);

        if (empty($data['voiture_id']) && empty($data['chauffeur_id'])) {
            throw ValidationException::withMessages(['general' => ['voiture_id ou chauffeur_id requis.']]);
        }

        $actorId = (int) $partner->id;

        return DB::transaction(function () use ($partner, $data, $actorId) {

            $q = AssociationChauffeurVoiturePartner::query();

            if (!empty($data['voiture_id'])) {
                $vid = (int)$data['voiture_id'];
                Voiture::whereHas('partenaires', fn($x) => $x->where('users.id', $partner->id))->findOrFail($vid);
                $q->where('voiture_id', $vid);
            }

            if (!empty($data['chauffeur_id'])) {
                $cid = (int)$data['chauffeur_id'];
                User::where('partner_id', $partner->id)->findOrFail($cid);
                $q->where('chauffeur_id', $cid);
            }

            $current = $q->first();
            if (!$current) {
                return response()->json(['ok' => true, 'message' => 'Aucune affectation active.']);
            }

            $this->endCurrentAssignment($current, (int)$partner->id, $actorId, $data['note'] ?? 'Désaffectation');

            return response()->json(['ok' => true, 'message' => 'Désaffecté.']);
        });
    }

    // ============ HISTORY PAGE ============
    public function history(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();

        $items = HistoriqueAssociationChauffeurVoiturePartner::query()
            ->where('partner_id', $partner->id)
            ->with([
                'voiture:id,immatriculation,marque,model',
                'chauffeur:id,nom,prenom,phone',
                'assigner:id,nom,prenom'
            ])
            ->orderByDesc('started_at')
            ->paginate(50);

        return view('associations.history', compact('items'));
    }

    private function endCurrentAssignment(
        AssociationChauffeurVoiturePartner $current,
        int $partnerId,
        int $actorId,
        string $note = ''
    ): void {
        $now = now();

        HistoriqueAssociationChauffeurVoiturePartner::query()
            ->where('partner_id', $partnerId)
            ->where('voiture_id', $current->voiture_id)
            ->where('chauffeur_id', $current->chauffeur_id)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->limit(1)
            ->update([
                'ended_at' => $now,
                'assigned_by' => $actorId,
                'note' => $note,
            ]);

        $current->delete();
    }
}
