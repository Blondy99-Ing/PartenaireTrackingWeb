<?php

namespace App\Http\Middleware;

use App\Support\UserMessages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartnerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => UserMessages::SESSION_EXPIRED,
                ], 401);
            }

            abort(401);
        }

        $roleSlug = optional($user->role)->slug;

        $isMainPartner = is_null($user->partner_id);
        $isPartnerAdmin = ! is_null($user->partner_id) && $roleSlug === 'partner_admin';

        if (! $isMainPartner && ! $isPartnerAdmin) {
            Log::warning('Partner area access denied for non-authorized user.', [
                'user_id' => $user->id,
                'partner_id' => $user->partner_id,
                'role' => $roleSlug,
                'email' => $user->email,
                'telephone' => $user->telephone ?? null,
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => UserMessages::ACCESS_DENIED,
                ], 403);
            }

            abort(403, UserMessages::ACCESS_DENIED);
        }

        return $next($request);
    }
}