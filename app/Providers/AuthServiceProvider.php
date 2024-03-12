<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Guards\MultiAuthGuard;
use App\Interfaces\Services\Authentication\AuthTokenManager;
use App\Interfaces\Services\Authentication\PersistentAuthTokenManager;
use App\Models\User;
use App\Policies\UserPolicy;
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
        // Implicitly grant "super_user" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::after(function ($user, $ability) {
            return $user->hasRole('super_user') ? true : null;
        });

        // Our custom multi auth guard
        Auth::extend('multi-auth', function (Application $app, string $name, array $config) {
            return new MultiAuthGuard();
        });
    }
}
