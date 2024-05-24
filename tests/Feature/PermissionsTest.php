<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use App\Enums\WebhookPermission;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use ConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/permissions';

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

    /**
     * @throws Throwable
     */
    public function test_it_fetches_user_permissions_by_default(): void
    {
        $response = $this->withToken($this->authToken)->getJson($this->baseUri);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson();

        // We count the number of permissions in the Permission enum class
        $count = count(ConversionHelper::enumToArray(Permission::class));
        $this->assertCount($count, $response['data']);
    }

    /**
     * @throws Throwable
     */
    public function test_it_fetches_webhook_permissions_if_type_is_api_keys(): void
    {
        $response = $this->withToken($this->authToken)->getJson($this->baseUri.'?type=api_keys');
        $response->assertStatus(200);

        $response = $response->decodeResponseJson();

        // We count the number of permissions in the WebhookPermission enum class
        $count = count(ConversionHelper::enumToArray(WebhookPermission::class));
        $this->assertCount($count, $response['data']);
    }

    /**
     * @throws Throwable
     */
    public function test_it_fetches_all_permissions_if_type_is_all(): void
    {
        $response = $this->withToken($this->authToken)->getJson($this->baseUri.'?type=all');
        $response->assertStatus(200);

        $response = $response->decodeResponseJson();

        // We count the number of permissions in the WebhookPermission enum class
        $webhookPermissionsCount = count(ConversionHelper::enumToArray(WebhookPermission::class));
        $userPermissionsCount = count(ConversionHelper::enumToArray(Permission::class));
        $total = $webhookPermissionsCount + $userPermissionsCount;
        $this->assertCount($total, $response['data']);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_validate_the_type_query_param(): void
    {
        $response = $this->withToken($this->authToken)->getJson($this->baseUri.'?type=not_exists');
        $response->assertStatus(422);

        // Fetch users permissions
        $response = $this->withToken($this->authToken)->getJson($this->baseUri.'?type=users');
        $response->assertStatus(200);

        // Fetch API key permissions
        $response = $this->withToken($this->authToken)->getJson($this->baseUri.'?type=api_keys');
        $response->assertStatus(200);

        // Fetch all permissions
        $response = $this->withToken($this->authToken)->getJson($this->baseUri.'?type=all');
        $response->assertStatus(200);
    }
}
