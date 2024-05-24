<?php

namespace Tests\Unit\MFA;

use App\Enums\VerificationMethod;
use App\Models\VerificationFactor;
use App\Notifications\EmailOtpNotification;
use App\Services\Verification\Methods\EmailVerificationChannel;
use Config;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Notification;
use Tests\TestCase;
use Throwable;

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
        Config::set('auth.verification_codes.expiration.email', 1);
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

    /**
     * @throws Throwable
     */
    public function test_it_can_complete_enrollment(): void
    {
        $user = $this->produceUsers();
        $this->channel->getOrCreateSecret($user);

        $factor = VerificationFactor::where('type', VerificationMethod::EMAIL_CHANNEL)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Email Verification Factor automatically enrolls users
        $this->assertNotNull($factor->enrolled_at);

        // Un-enroll for test
        $factor->update(['enrolled_at' => null]);
        $factor->refresh();

        $this->channel->enrollUser($user, VerificationMethod::EMAIL_CHANNEL);
        $factor->refresh();
        $this->assertNotNull($factor->enrolled_at);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_check_if_user_is_enrolled(): void
    {
        $user = $this->produceUsers();
        $this->channel->getOrCreateSecret($user);
        $isEnrolled = $this->channel->userIsEnrolled($user, VerificationMethod::EMAIL_CHANNEL);
        $this->assertTrue($isEnrolled);

        // Un-enroll for test
        VerificationFactor::where('type', VerificationMethod::EMAIL_CHANNEL)
            ->where('user_id', $user->id)
            ->update(['enrolled_at' => null]);

        $isEnrolled = $this->channel->userIsEnrolled($user, VerificationMethod::EMAIL_CHANNEL);
        $this->assertFalse($isEnrolled);
    }
}
