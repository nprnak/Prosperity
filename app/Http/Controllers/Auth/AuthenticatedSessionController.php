<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        // Clear any stale 2FA pending flag when showing the login page so a
        // previous partially-completed flow doesn't block new logins.
        $request->session()->forget('two_factor.pending');

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        // Ensure any prior pending state is cleared before attempting auth.
        $request->session()->forget('two_factor.pending');

        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->requiresTwoFactor()) {
            $request->session()->put('two_factor.pending', true);
            $twoFactor->send($user);

            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
