<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
            // 💡 Peut être un email OU un numéro de téléphone
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Tentative d’authentification avec email OU phone.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login    = $this->input('login');
        $password = $this->input('password');

        // 🧠 On détecte automatiquement si c’est un email ou un téléphone
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        } else {
            // ⚠️ suppose que ta colonne en DB s’appelle `phone`
            // adapte si c’est `telephone` ou autre
            $field = 'phone';
        }

        if (! Auth::attempt([$field => $login, 'password' => $password], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                // On renvoie l’erreur sur le champ "login"
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        // 🔑 On base la limitation sur "login" et l’IP
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
