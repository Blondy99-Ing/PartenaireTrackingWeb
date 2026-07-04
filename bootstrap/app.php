<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'rebuild.dashboard' => \App\Http\Middleware\RebuildDashboardCache::class,
            'partner.only' => \App\Http\Middleware\EnsurePartnerAccount::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\RefreshKeycloakToken::class,
        ]);

        
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A staff member who reaches a page they are not permitted to see
        // (permission gate denied) is redirected to their allowed home page
        // with a notice — rather than getting a raw 403 wall.
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return null; // keep default JSON 403 for API/AJAX
            }

            $user = $request->user();

            if (! $user) {
                return null; // not authenticated → default handling (login)
            }

            $home = $user->homeRouteName();

            // Loop guard: if the denied page IS the resolved home, fall back to
            // the always-accessible profile page instead of redirecting to self.
            if ($request->routeIs($home)) {
                $home = 'profile.edit';
            }

            return redirect()
                ->route($home)
                ->with('warning', "Vous n'avez pas la permission d'accéder à cette page.");
        });
    })->create();