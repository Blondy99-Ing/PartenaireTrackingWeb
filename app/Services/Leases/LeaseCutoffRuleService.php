<?php

namespace App\Services\Leases;

use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffContractRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service de paramétrage des règles de coupure lease.
 *
 * Règle métier retenue :
 * - le paramétrage peut être fait en masse ;
 * - mais chaque règle enregistrée reste attachée à un contrat/sous-contrat réel ;
 * - aucun sous-contrat absent du contrat n'est affiché ni créé ;
 * - pas de règle active sur le contract_link exact => aucune coupure.
 *
 * Règle d'affichage :
 * - aucun ID technique ne doit être affiché dans l'interface ;
 * - si le vrai libellé métier est absent, on affiche "Contrat principal" ou "Sous-contrat" ;
 * - on ne doit jamais afficher "Type #4", "Contrat #40", "#7", etc.
 */
class LeaseCutoffRuleService
{
    public function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    /**
     * Retourne les contrats principaux du partenaire avec leurs sous-contrats réels.
     *
     * La méthode garde son ancien nom pour ne pas casser le contrôleur, mais elle ne
     * retourne plus une matrice "véhicule + tous les types". Elle retourne une ligne
     * par contrat principal réel, contenant seulement le contrat et les sous-contrats
     * réellement associés dans lease_contract_links.
     */
    public function getPartnerVehiclesWithRules(User $user, array $contractTypes = []): Collection
    {
        return $this->getPartnerContractRowsWithRules($user);
    }

    public function getPartnerContractRowsWithRules(User $user): Collection
    {
        $partnerId = $this->resolvePartnerId($user);

        $links = LeaseContractLink::query()
            ->with(['vehicle', 'driver', 'cutoffRule'])
            ->where('partner_id', $partnerId)
            ->where('status', '!=', 'DELETED')
            ->orderBy('vehicle_id')
            ->orderByRaw("CASE WHEN contract_kind = 'MAIN' THEN 0 ELSE 1 END")
            ->orderBy('source_parent_contract_id')
            ->orderBy('source_contract_id')
            ->get();

        $mainLinks = $links
            ->where('contract_kind', LeaseContractLink::KIND_MAIN)
            ->values();

        $subsByParent = $links
            ->where('contract_kind', LeaseContractLink::KIND_SUB)
            ->groupBy('source_parent_contract_id');

        return $mainLinks->map(function (LeaseContractLink $main) use ($subsByParent) {
            $items = collect([$main])
                ->merge($subsByParent->get($main->source_contract_id, collect()))
                ->values();

            $contractRules = $items
                ->map(fn (LeaseContractLink $link) => $this->formatContractRuleRow($link))
                ->values();

            $enabledCount = $contractRules->where('is_enabled', true)->count();

            $missingTimeCount = $contractRules
                ->filter(fn (array $rule) => ! empty($rule['is_enabled']) && empty($rule['cutoff_time']))
                ->count();

            return [
                'main_contract_link_id' => (int) $main->id,

                /**
                 * Ces IDs restent utiles pour les actions serveur.
                 * Ils ne doivent simplement pas être affichés comme texte utilisateur dans la vue.
                 */
                'main_source_contract_id' => (int) $main->source_contract_id,
                'vehicle_id' => (int) $main->vehicle_id,
                'driver_id' => $main->driver_id ? (int) $main->driver_id : null,

                'driver_name' => $this->formatUserName($main->driver),
                'immatriculation' => $main->vehicle?->immatriculation ?: $main->immatriculation,
                'marque' => $main->vehicle?->marque,
                'model' => $main->vehicle?->model,
                'mac_id_gps' => $main->vehicle?->mac_id_gps,

                /**
                 * Libellé propre, sans Type #id.
                 */
                'main_type_label' => $this->safeContractTypeLabel($main),

                'contract_rules' => $contractRules->all(),
                'enabled_contract_rules_count' => $enabledCount,
                'missing_time_contract_rules_count' => $missingTimeCount,
                'has_any_rule' => $contractRules->contains(fn (array $row) => ! empty($row['rule_id'])),
                'is_enabled' => $enabledCount > 0,
                'cutoff_time' => $contractRules->firstWhere('is_enabled', true)['cutoff_time'] ?? null,
            ];
        })->values();
    }

