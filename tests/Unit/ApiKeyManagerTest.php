<?php

namespace Tests\Unit;

use App\Enums\WebhookPermission;
use App\Models\ApiKey;
use App\Services\ApiKeyManager;
use Carbon\Carbon;
use ConversionHelper;
use Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyManagerTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyManager $apiKeyService;

    private array $apiKeyPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->apiKeyService = new ApiKeyManager();
        $this->apiKeyPermissions = ConversionHelper::enumToArray(WebhookPermission::class);
    }

    public function test_it_can_create_an_api_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::now()->endOfDay();
        $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);
        $this->assertDatabaseCount('api_keys', 1);
    }

    public function test_a_newly_created_api_key_has_the_rawKeyValue_property(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::now()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);
        $this->assertNotNull($apiKey->rawKeyValue);
    }

    public function test_it_can_validate_correct_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::now()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $isValid = $this->apiKeyService->isValid($apiKey->rawKeyValue);
        $this->assertTrue($isValid);
    }

    public function test_it_can_validate_expired_token(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $isValid = $this->apiKeyService->isValid($apiKey->rawKeyValue);
        $this->assertFalse($isValid);
    }

    public function test_it_can_validate_tampered_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $isValid = $this->apiKeyService->isValid($apiKey->rawKeyValue.'_invalid');
        $this->assertFalse($isValid);
    }

    public function test_it_can_validate_malformed_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $isValid = $this->apiKeyService->isValid('malformed_key');
        $this->assertFalse($isValid);
    }

    public function test_it_can_validate_deactivated_keys(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);
        $apiKey->update(['active' => false]);

        $isValid = $this->apiKeyService->isValid($apiKey->rawKeyValue);
        $this->assertFalse($isValid);
    }

    public function test_it_can_set_active_status(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        // Set Via Model
        $isSuccessful = $this->apiKeyService->setActiveStatus($apiKey, false);
        $this->assertTrue($isSuccessful);

        $updatedKey = $apiKey->fresh();
        $this->assertFalse($updatedKey->active);

        // Set Via ID
        $isSuccessful = $this->apiKeyService->setActiveStatus($apiKey->id, false);
        $this->assertTrue($isSuccessful);

        $updatedKey = $apiKey->fresh();
        $this->assertFalse($updatedKey->active);
    }

    public function test_it_can_soft_delete_records(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $initialCount = ApiKey::count();
        $this->assertEquals(1, $initialCount);

        // Delete via Model
        $this->apiKeyService->destroy($apiKey);
        $countAfterSoftDelete = ApiKey::count();
        $this->assertEquals(0, $countAfterSoftDelete);
        $this->assertEquals(1, ApiKey::withTrashed()->count());

        // Delete via ID
        $newApiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);
        $this->apiKeyService->destroy($newApiKey->id);
        $countAfterSoftDelete = ApiKey::count();
        $this->assertEquals(0, $countAfterSoftDelete);
        $this->assertEquals(2, ApiKey::withTrashed()->count());
    }

    public function test_it_can_update_api_key_details(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $updatedInfo = [
            'name' => $name.'_updated',
            'description' => $description.'_updated',
        ];

        // Update via Model
        $updatedKey = $this->apiKeyService->update($apiKey, $updatedInfo['name'], $updatedInfo['description']);
        $this->assertEquals($updatedInfo['name'], $updatedKey->name);
        $this->assertEquals($updatedInfo['description'], $updatedKey->description);

        // Update via ID
        $updatedInfo = [
            'name' => $name.'_updated_again',
            'description' => $description.'_updated_again',
        ];
        $updatedKey = $this->apiKeyService->update($apiKey->id, $updatedInfo['name'], $updatedInfo['description']);
        $this->assertEquals($updatedInfo['name'], $updatedKey->name);
        $this->assertEquals($updatedInfo['description'], $updatedKey->description);
    }

    public function test_it_can_fetch_all_keys(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();

        // Create 2 records
        $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);
        $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $apiKeys = $this->apiKeyService->all();
        $this->assertCount(2, $apiKeys);
    }

    public function test_it_can_parse_id_from_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $this->assertEquals($apiKey->id, $this->apiKeyService->getIdFromKey($apiKey->rawKeyValue));
    }

    public function test_it_can_parse_raw_value_from_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::yesterday()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt, $this->apiKeyPermissions);

        $value = $this->apiKeyService->getValueFromKey($apiKey->rawKeyValue);
        $isMatched = Hash::check($value, $apiKey->key);

        $this->assertTrue($isMatched);
    }
}
