<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Auth\ApiKeyGuard;
use App\Auth\ApiKeyProvider;
use App\Auth\MultiTokenGuard;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use App\Services\Authentication\JWTAuthService;
use App\Services\Authentication\SanctumAuthService;
use Auth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(AuthTokenManager::class, function () {
            return new JWTAuthService();
        });

        $this->app->bind(PersistentAuthTokenManager::class, function () {
            return new SanctumAuthService();
        });
    }

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        /**
         * Implicitly grant "super_user" role all permissions.
         * This works in the app by using gate-related functions like auth()->user->can() and @can()
         *
         * @see https://spatie.be/docs/laravel-permission/v6/basic-usage/super-admin
         */
        Gate::after(function ($user, $ability) {
            return $user->hasRole('super_user') ? true : null;
        });

        // This checks the request's bearer token for either Sanctum Opaque token or JWT
        Auth::extend('multi_token_guard', function () {
            return new MultiTokenGuard(request());
        });

        // Provides ApiKey eloquent records
        Auth::provider('api_keys', function () {
            return new ApiKeyProvider();
        });

        // This checks for an API Key in the `X-API-Key` request header
        Auth::extend('api_key_guard', function (Application $app, string $name, array $config) {
            return new ApiKeyGuard(request(), Auth::createUserProvider($config['provider']));
        });
    }
}
