<?php

namespace Tests\Feature;

use App\Enums\VerificationMethod;
use App\Models\AppSettings;
use App\Services\MfaOrchestrator;
use App\Services\Verification\Methods\EmailVerificationChannel;
use ConversionHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class MfaTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUri = self::BASE_API_URI.'/auth/mfa';

    private MfaOrchestrator $mfaOrchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed');
        $this->mfaOrchestrator = new MfaOrchestrator(config('auth.mfa_methods'), now()->addHours(8));
    }

    /**
     * @throws Throwable
     */
    public function test_log_in_via_email_and_passwords_returns_mfa_code_if_mfa_is_enabled(): void
    {
        $mfaSteps = ConversionHelper::enumToArray(VerificationMethod::class);
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $email = fake()->email;
        $password = fake()->password;
        $this->produceUsers(1, ['email' => $email, 'password' => $password]);

        $response = $this->postJson(self::BASE_API_URI.'/auth/tokens', [
            'email' => $email,
            'password' => $password,
        ]);
        $response->assertStatus(200);

        $response = $response->decodeResponseJson();
        $this->assertArrayHasKey('mfa_token', $response['data']);
        $this->assertArrayHasKey('mfa_token_expires_at', $response['data']);
        $this->assertArrayHasKey('mfa_steps', $response['data']);

        // It returns the regular auth token when not enabled
        $value = json_encode([
            'enabled' => false,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;
        $response = $this->postJson(self::BASE_API_URI.'/auth/tokens', [
            'email' => $email,
            'password' => $password,
            'with_user' => true,
        ]);

        $response->assertStatus(200);
        $response = $response->decodeResponseJson();
        $this->assertArrayHasKey('token', $response['data']);
        $this->assertArrayHasKey('expires_at', $response['data']);
        $this->assertArrayHasKey('user', $response['data']);
    }

    public function test_it_sends_otp_code_for_delivery_based_mfa_steps(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $user = $this->produceUsers();
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $response = $this->postJson($this->baseUri.'/send-code', [
            'token' => $mfaToken['token'],
        ]);

        $response->assertStatus(202);

        // It returns 409 if the step does not support code delivery
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $response = $this->postJson($this->baseUri.'/send-code', [
            'token' => $mfaToken['token'],
        ]);

        $response->assertStatus(409);
    }

    /**
     * @throws Throwable
     */
    public function test_it_generates_qr_code_for_app_based_mfa_steps(): void
    {
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        $user = $this->produceUsers();
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $response = $this->postJson($this->baseUri.'/generate-qrcode', [
            'token' => $mfaToken['token'],
        ]);

        $response->assertStatus(200);

        $response = $response->decodeResponseJson();
        $this->assertArrayHasKey('qr_code', $response['data']);
        $this->assertArrayHasKey('backup_codes', $response['data']);
        $this->assertNotEmpty($response['data']['backup_codes']);

        // QR code can only be generated once
        $response = $this->postJson($this->baseUri.'/generate-qrcode', [
            'token' => $mfaToken['token'],
        ]);
        $response->assertStatus(403);

        // It returns 409 if the step does support qr code generation
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $response = $this->postJson($this->baseUri.'/generate-qrcode', [
            'token' => $mfaToken['token'],
        ]);

        $response->assertStatus(409);
    }

    public function test_it_can_verify_otp_code_for_the_current_step(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value, VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        $user = $this->produceUsers();
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $factor = new EmailVerificationChannel();
        $code = $factor->generateCode($user);
        $response = $this->postJson($this->baseUri.'/verify-code', [
            'token' => $mfaToken['token'],
            'code' => $code,
        ]);

        $response->assertStatus(200);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_verify_backup_codes(): void
    {
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value, VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        // Request for a QR code so the backup codes are generated
        $user = $this->produceUsers();
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $response = $this->postJson($this->baseUri.'/generate-qrcode', [
            'token' => $mfaToken['token'],
        ]);

        $codes = $response->decodeResponseJson()['data']['backup_codes'];

        $response = $this->postJson($this->baseUri.'/verify-backup-code', [
            'token' => $mfaToken['token'],
            'code' => $codes[0],
        ]);

        $response->assertStatus(200);

        // QR code is re-generated if the back-up code verification is a success
        $response = $response->decodeResponseJson();
        $this->assertArrayHasKey('qr_code', $response['data']);

        // Backup codes are only single-use
        $response = $this->postJson($this->baseUri.'/verify-backup-code', [
            'token' => $mfaToken['token'],
            'code' => $codes[0],
        ]);

        $response->assertStatus(422);

        // It returns 409 if the mfa step does not support backup codes
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $response = $this->postJson($this->baseUri.'/verify-backup-code', [
            'token' => $mfaToken['token'],
            'code' => $codes[1],
        ]);

        $response->assertStatus(409);
    }

    public function test_it_proceeds_to_next_step_after_successful_verification(): void
    {
        // TODO
    }

    public function test_it_returns_authentication_token_when_all_mfa_steps_are_complete(): void
    {
        // TODO
    }

    public function test_it_returns_422_if_code_is_incorrect(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value, VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        $user = $this->produceUsers();
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $response = $this->postJson($this->baseUri.'/verify-code', [
            'token' => $mfaToken['token'],
            'code' => 'incorrect_code',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_returns_422_if_mfa_token_is_invalid(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value, VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        $response = $this->postJson($this->baseUri.'/verify-code', [
            'token' => 'invalid_token',
            'code' => '2344',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_returns_409_if_all_mfa_steps_are_already_completed(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value, VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);
        $user = $this->produceUsers();
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken['token']);

        // Complete all the steps
        $mfaAttempt->steps = array_map(fn ($val) => ['name' => $val['name'], 'completed' => true],
            $mfaAttempt->steps
        );
        $mfaAttempt->save();
        $mfaAttempt->refresh();

        $response = $this->postJson($this->baseUri.'/send-code', [
            'token' => $mfaToken['token'],
        ]);

        $response->assertStatus(409);
    }
}
