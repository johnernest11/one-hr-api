<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeTest extends TestCase
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

    private string $baseUri = self::BASE_API_URI.'/libraries/offices';

    public function test_it_can_fetch_all_offices(): void
    {
        $response = $this->withToken($this->authToken)->getJson($this->baseUri);
        $response->assertStatus(200);
    }

    public function test_it_can_search_office_by_name(): void
    {

        $query = 'MAIN';
        $response = $this->withToken($this->authToken)->getJson($this->baseUri."/search?query=$query");
        $response = $response->decodeResponseJson();

        $this->assertCount(1, $response['data']);
    }
}
