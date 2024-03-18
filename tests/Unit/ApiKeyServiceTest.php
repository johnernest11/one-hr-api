<?php

namespace Tests\Unit;

use App\Models\ApiKey;
use App\Services\ApiKey\ApiKeyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->apiKeyService = new ApiKeyService(new ApiKey());
    }

    public function test_it_can_create_an_api_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::now()->endOfDay();
        $this->apiKeyService->create($name, $user->id, $description, $expiresAt);
        $this->assertDatabaseCount('api_keys', 1);
    }

    public function test_a_newly_created_api_key_has_the_rawKeyValue_property(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::now()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt);
        $this->assertNotNull($apiKey->rawKeyValue);
    }

    public function test_it_can_validate_correct_key(): void
    {
        $user = $this->produceUsers();
        $name = fake()->domainName;
        $description = fake()->text;
        $expiresAt = Carbon::now()->endOfDay();
        $apiKey = $this->apiKeyService->create($name, $user->id, $description, $expiresAt);

        $isValid = $this->apiKeyService->isValid($apiKey->rawKeyValue);
        $this->assertTrue($isValid);
    }
}
