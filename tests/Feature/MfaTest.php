<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\VerificationMethod;
use App\Models\AppSettings;
use App\Models\VerificationFactor;
use App\Notifications\EmailOtpNotification;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use App\Services\MfaOrchestrator;
use App\Services\Verification\Methods\EmailVerificationChannel;
use ConversionHelper;
use DB;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Notification;
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
        Notification::fake();
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

    /**
     * @throws Exception
     */
    public function test_it_delivers_mfa_code_if_first_mfa_step_supports_it_after_login(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $email = fake()->email;
        $password = fake()->password;
        $user = $this->produceUsers(1, ['email' => $email, 'password' => $password]);

        $this->postJson(self::BASE_API_URI.'/auth/tokens', [
            'email' => $email,
            'password' => $password,
        ]);
        Notification::assertSentTo($user, EmailOtpNotification::class);
    }

    /**
     * @throws Exception
     */
    public function test_it_does_not_deliver_mfa_code_if_first_mfa_step_does_not_support_it_after_login(): void
    {
        $mfaSteps = [VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value])->value;

        $email = fake()->email;
        $password = fake()->password;
        $user = $this->produceUsers(1, ['email' => $email, 'password' => $password]);

        $this->postJson(self::BASE_API_URI.'/auth/tokens', [
            'email' => $email,
            'password' => $password,
        ]);
        Notification::assertNotSentTo($user, EmailOtpNotification::class);
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
        $this->assertArrayHasKey('secret_key', $response['data']);
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

    public function test_it_automatically_verifies_user_email_if_verification_email_channel_is_successful(): void
    {
        $mfaSteps = [VerificationMethod::EMAIL_CHANNEL->value, VerificationMethod::GOOGLE_AUTHENTICATOR->value];
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        // Create a user with unverified email address
        $user = $this->produceUsers(1, [], true);
        $this->assertNull($user->email_verified_at);

        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps);
        $factor = new EmailVerificationChannel();
        $code = $factor->generateCode($user);
        $response = $this->postJson($this->baseUri.'/verify-code', [
            'token' => $mfaToken['token'],
            'code' => $code,
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * @throws Throwable
     */
    public function test_it_can_verify_backup_codes(): void
    {
        $mfaSteps = [
            VerificationMethod::GOOGLE_AUTHENTICATOR->value,
            VerificationMethod::EMAIL_CHANNEL->value,
        ];
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

    /**
     * @throws Throwable
     */
    public function test_it_proceeds_to_next_step_after_successful_verification(): void
    {
        $mfaSteps = [
            VerificationMethod::EMAIL_CHANNEL->value,
            VerificationMethod::GOOGLE_AUTHENTICATOR->value,
        ];

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
        $response = $response->decodeResponseJson();
        $this->assertEquals(VerificationMethod::GOOGLE_AUTHENTICATOR->value, $response['data']['next_step']);
    }

    /**
     * @throws Throwable
     */
    public function test_it_returns_authentication_token_when_all_mfa_steps_are_complete(): void
    {
        $mfaSteps = [
            VerificationMethod::GOOGLE_AUTHENTICATOR->value,
            VerificationMethod::EMAIL_CHANNEL->value,
        ];

        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);

        $user = $this->produceUsers();
        $tokenName = 'test_token';
        $mfaToken = $this->mfaOrchestrator->generateMfaAttemptToken($user, $mfaSteps,
            ['token_name' => $tokenName, 'with_user' => true]
        );
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken['token']);

        // Complete all steps except the last one
        $steps = [];
        foreach ($mfaAttempt->steps as $step) {
            if ($step['name'] === VerificationMethod::EMAIL_CHANNEL->value) {
                $steps[] = ['name' => $step['name'], 'completed' => false];

                continue;
            }

            $steps[] = ['name' => $step['name'], 'completed' => true];
        }

        $mfaAttempt->steps = $steps;
        $mfaAttempt->save();
        $mfaAttempt->refresh();

        $factor = new EmailVerificationChannel();
        $code = $factor->generateCode($user);
        $response = $this->postJson($this->baseUri.'/verify-code', [
            'token' => $mfaToken['token'],
            'code' => $code,
        ]);

        $response->assertStatus(200);
        $response = $response->decodeResponseJson();
        $this->assertArrayHasKey('user', $response['data']);
        $this->assertArrayHasKey('token', $response['data']);
        $this->assertArrayHasKey('expires_at', $response['data']);
        $this->assertEquals($tokenName, $response['data']['token_name']);
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

    /**
     * @throws Throwable
     */
    public function test_it_can_fetch_all_available_mfa_methods(): void
    {
        $registeredMfaClasses = config('auth.mfa_methods');
        $mfaSteps = array_map(fn ($m) => resolve($m)->verificationMethod()->value, $registeredMfaClasses);
        $value = json_encode([
            'enabled' => true,
            'steps' => $mfaSteps,
        ]);

        AppSettings::updateOrCreate(['name' => 'mfa'], ['value' => $value]);
        $user = $this->produceUsers();
        $authTokenManager = resolve(PersistentAuthTokenManager::class);
        $authToken = $authTokenManager->generateToken($user, now()->addHour());

        $response = $this->withToken($authToken)->getJson($this->baseUri.'/available-methods');
        $response->assertStatus(200);

        $response = $response->decodeResponseJson();
        $this->assertCount(count($mfaSteps), $response['data']);

        // Check if the order is correct
        $this->assertEquals($mfaSteps, array_map(fn ($method) => $method['name'], $response['data']));

        // Check if `enabled` status are correct
        foreach ($response['data'] as $method) {
            $this->assertTrue($method['enabled']);
        }
    }

    public function test_it_can_un_enroll_a_user(): void
    {
        $user = $this->produceUsers();
        $user->syncRoles(Role::ADMIN);
        $authTokenManager = resolve(PersistentAuthTokenManager::class);
        $authToken = $authTokenManager->generateToken($user, now()->addHour());

        $method = VerificationMethod::EMAIL_CHANNEL;
        $factor = VerificationFactor::factory()->create(['user_id' => $user->id, 'type' => $method->value]);
        $this->assertNotNull($factor->enrolled_at);

        // Returns 422 if mfa_step is not a valid MFA verification step
        $response = $this->withToken($authToken)->postJson($this->baseUri.'/un-enroll-user/'.$user->id, [
            'user_id' => $user->id,
            'mfa_step' => 'not_valid_method',
        ]);
        $response->assertStatus(422);

        // Returns 404 if user_id does not exists
        $nonExistentUserId = DB::table('users')->count('id') + 1;
        $response = $this->withToken($authToken)->postJson($this->baseUri.'/un-enroll-user/'.$nonExistentUserId, [
            'mfa_step' => $method->value,
        ]);
        $response->assertStatus(404);

        // Returns 404 if user_id is not yet associated with a verification factor
        $userWithoutFactor = $this->produceUsers();
        $response = $this->withToken($authToken)->postJson($this->baseUri.'/un-enroll-user/'.$userWithoutFactor->id, [
            'mfa_step' => $method->value,
        ]);
        $response->assertStatus(404);

        // Returns 200 if successful
        $response = $this->withToken($authToken)->postJson($this->baseUri.'/un-enroll-user/'.$user->id, [
            'mfa_step' => $method->value,
        ]);
        $response->assertStatus(200);

        // enrolled_at should be null
        $factor->refresh();
        $this->assertNull($factor->enrolled_at);
    }
}