    /**
     * Sauvegarde en masse des règles.
     *
     * Format attendu : rules[*][contract_rules][*][contract_link_id].
     * Seules les lignes présentes dans lease_contract_links pour ce partenaire sont
     * acceptées. Cela empêche de créer une règle pour Parapluie si le contrat n'a
     * réellement qu'un sous-contrat Téléphone.
     */
    public function saveRules(User $user, array $rulesPayload, array $contractTypes = []): void
    {
        $partnerId = $this->resolvePartnerId($user);
        $actorId = (int) $user->id;

        $submitted = collect($rulesPayload)
            ->flatMap(fn ($row) => collect($row['contract_rules'] ?? []))
            ->filter(fn ($row) => is_array($row))
            ->values();

        if ($submitted->isEmpty()) {
            return;
        }

        $linkIds = $submitted
            ->pluck('contract_link_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $links = LeaseContractLink::query()
            ->with(['cutoffRule'])
            ->where('partner_id', $partnerId)
            ->whereIn('id', $linkIds)
            ->where('status', '!=', 'DELETED')
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($submitted, $links, $partnerId, $actorId) {
            foreach ($submitted as $payload) {
                $linkId = (int) ($payload['contract_link_id'] ?? 0);

                /** @var LeaseContractLink|null $link */
                $link = $links->get($linkId);

                if (! $link) {
                    Log::warning('[LEASE_CUTOFF_RULE_SAVE_SKIPPED_INVALID_LINK]', [
                        'partner_id' => $partnerId,
                        'contract_link_id' => $linkId,
                    ]);
                    continue;
                }

                $rule = LeaseCutoffContractRule::firstOrNew([
                    'partner_id' => $partnerId,
                    'contract_link_id' => $link->id,
                ]);

                if (! $rule->exists) {
                    $rule->created_by = $actorId;
                }

                /**
                 * On sauvegarde un libellé propre pour éviter de réinjecter Type #id
                 * dans les prochaines vues ou historiques.
                 */
                $safeTypeLabel = $this->safeContractTypeLabel($link);

                /**
                 * active_days pilote aussi le calcul des jours de retard côté dashboard.
                 * Si cette page ne l'envoie pas (ou envoie une sélection vide), on
                 * préserve la valeur déjà enregistrée au lieu de l'écraser à Lun-Sam :
                 * un contrat paramétré avec un rythme personnalisé sur la page contrat
                 * ne doit pas perdre ce rythme simplement parce qu'on a coché "Activer" ici.
                 */
                $submittedActiveDays = $this->normalizeActiveDays($payload['active_days'] ?? []);
                $activeDays = ! empty($submittedActiveDays)
                    ? $submittedActiveDays
                    : (! empty($rule->active_days) ? $rule->active_days : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);

                $rule->fill([
                    'vehicle_id' => $link->vehicle_id,
                    'driver_id' => $link->driver_id,
                    'source_contract_id' => $link->source_contract_id,
                    'source_parent_contract_id' => $link->source_parent_contract_id,
                    'contract_kind' => $link->contract_kind ?: LeaseContractLink::KIND_MAIN,
                    'type_contrat_id' => $link->type_contrat_id,
                    'type_contrat_label' => $safeTypeLabel,
                    'is_enabled' => $this->toBool($payload['is_enabled'] ?? false),
                    'cutoff_time' => ! empty($payload['cutoff_time']) ? $payload['cutoff_time'] : null,
                    'timezone' => $payload['timezone'] ?? 'Africa/Douala',
                    'grace_days' => (int) ($payload['grace_days'] ?? 0),
                    'active_days' => $activeDays,
                    'only_when_stopped' => $this->toBool($payload['only_when_stopped'] ?? true),
                    'notify_before_cutoff' => $this->toBool($payload['notify_before_cutoff'] ?? false),
                    'updated_by' => $actorId,
                ]);

                $rule->save();
            }
        });
    }

