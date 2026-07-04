<?php

namespace App\Services\Leases;

use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffContractRule;
use App\Models\LeaseCutoffDefaultRule;
use App\Models\User;

class LeaseContractCutoffRuleApplicationService
{
    private const DEFAULT_ACTIVE_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
    ];

    private const ALLOWED_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function applyDefaultRule(User $actor, LeaseContractLink $contractLink): ?LeaseCutoffContractRule
    {
        $partnerId = $this->resolvePartnerId($actor);

        if (empty($contractLink->type_contrat_id)) {
            return null;
        }

        $defaultRule = LeaseCutoffDefaultRule::query()
            ->where('partner_id', $partnerId)
            ->where('type_contrat_id', $contractLink->type_contrat_id)
            ->first();

        if (! $defaultRule || ! $defaultRule->is_enabled) {
            return null;
        }

        return LeaseCutoffContractRule::query()->updateOrCreate(
            [
                'partner_id' => $partnerId,
                'contract_link_id' => $contractLink->id,
            ],
            [
                'vehicle_id' => $contractLink->vehicle_id,
                'driver_id' => $contractLink->driver_id,
                'source_contract_id' => $contractLink->source_contract_id,
                'source_parent_contract_id' => $contractLink->source_parent_contract_id,
                'contract_kind' => $contractLink->contract_kind,
                'type_contrat_id' => $contractLink->type_contrat_id,
                'type_contrat_label' => $contractLink->type_contrat_label ?: $defaultRule->type_contrat_label,
                'is_enabled' => true,
                'cutoff_time' => $defaultRule->cutoff_time,
                'timezone' => $defaultRule->timezone ?: 'Africa/Douala',
                'grace_days' => (int) ($defaultRule->grace_days ?? 0),
                'active_days' => $this->normalizeActiveDays($defaultRule->active_days ?? self::DEFAULT_ACTIVE_DAYS),
                'only_when_stopped' => (bool) $defaultRule->only_when_stopped,
                'notify_before_cutoff' => (bool) $defaultRule->notify_before_cutoff,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );
    }

    public function applyCustomRule(User $actor, LeaseContractLink $contractLink, array $payload): LeaseCutoffContractRule
    {
        $partnerId = $this->resolvePartnerId($actor);

        return LeaseCutoffContractRule::query()->updateOrCreate(
            [
                'partner_id' => $partnerId,
                'contract_link_id' => $contractLink->id,
            ],
            [
                'vehicle_id' => $contractLink->vehicle_id,
                'driver_id' => $contractLink->driver_id,
                'source_contract_id' => $contractLink->source_contract_id,
                'source_parent_contract_id' => $contractLink->source_parent_contract_id,
                'contract_kind' => $contractLink->contract_kind,
                'type_contrat_id' => $contractLink->type_contrat_id,
                'type_contrat_label' => $contractLink->type_contrat_label,
                'is_enabled' => (bool) ($payload['custom_rule_is_enabled'] ?? true),
                'cutoff_time' => $payload['custom_rule_cutoff_time'] ?? null,
                'timezone' => $payload['custom_rule_timezone'] ?? 'Africa/Douala',
                'grace_days' => (int) ($payload['custom_rule_grace_days'] ?? 0),
                'active_days' => $this->normalizeActiveDays($payload['custom_rule_active_days'] ?? self::DEFAULT_ACTIVE_DAYS),
                'only_when_stopped' => (bool) ($payload['custom_rule_only_when_stopped'] ?? true),
                'notify_before_cutoff' => (bool) ($payload['custom_rule_notify_before_cutoff'] ?? false),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );
    }

    private function normalizeActiveDays(array $days): array
    {
        return collect($days)
            ->map(fn ($day) => strtolower((string) $day))
            ->filter(fn ($day) => in_array($day, self::ALLOWED_DAYS, true))
            ->unique()
            ->values()
            ->all();
    }

    private function resolvePartnerId(User $actor): int
    {
        return (int) ($actor->partner_id ?: $actor->id);
    }
}
