<?php

namespace Tests\Unit\MFA;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Services\Verification\Methods\GAuthenticatorVerificationApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class GAuthenticatorVerificationAppTest extends TestCase
{
    use RefreshDatabase;

    private GAuthenticatorVerificationApp $authenticator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->authenticator = new GAuthenticatorVerificationApp();
        $this->user = $this->produceUsers();
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_generate_base64_qr_code(): void
    {
        $qrCode = $this->authenticator->generateQrCode($this->user);
        $this->assertIsString($qrCode);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_generate_secret(): void
    {
        $this->authenticator->getOrCreateSecret($this->user);
        $this->assertDatabaseCount('verification_factors', 1);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_complete_enrollment(): void
    {
        $this->authenticator->getOrCreateSecret($this->user);
        $factor = VerificationFactor::where('type', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->where('user_id', $this->user->id)
            ->firstOrFail();
        $this->assertNull($factor->enrolled_at);

        $this->authenticator->enrollUser($this->user, VerificationMethod::GOOGLE_AUTHENTICATOR);
        $factor->refresh();
        $this->assertNotNull($factor->enrolled_at);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_check_if_user_is_enrolled(): void
    {
        $this->authenticator->getOrCreateSecret($this->user);
        $isEnrolled = $this->authenticator->userIsEnrolled($this->user, VerificationMethod::GOOGLE_AUTHENTICATOR);
        $this->assertFalse($isEnrolled);

        $this->authenticator->enrollUser($this->user, VerificationMethod::GOOGLE_AUTHENTICATOR);
        $isEnrolled = $this->authenticator->userIsEnrolled($this->user, VerificationMethod::GOOGLE_AUTHENTICATOR);
        $this->assertTrue($isEnrolled);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_generate_backup_codes(): void
    {
        $this->authenticator->getOrCreateSecret($this->user);
        $codes = $this->authenticator->generateBackupCodes($this->user, 12);
        $this->assertCount(12, $codes);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_verify_backup_code(): void
    {
        $this->authenticator->getOrCreateSecret($this->user);
        $codes = $this->authenticator->generateBackupCodes($this->user);
        $success = $this->authenticator->verifyBackupCode($this->user, $codes[0]);
        $this->assertTrue($success);

        $success = $this->authenticator->verifyCode($this->user, 'non_existent_code');
        $this->assertFalse($success);
    }
}
