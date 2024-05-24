<?php

namespace App\Providers;

use App\Services\DbSchemaInspector;
use Illuminate\Support\ServiceProvider;

class DbServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(DbSchemaInspector::class, function () {
            return new DbSchemaInspector();
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
