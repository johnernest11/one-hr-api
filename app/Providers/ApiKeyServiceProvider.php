<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Services\ApiKey\ApiKeyManager;
use App\Services\ApiKey\ApiKeyService;
use Illuminate\Support\ServiceProvider;

class ApiKeyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ApiKeyManager::class, function () {
            return new ApiKeyService(new ApiKey());
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
