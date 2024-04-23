<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Role as RoleEnum;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use ConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class RolesTest extends TestCase
{
    use RefreshDatabase;

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

        // We count the number of permissions in the Role enum class
        $count = count(ConversionHelper::enumToArray(Role::class));
        $this->assertCount($count, $response['data']);
    }
}
