<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /**
     * Routes reachable while the second factor is still pending.
     */
    protected array $except = [
        'two-factor.challenge',
        'two-factor.verify',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()
            && $request->session()->get('two_factor.pending')
            && ! $request->routeIs(...$this->except)) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
