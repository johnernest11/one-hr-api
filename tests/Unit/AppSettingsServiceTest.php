<?php

namespace Tests\Unit;

use App\Enums\AppTheme;
use App\Enums\MfaMethod;
use App\Models\AppSettings;
use App\Services\AppSettings\AppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class AppSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->service = new AppSettingsService(new AppSettings());
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_set_app_settings(): void
    {
        $settings = [
            'theme' => AppTheme::DARK->value,
            'mfa' => [
                'enabled' => true,
                'steps' => [MfaMethod::EMAIL_CHANNEL->value, MfaMethod::GOOGLE_AUTHENTICATOR->value],
            ],
        ];

        $settings = $this->service->setSettings($settings);

        // Theme and MFA records should be created in the database
        $this->assertCount(2, $settings);
        $this->assertDatabaseCount('app_settings', 2);
    }

    public function test_it_can_set_theme(): void
    {
        $darkTheme = AppTheme::DARK->value;
        $success = $this->service->setTheme($darkTheme);
        $this->assertTrue($success);

        $themeConfig = AppSettings::where('name', 'theme')->first();
        $this->assertEquals($themeConfig->value, $darkTheme);
    }

    public function test_it_can_get_current_theme(): void
    {
        $lightTheme = AppTheme::LIGHT->value;
        $this->service->setTheme($lightTheme);

        $currentTheme = $this->service->getTheme();
        $this->assertEquals($lightTheme, $currentTheme);
    }

    public function test_it_can_set_mfa_config(): void
    {
        $success = $this->service->setMfaConfig(
            true,
            MfaMethod::EMAIL_CHANNEL,
            MfaMethod::GOOGLE_AUTHENTICATOR,
            MfaMethod::SMS_CHANNEL
        );

        $this->assertTrue($success);
    }

    public function test_it_only_sets_unique_mfa_steps(): void
    {
        $this->service->setMfaConfig(
            true,
            MfaMethod::EMAIL_CHANNEL,
            MfaMethod::GOOGLE_AUTHENTICATOR,
            MfaMethod::SMS_CHANNEL,
            MfaMethod::EMAIL_CHANNEL, // repeated
            MfaMethod::SMS_CHANNEL // repeated
        );

        $mfaConfig = $this->service->getMfaConfig();

        // Only the unique values are set (also, order matters)
        $this->assertEquals($mfaConfig['steps'], [
            MfaMethod::EMAIL_CHANNEL->value,
            MfaMethod::GOOGLE_AUTHENTICATOR->value,
            MfaMethod::SMS_CHANNEL->value,
        ]);
    }

    public function test_it_can_get_theme_config(): void
    {
        $this->service->setMfaConfig(
            true,
            MfaMethod::EMAIL_CHANNEL,
            MfaMethod::GOOGLE_AUTHENTICATOR,
            MfaMethod::SMS_CHANNEL
        );

        $mfaConfig = $this->service->getMfaConfig();
        $this->assertTrue($mfaConfig['enabled']);
        $this->assertEquals($mfaConfig['steps'], [
            MfaMethod::EMAIL_CHANNEL->value,
            MfaMethod::GOOGLE_AUTHENTICATOR->value,
            MfaMethod::SMS_CHANNEL->value,
        ]);
    }
}
