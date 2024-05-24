<?php

namespace Tests\Unit\MFA;

use App\Enums\VerificationMethod;
use App\Models\AppSettings;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use App\Services\AppSettingsManager;
use App\Services\MfaOrchestrator;
use App\Services\Verification\Methods\EmailVerificationChannel;
use App\Services\Verification\Methods\GAuthenticatorVerificationApp;
use ConversionHelper;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Notification;
use Tests\TestCase;
use Throwable;

class MfaOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    private MfaOrchestrator $mfaOrchestrator;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->user = $this->produceUsers();
        $this->mfaOrchestrator = new MfaOrchestrator(config('auth.mfa_methods'), now()->addHours(8));
    }

    public function test_it_can_generate_mfa_token(): void
    {
        // Set-up MFA settings
        $mfaSteps = ConversionHelper::enumToArray(VerificationMethod::class);
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps);
        $this->assertIsArray($mfaToken);

        $this->assertArrayHasKey('token', $mfaToken);
        $this->assertArrayHasKey('expires_at', $mfaToken);
        $this->assertArrayHasKey('steps', $mfaToken);
    }

    public function test_it_can_run_secret_generation(): void
    {
        // Set-up MFA settings
        $mfaSteps = ConversionHelper::enumToArray(VerificationMethod::class);
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps);
        $success = $this->mfaOrchestrator->runSecretGeneration($mfaToken['token']);
        $this->assertTrue($success);
        $this->assertDatabaseCount('verification_factors', 1);
    }

    /**
     * @throws Exception
     */
    public function test_it_can_send_code_for_delivery_based_mfa(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);

        Notification::fake();
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $success = $this->mfaOrchestrator->runCodeDelivery($mfaAttempt);
        $this->assertTrue($success);
        Notification::assertSentTo($this->user, EmailOtpNotification::class);
    }

    public function test_it_can_generate_qr_code_app_based_mfa(): void
    {
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);

        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $qrCode = $this->mfaOrchestrator->runQrCodeGeneration($mfaAttempt);
        $this->assertIsString($qrCode);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_generate_backup_codes_for_app_based_mfa(): void
    {
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);

        $codes = $this->mfaOrchestrator->runBackupCodeGeneration($step, $this->user);
        $this->assertNotEmpty($codes);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_verify_mfa_code(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);

        $factor = new EmailVerificationChannel();
        $code = $factor->generateCode($this->user);
        $success = $this->mfaOrchestrator->runCodeVerification($mfaAttempt, $code);
        $this->assertTrue($success);
    }

    public function test_it_can_get_secret_key(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);
        $key = $this->mfaOrchestrator->runGetSecretKey(VerificationMethod::EMAIL_CHANNEL, $this->user);
        $this->assertIsString($key);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_verify_backup_code(): void
    {
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);

        $authenticator = new GAuthenticatorVerificationApp();
        $backupCodes = $authenticator->generateBackupCodes($this->user);

        $success = $this->mfaOrchestrator->runBackupCodeVerification($mfaAttempt, $backupCodes[0]);
        $this->assertTrue($success);

        // Backup codes can only be used a single time
        $success = $this->mfaOrchestrator->runBackupCodeVerification($mfaAttempt, $backupCodes[0]);
        $this->assertFalse($success);
    }

    public function test_it_can_check_if_user_is_enrolled_to_mfa_step(): void
    {
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        $isEnrolled = $this->mfaOrchestrator->userIsEnrolledToMfaStep($step, $this->user);
        $this->assertFalse($isEnrolled);

        // The user is automatically enrolled when the QR code is generated
        $this->mfaOrchestrator->runQrCodeGeneration($mfaAttempt);
        $isEnrolled = $this->mfaOrchestrator->userIsEnrolledToMfaStep($step, $this->user);
        $this->assertTrue($isEnrolled);
    }

    public function test_it_can_un_enroll_a_user_from_mfa_method(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $this->mfaOrchestrator->runSecretGeneration($mfaToken);

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        $isEnrolled = $this->mfaOrchestrator->userIsEnrolledToMfaStep($step, $this->user);
        $this->assertTrue($isEnrolled);

        $success = $this->mfaOrchestrator->unEnrollUser($this->user, $step);
        $this->assertTrue($success);

        $verificationFactor = $this->user->verificationFactors()
            ->where('type', $step->value)
            ->firstOrFail();

        $this->assertNull($verificationFactor->enrolled_at);
    }

    public function test_it_can_get_the_current_mfa_step(): void
    {
        $mfaSteps = ConversionHelper::enumToArray(VerificationMethod::class);
        shuffle($mfaSteps);

        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        $currentStep = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);

        $this->assertEquals($currentStep, VerificationMethod::from($mfaSteps[0]));
    }

    public function test_it_can_check_if_mfa_step_supports_code_deliver(): void
    {
        $supported = $this->mfaOrchestrator->stepSupportsCodeDelivery(VerificationMethod::EMAIL_CHANNEL);
        $this->assertTrue($supported);

        $supported = $this->mfaOrchestrator->stepSupportsCodeDelivery(VerificationMethod::GOOGLE_AUTHENTICATOR);
        $this->assertFalse($supported);
    }

    public function test_it_can_check_if_mfa_step_supports_qrcode_generation(): void
    {
        $supported = $this->mfaOrchestrator->stepSupportsQrCodeGeneration(VerificationMethod::EMAIL_CHANNEL);
        $this->assertFalse($supported);

        $supported = $this->mfaOrchestrator->stepSupportsQrCodeGeneration(VerificationMethod::GOOGLE_AUTHENTICATOR);
        $this->assertTrue($supported);
    }

    public function test_it_can_check_if_mfa_step_supports_backup_code_verification(): void
    {
        $supported = $this->mfaOrchestrator->stepSupportsBackupCodeVerification(VerificationMethod::EMAIL_CHANNEL);
        $this->assertFalse($supported);

        $supported = $this->mfaOrchestrator->stepSupportsBackupCodeVerification(VerificationMethod::GOOGLE_AUTHENTICATOR);
        $this->assertTrue($supported);
    }

    public function test_it_can_check_if_all_mfa_steps_are_completed(): void
    {
        $mfaSteps = ConversionHelper::enumToArray(VerificationMethod::class);
        shuffle($mfaSteps);

        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($this->user, $mfaSteps)['token'];
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);

        $completedSteps = [];
        foreach ($mfaSteps as $step) {
            $completedSteps[] = ['name' => $step, 'completed' => true];
        }

        $mfaAttempt->steps = $completedSteps;
        $mfaAttempt->save();
        $mfaAttempt->refresh();

        $completed = $this->mfaOrchestrator->allMfaStepsAreCompleted($mfaAttempt);
        $this->assertTrue($completed);
    }

    public function test_it_can_fetch_all_available_mfa_methods(): void
    {
        $registeredMfaClasses = config('auth.mfa_methods');
        $mfaSteps = array_map(fn ($m) => resolve($m)->verificationMethod()->value, $registeredMfaClasses);
        shuffle($mfaSteps);

        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $availableSteps = $this->mfaOrchestrator->getAllMfaMethods(resolve(AppSettingsManager::class));
        $this->assertCount(count($mfaSteps), $availableSteps);
    }
}
