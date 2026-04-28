<?php

namespace App\Http\Requests\Leases;

use Illuminate\Foundation\Http\FormRequest;

class LeaseCutoffHistoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'max:50'],
            'period' => ['nullable', 'string', 'in:today,yesterday,this_week,this_month,this_year,specific_date,range'],
            'specific_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'La date de fin doit être supérieure ou égale à la date de début.',
            'period.in' => 'Le filtre de période demandé est invalide.',
        ];
    }
}