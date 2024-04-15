<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\SexualCategory;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\Auth\QueuedVerifyEmailNotification;
use App\Notifications\WelcomeNotification;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Throwable;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/auth';

    private array $userCreds;

    private array $userProfile;

    private User $user;

    private string $authToken;

    private PersistentAuthTokenManager $tokenManager;

    public function setUp(): void
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

        $this->tokenManager = resolve(PersistentAuthTokenManager::class);
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->authToken = $this->tokenManager->generateToken($this->user, $authTokenExpiration, 'mock_token');
    }

    /** Start */

    /** @throws Throwable */
    public function test_user_can_request_an_access_token_via_email(): void
    {
        $response = $this->postJson("$this->baseUri/tokens", [
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

        $response = $this->postJson("$this->baseUri/tokens", [
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

        $response = $this->postJson("$this->baseUri/register", $input);
        $response->assertStatus(201);

        $createdUser = User::find($response->decodeResponseJson()['data']['user']['id']);

        // Email notifications
        Notification::assertSentTo($createdUser, WelcomeNotification::class);
        Notification::assertSentTo($createdUser, QueuedVerifyEmailNotification::class);
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

        $response = $this->postJson("$this->baseUri/register", $input);
        $roles = $response->decodeResponseJson()['data']['user']['roles'];
        $this->assertCount(1, $roles);
        $this->assertEquals(Role::STANDARD_USER->value, $roles[0]['name']);
    }

    /** @throws Throwable */
    public function test_user_can_request_access_token_with_user_info(): void
    {
        $response = $this->postJson("$this->baseUri/tokens", [
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

    /** @throws Throwable */
    public function test_user_can_fetch_all_access_tokens_owned(): void
    {
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->tokenManager->generateToken($this->user, $authTokenExpiration, 'mock_token');
        $this->tokenManager->generateToken($this->user, $authTokenExpiration, 'mock_token');

        $response = $this->withToken($this->authToken)->getJson("$this->baseUri/tokens", $this->userCreds);

        $response->assertStatus(200);

        // We created 2 tokens + 1 more in the setup() method
        $response->assertJsonCount(3, 'data');
    }

    /** @throws Throwable */
    public function test_user_must_be_logged_in_to_fetch_tokens(): void
    {
        $response = $this->getjson("$this->baseUri/tokens", $this->userCreds);
        $response->assertStatus(401);
    }

    public function test_user_can_invalidate_current_access_token(): void
    {
        $response = $this->withToken($this->authToken)->delete("$this->baseUri/tokens");

        $response->assertStatus(204);
        $this->assertEquals(0, $this->user->tokens()->count());
    }

    public function test_user_can_invalidate_specific_access_tokens(): void
    {
        $tokenId = $this->user->tokens()->first()->id;
        $response = $this->withToken($this->authToken)->postJson("$this->baseUri/tokens/invalidate", ['token_ids' => [$tokenId]]);

        $response->assertStatus(204);
        $this->assertEquals(0, $this->user->tokens()->count());
    }

    public function test_user_can_invalidate_all_access_tokens(): void
    {
        // create multiple tokens
        $authTokenExpiration = now()->addMinutes(config('sanctum.expiration'));
        $this->tokenManager->generateToken($this->user, $authTokenExpiration, 'mock_token');
        $this->tokenManager->generateToken($this->user, $authTokenExpiration, 'mock_token');

        $response = $this
            ->withToken($this->authToken)
            ->postJson("$this->baseUri/tokens/invalidate", ['token_ids' => ['*']]);

        $response->assertStatus(204);
        $this->assertEquals(0, $this->user->tokens()->count());
    }

    public function test_protected_routes_return_401_when_token_expires(): void
    {
        // This will expire after 1 second
        $authTokenExpiration = now()->addSecond();
        $this->authToken = (resolve(PersistentAuthTokenManager::class))
            ->generateToken($this->user, $authTokenExpiration);

        // The route that fetches the auth token is protected
        $response = $this->withToken($this->authToken)
            ->getJson("$this->baseUri/tokens", $this->userCreds);

        $response->assertStatus(200);

        // We let the token expire
        sleep(1);

        $response = $this->withToken($this->authToken)
            ->getJson("$this->baseUri/tokens", $this->userCreds);

        $response->assertStatus(401);
    }

    public function test_it_returns_401_if_the_token_is_malformed(): void
    {
        // The route that fetches the auth token is protected
        $response = $this->withToken('incorrect_token')
            ->getJson("$this->baseUri/tokens", $this->userCreds);

        $response->assertStatus(401);
    }
}
