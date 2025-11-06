<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRefreshTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $refreshToken = $request->bearerToken();

        if (! $refreshToken) {
            throw new AuthenticationException('Refresh token missing');
        }

        $tokenModel = PersonalAccessToken::findToken($refreshToken);

        if (! $tokenModel || $tokenModel->name !== 'refresh_token') {
            throw new AuthenticationException('Invalid refresh token');
        }

        // Attach user to request
        $request->setUserResolver(fn () => $tokenModel->tokenable);

        return $next($request);
    }
}
