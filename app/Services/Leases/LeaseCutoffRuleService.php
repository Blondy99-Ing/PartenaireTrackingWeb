<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffRule;
use App\Models\LeaseCutoffRuleContractType;
use App\Models\User;
use App\Models\Voiture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de paramétrage des règles de coupure lease.
 *
 * Conception retenue :
 * - lease_cutoff_rules = règle générale du véhicule ;
 * - lease_cutoff_rule_contract_types = règles filles par type de contrat/sous-contrat ;
 * - recouvrement reste la source de vérité des types de contrats ;
 * - Tracking stocke seulement le paramétrage de coupure par véhicule + type.
 */
class LeaseCutoffRuleService
{
    /**
     * Résout le partenaire propriétaire des règles.
     */
    public function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    /**
     * Retourne les véhicules du partenaire avec la règle véhicule et la matrice
     * de règles par type de contrat/sous-contrat.
     *
     * La vue peut ainsi afficher :
     * - une ligne par véhicule ;
     * - une colonne ou carte par type : Moto, Téléphone, Parapluie, etc.
     */
    public function getPartnerVehiclesWithRules(User $user, array $contractTypes = []): Collection
    {
        $partnerId = $this->resolvePartnerId($user);
        $types = $this->normalizeContractTypes($contractTypes);

        $vehicles = Voiture::query()
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
                'lease_cutoff_rules.grace_days',
                'lease_cutoff_rules.only_when_stopped',
                'lease_cutoff_rules.notify_before_cutoff',
            ])
            ->join('association_user_voitures', 'association_user_voitures.voiture_id', '=', 'voitures.id')
            ->leftJoin('lease_cutoff_rules', function ($join) use ($partnerId) {
                $join->on('lease_cutoff_rules.vehicle_id', '=', 'voitures.id')
                    ->where('lease_cutoff_rules.partner_id', '=', $partnerId);
            })
            ->where('association_user_voitures.user_id', $partnerId)
            ->orderBy('voitures.immatriculation')
            ->get();

        $ruleIds = $vehicles
            ->pluck('rule_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $childrenByRuleId = LeaseCutoffRuleContractType::query()
            ->whereIn('rule_id', $ruleIds)
            ->get()
            ->groupBy('rule_id')
            ->map(fn ($rows) => $rows->keyBy('type_contrat_id'));

        return $vehicles->map(function ($row) use ($types, $childrenByRuleId) {
            $ruleId = $row->rule_id ? (int) $row->rule_id : null;
            $generalEnabled = (bool) ($row->is_enabled ?? false);
            $generalGraceDays = (int) ($row->grace_days ?? 0);
            $generalCutoffTime = $row->cutoff_time ? substr((string) $row->cutoff_time, 0, 5) : null;
            $generalOnlyStopped = (bool) ($row->only_when_stopped ?? true);
            $generalNotifyBefore = (bool) ($row->notify_before_cutoff ?? false);
            $childrenByType = $ruleId ? ($childrenByRuleId->get($ruleId) ?? collect()) : collect();

            $typeRules = $types->map(function (array $type) use (
                $childrenByType,
                $generalEnabled,
                $generalGraceDays,
                $generalCutoffTime,
                $generalOnlyStopped,
                $generalNotifyBefore
            ) {
                /** @var LeaseCutoffRuleContractType|null $child */
                $child = $childrenByType->get((int) $type['id']);

                return [
                    'type_contrat_id' => (int) $type['id'],
                    'type_contrat_label' => $child?->type_contrat_label ?: $type['label'],
                    'code' => $type['code'] ?? null,
                    'is_main' => (bool) ($type['is_main'] ?? false),

                    /**
                     * Si aucune ligne fille n’existe encore, on part sur false.
                     * C’est volontaire : un nouveau type ne coupe jamais par accident.
                     */
                    'is_enabled' => (bool) ($child?->is_enabled ?? false),

                    /**
                     * Les paramètres spécifiques au type héritent visuellement de
                     * la règle véhicule si non définis côté enfant.
                     */
                    'grace_days' => $child && $child->grace_days !== null
                        ? (int) $child->grace_days
                        : $generalGraceDays,
                    'cutoff_time' => $child && $child->cutoff_time
                        ? substr((string) $child->cutoff_time, 0, 5)
                        : $generalCutoffTime,
                    'only_when_stopped' => $child && $child->only_when_stopped !== null
                        ? (bool) $child->only_when_stopped
                        : $generalOnlyStopped,
                    'notify_before_cutoff' => $child && $child->notify_before_cutoff !== null
                        ? (bool) $child->notify_before_cutoff
                        : $generalNotifyBefore,
                ];
            })->values()->all();

            return [
                'vehicle_id' => (int) $row->id,
                'rule_id' => $ruleId,
                'immatriculation' => $row->immatriculation,
                'marque' => $row->marque,
                'model' => $row->model,
                'mac_id_gps' => $row->mac_id_gps,
                'is_enabled' => $generalEnabled,
                'cutoff_time' => $generalCutoffTime,
                'timezone' => $row->timezone ?: 'Africa/Douala',
                'grace_days' => $generalGraceDays,
                'only_when_stopped' => $generalOnlyStopped,
                'notify_before_cutoff' => $generalNotifyBefore,
                'contract_type_rules' => $typeRules,
                'enabled_type_rules_count' => collect($typeRules)->where('is_enabled', true)->count(),
            ];
        });
    }

    /**
     * Sauvegarde la matrice complète véhicule + types.
     *
     * Format attendu depuis la vue :
     * rules[vehicle][is_enabled], cutoff_time, grace_days, ...
     * rules[vehicle][contract_types][type][type_contrat_id], is_enabled, ...
     */
    public function saveRules(User $user, array $rulesPayload, array $contractTypes = []): void
    {
        $partnerId = $this->resolvePartnerId($user);
        $actorId = (int) $user->id;
        $types = $this->normalizeContractTypes($contractTypes);

        DB::transaction(function () use ($partnerId, $actorId, $rulesPayload, $types) {
            foreach ($rulesPayload as $row) {
                $vehicleId = (int) ($row['vehicle_id'] ?? 0);

                if ($vehicleId <= 0) {
                    continue;
                }

                $rule = LeaseCutoffRule::firstOrNew([
                    'partner_id' => $partnerId,
                    'vehicle_id' => $vehicleId,
                ]);

                $isNew = ! $rule->exists;

                $rule->is_enabled = $this->toBool($row['is_enabled'] ?? false);
                $rule->cutoff_time = ! empty($row['cutoff_time']) ? $row['cutoff_time'] : null;
                $rule->timezone = $row['timezone'] ?? 'Africa/Douala';
                $rule->grace_days = (int) ($row['grace_days'] ?? 0);
                $rule->only_when_stopped = $this->toBool($row['only_when_stopped'] ?? true);
                $rule->notify_before_cutoff = $this->toBool($row['notify_before_cutoff'] ?? false);

                if ($isNew) {
                    $rule->created_by = $actorId;
                }

                $rule->updated_by = $actorId;
                $rule->save();

                $submittedTypes = collect($row['contract_types'] ?? [])
                    ->filter(fn ($typeRow) => is_array($typeRow))
                    ->keyBy(fn ($typeRow) => (int) ($typeRow['type_contrat_id'] ?? $typeRow['type_contrat'] ?? 0));

                /**
                 * On parcourt les types dynamiques connus, pas uniquement les cases
                 * cochées. Ainsi, une case absente ou décochée devient explicitement OFF.
                 */
                foreach ($types as $type) {
                    $typeId = (int) $type['id'];
                    $typeRow = $submittedTypes->get($typeId, []);

                    LeaseCutoffRuleContractType::updateOrCreate(
                        [
                            'rule_id' => $rule->id,
                            'type_contrat_id' => $typeId,
                        ],
                        [
                            'partner_id' => $partnerId,
                            'vehicle_id' => $vehicleId,
                            'type_contrat_label' => $typeRow['type_contrat_label'] ?? $type['label'],
                            'is_enabled' => $this->toBool($typeRow['is_enabled'] ?? false),
                            'grace_days' => array_key_exists('grace_days', $typeRow)
                                ? (int) $typeRow['grace_days']
                                : (int) ($row['grace_days'] ?? 0),
                            'cutoff_time' => ! empty($typeRow['cutoff_time'])
                                ? $typeRow['cutoff_time']
                                : (! empty($row['cutoff_time']) ? $row['cutoff_time'] : null),
                            'only_when_stopped' => array_key_exists('only_when_stopped', $typeRow)
                                ? $this->toBool($typeRow['only_when_stopped'])
                                : $this->toBool($row['only_when_stopped'] ?? true),
                            'notify_before_cutoff' => array_key_exists('notify_before_cutoff', $typeRow)
                                ? $this->toBool($typeRow['notify_before_cutoff'])
                                : $this->toBool($row['notify_before_cutoff'] ?? false),
                        ]
                    );
                }
            }
        });
    }

    /**
     * Sauvegarde les règles de coupure par type de contrat pour un seul véhicule.
     * Utilisée par la page contrats.
     */
    public function saveContractTypePolicyForVehicle(User $user, int $vehicleId, array $payload): LeaseCutoffRule
    {
        $partnerId = $this->resolvePartnerId($user);
        $actorId = (int) $user->id;

        return DB::transaction(function () use ($partnerId, $actorId, $vehicleId, $payload) {
            $rule = LeaseCutoffRule::firstOrNew([
                'partner_id' => $partnerId,
                'vehicle_id' => $vehicleId,
            ]);

            $isNew = ! $rule->exists;

            $rule->is_enabled = $this->toBool($payload['is_enabled'] ?? false);
            $rule->cutoff_time = ! empty($payload['cutoff_time']) ? $payload['cutoff_time'] : '12:00';
            $rule->timezone = $payload['timezone'] ?? 'Africa/Douala';
            $rule->grace_days = (int) ($payload['grace_days'] ?? 0);
            $rule->only_when_stopped = $this->toBool($payload['only_when_stopped'] ?? true);
            $rule->notify_before_cutoff = $this->toBool($payload['notify_before_cutoff'] ?? false);

            if ($isNew) {
                $rule->created_by = $actorId;
            }

            $rule->updated_by = $actorId;
            $rule->save();

            foreach (($payload['contract_types'] ?? []) as $typeRow) {
                $typeId = (int) ($typeRow['type_contrat_id'] ?? $typeRow['type_contrat'] ?? 0);

                if ($typeId <= 0) {
                    continue;
                }

                LeaseCutoffRuleContractType::updateOrCreate(
                    [
                        'rule_id' => $rule->id,
                        'type_contrat_id' => $typeId,
                    ],
                    [
                        'partner_id' => $partnerId,
                        'vehicle_id' => $vehicleId,
                        'type_contrat_label' => $typeRow['type_contrat_label'] ?? $typeRow['label'] ?? null,
                        'is_enabled' => $this->toBool($typeRow['is_enabled'] ?? $typeRow['enabled'] ?? false),
                        'grace_days' => array_key_exists('grace_days', $typeRow) ? (int) $typeRow['grace_days'] : (int) ($payload['grace_days'] ?? 0),
                        'cutoff_time' => ! empty($typeRow['cutoff_time']) ? $typeRow['cutoff_time'] : ($payload['cutoff_time'] ?? null),
                        'only_when_stopped' => array_key_exists('only_when_stopped', $typeRow) ? $this->toBool($typeRow['only_when_stopped']) : $this->toBool($payload['only_when_stopped'] ?? true),
                        'notify_before_cutoff' => array_key_exists('notify_before_cutoff', $typeRow) ? $this->toBool($typeRow['notify_before_cutoff']) : $this->toBool($payload['notify_before_cutoff'] ?? false),
                    ]
                );
            }

            return $rule->fresh('contractTypeRules');
        });
    }

    /**
     * Retourne les règles sous forme de map véhicule => politique.
     * Utilisé par la console contrats pour afficher les règles du véhicule lié.
     */
    public function getPolicyMapForVehicles(User $user, array $vehicleIds, array $contractTypes): array
    {
        $partnerId = $this->resolvePartnerId($user);

        $vehicleIds = collect($vehicleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            return [];
        }

        $types = $this->normalizeContractTypes($contractTypes);

        $rulesByVehicle = LeaseCutoffRule::query()
            ->with('contractTypeRules')
            ->where('partner_id', $partnerId)
            ->whereIn('vehicle_id', $vehicleIds)
            ->get()
            ->keyBy('vehicle_id');

        $result = [];

        foreach ($vehicleIds as $vehicleId) {
            /** @var LeaseCutoffRule|null $rule */
            $rule = $rulesByVehicle->get($vehicleId);
            $childrenByType = $rule ? $rule->contractTypeRules->keyBy('type_contrat_id') : collect();

            $result[$vehicleId] = [
                'enabled' => (bool) ($rule?->is_enabled ?? false),
                'grace_days' => (int) ($rule?->grace_days ?? 0),
                'cutoff_time' => $rule?->cutoff_time ? substr((string) $rule->cutoff_time, 0, 5) : '12:00',
                'only_when_stopped' => (bool) ($rule?->only_when_stopped ?? true),
                'notify_before_cutoff' => (bool) ($rule?->notify_before_cutoff ?? false),
                'contract_types' => $types->map(function (array $type) use ($childrenByType, $rule) {
                    $child = $childrenByType->get((int) $type['id']);

                    return [
                        'type_contrat' => (int) $type['id'],
                        'label' => $child?->type_contrat_label ?: $type['label'],
                        'enabled' => (bool) ($child?->is_enabled ?? false),
                        'grace_days' => $child && $child->grace_days !== null
                            ? (int) $child->grace_days
                            : (int) ($rule?->grace_days ?? 0),
                        'cutoff_time' => $child && $child->cutoff_time
                            ? substr((string) $child->cutoff_time, 0, 5)
                            : ($rule?->cutoff_time ? substr((string) $rule->cutoff_time, 0, 5) : '12:00'),
                    ];
                })->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * Applique une politique identique à plusieurs véhicules.
     */
    public function saveBulkContractTypePolicies(User $user, array $vehicleIds, array $payload): void
    {
        foreach ($vehicleIds as $vehicleId) {
            $vehicleId = (int) $vehicleId;

            if ($vehicleId <= 0) {
                continue;
            }

            $this->saveContractTypePolicyForVehicle($user, $vehicleId, $payload);
        }
    }

    /**
     * Après création d’un nouveau type côté recouvrement, crée des règles OFF
     * pour les véhicules existants du partenaire afin que la matrice soit stable.
     */
    public function initializeContractTypeForPartner(User $user, array $contractType, bool $enabledByDefault = false): void
    {
        $partnerId = $this->resolvePartnerId($user);
        $actorId = (int) $user->id;
        $typeId = (int) ($contractType['id'] ?? $contractType['type_contrat_id'] ?? 0);
        $label = (string) ($contractType['label'] ?? $contractType['libelle'] ?? $contractType['nom'] ?? ('Type #' . $typeId));

        if ($typeId <= 0) {
            return;
        }

        $vehicleIds = Voiture::query()
            ->join('association_user_voitures', 'association_user_voitures.voiture_id', '=', 'voitures.id')
            ->where('association_user_voitures.user_id', $partnerId)
            ->pluck('voitures.id')
            ->unique()
            ->values();

        DB::transaction(function () use ($vehicleIds, $partnerId, $actorId, $typeId, $label, $enabledByDefault) {
            foreach ($vehicleIds as $vehicleId) {
                $rule = LeaseCutoffRule::firstOrCreate(
                    [
                        'partner_id' => $partnerId,
                        'vehicle_id' => (int) $vehicleId,
                    ],
                    [
                        'is_enabled' => false,
                        'cutoff_time' => null,
                        'timezone' => 'Africa/Douala',
                        'grace_days' => 0,
                        'only_when_stopped' => true,
                        'notify_before_cutoff' => false,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]
                );

                LeaseCutoffRuleContractType::updateOrCreate(
                    [
                        'rule_id' => $rule->id,
                        'type_contrat_id' => $typeId,
                    ],
                    [
                        'partner_id' => $partnerId,
                        'vehicle_id' => (int) $vehicleId,
                        'type_contrat_label' => $label,
                        'is_enabled' => $enabledByDefault,
                        'grace_days' => $rule->grace_days,
                        'cutoff_time' => $rule->cutoff_time,
                        'only_when_stopped' => $rule->only_when_stopped,
                        'notify_before_cutoff' => $rule->notify_before_cutoff,
                    ]
                );
            }
        });

        Log::info('[LEASE_CUTOFF_TYPE_INITIALIZED_FOR_PARTNER]', [
            'partner_id' => $partnerId,
            'type_contrat_id' => $typeId,
            'type_contrat_label' => $label,
            'vehicles_count' => $vehicleIds->count(),
            'enabled_by_default' => $enabledByDefault,
        ]);
    }

    private function normalizeContractTypes(array $contractTypes): Collection
    {
        return collect($contractTypes)
            ->filter(fn ($row) => is_array($row))
            ->map(function (array $row) {
                $id = (int) ($row['id'] ?? $row['type_contrat_id'] ?? $row['type_contrat'] ?? 0);

                return [
                    'id' => $id,
                    'label' => (string) ($row['label'] ?? $row['libelle'] ?? $row['nom'] ?? $row['name'] ?? ('Type #' . $id)),
                    'code' => $row['code'] ?? null,
                    'is_main' => $this->toBool($row['is_main'] ?? $row['est_principal'] ?? false),
                ];
            })
            ->filter(fn (array $row) => $row['id'] > 0)
            ->values();
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
