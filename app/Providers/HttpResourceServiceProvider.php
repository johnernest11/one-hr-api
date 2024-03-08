<?php

namespace App\Providers;

use App\Enums\AuthTokenType;
use App\Interfaces\Authentication\AuthTokenManager;
use App\Interfaces\Authentication\TokenAuthServiceInterface;
use App\Interfaces\HttpResources\UserServiceInterface;
use App\Models\User;
use App\Services\Authentication\JWTAuthService;
use App\Services\Authentication\SanctumAuthService;
use App\Services\Authentication\TokenAuthService;
use App\Services\HttpResources\UserService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

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
        $this->app->bind(TokenAuthServiceInterface::class, function () {
            return new TokenAuthService();
        });
        $this->app->bind(AuthTokenManager::class, function (Application $app, array $params) {
            if ($params && count($params) > 1 && ! $params[0] instanceof AuthTokenType) {
                throw new InvalidArgumentException('The argument should be an instance of '.AuthTokenType::class);
            }

            if ($params[0] === AuthTokenType::JWT) {
                return new JWTAuthService();
            }

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