    /**
     * Retourne une map contract_link_id => règle, utile pour les vues contrats.
     */
    public function getPolicyMapForContractLinks(User $user, array $contractLinkIds): array
    {
        $partnerId = $this->resolvePartnerId($user);

        $ids = collect($contractLinkIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return LeaseCutoffContractRule::query()
            ->where('partner_id', $partnerId)
            ->whereIn('contract_link_id', $ids)
            ->get()
            ->mapWithKeys(fn (LeaseCutoffContractRule $rule) => [
                $rule->contract_link_id => [
                    'id' => (int) $rule->id,
                    'enabled' => (bool) $rule->is_enabled,
                    'cutoff_time' => $rule->effectiveCutoffTime(),
                    'timezone' => $rule->timezone ?: 'Africa/Douala',
                    'grace_days' => (int) $rule->grace_days,
                    'only_when_stopped' => (bool) $rule->only_when_stopped,
                    'notify_before_cutoff' => (bool) $rule->notify_before_cutoff,
                ],
            ])
            ->all();
    }

    /**
     * Compatibilité avec l'ancien code : ne doit plus servir à décider la coupure.
     * On retourne une map vide afin d'éviter toute logique "véhicule + type général".
     */
    public function getPolicyMapForVehicles(User $user, array $vehicleIds, array $contractTypes): array
    {
        return [];
    }

    /**
     * Compatibilité : l'ancien bulk par véhicule est volontairement neutralisé.
     * Le nouveau bulk passe par saveRules() et contract_link_id.
     */
    public function saveBulkContractTypePolicies(User $user, array $vehicleIds, array $payload): void
    {
        Log::warning('[LEASE_CUTOFF_LEGACY_BULK_IGNORED]', [
            'user_id' => $user->id,
            'vehicle_ids' => $vehicleIds,
            'message' => 'Le bulk par véhicule est désactivé. Utiliser contract_link_id.',
        ]);
    }

    public function saveContractTypePolicyForVehicle(User $user, int $vehicleId, array $payload): LeaseCutoffContractRule
    {
        throw new \LogicException('Le paramétrage par véhicule + type général est désactivé. Utiliser le paramétrage par contrat/sous-contrat réel.');
    }

    /**
     * Après création d'un type côté Recouvrement, on ne crée aucune règle abstraite.
     * La règle apparaîtra seulement quand un contrat/sous-contrat réel de ce type
     * sera associé à un chauffeur et synchronisé dans lease_contract_links.
     */
    public function initializeContractTypeForPartner(User $user, array $contractType, bool $enabledByDefault = false): void
    {
        Log::info('[LEASE_CUTOFF_TYPE_CREATED_NO_ABSTRACT_RULE]', [
            'partner_id' => $this->resolvePartnerId($user),
            'type_contrat' => $contractType,
            'message' => 'Aucune règle de coupure créée : les règles sont spécifiques aux contrats réels.',
        ]);
    }

    public function findActiveRuleForContractLink(LeaseContractLink|int|null $link): ?LeaseCutoffContractRule
    {
        $linkId = $link instanceof LeaseContractLink ? (int) $link->id : (int) $link;

        if ($linkId <= 0) {
            return null;
        }

        return LeaseCutoffContractRule::query()
            ->where('contract_link_id', $linkId)
            ->where('is_enabled', true)
            ->first();
    }

    private function formatContractRuleRow(LeaseContractLink $link): array
    {
        /** @var LeaseCutoffContractRule|null $rule */
        $rule = $link->cutoffRule;

        $isMain = $this->isMainContract($link);
        $safeTypeLabel = $this->safeContractTypeLabel($link);

        return [
            'rule_id' => $rule?->id,

            /**
             * Ces valeurs peuvent rester dans le payload pour les actions serveur,
             * mais la vue ne doit pas les afficher comme libellés.
             */
            'contract_link_id' => (int) $link->id,
            'vehicle_id' => (int) $link->vehicle_id,
            'driver_id' => $link->driver_id ? (int) $link->driver_id : null,
            'source_contract_id' => (int) $link->source_contract_id,
            'source_parent_contract_id' => $link->source_parent_contract_id ? (int) $link->source_parent_contract_id : null,

            'contract_kind' => $link->contract_kind ?: LeaseContractLink::KIND_MAIN,
            'is_main' => $isMain,
            'kind_label' => $isMain ? 'Contrat principal' : 'Sous-contrat',

            'type_contrat_id' => $link->type_contrat_id ? (int) $link->type_contrat_id : null,

            /**
             * Libellé sécurisé pour l'interface.
             */
            'type_contrat_label' => $safeTypeLabel,

            'is_enabled' => (bool) ($rule?->is_enabled ?? false),
            'cutoff_time' => $rule?->effectiveCutoffTime(),
            'timezone' => $rule?->timezone ?: 'Africa/Douala',
            'grace_days' => (int) ($rule?->grace_days ?? 0),
            'active_days' => ! empty($rule?->active_days) ? $rule->active_days : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'only_when_stopped' => (bool) ($rule?->only_when_stopped ?? true),
            'notify_before_cutoff' => (bool) ($rule?->notify_before_cutoff ?? false),
            'status' => $link->status,
            'last_payment_status' => $link->last_payment_status,
        ];
    }

    private function formatUserName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $user->prenom ?? null,
            $user->nom ?? null,
        ])));

        /**
         * On évite "Utilisateur #id" dans la vue.
         */
        return $name ?: ($user->email ?? 'Chauffeur non renseigné');
    }

    private function isMainContract(LeaseContractLink $link): bool
    {
        return ($link->contract_kind ?: LeaseContractLink::KIND_MAIN) === LeaseContractLink::KIND_MAIN;
    }

    /**
     * Retourne un libellé propre pour l'interface.
     *
     * Priorité :
     * 1. type_contrat_label propre venant du lien contrat ;
     * 2. displayTypeLabel() si propre ;
     * 3. libellé de la règle si propre ;
     * 4. fallback métier sans ID : Contrat principal / Sous-contrat.
     */
    private function safeContractTypeLabel(LeaseContractLink $link): string
    {
        $isMain = $this->isMainContract($link);

        $candidates = [
            $link->type_contrat_label,
            method_exists($link, 'displayTypeLabel') ? $link->displayTypeLabel() : null,
            $link->cutoffRule?->type_contrat_label,
        ];

        foreach ($candidates as $candidate) {
            $label = trim((string) $candidate);

            if (! $this->labelLooksTechnical($label)) {
                return $label;
            }
        }

        return $isMain ? 'Contrat principal' : 'Sous-contrat';
    }

    private function labelLooksTechnical(?string $label): bool
    {
        $label = trim((string) $label);

        if ($label === '') {
            return true;
        }

        return (bool) preg_match('/^(type|contrat|sous-contrat)\s*#?\d+$/i', $label)
            || (bool) preg_match('/^#\d+$/', $label)
            || (bool) preg_match('/^\d+$/', $label)
            || (bool) preg_match('/^CTR[-_ ]?\d+$/i', $label)
            || (bool) preg_match('/^parent\s*#?\d+$/i', $label);
    }


    private function normalizeActiveDays(array $days): array
    {
        $allowed = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

        return collect($days)
            ->map(fn ($day) => strtolower((string) $day))
            ->filter(fn ($day) => in_array($day, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}