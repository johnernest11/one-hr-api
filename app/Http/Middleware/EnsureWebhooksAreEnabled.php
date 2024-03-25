<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebhooksAreEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('webhooks.enabled')) {
            return response()->json([
                'success' => false,
                'message' => 'Webhooks are disabled for this API',
                'error_code' => ApiErrorCode::WEBHOOKS_DISABLED,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
