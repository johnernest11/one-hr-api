<?php

namespace Tests\Unit;

use App\Enums\AppTheme;
use App\Enums\VerificationMethod;
use App\Models\AppSettings;
use App\Services\AppSettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class AppSettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    private AppSettingsManager $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->service = new AppSettingsManager();
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
                'steps' => [VerificationMethod::EMAIL_CHANNEL->value, VerificationMethod::GOOGLE_AUTHENTICATOR->value],
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
            true,
            VerificationMethod::EMAIL_CHANNEL,
            VerificationMethod::GOOGLE_AUTHENTICATOR,
        );

        $this->assertTrue($success);
    }

    public function test_it_only_sets_unique_mfa_steps(): void
    {
        $this->service->setMfaConfig(
            true,
            true,
            VerificationMethod::EMAIL_CHANNEL,
            VerificationMethod::GOOGLE_AUTHENTICATOR,
            VerificationMethod::EMAIL_CHANNEL // repeated
        );

        $mfaConfig = $this->service->getMfaConfig();

        // Only the unique values are set (also, order matters)
        $this->assertEquals($mfaConfig['steps'], [
            VerificationMethod::EMAIL_CHANNEL->value,
            VerificationMethod::GOOGLE_AUTHENTICATOR->value,
        ]);
    }

    public function test_it_can_get_theme_config(): void
    {
        $this->service->setMfaConfig(
            true,
            true,
            VerificationMethod::EMAIL_CHANNEL,
            VerificationMethod::GOOGLE_AUTHENTICATOR,
        );

        $mfaConfig = $this->service->getMfaConfig();
        $this->assertTrue($mfaConfig['enabled']);
        $this->assertEquals($mfaConfig['steps'], [
            VerificationMethod::EMAIL_CHANNEL->value,
            VerificationMethod::GOOGLE_AUTHENTICATOR->value,
        ]);
    }
}
