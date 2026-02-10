<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);

        // 1) Si email
        if (str_contains($login, '@')) {
            $user = User::query()
                ->where('email', strtolower($login))
                ->first();
        } else {
            // 2) Sinon téléphone multi-formats
            $candidates = Phone::candidates($login);

            if (empty($candidates)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Validation failed',
                    'errors' => [
                        'login' => ['Numéro de téléphone invalide'],
                    ],
                ], 422);
            }

            $user = User::query()
                ->whereIn('phone', $candidates)
                ->first();
        }

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'ok' => false,
                'message' => 'Identifiants invalides',
                'errors' => [
                    'login' => ['Login ou mot de passe incorrect'],
                ],
            ], 401);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'ok' => true,
            'message' => 'Authentifié',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'phone' => $user->phone,
                'email' => $user->email,
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Déconnecté',
        ], 200);
    }
}
