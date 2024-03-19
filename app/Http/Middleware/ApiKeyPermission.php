<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, mixed ...$params): Response
    {
        /** @var ApiKey $apiKey */
        $apiKey = auth('api_key')->user();

        if (! $apiKey) {
            throw new AuthenticationException('Unable to authenticated the API Key');
        }

        $hasPermissions = $apiKey->hasAnyPermission($params);
        if (! $hasPermissions) {
            throw new AuthorizationException('The API Key does not have the correct permissions');
        }

        return $next($request);
    }
}
