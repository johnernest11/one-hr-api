<?php

namespace App\Providers;

use App\Models\ApiKey;
use App\Services\ApiKey\ApiKeyService;
use App\Services\ApiKey\ApiKeyServiceInterface;
use Illuminate\Support\ServiceProvider;

class ApiKeyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(ApiKeyServiceInterface::class, function () {
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
