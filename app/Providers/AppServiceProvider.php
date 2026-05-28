<?php

namespace App\Providers;

use App\Enums\AppEnvironment;
use App\Models\ComprehensiveRecords\IndividualBasicDetail;
use App\Models\DailyTimeRecords\DailyTimeRecord;
use App\Models\DailyTimeRecords\QrCode;
use App\Models\DailyTimeRecords\TimeLog;
use App\Models\Item;
use App\Models\LocatorSlip\LocatorSlip;
use App\Models\PersonalAccessToken;
use App\Services\AccomplishmentReport\AccomplishmentReportManager;
use App\Services\AccomplishmentReport\AccomplishmentReportService;
use App\Services\AppSettingsManager;
use App\Services\CloudStorageServices\AwsS3StorageService;
use App\Services\CloudStorageServices\CloudStorageManager;
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
use App\Services\LocatorSlips\LocatorSlipManager;
use App\Services\LocatorSlips\LocatorSlipService;
use App\Services\User\UserAccountManager;
use App\Services\User\UserCredentialManager;
use App\Services\User\UserManager;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use TheIconic\NameParser\Parser;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // IDE Helper for local
        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        // User services
        $this->app->bind(UserAccountManager::class, fn () => new UserManager);
        $this->app->bind(UserCredentialManager::class, fn () => new UserManager);

        // App Settings
        $this->app->bind(AppSettingsManager::class, fn () => new AppSettingsManager);

        // Item services
        $this->app->bind(ItemManager::class, fn () => new ItemService(new Item));

        // Accomplishment Reports
        $this->app->bind(AccomplishmentReportManager::class, fn () => new AccomplishmentReportService);

        // IndividualBasicDetail
        $this->app->bind(IndividualBasicDetailManager::class, function ($app) {
            return new IndividualBasicDetailService(
                new IndividualBasicDetail,
                new Parser,
                $this->app->make(QrCodeManager::class)
            );
        });

        // QR Code
        $this->app->bind(QrCodeManager::class, fn () => new QrCodeService(new QrCode));

        // Cloud Storage binding
        $this->app->bind(CloudStorageManager::class, fn () => new AwsS3StorageService);

        // Daily Time Records
        $this->app->bind(DailyTimeRecordManager::class, function ($app) {
            return new DailyTimeRecordService(
                $app->make(DailyTimeRecord::class),
                $app->make(CloudStorageManager::class)
            );
        });

        // Time Logs
        $this->app->bind(TimeLogManager::class, function ($app) {
            return new TimeLogService(
                $app->make(TimeLog::class),
                $app->make(CloudStorageManager::class),
                $app->make(LocatorSlipManager::class)
            );
        });

        // Locator Slips
        $this->app->bind(LocatorSlipManager::class, fn () => new LocatorSlipService(new LocatorSlip));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        if (
            in_array(app()->environment(), [
                AppEnvironment::PRODUCTION->value,
                AppEnvironment::UAT->value,
                AppEnvironment::DEVELOPMENT->value,
            ])
        ) {
            $this->app['request']->server->set('HTTPS', 'on');
            URL::forceScheme('https');
        }
    }
}
