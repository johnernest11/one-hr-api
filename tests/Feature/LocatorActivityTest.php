<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocatorActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $authToken;

    private PersistentAuthTokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user = $this->produceUsers();
        $roles = [RoleEnum::ADMIN, RoleEnum::STANDARD_USER];
        $user->syncRoles(fake()->randomElement($roles));
        $this->user = $user; // save random user

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $this->tokenManager->generateToken($user, $authTokenExpiration, 'mock_token');

    }

    private string $baseUri = self::BASE_API_URI.'/libraries/locator-activities';

    public function test_it_can_fetch_all_activities(): void
    {
        $response = $this->withToken($this->authToken)->getJson($this->baseUri);
        $response->assertStatus(200);
    }
}
