<?php

namespace App\Http\Requests\Leases;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation du paramétrage de coupure par contrats/sous-contrats réels.
 *
 * Une ligne de formulaire représente un contrat principal et ses sous-contrats
 * réellement associés. Chaque carte interne envoie un contract_link_id réel.
 */
class SaveLeaseCutoffRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.main_contract_link_id' => ['nullable', 'integer', 'exists:lease_contract_links,id'],
            'rules.*.vehicle_id' => ['nullable', 'integer', 'exists:voitures,id'],

            'rules.*.contract_rules' => ['required', 'array', 'min:1'],
            'rules.*.contract_rules.*.contract_link_id' => ['required', 'integer', 'exists:lease_contract_links,id'],
            'rules.*.contract_rules.*.is_enabled' => ['nullable', 'boolean'],
            'rules.*.contract_rules.*.cutoff_time' => ['nullable', 'date_format:H:i'],
            'rules.*.contract_rules.*.timezone' => ['nullable', 'string', 'max:64'],
            'rules.*.contract_rules.*.grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'rules.*.contract_rules.*.only_when_stopped' => ['nullable', 'boolean'],
            'rules.*.contract_rules.*.notify_before_cutoff' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rules.required' => 'Aucune règle reçue.',
            'rules.array' => 'Le format des règles est invalide.',
            'rules.*.contract_rules.required' => 'Chaque contrat doit contenir au moins une règle de contrat ou sous-contrat réel.',
            'rules.*.contract_rules.*.contract_link_id.required' => 'Une règle transmise ne contient pas son contrat/sous-contrat réel.',
            'rules.*.contract_rules.*.contract_link_id.exists' => 'Un contrat ou sous-contrat transmis est introuvable.',
            'rules.*.contract_rules.*.cutoff_time.date_format' => 'Le format de l’heure de coupure doit être HH:MM.',
        ];
    }

    /**
     * HTML n'envoie pas les checkboxes décochées. On les force donc à false
     * pour que la désactivation en masse soit bien enregistrée.
     */
    protected function prepareForValidation(): void
    {
        $rules = collect($this->input('rules', []))
            ->map(function ($row) {
                $row['contract_rules'] = collect($row['contract_rules'] ?? [])
                    ->map(function ($ruleRow) {
                        $ruleRow['is_enabled'] = filter_var($ruleRow['is_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $ruleRow['only_when_stopped'] = filter_var($ruleRow['only_when_stopped'] ?? true, FILTER_VALIDATE_BOOLEAN);
                        $ruleRow['notify_before_cutoff'] = filter_var($ruleRow['notify_before_cutoff'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $ruleRow['timezone'] = $ruleRow['timezone'] ?? 'Africa/Douala';
                        $ruleRow['grace_days'] = $ruleRow['grace_days'] ?? 0;

                        return $ruleRow;
                    })
                    ->values()
                    ->all();

                return $row;
            })
            ->values()
            ->all();

        $this->merge(['rules' => $rules]);
    }
}
