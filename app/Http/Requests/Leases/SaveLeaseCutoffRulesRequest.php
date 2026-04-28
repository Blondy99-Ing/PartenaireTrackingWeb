<?php

namespace App\Http\Requests\Leases;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    public function messages(): array
    {
        return [
            'rules.required' => 'Aucune règle reçue.',
            'rules.array' => 'Le format des règles est invalide.',
            'rules.*.vehicle_id.required' => 'Le véhicule est obligatoire.',
            'rules.*.vehicle_id.exists' => 'Un véhicule transmis est invalide.',
            'rules.*.cutoff_time.date_format' => 'Le format de l’heure doit être HH:MM.',
        ];
    }

    /**
     * Normalise les cases à cocher absentes.
     */
    protected function prepareForValidation(): void
    {
        $rules = collect($this->input('rules', []))
            ->map(function ($row) {
                $row['is_enabled'] = filter_var($row['is_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                return $row;
            })
            ->values()
            ->all();

        $this->merge([
            'rules' => $rules,
        ]);
    }
}