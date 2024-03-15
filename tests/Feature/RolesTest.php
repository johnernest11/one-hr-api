<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Throwable;

class RolesTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private string $baseUri = self::BASE_API_URI.'/roles';

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        $user = $this->produceUsers();
        $user->syncRoles(RoleEnum::ADMIN);

        $authSanctumService = resolve(AuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $authSanctumService->generateToken($user, $authTokenExpiration, 'mock_token');
    }

    /** @throws Throwable */
    public function test_it_can_fetch_all_roles(): void
    {
        $response = $this->withToken($this->authToken)->getJson($this->baseUri);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson();

        // We seed the ff: standard_user, admin, system_support, super_user
        $this->assertCount(4, $response['data']);
    }
}
