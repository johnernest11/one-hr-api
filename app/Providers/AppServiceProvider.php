<?php

namespace App\Providers;

use App\Enums\AppEnvironment;
use App\Services\AppSettingsManager;
use App\Services\User\UserAccountManager;
use App\Services\User\UserCredentialManager;
use App\Services\User\UserManager;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /**
         * Load IDE helper for non-production environment
         *
         * @see https://github.com/barryvdh/laravel-ide-helper
         */
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        $this->app->bind(UserAccountManager::class, function () {
            return new UserManager();
        });

        $this->app->bind(UserCredentialManager::class, function () {
            return new UserManager();
        });

        $this->app->bind(AppSettingsManager::class, function () {
            return new AppSettingsManager();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (in_array(app()->environment(), [
            AppEnvironment::PRODUCTION->value,
            AppEnvironment::UAT->value,
            AppEnvironment::DEVELOPMENT->value,
        ])) {
            $this->app['request']->server->set('HTTPS', 'on');
            URL::forceScheme('https');
        }
    }
}
