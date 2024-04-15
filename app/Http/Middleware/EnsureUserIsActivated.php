<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActivated
{
    /**
     * Check the `active` flag from the authenticated user (via JWT or Sanctum)
     * Note that API Keys already handle the `active` flag separately
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = auth('token')->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->active) {
            throw new AuthorizationException(get_class($user).' is deactivated');
        }

        return $next($request);
    }
}
