<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingIsComplete
{
    /**
     * Handle an incoming request.
     *
     * Ensures users who haven't completed onboarding cannot access protected routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Allow if onboarding is complete
        if ($user->isOnboarded()) {
            return $next($request);
        }

        // Allow access to onboarding routes
        $onboardingRoutes = [
            'onboarding.index',
            'onboarding.password',
            'onboarding.password.update',
            'onboarding.two-factor',
            'onboarding.two-factor.confirmed',
            'onboarding.passkey',
            'onboarding.passkey.skip',
            'onboarding.complete',
        ];

        if (in_array($request->route()?->getName(), $onboardingRoutes)) {
            return $next($request);
        }

        if ($request->routeIs('passkey.*') || $request->is('user/passkeys*') || $request->is('passkeys/*')) {
            return $next($request);
        }

        // Allow logout
        if ($request->routeIs('logout')) {
            return $next($request);
        }

        // Redirect to onboarding
        return redirect()->route('onboarding.index');
    }
}
