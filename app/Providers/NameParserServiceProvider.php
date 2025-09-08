<?php

namespace App\Providers;

use App\NameParser\CustomParser;
use App\NameParser\Language\Filipino;
use Illuminate\Support\ServiceProvider;
use TheIconic\NameParser\Parser;

class NameParserServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Parser::class, function ($app) {
            return new CustomParser([new Filipino]);
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
