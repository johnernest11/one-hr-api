<?php

namespace App\Providers;

use App\Services\Database\SchemaInfoService;
use App\Services\Database\SchemaInspector;
use Illuminate\Support\ServiceProvider;

class DbServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(SchemaInspector::class, function () {
            return new SchemaInfoService();
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
