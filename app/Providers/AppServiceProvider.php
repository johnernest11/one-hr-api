<?php

namespace App\Providers;

use App\Enums\AppEnvironment;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\QrCode;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Item;
use App\Models\PersonalAccessToken;
use App\Services\AccomplishmentReport\AccomplishmentReportManager;
use App\Services\AccomplishmentReport\AccomplishmentReportService;
use App\Services\AppSettingsManager;
use App\Services\ComprehensiveRecords\IndividualBasicDetailManager;
use App\Services\ComprehensiveRecords\IndividualBasicDetailService;
use App\Services\DailyTimeRecords\DailyTimeRecordManager;
use App\Services\DailyTimeRecords\DailyTimeRecordService;
use App\Services\DailyTimeRecords\QrCodeManager;
use App\Services\DailyTimeRecords\QrCodeService;
use App\Services\DailyTimeRecords\TimeLogManager;
use App\Services\DailyTimeRecords\TimeLogService;
use App\Services\Item\ItemManager;
use App\Services\Item\ItemService;
use App\Services\User\UserAccountManager;
use App\Services\User\UserCredentialManager;
use App\Services\User\UserManager;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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

        $this->app->bind(ItemManager::class, function () {
            return new ItemService(new Item());
        });

        $this->app->bind(AccomplishmentReportManager::class, function () {
            return new AccomplishmentReportService();
        });
        $this->app->bind(IndividualBasicDetailManager::class, function ($app) {
            return new IndividualBasicDetailService(new IndividualBasicDetail(), $app->make(\TheIconic\NameParser\Parser::class));
        });
        $this->app->bind(QrCodeManager::class, function () {
            return new QrCodeService(new QrCode());
        });
        $this->app->bind(DailyTimeRecordManager::class, function () {
            return new DailyTimeRecordService(new DailyTimeRecord());
        });
        $this->app->bind(TimeLogManager::class, function () {
            return new TimeLogService(new TimeLog());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
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
