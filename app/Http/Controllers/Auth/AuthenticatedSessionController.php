<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginRateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            /** @var LoginRateLimiter $rateLimiter */
            $rateLimiter = app(LoginRateLimiter::class);

            $seconds = 0;

            if ($rateLimiter->tooManyAttempts($request->input('login'), $request->ip())) {
                $seconds = $rateLimiter->availableIn(
                    $request->input('login'),
                    $request->ip()
                );
            }

            return back()
                ->withInput($request->only('login'))
                ->withErrors($e->errors())
                ->with('login_lock_seconds', $seconds > 0 ? $seconds : null);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        auth()->guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}