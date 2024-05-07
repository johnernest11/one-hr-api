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
            $key = $this->getApiUserThrottleKey($request);

            return Limit::perMinute(120)->by($key);
        });

        // Default rate limit for API Keys accessing Webhook API routes
        RateLimiter::for('api-webhooks', function (Request $request) {
            $key = $this->getApiWebhookThrottleKey($request);

            return Limit::perMinute(250)->by($key);
        });

        // Rate limit for requesting a forgot password email request
        RateLimiter::for('api-forgot-password', function (Request $request) {
            $key = 'forgot-password'.$request->get('email').$request->ip();

            return Limit::perMinute(2)->by($key);
        });

        // Rate limit for requesting email verifications
        RateLimiter::for('api-email-verification', function (Request $request) {
            $identifier = $request->user('token')?->id ?? $request->ip();
            $key = 'email-verification'.$identifier;

            return Limit::perMinute(2)->by($key);
        });

        // Default Rate limit for MFA routes (based on MFA token)
        RateLimiter::for('api-mfa', function (Request $request) {
            $key = $this->getApiMfaThrottleKey($request);

            return Limit::perMinute(5)->by($key);
        });

        // Rate limit for sending MFA code via delivery channels
        RateLimiter::for('api-mfa-send-code', function (Request $request) {
            $key = $this->getApiMfaThrottleKey($request);

            return Limit::perMinute(2)->by($key);
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

    /**
     * Create the throttle key for User API Endpoints
     *
     * Authenticated:   User ID + '_user'
     * Unauthenticated: Route Name or URI + IP address + '_users'
     */
    private function getApiUserThrottleKey(Request $request): string
    {
        $appendKey = '_user';
        $userId = $request->user('token')?->id;
        if ($userId) {
            return $userId.$appendKey;
        }

        $ip = $request->ip();
        $route = $request->route()->getName() ?? $request->route()->uri();

        return $route.$ip.$appendKey;
    }

    /**
     * Create the throttle key for Webhook API Endpoints
     *
     * Authenticated:   API Key ID + '_hook'
     * Unauthenticated: Route Name or URI + IP address + '_hooks'
     */
    private function getApiWebhookThrottleKey(Request $request): string
    {
        $appendKey = '_hook';
        $apiKeyId = $request->user('api_key')?->id;
        if ($apiKeyId) {
            return $apiKeyId.$appendKey;
        }

        $ip = $request->ip();
        $route = $request->route()->getName() ?? $request->route()->uri();

        return $route.$ip.$appendKey;
    }

    /**
     * Create the throttle key for MFA endpoints.
     * The key is a combination of the token (or IP if it does not exist)
     * route name.
     */
    private function getApiMfaThrottleKey(Request $request): string
    {
        $identifier = $request->input('token', $request->ip());
        $route = $request->route()->getName() ?? $request->route()->uri();

        return $identifier.$route;
    }
}
