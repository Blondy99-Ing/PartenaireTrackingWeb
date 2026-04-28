<?php

namespace App\Http\Requests\Auth;

use App\Services\Auth\LoginIdentifierResolver;
use App\Services\Auth\LoginRateLimiter;
use App\Services\Keycloak\KeycloakAuthService;
use App\Services\Keycloak\KeycloakTokenValidator;
use App\Services\Keycloak\KeycloakUserSyncService;
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
            'password.required' => 'Le mot de passe est obligatoire.',
        ];
    }

    public function authenticate(): void
    {
        $rateLimiter = app(LoginRateLimiter::class);

        $login = $this->input('login');
        $password = $this->input('password');

        $rateLimiter->ensureIsNotRateLimited($login, $this->ip());

        $resolver = app(LoginIdentifierResolver::class);
        $keycloak = app(KeycloakAuthService::class);
        $validator = app(KeycloakTokenValidator::class);
        $sync = app(KeycloakUserSyncService::class);

        $localUser = $resolver->resolveUser($login);
        $keycloakUsername = $resolver->resolveKeycloakUsername($login);

        try {
            $tokenResponse = $keycloak->login($keycloakUsername, $password);
            $claims = $validator->validate($tokenResponse['access_token']);
            $user = $sync->syncFromClaims($claims, $localUser);

           Auth::login($user, $this->boolean('remember'));

            $this->session()->regenerate();

            session([
                'keycloak_access_token' => $tokenResponse['access_token'] ?? null,
                'keycloak_refresh_token' => $tokenResponse['refresh_token'] ?? null,
                'keycloak_id_token' => $tokenResponse['id_token'] ?? null,
                'keycloak_expires_in' => $tokenResponse['expires_in'] ?? null,
                'keycloak_refresh_expires_in' => $tokenResponse['refresh_expires_in'] ?? null,
                'keycloak_session_state' => $tokenResponse['session_state'] ?? null,
                'keycloak_issued_at' => now()->timestamp,
            ]);
        } catch (ValidationException $e) {
            $rateLimiter->hit($login, $this->ip());
            throw $e;
        } 

        $rateLimiter->clear($login, $this->ip());
    }
}