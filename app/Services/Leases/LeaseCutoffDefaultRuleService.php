<?php

namespace App\Services\Leases;

use App\Models\LeaseCutoffDefaultRule;
use App\Models\User;
use Illuminate\Support\Collection;

class LeaseCutoffDefaultRuleService
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

    public function getRulesForPartner(User $partner): Collection
    {
        return LeaseCutoffDefaultRule::query()
            ->where('partner_id', $partner->id)
            ->get()
            ->keyBy('type_contrat_id');
    }

    public function upsertRules(User $partner, array $rules, ?User $actor = null): void
    {
        foreach ($rules as $rule) {
            if (empty($rule['type_contrat_id'])) {
                continue;
            }

            $activeDays = $this->normalizeActiveDays($rule['active_days'] ?? []);

            LeaseCutoffDefaultRule::query()->updateOrCreate(
                [
                    'partner_id' => $partner->id,
                    'type_contrat_id' => $rule['type_contrat_id'],
                ],
                [
                    'type_contrat_label' => $rule['type_contrat_label'] ?? '',
                    'type_contrat_code' => $rule['type_contrat_code'] ?? null,
                    'is_enabled' => (bool) ($rule['is_enabled'] ?? false),
                    'cutoff_time' => $rule['cutoff_time'] ?: null,
                    'timezone' => 'Africa/Douala',
                    'grace_days' => (int) ($rule['grace_days'] ?? 0),
                    'active_days' => $activeDays,
                    'only_when_stopped' => (bool) ($rule['only_when_stopped'] ?? true),
                    'notify_before_cutoff' => (bool) ($rule['notify_before_cutoff'] ?? false),
                    'updated_by' => $actor?->id,
                    'created_by' => $actor?->id,
                ]
            );
        }
    }

    private function normalizeActiveDays(array $days): array
    {
        $days = array_map('strtolower', $days);

        return collect($days)
            ->filter(fn ($day) => in_array($day, self::ALLOWED_DAYS, true))
            ->unique()
            ->values()
            ->all();
    }

    public function defaultActiveDays(): array
    {
        return self::DEFAULT_ACTIVE_DAYS;
    }
}