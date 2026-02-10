<?php

namespace App\Http\Requests\Api\V1\Partner;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::e164($this->input('phone')),
            'email' => $this->filled('email') ? strtolower(trim($this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+237[6-9]\d{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],

            'password' => ['required', 'string', 'min:6'],
            'photo' => ['nullable', 'image', 'max:4096'],

            'ville' => ['nullable', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Numéro invalide. Format attendu : +2376XXXXXXXX',
        ];
    }
}
