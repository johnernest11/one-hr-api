<?php

namespace Tests\Unit;

use App\Auth\ApiKeyProvider;
use App\Services\ApiKey\ApiKeyServiceInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyProviderTest extends TestCase
{
    use RefreshDatabase;

    private UserProvider $apiKeyProvider;

    private ApiKeyServiceInterface $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->apiKeyService = resolve(ApiKeyServiceInterface::class);
        $this->apiKeyProvider = new ApiKeyProvider($this->apiKeyService);
    }

    public function test_can_retrieve_by_id(): void
    {
        $user = $this->produceUsers();

        // Create 2 keys
        $this->apiKeyService->create(fake()->domainName, $user->id, fake()->text, now()->endOfDay());
        $apikey = $this->apiKeyService->create(fake()->domainName, $user->id, fake()->text, now()->endOfDay());

        $foundKey = $this->apiKeyProvider->retrieveById($apikey->id);

        $this->assertEquals($foundKey->id, $apikey->id);
    }

    public function test_it_can_retrieve_by_token(): void
    {
        $user = $this->produceUsers();

        // Create 2 keys
        $this->apiKeyService->create(fake()->domainName, $user->id, fake()->text, now()->endOfDay());
        $apikey = $this->apiKeyService->create(fake()->domainName, $user->id, fake()->text, now()->endOfDay());

        $identified = $this->apiKeyService->getIdFromKey($apikey->rawKeyValue);
        $rawValue = $this->apiKeyService->getValueFromKey($apikey->rawKeyValue);
        $foundKey = $this->apiKeyProvider->retrieveByToken($identified, $rawValue);

        $this->assertEquals($apikey->id, $foundKey->id);
    }

    public function test_it_does_not_return_deactivate_keys(): void
    {
        $user = $this->produceUsers();
        $apikey = $this->apiKeyService->create(fake()->domainName, $user->id, fake()->text, now()->endOfDay());
        $apikey->update(['active' => false]);

        // Via ID
        $foundKey = $this->apiKeyProvider->retrieveById($apikey->id);
        $this->assertNull($foundKey);

        // Via Token
        $identified = $this->apiKeyService->getIdFromKey($apikey->rawKeyValue);
        $rawValue = $this->apiKeyService->getValueFromKey($apikey->rawKeyValue);
        $foundKey = $this->apiKeyProvider->retrieveByToken($identified, $rawValue);
        $this->assertNull($foundKey);
    }

    public function test_it_does_not_return_expired_keys(): void
    {
        $user = $this->produceUsers();
        $apikey = $this->apiKeyService->create(fake()->domainName, $user->id, fake()->text, Carbon::yesterday()->endOfDay());

        // Via ID
        $foundKey = $this->apiKeyProvider->retrieveById($apikey->id);
        $this->assertNull($foundKey);

        // Via Token
        $identified = $this->apiKeyService->getIdFromKey($apikey->rawKeyValue);
        $rawValue = $this->apiKeyService->getValueFromKey($apikey->rawKeyValue);
        $foundKey = $this->apiKeyProvider->retrieveByToken($identified, $rawValue);
        $this->assertNull($foundKey);
    }

    public function test_it_returns_null_if_id_not_found(): void
    {
        $user = $this->produceUsers();
        $apikey = $this->apiKeyService->create(fake()->domainName, $user->id, fake()->text, Carbon::yesterday()->endOfDay());
        $nonExistentId = $apikey->id + 1;

        // Via ID
        $foundKey = $this->apiKeyProvider->retrieveById($nonExistentId);
        $this->assertNull($foundKey);

        // Via Token
        $rawValue = $this->apiKeyService->getValueFromKey($apikey->rawKeyValue);
        $foundKey = $this->apiKeyProvider->retrieveByToken($nonExistentId, $rawValue);
        $this->assertNull($foundKey);
    }
}
