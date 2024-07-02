<?php

namespace Database\Seeders;

use App\Enums\AppTheme;
use App\Models\AppSettings;
use App\Services\AppSettingsManager;
use Throwable;

class AppSettingsSeeder extends CiCdCompliantSeeder
{
    /**
     * Run the database seeds.
     *
     * @throws Throwable
     */
    public function run(): void
    {
        AppSettings::query()->delete();
        $settingsManager = resolve(AppSettingsManager::class);

        $default = [
            'theme' => AppTheme::LIGHT,
            'mfa' => ['enabled' => false, 'steps' => [], 'allow_api_management' => true],
        ];

        $settingsManager->setSettings($default);
    }

    /** {@inheritDoc} */
    public function shouldRun(): bool
    {
        return $this->tableIsEmpty();
    }

    /** {@inheritDoc} */
    protected function tableName(): string
    {
        return app(AppSettings::class)->getTable();
    }
}
