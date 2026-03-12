<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\LoginIdentifierResolver;
use App\Services\Auth\LoginRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ], [
            'login.required' => 'Le champ identifiant est obligatoire.',
            'login.string' => 'Le champ identifiant doit être une chaîne de caractères.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères.',
            'device_name.required' => 'Le nom de l’appareil est obligatoire.',
            'device_name.string' => 'Le nom de l’appareil doit être une chaîne de caractères.',
            'device_name.max' => 'Le nom de l’appareil ne doit pas dépasser 255 caractères.',
        ]);

        /** @var LoginRateLimiter $rateLimiter */
        $rateLimiter = app(LoginRateLimiter::class);

        try {
            $rateLimiter->ensureIsNotRateLimited(
                $validated['login'],
                $request->ip()
            );
        } catch (ValidationException $e) {
            $seconds = $rateLimiter->availableIn(
                $validated['login'],
                $request->ip()
            );

            return response()->json([
                'message' => $e->errors()['login'][0] ?? 'Trop de tentatives de connexion.',
                'errors' => $e->errors(),
                'retry_after' => $seconds,
            ], 429);
        }

        /** @var LoginIdentifierResolver $resolver */
        $resolver = app(LoginIdentifierResolver::class);

        $user = $resolver->resolveUser($validated['login']);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $rateLimiter->hit($validated['login'], $request->ip());

            throw ValidationException::withMessages([
                'login' => ['Identifiant ou mot de passe incorrect.'],
            ]);
        }

        if (isset($user->status) && $user->status !== 'active') {
            throw ValidationException::withMessages([
                'login' => ['Votre compte est inactif. Veuillez contacter l’administrateur.'],
            ]);
        }

        $rateLimiter->clear($validated['login'], $request->ip());

        $user->tokens()
            ->where('name', $validated['device_name'])
            ->delete();

        $plainTextToken = $user->createToken(
            $validated['device_name'],
            ['api:access']
        )->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_in_minutes' => (int) config('sanctum.expiration'),
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Tous les appareils ont été déconnectés.',
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->latest('id')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at', 'expires_at']);

        return response()->json([
            'tokens' => $tokens,
        ]);
    }

    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $request->user()->tokens()
            ->where('id', $tokenId)
            ->delete();

        return response()->json([
            'message' => 'Session révoquée.',
        ]);
    }
}