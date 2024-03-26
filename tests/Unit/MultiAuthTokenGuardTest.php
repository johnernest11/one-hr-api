<?php

namespace Tests\Unit;

use App\Auth\MultiTokenGuard;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MultiAuthTokenGuardTest extends TestCase
{
    use RefreshDatabase;

    private PersistentAuthTokenManager $sanctumAuthService;

    private AuthTokenManager $jwtAuthService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->sanctumAuthService = resolve(PersistentAuthTokenManager::class);
        $this->jwtAuthService = resolve(AuthTokenManager::class);

        // seed 2 dummy users every time
        $this->produceUsers(2);
    }

    public function test_it_can_check_valid_sanctum_bearer_token(): void
    {
        $user = $this->produceUsers();
        $token = $this->sanctumAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $isValid = $guard->check();
        $this->assertTrue($isValid);
    }

    public function test_it_can_check_invalid_sanctum_bearer_token(): void
    {
        $user = $this->produceUsers();
        $token = $this->sanctumAuthService->generateToken($user, now()->addMinutes(5));
        $token .= '_invalid';
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $isValid = $guard->check();
        $this->assertFalse($isValid);
    }

    public function test_it_can_check_valid_jwt_bearer_token(): void
    {
        $user = $this->produceUsers();
        $token = $this->jwtAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $isValid = $guard->check();
        $this->assertTrue($isValid);
    }

    public function test_it_can_check_invalid_jwt_bearer_token(): void
    {
        $user = $this->produceUsers();
        $token = $this->sanctumAuthService->generateToken($user, now()->addMinutes(5));
        $token .= '_invalid';
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $isValid = $guard->check();
        $this->assertFalse($isValid);
    }

    public function test_it_can_get_user_via_sanctum(): void
    {
        $user = $this->produceUsers();
        $token = $this->sanctumAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $authUser = $guard->user();

        $this->assertEquals($user->id, $authUser->id);
    }

    public function test_it_can_get_user_via_jwt(): void
    {
        $user = $this->produceUsers();
        $token = $this->jwtAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);

        $authUser = $guard->user();
        $this->assertEquals($user->id, $authUser->id);
    }

    public function test_it_can_check_if_there_is_a_user_via_sanctum(): void
    {
        $user = $this->produceUsers();
        $token = $this->sanctumAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);

        $this->assertTrue($guard->hasUser());
    }

    public function test_it_can_check_if_there_is_a_user_via_jwt(): void
    {
        $user = $this->produceUsers();
        $token = $this->jwtAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);

        $this->assertTrue($guard->hasUser());
    }

    public function test_it_can_check_if_guest_via_sanctum(): void
    {
        $user = $this->produceUsers();
        $token = $this->sanctumAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $this->assertFalse($guard->guest());

        // Without the Bearer token
        $guard = new MultiTokenGuard(new Request());
        $this->assertTrue($guard->guest());
    }

    public function test_it_can_check_if_guest_via_jwt(): void
    {
        $user = $this->produceUsers();
        $token = $this->jwtAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $this->assertFalse($guard->guest());

        // Without the Bearer token
        $guard = new MultiTokenGuard(new Request());
        $this->assertTrue($guard->guest());
    }

    public function test_it_can_set_user(): void
    {
        $request = new Request();
        $guard = new MultiTokenGuard($request);

        $this->assertFalse($guard->hasUser());

        $user = $this->produceUsers();
        $guard->setUser($user);
        $this->assertEquals($user->id, $guard->user()->id);
    }

    public function test_it_can_not_check_sanctum_token_if_config_is_turned_off(): void
    {
        config(['auth.mechanism.sanctum_enabled' => false]);

        $user = $this->produceUsers();
        $token = $this->sanctumAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $this->assertFalse($guard->check());
    }

    public function test_it_can_not_check_jwt_token_if_config_is_turned_off(): void
    {
        config(['auth.mechanism.jwt_enabled' => false]);

        $user = $this->produceUsers();
        $token = $this->jwtAuthService->generateToken($user, now()->addMinutes(5));
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");

        $guard = new MultiTokenGuard($request);
        $this->assertFalse($guard->check());
    }
}
