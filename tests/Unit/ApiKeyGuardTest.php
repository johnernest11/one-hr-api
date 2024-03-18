<?php

namespace Tests\Unit;

use App\Auth\ApiKeyGuard;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\ApiKey\ApiKeyServiceInterface;
use Auth;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiKeyGuardTest extends TestCase
{
    use RefreshDatabase;

    const API_KEY_HEADER = 'X-API-KEY';

    private ApiKeyServiceInterface $apiKeyService;

    private User $user;

    private string $apiKeyName;

    private string $apiKeyDescription;

    private Carbon $apiKeyExpiration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->apiKeyService = resolve(ApiKeyServiceInterface::class);

        $this->user = $this->produceUsers();
        $this->apiKeyName = fake()->domainName;
        $this->apiKeyDescription = fake()->text;
        $this->apiKeyExpiration = Carbon::now()->endOfDay();
    }

    public function test_it_can_check_valid_api_key_from_header(): void
    {
        $apiKey = $this->apiKeyService->create(
            $this->apiKeyName, $this->user->id, $this->apiKeyDescription, $this->apiKeyExpiration
        );

        $request = new Request();
        $request->headers->set(static::API_KEY_HEADER, $apiKey->rawKeyValue);

        $guard = new ApiKeyGuard($request, Auth::createUserProvider('api_keys'));
        $isValid = $guard->check();
        $this->assertTrue($isValid);
    }

    public function test_it_can_check_malformed_api_key_from_header()
    {
        $this->apiKeyService->create(
            $this->apiKeyName, $this->user->id, $this->apiKeyDescription, $this->apiKeyExpiration
        );

        $request = new Request();
        $request->headers->set(static::API_KEY_HEADER, 'something_else');

        $guard = new ApiKeyGuard($request, Auth::createUserProvider('api_keys'));
        $isValid = $guard->check();
        $this->assertFalse($isValid);
    }

    public function test_it_can_check_tampered_api_key_from_header(): void
    {
        $apiKey = $this->apiKeyService->create(
            $this->apiKeyName, $this->user->id, $this->apiKeyDescription, $this->apiKeyExpiration
        );

        $request = new Request();
        $request->headers->set(static::API_KEY_HEADER, $apiKey->rawKeyValue.'K1');

        $guard = new ApiKeyGuard($request, Auth::createUserProvider('api_keys'));
        $isValid = $guard->check();
        $this->assertFalse($isValid);
    }

    public function test_it_can_fetch_the_user(): void
    {
        $apiKey = $this->apiKeyService->create(
            $this->apiKeyName, $this->user->id, $this->apiKeyDescription, $this->apiKeyExpiration
        );

        $request = new Request();
        $request->headers->set(static::API_KEY_HEADER, $apiKey->rawKeyValue);

        $guard = new ApiKeyGuard($request, Auth::createUserProvider('api_keys'));

        // The API Key acts as authenticatable user
        $apiKeyFromGuard = $guard->user();

        $this->assertEquals($apiKey->key, $apiKeyFromGuard->key);
        $this->assertTrue($apiKeyFromGuard instanceof ApiKey);
    }

    public function test_it_can_check_if_there_is_a_user(): void
    {
        $apiKey = $this->apiKeyService->create(
            $this->apiKeyName, $this->user->id, $this->apiKeyDescription, $this->apiKeyExpiration
        );

        $request = new Request();
        $request->headers->set(static::API_KEY_HEADER, $apiKey->rawKeyValue);

        $guard = new ApiKeyGuard($request, Auth::createUserProvider('api_keys'));

        $this->assertTrue($guard->hasUser());
    }

    public function test_it_can_check_if_guest(): void
    {
        $apiKey = $this->apiKeyService->create(
            $this->apiKeyName, $this->user->id, $this->apiKeyDescription, $this->apiKeyExpiration
        );

        $request = new Request();
        $request->headers->set(static::API_KEY_HEADER, $apiKey->rawKeyValue);
        $guard = new ApiKeyGuard($request, Auth::createUserProvider('api_keys'));
        $this->assertFalse($guard->guest());

        // Without the request header
        $guard = new ApiKeyGuard(request(), Auth::createUserProvider('api_keys'));
        $this->assertTrue($guard->guest());
    }

    public function test_it_can_set_user(): void
    {
        $request = new Request();
        $guard = new ApiKeyGuard($request, Auth::createUserProvider('api_keys'));
        $this->assertFalse($guard->hasUser());

        // Create an API Key that acts as a user
        $apiKey = $this->apiKeyService->create(
            $this->apiKeyName, $this->user->id, $this->apiKeyDescription, $this->apiKeyExpiration
        );

        $guard->setUser($apiKey);
        $this->assertEquals($apiKey->key, $guard->user()->key);
    }
}
