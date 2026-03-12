<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;

class LoginIdentifierResolver
{
    public function resolveUser(?string $login): ?User
    {
        $login = trim((string) $login);

        if ($login === '') {
            return null;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::whereRaw('LOWER(email) = ?', [Str::lower($login)])->first();
        }

        $phoneCandidates = $this->phoneCandidates($login);

        if (empty($phoneCandidates)) {
            return null;
        }

        return User::whereIn('phone', $phoneCandidates)->first();
    }

    public function resolveCredentials(?string $login, ?string $password): array
    {
        $user = $this->resolveUser($login);

        if (! $user) {
            return [
                'user' => null,
                'credentials' => [],
            ];
        }

        return [
            'user' => $user,
            'credentials' => [
                'email' => $user->email,
                'password' => $password,
            ],
        ];
    }

    public function normalizedThrottleKey(?string $login, ?string $ip): string
    {
        $login = trim((string) $login);

        if ($login === '') {
            return 'login:unknown|'.$ip;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return 'login:'.Str::lower($login).'|'.$ip;
        }

        $candidates = $this->phoneCandidates($login);

        if (! empty($candidates)) {
            return 'login:'.$candidates[0].'|'.$ip;
        }

        return 'login:'.$login.'|'.$ip;
    }

    private function phoneCandidates(string $phone): array
    {
        $raw = trim($phone);

        // On retire tout sauf chiffres
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return [];
        }

        $candidates = [];

        // Cas local camerounais 9 chiffres : 690123456
        if (strlen($digits) === 9) {
            $candidates[] = $digits;
            $candidates[] = '237'.$digits;
            $candidates[] = '+237'.$digits;
        }

        // Cas 237690123456
        if (str_starts_with($digits, '237') && strlen($digits) === 12) {
            $local = substr($digits, 3);
            $candidates[] = $local;
            $candidates[] = $digits;
            $candidates[] = '+'.$digits;
        }

        // Cas générique
        $candidates[] = $digits;

        if (! str_starts_with($raw, '+')) {
            $candidates[] = '+'.$digits;
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}