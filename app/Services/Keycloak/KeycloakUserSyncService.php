<?php

namespace App\Services\Keycloak;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class KeycloakUserSyncService
{
    public function syncFromClaims(array $claims, ?User $matchedLocalUser = null): User
    {
        return DB::transaction(function () use ($claims, $matchedLocalUser) {
            $keycloakId = $claims['sub'] ?? null;

            if (! $keycloakId) {
                throw new \RuntimeException('Le token Keycloak ne contient pas de claim sub.');
            }

            $email = isset($claims['email']) && $claims['email'] !== ''
                ? Str::lower(trim((string) $claims['email']))
                : null;

            $username = isset($claims['preferred_username']) && $claims['preferred_username'] !== ''
                ? trim((string) $claims['preferred_username'])
                : null;

            $prenom = isset($claims['given_name']) && $claims['given_name'] !== ''
                ? trim((string) $claims['given_name'])
                : null;

            $nom = isset($claims['family_name']) && $claims['family_name'] !== ''
                ? trim((string) $claims['family_name'])
                : null;

            $roles = Arr::get($claims, 'resource_access.tracking_app.roles', []);
            if (!is_array($roles)) {
                $roles = [];
            }

            $roleId = $this->resolveLocalRoleId($roles);

            $user = User::where('keycloak_id', $keycloakId)->first();

            if (! $user && $matchedLocalUser) {
                $user = $matchedLocalUser;
            }

            if (! $user && $email) {
                $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            }

            if (! $user && $username) {
                $normalizedUsername = preg_replace('/\D+/', '', $username);

                if ($normalizedUsername !== '') {
                    $phoneCandidates = $this->phoneCandidates($normalizedUsername);

                    if (!empty($phoneCandidates)) {
                        $user = User::whereIn('phone', $phoneCandidates)->first();
                    }
                }
            }

            if (! $user) {
                $user = new User();

                $user->password = Hash::make(Str::random(32));
                $user->ville = 'Non renseignée';
                $user->quartier = 'Non renseigné';
                $user->phone = $this->generateFallbackPhone();
                $user->email = $email;
                $user->prenom = $prenom ?: 'Non renseigné';
                $user->nom = $nom ?: 'Non renseigné';

                if (empty($user->user_unique_id)) {
                    $user->user_unique_id = (string) Str::uuid();
                }
            }

            $user->keycloak_id = $keycloakId;
            $user->keycloak_username = $username ?: $user->keycloak_username;
            $user->email = $email ?: $user->email;
            $user->prenom = $prenom ?: $user->prenom ?: 'Non renseigné';
            $user->nom = $nom ?: $user->nom ?: 'Non renseigné';

            if (property_exists($user, 'last_synced_at') || array_key_exists('last_synced_at', $user->getAttributes())) {
                $user->last_synced_at = now();
            }

            if ($roleId) {
                $user->role_id = $roleId;
            }

            $user->save();

            return $user->fresh();
        });
    }

    protected function resolveLocalRoleId(array $keycloakRoles): ?int
    {
        if (empty($keycloakRoles)) {
            return null;
        }

        $map = [
                'ADMIN' => 'admin',
                'CALL_CENTER' => 'call_center',
                'GESTIONNAIRE_PLATEFORME' => 'gestionnaire_plateforme',
                'UTILISATEUR_PRINCIPALE' => 'utilisateur_principale',
                'UTILISATEUR_SECONDAIRE' => 'utilisateur_secondaire',
            ];

        foreach ($keycloakRoles as $kcRole) {
            $slug = $map[$kcRole] ?? null;

            if (! $slug) {
                continue;
            }

            $role = Role::where('slug', $slug)->first();

            if ($role) {
                return $role->id;
            }
        }

        return null;
    }

    protected function generateFallbackPhone(): string
    {
        do {
            $phone = '699' . random_int(100000, 999999);
        } while (User::where('phone', $phone)->exists());

        return $phone;
    }

    protected function phoneCandidates(string $phone): array
    {
        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return [];
        }

        $candidates = [];

        if (strlen($digits) === 9) {
            $candidates[] = $digits;
            $candidates[] = '237' . $digits;
            $candidates[] = '+237' . $digits;
        }

        if (str_starts_with($digits, '237') && strlen($digits) === 12) {
            $local = substr($digits, 3);
            $candidates[] = $local;
            $candidates[] = $digits;
            $candidates[] = '+' . $digits;
        }

        $candidates[] = $digits;

        if (! str_starts_with($raw, '+')) {
            $candidates[] = '+' . $digits;
        }

        return array_values(array_unique(array_filter($candidates)));
    }
}