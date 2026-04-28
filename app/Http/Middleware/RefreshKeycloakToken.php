<?php

namespace App\Http\Middleware;

use App\Services\Keycloak\KeycloakSessionTokenManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RefreshKeycloakToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        if (! session()->has('keycloak_refresh_token')) {
            return $next($request);
        }

        try {
            /**
             * On rafraîchit si le token expire dans moins de 60 secondes.
             * La documentation recommande de ne pas attendre l’expiration réelle.
             */
            app(KeycloakSessionTokenManager::class)->getValidAccessToken(60);
        } catch (Throwable $e) {
            Log::warning('[KEYCLOAK_MIDDLEWARE_REFRESH_FAILED]', [
                'user_id' => Auth::id(),
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Session expirée. Veuillez vous reconnecter.',
                ], 401);
            }

            return redirect()
                ->route('login')
                ->withErrors([
                    'login' => 'Session expirée. Veuillez vous reconnecter.',
                ]);
        }

        return $next($request);
    }
}