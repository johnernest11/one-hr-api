<?php

namespace App\Providers;

use App\Services\Verification\Methods\EmailVerificationChannel;
use App\Services\Verification\Methods\GAuthenticatorVerificationApp;
use Illuminate\Support\ServiceProvider;

class VerificationFactorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(EmailVerificationChannel::class, function () {
            return new EmailVerificationChannel();
        });

        $this->app->bind(GAuthenticatorVerificationApp::class, function () {
            return new GAuthenticatorVerificationApp();
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
