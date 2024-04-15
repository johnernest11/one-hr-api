<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserActivationTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/users';

    private string $authToken;

    private PersistentAuthTokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');

        /** @var User $user */
        $user = $this->produceUsers();
        $roles = [RoleEnum::ADMIN, RoleEnum::SUPER_USER];
        $user->syncRoles(fake()->randomElement($roles));

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $this->tokenManager->generateToken($user, $authTokenExpiration, 'mock_token');
    }

    public function test_deactivated_users_cannot_login(): void
    {
        $user = $this->produceUsers();
        $password = 'Sample123123';
        $email = fake()->unique()->safeEmail();
        $user->update(['active' => false, 'password' => $password, 'email' => $email]);

        $response = $this->postJson(self::BASE_API_URI.'/auth/tokens', ['email' => $email, 'password' => $password]);
        $response->assertStatus(403);
    }

    public function test_deactivated_user_cannot_request_password_reset(): void
    {
        $user = $this->produceUsers();
        $email = fake()->unique()->safeEmail();
        $user->update(['active' => false, 'email' => $email]);
        $response = $this->postJson(self::BASE_API_URI.'/auth/forgot-password', ['email' => $email]);
        $response->assertStatus(403);
    }

    public function test_deactivated_user_cannot_access_endpoints_that_need_auth(): void
    {
        $user = $this->produceUsers();
        $email = fake()->unique()->safeEmail();
        $user->update(['active' => false, 'email' => $email]);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $token = $this->tokenManager->generateToken($user, $authTokenExpiration, 'mock_token');

        $response = $this->withToken($token)->getJson(self::BASE_API_URI.'/profile');
        $response->assertStatus(403);
    }
}
