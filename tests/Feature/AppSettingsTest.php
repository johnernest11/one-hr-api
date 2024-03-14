<?php

namespace Tests\Feature;

use App\Enums\AppTheme;
use App\Enums\Role as RoleEnum;
use App\Interfaces\Services\Authentication\PersistentAuthTokenManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

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
        ];

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus(201);
    }

    public function test_it_can_validated_themes(): void
    {
        $input = [
            'theme' => 'this-theme-does-not-exists',
        ];

        $response = $this->withToken($this->authToken)->postJson($this->baseUri, $input);
        $response->assertStatus(422);
    }

    public function test_it_can_fetch_app_settings(): void
    {
        $response = $this->getJson($this->baseUri);
        $response->assertStatus(200);
    }
}
