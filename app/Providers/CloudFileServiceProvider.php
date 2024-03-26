<?php

namespace App\Providers;

use App\Services\CloudStorageServices\AwsS3StorageService;
use App\Services\CloudStorageServices\CloudStorageManager;
use Illuminate\Support\ServiceProvider;

class CloudFileServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CloudStorageManager::class, function () {
            return new AwsS3StorageService();
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
