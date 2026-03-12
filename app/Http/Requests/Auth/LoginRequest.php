<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\LoginIdentifierResolver;
use App\Services\Auth\LoginRateLimiter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Le champ identifiant est obligatoire.',
            'login.string' => 'Le champ identifiant doit être une chaîne de caractères.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères.',
            'remember.boolean' => 'La valeur du champ "Se souvenir de moi" est invalide.',
        ];
    }

    public function authenticate(): void
    {
        /** @var LoginRateLimiter $rateLimiter */
        $rateLimiter = app(LoginRateLimiter::class);

        $rateLimiter->ensureIsNotRateLimited(
            $this->input('login'),
            $this->ip()
        );

        /** @var LoginIdentifierResolver $resolver */
        $resolver = app(LoginIdentifierResolver::class);

        $resolved = $resolver->resolveCredentials(
            $this->input('login'),
            $this->input('password')
        );

        if (
            empty($resolved['credentials']) ||
            ! Auth::attempt($resolved['credentials'], $this->boolean('remember'))
        ) {
            $rateLimiter->hit($this->input('login'), $this->ip());

            throw ValidationException::withMessages([
                'login' => ['Identifiant ou mot de passe incorrect.'],
            ]);
        }

        $user = $resolved['user'] ?? null;

        if ($user && isset($user->status) && $user->status !== 'active') {
            throw ValidationException::withMessages([
                'login' => ['Votre compte est inactif. Veuillez contacter l’administrateur.'],
            ]);
        }

        $rateLimiter->clear($this->input('login'), $this->ip());
    }
}