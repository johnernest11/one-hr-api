<?php

namespace Tests\Unit\MFA;

use App\Notifications\EmailOtpNotification;
use App\Services\Verification\Methods\EmailVerificationChannel;
use Config;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Notification;
use Tests\TestCase;

class EmailVerificationChannelTest extends TestCase
{
    use RefreshDatabase;

    private EmailVerificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->channel = new EmailVerificationChannel();
    }

    public function test_it_can_generate_code(): void
    {
        $user = $this->produceUsers();
        $code = $this->channel->generateCode($user);
        $this->assertNotNull($code);
    }

    public function test_it_can_verify_code(): void
    {
        $user = $this->produceUsers();
        $code = $this->channel->generateCode($user);
        $correct = $this->channel->verifyCode($user, $code);
        $this->assertTrue($correct);
    }

    public function test_verification_fails_after_code_expiration(): void
    {
        Config::set('auth.mfa_codes.expiration.email', 1);
        $user = $this->produceUsers();
        $code = $this->channel->generateCode($user);

        sleep(1);
        $correct = $this->channel->verifyCode($user, $code);
        $this->assertFalse($correct);
    }

    /**
     * @throws Exception
     */
    public function test_it_can_send_code_via_email_notification(): void
    {
        Notification::fake();

        $user = $this->produceUsers();
        $code = $this->channel->generateCode($user);
        $this->channel->sendCode($user, $code);

        Notification::assertSentTo($user, EmailOtpNotification::class);
    }
}
