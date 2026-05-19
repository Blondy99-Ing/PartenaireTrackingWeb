<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerAccount
{
    /**
     * Ensure that only a real partner account can access partner routes.
     *
     * Business rule:
     * - Partner account: users.partner_id IS NULL
     * - Driver / secondary user: users.partner_id IS NOT NULL
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non authentifié.',
                ], 401);
            }

            abort(401);
        }

        if (!is_null($user->partner_id)) {
            Log::warning('Partner area access denied for non-partner user.', [
                'user_id' => $user->id,
                'partner_id' => $user->partner_id,
                'email' => $user->email,
                'telephone' => $user->telephone ?? null,
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès réservé au compte partenaire.',
                ], 403);
            }

            abort(403, 'Accès réservé au compte partenaire.');
        }

        return $next($request);
    }
}