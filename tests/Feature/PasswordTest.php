<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\Auth\QueuedResetPasswordNotification;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/auth';

    protected function setUp(): void
    {
        parent::setUp();

        \DB::setDefaultConnection('one_account');
        Notification::fake();

        $this->userCreds = [
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('Jeg123123!'),
        ];
        $this->user = User::factory()
            ->create($this->userCreds);
    }

    /** @throws Exception */
    public function test_users_can_request_a_password_reset_email(): void
    {

        $response = $this->post("$this->baseUri/forgot-password", ['email' => $this->user->email]);

        $response->assertStatus(200);
        Notification::assertSentTo($this->user, QueuedResetPasswordNotification::class);
    }

    public function test_deactivated_user_cannot_request_password_reset(): void
    {
        $this->user->update(['active' => false, 'email' => $this->user->email]);
        $response = $this->postJson("$this->baseUri/forgot-password", ['email' => $this->user->email]);

        $response->assertStatus(403);
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
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'token']);
        $this->user->assignRole('admin');
        $creds = ['email' => $this->user->email, 'password' => $newPassword];
        $response = $this->post("$this->baseUri/tokens", $creds);

        $response->assertStatus(200);
    }
}
