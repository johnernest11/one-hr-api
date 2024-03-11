<?php

namespace App\Providers;

use App\Interfaces\Authentication\AuthTokenManager;
use App\Interfaces\Authentication\PersistentAuthTokenManager;
use App\Interfaces\HttpResources\UserServiceInterface;
use App\Models\User;
use App\Services\Authentication\JWTAuthService;
use App\Services\Authentication\SanctumAuthService;
use App\Services\HttpResources\UserService;
use Illuminate\Support\ServiceProvider;

class HttpResourceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(UserServiceInterface::class, function () {
            return new UserService(new User());
        });
        $this->app->bind(AuthTokenManager::class, function () {
            return new JWTAuthService();
        });
        $this->app->bind(PersistentAuthTokenManager::class, function () {
            return new SanctumAuthService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
