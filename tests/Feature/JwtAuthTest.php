<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SexualCategory;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\Auth\QueuedVerifyEmailNotification;
use App\Notifications\WelcomeNotification;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use JWT;
use Tests\TestCase;
use Throwable;

class JwtAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/auth';

    private array $userCreds;

    private array $userProfile;

    private User $user;

    private string $authToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        Notification::fake();

        $this->userCreds = [
            'email' => 'jegramos-test@sample.com',
            'password' => 'Jeg123123!',
        ];

        $this->userProfile = ['mobile_number' => '+639064647295'];

        $this->user = User::factory($this->userCreds)
            ->has(UserProfile::factory())
            ->create();
        $this->user->syncRoles([Role::STANDARD_USER]);

        $jwtAuthService = resolve(AuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('jwt.lifetime_minutes'));
        $this->authToken = $jwtAuthService->generateToken($this->user, $authTokenExpiration, 'mock_token');
    }

    /** @throws Throwable */
    public function test_user_can_request_an_access_token_via_email(): void
    {
        $response = $this->postJson("$this->baseUri/tokens?auth_type=jwt", [
            'email' => $this->userCreds['email'],
            'password' => $this->userCreds['password'],
        ]);

        $result = $response->decodeResponseJson();
        $this->assertArrayHasKey('token', $result['data']);
        $response->assertStatus(200);
    }

    /** @throws Throwable */
    public function test_user_can_request_an_access_token_via_mobile_number(): void
    {
        $user = User::where('email', $this->userCreds['email'])->first();
        $user->userProfile()->update($this->userProfile);

        $response = $this->postJson("$this->baseUri/tokens?auth_type=jwt", [
            'mobile_number' => $this->userProfile['mobile_number'],
            'password' => $this->userCreds['password'],
        ]);

        $result = $response->decodeResponseJson();

        $this->assertArrayHasKey('token', $result['data']);
        $response->assertStatus(200);
    }

    /**
     * @throws Throwable
     */
    public function test_users_receive_email_notifications_when_they_register(): void
    {
        $input = [
            'email' => fake()->unique()->email(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'password' => 'SamplePass123',
            'password_confirmation' => 'SamplePass123',
            'mobile_number' => '+639064648112',
            'sex' => fake()->randomElement(array_column(SexualCategory::cases(), 'value')),
            'birthday' => fake()->date(),
        ];

        $response = $this->postJson("$this->baseUri/register?auth_type=jwt", $input);
        $response->assertStatus(201);

        $createdUser = User::find($response->decodeResponseJson()['data']['user']['id']);

        // Email notifications
        Notification::assertSentTo($createdUser, WelcomeNotification::class);
        Notification::assertSentTo($createdUser, QueuedVerifyEmailNotification::class);
    }

    /** @throws Throwable */
    public function test_the_returned_token_has_the_user_id_in_the_payload(): void
    {
        $user = User::where('email', $this->userCreds['email'])->first();
        $user->userProfile()->update($this->userProfile);

        $response = $this->postJson("$this->baseUri/tokens?auth_type=jwt", [
            'mobile_number' => $this->userProfile['mobile_number'],
            'password' => $this->userCreds['password'],
        ]);

        $result = $response->decodeResponseJson();
        $parsedToken = JWT::parse($result['data']['token']);

        // The payload should have the user_id key
        $parsedUserId = $parsedToken->getPayload()['user_id'];
        $this->assertEquals($this->user->id, $parsedUserId);
    }

    /** @throws Throwable */
    public function test_a_user_created_via_registration_is_always_a_standard_user(): void
    {
        $input = [
            'email' => fake()->unique()->email(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'password' => 'SamplePass123',
            'password_confirmation' => 'SamplePass123',
            'mobile_number' => '+639064648112',
            'sex' => fake()->randomElement(array_column(SexualCategory::cases(), 'value')),
            'birthday' => fake()->date(),
        ];

        $response = $this->postJson("$this->baseUri/register?auth_type=jwt", $input);
        $roles = $response->decodeResponseJson()['data']['user']['roles'];
        $this->assertCount(1, $roles);
        $this->assertEquals(Role::STANDARD_USER->value, $roles[0]['name']);
    }

    /** @throws Throwable */
    public function test_user_can_request_access_token_with_user_info(): void
    {
        $response = $this->post("$this->baseUri/tokens?auth_type=jwt", [
            'email' => $this->userCreds['email'],
            'password' => $this->userCreds['password'],
            'with_user' => true,
        ]);

        $result = $response->decodeResponseJson();
        $this->assertArrayHasKey('user', $result['data']);
        $response->assertStatus(200);
    }

    /** @throws Throwable */
    public function test_user_can_request_access_token_with_client_name(): void
    {
        $clientName = "Jeg's Chrome Browser";
        $response = $this->postJson("$this->baseUri/tokens", [
            'email' => $this->userCreds['email'],
            'password' => $this->userCreds['password'],
            'client_name' => $clientName,
        ]);

        $result = $response->decodeResponseJson();
        $this->assertEquals($clientName, $result['data']['token_name']);
        $response->assertStatus(200);
    }

    public function test_it_returns_401_for_protected_routes_if_unauthenticated(): void
    {
        // We visit the profile route
        $response = $this->getJson('api/v1/profile');
        $response->assertStatus(401);
    }

    public function test_it_returns_200_for_protected_routes_if_authenticated(): void
    {
        // It's a 200 if the token is properly added in the Authorization Header (Bearer Token)
        $response = $this->withToken($this->authToken)->getJson('api/v1/profile');
        $response->assertStatus(200);
    }

    public function test_it_returns_401_if_token_is_malformed(): void
    {
        // The route that fetches the auth token is protected
        $response = $this->withToken('invalid_token')->getJson('api/v1/profile');
        $response->assertStatus(401);
    }

    public function test_it_returns_401_if_token_is_tampered_with_fake_signing_key(): void
    {
        // We change the JWT signing key by appending 'W' in our app config to simulate a mismatch
        Config::set('jwt.signing_key', env('JWT_SIGNING_KEY').'W');
        $response = $this->withToken($this->authToken)->getJson('api/v1/profile');
        $response->assertStatus(401);

        // If we change it back it should be 200 again
        Config::set('jwt.signing_key', env('JWT_SIGNING_KEY'));
        $response = $this->withToken($this->authToken)->getJson('api/v1/profile');
        $response->assertStatus(200);
    }

    public function test_protected_routes_return_401_when_token_expires(): void
    {
        // This will expire after 1 second
        $authTokenExpiration = now()->addSecond();
        $this->authToken = (resolve(AuthTokenManager::class))
            ->generateToken($this->user, $authTokenExpiration);

        // The route that fetches the auth token is protected
        $response = $this->withToken($this->authToken)->getJson('api/v1/profile');
        $response->assertStatus(200);

        // We let the token expire
        sleep(1);
        $response = $this->withToken($this->authToken)->getJson('api/v1/profile');
        $response->assertStatus(401);
    }
}
