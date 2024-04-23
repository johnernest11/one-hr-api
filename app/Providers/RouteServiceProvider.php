<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Specific rate limit for login API route (combination of email and ip address)
        RateLimiter::for('api-login', function (Request $request) {
            $key = $this->getLoginThrottleKey($request);

            return Limit::perMinutes(3, 10)->by($key);
        });

        // Specific rate limit for the registration API route
        RateLimiter::for('api-register', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Default rate limit for Users accessing API routes
        RateLimiter::for('api-users', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user('token')?->id ? $request->user('token')->id.'_user' : $request->ip());
        });

        // Default rate limit for API Keys accessing Webhook API routes
        RateLimiter::for('api-webhooks', function (Request $request) {
            return Limit::perMinute(250)->by(
                $request->user('api_key')?->id ? $request->user('api_key')->id.'_hook' : $request->ip()
            );
        });
    }

    /**
     * Login throttle key is the combination of the IP address
     * and email from the request payload (if it exists)
     */
    private function getLoginThrottleKey(Request $request): string
    {
        $email = $request->input('email');
        $ip = $request->ip();

        return $email ? $email.$ip : $ip;
    }
}
