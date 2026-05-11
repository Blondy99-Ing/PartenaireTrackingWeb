<?php

namespace App\Http\Requests\Leases;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valide la matrice de règles de coupure lease.
 *
 * Le formulaire envoie une ligne par véhicule et, dans chaque ligne, une liste
 * de règles par type de contrat / sous-contrat recouvrement.
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
            'rules.*.vehicle_id' => ['required', 'integer', 'exists:voitures,id'],
            'rules.*.is_enabled' => ['nullable', 'boolean'],
            'rules.*.cutoff_time' => ['nullable', 'date_format:H:i'],
            'rules.*.timezone' => ['nullable', 'string', 'max:64'],
            'rules.*.grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'rules.*.only_when_stopped' => ['nullable', 'boolean'],
            'rules.*.notify_before_cutoff' => ['nullable', 'boolean'],

            'rules.*.contract_types' => ['nullable', 'array'],
            'rules.*.contract_types.*.type_contrat_id' => ['required_with:rules.*.contract_types', 'integer', 'min:1'],
            'rules.*.contract_types.*.type_contrat_label' => ['nullable', 'string', 'max:150'],
            'rules.*.contract_types.*.is_enabled' => ['nullable', 'boolean'],
            'rules.*.contract_types.*.grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'rules.*.contract_types.*.cutoff_time' => ['nullable', 'date_format:H:i'],
            'rules.*.contract_types.*.only_when_stopped' => ['nullable', 'boolean'],
            'rules.*.contract_types.*.notify_before_cutoff' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rules.required' => 'Aucune règle reçue.',
            'rules.array' => 'Le format des règles est invalide.',
            'rules.*.vehicle_id.required' => 'Le véhicule est obligatoire.',
            'rules.*.vehicle_id.exists' => 'Un véhicule transmis est invalide.',
            'rules.*.cutoff_time.date_format' => 'Le format de l’heure véhicule doit être HH:MM.',
            'rules.*.contract_types.*.type_contrat_id.required_with' => 'Chaque règle de type doit contenir le type de contrat.',
            'rules.*.contract_types.*.cutoff_time.date_format' => 'Le format de l’heure d’un type de contrat doit être HH:MM.',
        ];
    }

    /**
     * Normalise les cases à cocher absentes.
     * HTML n’envoie pas les checkboxes décochées ; on les force donc à false
     * pour que la désactivation soit bien enregistrée en base.
     */
    protected function prepareForValidation(): void
    {
        $rules = collect($this->input('rules', []))
            ->map(function ($row) {
                $row['is_enabled'] = filter_var($row['is_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $row['only_when_stopped'] = filter_var($row['only_when_stopped'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $row['notify_before_cutoff'] = filter_var($row['notify_before_cutoff'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $row['contract_types'] = collect($row['contract_types'] ?? [])
                    ->map(function ($typeRow) {
                        $typeRow['is_enabled'] = filter_var($typeRow['is_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $typeRow['only_when_stopped'] = filter_var($typeRow['only_when_stopped'] ?? true, FILTER_VALIDATE_BOOLEAN);
                        $typeRow['notify_before_cutoff'] = filter_var($typeRow['notify_before_cutoff'] ?? false, FILTER_VALIDATE_BOOLEAN);

                        return $typeRow;
                    })
                    ->values()
                    ->all();

                return $row;
            })
            ->values()
            ->all();

        $this->merge([
            'rules' => $rules,
        ]);
    }
}
