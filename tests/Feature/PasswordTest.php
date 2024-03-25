<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\Auth\QueuedResetPasswordNotification;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/auth';

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
    }

    /** @throws Exception */
    public function test_users_can_request_a_password_reset_email(): void
    {
        $response = $this->post("$this->baseUri/forgot-password", ['email' => $this->user->email]);
        $response->assertStatus(200);

        Notification::assertSentTo($this->user, QueuedResetPasswordNotification::class);
    }

    public function test_users_can_reset_their_passwords(): void
    {
        $token = app('auth.password.broker')->createToken($this->user);
        $newPassword = 'Sample123123';
        $input = [
            'token' => $token,
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        $response = $this->postJson("$this->baseUri/reset-password", $input);
        $response->assertStatus(200);

        // login again
        $creds = ['email' => $this->user->email, 'password' => $newPassword];
        $response = $this->post("$this->baseUri/tokens", $creds);
        $response->assertStatus(200);
    }
}
