<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbility
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            // Not a token request (e.g., web session), skip scope check
            return $next($request);
        }

        $abilities = $token->abilities ?? [];

        // Wildcard ability grants all access
        if (in_array('*', $abilities, true)) {
            return $next($request);
        }

        if (! in_array($ability, $abilities, true)) {
            return response()->json([
                'message' => 'Token does not have the required scope.',
                'required_scope' => $ability,
                'token_scopes' => $abilities,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
