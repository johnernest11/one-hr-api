<?php

namespace Tests\Feature;

use App\Enums\AppTheme;
use App\Enums\Role as RoleEnum;
use App\Enums\VerificationMethod;
use App\Models\User;
use App\Services\AppSettingsManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use ConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/app-settings';

    private string $authToken;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        /** @var User $user */
        $user = $this->produceUsers();
        $roles = [RoleEnum::ADMIN, RoleEnum::SUPER_USER];
        $user->syncRoles(fake()->randomElement($roles));

        $authSanctumService = resolve(PersistentAuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $authSanctumService->generateToken($user, $authTokenExpiration, 'mock_token');
    }

    public function test_it_can_store_app_settings(): void
    {
        $input = [
            'theme' => AppTheme::LIGHT->value,
            'mfa' => [
                'enabled' => true,
                'steps' => ConversionHelper::enumToArray(VerificationMethod::class),
            ],
        ];

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus(200);
    }

    public function test_it_can_validated_themes(): void
    {
        $input = [
            'theme' => 'this-theme-does-not-exists',
        ];

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus(422);
    }

    /** @dataProvider mfaConfigInputs */
    public function test_it_can_validate_mfa_inputs_and_optional_values(array $input, int $statusCode): void
    {
        $payload = ['mfa' => $input];

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $payload);
        $response->assertStatus($statusCode);
    }

    public static function mfaConfigInputs(): array
    {
        return [
            [['enabled' => 123, 'steps' => ['invalid_method']], 422],
            [['enabled' => true, 'steps' => ['invalid_method']], 422],
            [['enabled' => false, 'steps' => [VerificationMethod::EMAIL_CHANNEL->value]], 200],
            [['enabled' => true, 'steps' => [VerificationMethod::GOOGLE_AUTHENTICATOR->value]], 200],
            [['enabled' => false], 200],
            [['steps' => [VerificationMethod::GOOGLE_AUTHENTICATOR->value, VerificationMethod::EMAIL_CHANNEL->value]], 200],
        ];
    }

    public function test_it_can_fetch_app_settings(): void
    {
        $response = $this->getJson($this->baseUri);
        $response->assertStatus(200);
    }

    public function test_it_returns_403_if_mfa_configuration_via_api_is_disabled(): void
    {
        // Disable the MFA Config Management via API
        $manager = resolve(AppSettingsManager::class);
        $manager->setMfaConfig(true, false, VerificationMethod::EMAIL_CHANNEL, VerificationMethod::GOOGLE_AUTHENTICATOR);

        $input = [
            'theme' => AppTheme::LIGHT->value,
            'mfa' => [
                'enabled' => false,
                'steps' => ConversionHelper::enumToArray(VerificationMethod::class),
            ],
        ];

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus(403);
    }
}
