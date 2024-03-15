<?php

namespace App\Providers;

use App\Services\Database\SchemaService;
use App\Services\Database\SchemaServiceInterface;
use Illuminate\Support\ServiceProvider;

class DbServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SchemaServiceInterface::class, function () {
            return new SchemaService();
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
