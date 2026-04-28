<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffRule;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaseCutoffRuleService
{
    /**
     * Retourne l'identifiant du partenaire courant.
     * Dans votre modèle actuel :
     * - si partner_id est null => l'utilisateur connecté est lui-même le partenaire
     * - sinon => l'utilisateur connecté dépend d'un partenaire
     */
    public function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    /**
     * Retourne la liste des véhicules du partenaire avec leur règle de coupure actuelle si elle existe.
     */
    public function getPartnerVehiclesWithRules(User $user): Collection
    {
        $partnerId = $this->resolvePartnerId($user);

        return Voiture::query()
            ->select([
                'voitures.id',
                'voitures.immatriculation',
                'voitures.marque',
                'voitures.model',
                'voitures.mac_id_gps',
                'lease_cutoff_rules.id as rule_id',
                'lease_cutoff_rules.is_enabled',
                'lease_cutoff_rules.cutoff_time',
                'lease_cutoff_rules.timezone',
            ])
            ->join('association_user_voitures', 'association_user_voitures.voiture_id', '=', 'voitures.id')
            ->leftJoin('lease_cutoff_rules', function ($join) use ($partnerId) {
                $join->on('lease_cutoff_rules.vehicle_id', '=', 'voitures.id')
                    ->where('lease_cutoff_rules.partner_id', '=', $partnerId);
            })
            ->where('association_user_voitures.user_id', $partnerId)
            ->orderBy('voitures.immatriculation')
            ->get()
            ->map(function ($row) {
                return [
                    'vehicle_id' => (int) $row->id,
                    'rule_id' => $row->rule_id ? (int) $row->rule_id : null,
                    'immatriculation' => $row->immatriculation,
                    'marque' => $row->marque,
                    'model' => $row->model,
                    'mac_id_gps' => $row->mac_id_gps,
                    'is_enabled' => (bool) ($row->is_enabled ?? false),
                    'cutoff_time' => $row->cutoff_time ? substr((string) $row->cutoff_time, 0, 5) : null,
                    'timezone' => $row->timezone,
                ];
            });
    }

    /**
     * Sauvegarde les règles de coupure véhicule par véhicule.
     * Le frontend envoie une ligne par véhicule affiché.
     */
    public function saveRules(User $user, array $rulesPayload): void
    {
        $partnerId = $this->resolvePartnerId($user);
        $actorId = (int) $user->id;

        DB::transaction(function () use ($partnerId, $actorId, $rulesPayload) {
            foreach ($rulesPayload as $row) {
                $vehicleId = (int) $row['vehicle_id'];

                $rule = LeaseCutoffRule::firstOrNew([
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicleId,
                ]);

                $isNew = ! $rule->exists;

                $rule->is_enabled = (bool) ($row['is_enabled'] ?? false);
                $rule->cutoff_time = ! empty($row['cutoff_time']) ? $row['cutoff_time'] : null;

                // Pour l’instant timezone nullable comme vous l’avez demandé.
                $rule->timezone = $row['timezone'] ?? null;

                if ($isNew) {
                    $rule->created_by = $actorId;
                }

                $rule->updated_by = $actorId;
                $rule->save();
            }
        });
    }
}