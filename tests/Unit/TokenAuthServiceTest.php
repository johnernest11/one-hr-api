<?php

namespace Tests\Unit;

use App\Interfaces\Authentication\TokenAuthServiceInterface;
use App\Models\User;
use App\Services\Authentication\TokenAuthService;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TokenAuthServiceTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private TokenAuthServiceInterface $authService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->authService = new TokenAuthService();

        // Create a test user
        $this->user = $this->produceUsers();
    }

    public function test_it_can_create_an_access_token_for_a_user(): void
    {
        $tokenName = 'Safari Mac';

        $user = $this->authService->bindAuthToken($this->user, $tokenName);
        $this->assertNotNull($user);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $record = DB::table('personal_access_tokens')->first();
        $this->assertEquals($tokenName, $record->name);
        $this->assertEquals($user['user']['id'], $record->tokenable_id);
    }

    public function test_it_can_fetch_active_access_tokens(): void
    {
        $tokenName = 'Safari Mac';

        // Bind 2 tokens
        $this->authService->bindAuthToken($this->user, $tokenName);
        $this->authService->bindAuthToken($this->user, $tokenName);

        $tokens = $this->authService->getUserAuthTokens($this->user);
        $this->assertCount(2, $tokens);
    }
}
