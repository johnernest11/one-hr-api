<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Enums\WebhookPermission;
use App\Models\User;
use App\Services\ApiKeyManager;
use ConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebhooksTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/webhooks/test-resources';

    private ApiKeyManager $apiKeyService;

    private string $apiKey;

    const API_KEY_HEADER = 'X-API-KEY';

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        Notification::fake();

        /** @var User $user */
        $user = $this->produceUsers();
        $roles = [RoleEnum::ADMIN, RoleEnum::SUPER_USER];
        $user->syncRoles(fake()->randomElement($roles));

        $apiKeyPermissions = ConversionHelper::enumToArray(WebhookPermission::class);
        $this->apiKeyService = resolve(ApiKeyManager::class);
        $this->apiKey = $this->apiKeyService->create(
            'test_name', $user->id, 'test_desc', now()->endOfDay(), $apiKeyPermissions
        )->rawKeyValue;
    }

    public function test_it_can_fetch_test_resources(): void
    {
        $response = $this->withHeader(static::API_KEY_HEADER, $this->apiKey)->getJson($this->baseUri);
        $response->assertStatus(200);
    }

    public function test_it_can_create_test_resources(): void
    {
        $response = $this->withHeader(static::API_KEY_HEADER, $this->apiKey)->postJson($this->baseUri);
        $response->assertStatus(201);
    }

    public function test_it_returns_401_without_an_api_key(): void
    {
        $response = $this->postJson($this->baseUri);
        $response->assertStatus(401);
    }

    public function test_it_returns_401_for_invalid_api_key(): void
    {
        $response = $this->withHeader(static::API_KEY_HEADER, '1|incorrect_key')->postJson($this->baseUri);
        $response->assertStatus(401);
    }

    public function test_it_returns_401_if_api_key_is_disabled(): void
    {
        $user = $this->produceUsers();
        $apiKeyPermissions = ConversionHelper::enumToArray(WebhookPermission::class);
        $apiKey = $this->apiKeyService->create('test_name', $user->id, 'test_desc', now()->endOfDay(), $apiKeyPermissions);
        $rawKey = $apiKey->rawKeyValue;
        $apiKey->active = false;
        $apiKey->save();

        $response = $this->withHeader(static::API_KEY_HEADER, $rawKey)->postJson($this->baseUri);
        $response->assertStatus(401);
    }

    public function test_it_returns_403_for_incorrect_permissions(): void
    {
        // Create a key with not enough permissions (view only)
        $user = $this->produceUsers();
        $permission = [WebhookPermission::VIEW_TEST_RESOURCES];
        $apiKey = $this->apiKeyService
            ->create('test_name', $user->id, 'test_desc', now()->endOfDay(), $permission)
            ->rawKeyValue;

        $response = $this->withHeader(static::API_KEY_HEADER, $apiKey)->postJson($this->baseUri);
        $response->assertStatus(403);
    }

    public function test_it_returns_403_if_webhooks_are_disabled(): void
    {
        config(['webhooks.enabled' => false]);
        $response = $this->withHeader(static::API_KEY_HEADER, $this->apiKey)->postJson($this->baseUri);
        $response->assertStatus(403);
    }
}
