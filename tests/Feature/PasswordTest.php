<?php

namespace Tests\Feature;

use App\Models\User;
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

        config(['database.default' => 'one_account']);

        Notification::fake();

        $this->userCreds = [
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('Jeg123123!'),  // hash the password for realistic login
        ];

        $this->user = User::on('one_account')->create($this->userCreds);

        config([
            'auth.providers.users.connection' => 'one_account',
            'auth.providers.users.model' => User::class,
            'auth.passwords.users.provider' => 'users',
        ]);
    }

    /** @throws Exception */
    public function test_users_can_request_a_password_reset_email(): void
    {

        $response = $this->post("$this->baseUri/forgot-password", ['email' => $this->user->email]);

        $response->assertStatus(200);
        Notification::assertSentTo($this->user, QueuedResetPasswordNotification::class);
    }

    //     public function test_users_can_reset_their_passwords(): void
    //     {
    // $token = Password::broker('users_one_account')->createToken($this->user);
    //         $token = app('auth.password.broker')->createToken($this->user);
    //         $newPassword = 'Sample123123';
    //         $input = [
    //             'token' => $token,
    //             'email' => $this->user->email,
    //             'password' => $newPassword,
    //             'password_confirmation' => $newPassword,
    //         ];

    //         $response = $this->postJson("$this->baseUri/reset-password", $input);
    //         $response->assertStatus(200);

    //         // login again
    //         $creds = ['email' => $this->user->email, 'password' => $newPassword];
    //         $response = $this->post("$this->baseUri/tokens", $creds);
    //         $response->assertStatus(200);
    //     }
}
