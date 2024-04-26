<?php

namespace App\Services\MFA;

use App\Enums\MfaPipelineAction;
use App\Enums\VerificationMethod;
use App\Models\MfaAttempt;
use App\Models\User;
use App\Services\Verification\AppVerificationMethod;
use App\Services\Verification\DeliveryVerificationMethod;
use App\Traits\Services\CanResolveModelFromId;
use Carbon\Carbon;
use Hash;
use Illuminate\Pipeline\Pipeline;
use Log;
use Str;

class MfaPipelineManager
{
    use CanResolveModelFromId;

    protected array $mfaMethodsRegistry;

    protected Carbon $mfaAttemptExpiresAt;

    private array $channelBasedMethodsRegistry;

    private array $appBasedMethodsRegistry;

    public function __construct(array $mfaMethodsRegistry, Carbon $mfaAttemptExpiresAt)
    {
        $this->mfaMethodsRegistry = $mfaMethodsRegistry;
        $this->mfaAttemptExpiresAt = $mfaAttemptExpiresAt;

        $this->channelBasedMethodsRegistry = array_filter(
            $this->mfaMethodsRegistry,
            fn ($method) => is_subclass_of($method, DeliveryVerificationMethod::class)
        );

        $this->appBasedMethodsRegistry = array_filter(
            $this->mfaMethodsRegistry,
            fn ($method) => is_subclass_of($method, AppVerificationMethod::class)
        );
    }

    /**
     * The MFA token is a unique identifier used to track a user's current
     * MFA verification state. This enables support for concurrent login
     * attempts by verifying tokens associated with previous login attempts.
     */
    public function generateMfaAttemptToken(User|int|string $userModelOrId, array|string $mfaSteps): array
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $token = Str::upper(Str::uuid());

        // Add default 'false' status to the steps
        $stepsWithStatus = array_map(fn ($s) => ['name' => $s, 'completed' => false], $mfaSteps);

        $mfaAttempt = MfaAttempt::create([
            'user_id' => $user->id,
            'token' => $token,
            'steps' => $stepsWithStatus,
            'expires_at' => $this->mfaAttemptExpiresAt,
        ]);

        Log::debug(__METHOD__, ['steps' => $stepsWithStatus, 'is_array' => is_array($stepsWithStatus)]);

        return [
            'token' => $this->buildRawMfaTokenFormat($mfaAttempt, $token),
            'steps' => $stepsWithStatus,
        ];
    }

    /**
     * Generate the secret for all the verification options
     * in the MFA pipeline
     */
    public function runSecretGeneration(string $mfaAttemptToken): string
    {
        $mfaAttempt = $this->getMfaAttemptRecordFromToken($mfaAttemptToken);
        $activeStep = $this->getCurrentMfaStep($mfaAttemptToken);
        $passable = new MfaPipePasssable(MfaPipelineAction::GENERATE_SECRET, $mfaAttempt->user, $activeStep);

        /** @var bool $success */
        return app(Pipeline::class)
            ->send($passable)
            ->through(...$this->mfaMethodsRegistry)
            ->thenReturn();
    }

    /**
     * Deliver the OTP to the user for the current
     * channel-based verification options in the pipeline.
     *
     * E.g. EmailVerificationChannel, SmsVerificationChannel, PushNotifVerificationChannel
     */
    public function runCodeDelivery(string $mfaAttemptToken): bool
    {
        $mfaAttempt = $this->getMfaAttemptRecordFromToken($mfaAttemptToken);
        $activeStep = $this->getCurrentMfaStep($mfaAttemptToken);
        $passable = new MfaPipePasssable(MfaPipelineAction::SEND_CODE, $mfaAttempt->user, $activeStep);

        /** @var bool $success */
        $success = app(Pipeline::class)
            ->send($passable)
            ->through(...$this->channelBasedMethodsRegistry)
            ->thenReturn();

        return $success;
    }

    public function runQrCodeGeneration(string $mfaAttemptToken): bool
    {
        return true;
    }

    /**
     * Verify the code given by the user
     * with the current MFA option in the pipeline
     */
    public function runCodeVerification(): bool
    {
        return true;
    }

    public function verifyMfaAttemptToken(string $mfaToken): bool
    {
        $idAndToken = $this->extractMfaTokenIdAndValue($mfaToken);
        if (count($idAndToken) === 0) {
            return false;
        }

        $mfaAttempt = MfaAttempt::find($idAndToken['id']);
        if (! $mfaAttempt) {
            Log::debug('MFA Attempt ID not found', ['ID' => $idAndToken['id'], 'method' => __METHOD__]);

            return false;
        }

        if (! Hash::check($idAndToken['token'], $mfaAttempt->token)) {
            Log::debug('MFA Attempt token is invalid', ['token' => $idAndToken['token'], 'method' => __METHOD__]);

            return false;
        }

        if (now() >= $mfaAttempt->expires_at) {
            Log::debug('MFA Attempt token expired', ['mfa_id' => $mfaAttempt->id, 'method' => __METHOD__]);

            return false;
        }

        return true;
    }

    /**
     * Get the current MFA step the user needs to complete
     * in an MFA attempt
     */
    public function getCurrentMfaStep(string $mfaToken): ?VerificationMethod
    {
        $attempt = $this->getMfaAttemptRecordFromToken($mfaToken);
        if (! $attempt) {
            return null;
        }

        foreach ($attempt->steps as $step) {
            if (! $step['completed']) {
                return VerificationMethod::from($step['name']);
            }
        }

        Log::debug('Unable to find the next step', [
            'method' => __METHOD__,
            'mfa_attempt_id' => $attempt->id,
        ]);

        return null;
    }

    private function buildRawMfaTokenFormat(MfaAttempt $mfaAttempt, string $token): string
    {
        return "$mfaAttempt->id|$token";
    }

    private function extractMfaTokenIdAndValue(string $mfaToken): array
    {
        $idAndToken = explode('|', $mfaToken);
        if (count($idAndToken) !== 2) {
            Log::debug('Cannot separate the MFA token ID from the raw value correctly', [
                'value' => $mfaToken,
                'method' => __METHOD__,
            ]);

            return [];
        }

        return ['id' => $idAndToken[0], 'token' => $idAndToken[1]];
    }

    private function getMfaAttemptRecordFromToken(string $mfaAttemptToken): ?MfaAttempt
    {
        $idAndToken = $this->extractMfaTokenIdAndValue($mfaAttemptToken);
        if (count($idAndToken) === 0) {
            return null;
        }

        $attempt = MfaAttempt::where('id', $idAndToken['id'])->firstOrFail();

        if (! Hash::check($idAndToken['token'], $attempt->token)) {
            Log::debug('The token has an incorrect hash', [
                'method' => __METHOD__,
                'mfa_attempt_id' => $idAndToken['id'],
            ]);

            return null;
        }

        return $attempt;
    }
}
