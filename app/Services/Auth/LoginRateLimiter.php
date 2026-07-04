<?php

namespace App\Services\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly LoginIdentifierResolver $resolver
    ) {
    }

    public function ensureIsNotRateLimited(?string $login, ?string $ip): void
    {
        $key = $this->throttleKey($login, $ip);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);
        $minutes = (int) ceil($seconds / 60);

        $message = $seconds < 60
            ? "Trop de tentatives de connexion. Veuillez réessayer dans {$seconds} seconde(s)."
            : "Trop de tentatives de connexion. Veuillez réessayer dans {$minutes} minute(s).";

        throw ValidationException::withMessages([
            'login' => [$message],
        ])->status(429);
    }

    public function hit(?string $login, ?string $ip): void
    {
        RateLimiter::hit($this->throttleKey($login, $ip), self::DECAY_SECONDS);
    }

    public function clear(?string $login, ?string $ip): void
    {
        RateLimiter::clear($this->throttleKey($login, $ip));
    }

    public function availableIn(?string $login, ?string $ip): int
    {
        return RateLimiter::availableIn($this->throttleKey($login, $ip));
    }

    public function tooManyAttempts(?string $login, ?string $ip): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->throttleKey($login, $ip),
            self::MAX_ATTEMPTS
        );
    }

    private function throttleKey(?string $login, ?string $ip): string
    {
        return $this->resolver->normalizedThrottleKey($login, $ip);
    }
}