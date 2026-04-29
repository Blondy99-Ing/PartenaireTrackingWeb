<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Keycloak\KeycloakAuthService;
use App\Services\Keycloak\KeycloakTokenValidator;
use App\Services\Keycloak\KeycloakUserProvisioningService;
use App\Services\Keycloak\KeycloakUserSyncService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
            /**
             * Ton formulaire peut envoyer login ou email.
             * On supporte les deux pour ne pas casser l’existant.
             */
            'login' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable'],
        ];
    }

    /**
     * Authentification :
     *
     * 1. Tentative Keycloak normale.
     * 2. Si Keycloak refuse :
     *    - recherche user local
     *    - Hash::check() sur le password local
     *    - si OK : création/rattachement Keycloak avec ce même mot de passe
     *    - deuxième tentative Keycloak
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = $this->loginValue();
        $password = (string) $this->input('password');

        /** @var KeycloakAuthService $keycloak */
        $keycloak = app(KeycloakAuthService::class);

        /** @var KeycloakTokenValidator $validator */
        $validator = app(KeycloakTokenValidator::class);

        /** @var KeycloakUserSyncService $sync */
        $sync = app(KeycloakUserSyncService::class);

        /** @var KeycloakUserProvisioningService $provisioner */
        $provisioner = app(KeycloakUserProvisioningService::class);

        $localUser = $this->findLocalUserForLogin($login);

        Log::info('[LOGIN_ATTEMPT_START]', [
            'login' => $login,
            'local_user_id' => $localUser?->id,
            'has_keycloak_id' => ! empty($localUser?->keycloak_id),
        ]);

        try {
            /**
             * 1. Connexion normale via Keycloak.
             */
            $tokenResponse = $keycloak->login($login, $password);

            Log::info('[LOGIN_KEYCLOAK_SUCCESS_FIRST_TRY]', [
                'login' => $login,
                'local_user_id' => $localUser?->id,
            ]);
        } catch (\Throwable $keycloakException) {
            /**
             * 2. Keycloak refuse.
             * On tente le fallback legacy local.
             */
            Log::warning('[LOGIN_KEYCLOAK_FAILED_TRY_LOCAL_FALLBACK]', [
                'login' => $login,
                'local_user_id' => $localUser?->id,
                'error' => $keycloakException->getMessage(),
            ]);

            if (! $localUser) {
                RateLimiter::hit($this->throttleKey());

                Log::warning('[LOGIN_LOCAL_FALLBACK_NO_USER]', [
                    'login' => $login,
                ]);

                throw ValidationException::withMessages([
                    $this->loginFieldName() => __('auth.failed'),
                ]);
            }

            if (! Hash::check($password, (string) $localUser->password)) {
                RateLimiter::hit($this->throttleKey());

                Log::warning('[LOGIN_LOCAL_PASSWORD_MISMATCH]', [
                    'login' => $login,
                    'local_user_id' => $localUser->id,
                ]);

                throw ValidationException::withMessages([
                    $this->loginFieldName() => __('auth.failed'),
                ]);
            }

            /**
             * 3. Le mot de passe local est correct.
             * On crée/rattache le user Keycloak et on définit ce même password.
             */
            Log::info('[LOGIN_LOCAL_PASSWORD_MATCH_PROVISION_KEYCLOAK]', [
                'login' => $login,
                'local_user_id' => $localUser->id,
                'partner_id' => $localUser->partner_id,
                'business_type' => empty($localUser->partner_id) ? 'PARTNER' : 'DRIVER',
            ]);

            try {
                $provisionResult = $provisioner->provisionUserWithPassword(
                    user: $localUser,
                    plainPassword: $password
                );

                Log::info('[LOGIN_LOCAL_PROVISION_DONE]', [
                    'local_user_id' => $localUser->id,
                    'keycloak_id' => $provisionResult['keycloak_user_id'] ?? null,
                    'role' => $provisionResult['role_name'] ?? null,
                    'compte_id' => $provisionResult['compte_id'] ?? null,
                ]);
            } catch (\Throwable $provisionException) {
                RateLimiter::hit($this->throttleKey());

                Log::error('[LOGIN_LOCAL_PROVISION_FAILED]', [
                    'login' => $login,
                    'local_user_id' => $localUser->id,
                    'error' => $provisionException->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    $this->loginFieldName() => 'Mot de passe local correct, mais création du compte Keycloak impossible : '
                        . $provisionException->getMessage(),
                ]);
            }

            /**
             * 4. On relance le login Keycloak.
             */
            try {
                $keycloakUsername = $localUser->fresh()->keycloak_username ?: $login;

                $tokenResponse = $keycloak->login($keycloakUsername, $password);

                Log::info('[LOGIN_KEYCLOAK_SUCCESS_AFTER_LOCAL_PROVISION]', [
                    'login' => $login,
                    'keycloak_username' => $keycloakUsername,
                    'local_user_id' => $localUser->id,
                ]);
            } catch (\Throwable $secondException) {
                RateLimiter::hit($this->throttleKey());

                Log::error('[LOGIN_KEYCLOAK_FAILED_AFTER_LOCAL_PROVISION]', [
                    'login' => $login,
                    'local_user_id' => $localUser->id,
                    'error' => $secondException->getMessage(),
                ]);

                throw ValidationException::withMessages([
                    $this->loginFieldName() => 'Compte Keycloak créé, mais connexion impossible. Veuillez réessayer.',
                ]);
            }
        }

        /**
         * 5. Validation JWT + synchronisation locale.
         */
        try {
            $claims = $validator->validate($tokenResponse['access_token']);
            $user = $sync->syncFromClaims($claims, $localUser);
        } catch (\Throwable $e) {
            RateLimiter::hit($this->throttleKey());

            Log::error('[LOGIN_TOKEN_VALIDATE_OR_SYNC_FAILED]', [
                'login' => $login,
                'local_user_id' => $localUser?->id,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                $this->loginFieldName() => 'Connexion Keycloak réussie, mais synchronisation locale impossible.',
            ]);
        }

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

        RateLimiter::clear($this->throttleKey());

        Log::info('[LOGIN_DONE]', [
            'login' => $login,
            'user_id' => $user->id,
            'keycloak_id' => $user->keycloak_id ?? null,
        ]);
    }

    private function findLocalUserForLogin(string $login): ?User
    {
        return User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->orWhere('keycloak_username', $login)
            ->first();
    }

    private function loginValue(): string
    {
        return trim((string) ($this->input('login') ?: $this->input('email')));
    }

    private function loginFieldName(): string
    {
        return $this->has('login') ? 'login' : 'email';
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            $this->loginFieldName() => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->loginValue()) . '|' . $this->ip()
        );
    }
}