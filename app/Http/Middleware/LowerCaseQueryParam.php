<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Convert the values of route query parameter to lowercase */
class LowerCaseQueryParam
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, mixed ...$paramNames): Response
    {

        foreach ($paramNames as $paramName) {
            if ($request->has($paramName)) {
                $value = strtolower($request->query($paramName));
                $request->merge([$paramName => $value]);
            }
        }

        return $next($request);
    }
}
