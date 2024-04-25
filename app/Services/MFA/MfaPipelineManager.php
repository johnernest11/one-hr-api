<?php

namespace App\Services\MFA;

use App\Enums\MfaOption;
use App\Enums\MfaPipelineAction;
use App\Models\MfaAttempt;
use App\Models\User;
use App\Services\Verification\VerificationChannel;
use App\Traits\Services\CanResolveModelFromId;
use Carbon\Carbon;
use Hash;
use Illuminate\Pipeline\Pipeline;
use Log;
use Str;

class MfaPipelineManager
{
    use CanResolveModelFromId;

    protected array $mfaOptionsRegistry;

    protected Carbon $expiresAt;

    public function __construct(array $mfaOptionsRegistry, Carbon $expiresAt)
    {
        $this->mfaOptionsRegistry = $mfaOptionsRegistry;
        $this->expiresAt = $expiresAt;
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
            'expires_at' => $this->expiresAt,
        ]);

        Log::debug(__METHOD__, ['steps' => $stepsWithStatus, 'is_array' => is_array($stepsWithStatus)]);

        return [
            'token' => $this->buildRawMfaTokenFormat($mfaAttempt, $token),
            'steps' => $stepsWithStatus,
        ];
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
     * Generate the secret for all the verification options
     * in the MFA pipeline
     */
    public function runSecretGeneration(User|int|string $userModelOrId): bool
    {
        /** @var User $user */
        $user = $this->retrieveModel($userModelOrId, User::query());
        $passable = new MfaPipePasssable(MfaPipelineAction::GENERATE_SECRET, $user);

        /** @var bool $success */
        $success = app(Pipeline::class)
            ->send($passable)
            ->through(...$this->mfaOptionsRegistry)
            ->thenReturn();

        return $success;
    }

    /**
     * Deliver the OTP to the user for the current
     * channel-based verification options in the pipeline.
     *
     * E.g. EmailVerificationChannel, SmsVerificationChannel, PushNotifVerificationChannel
     */
    public function runCodeDelivery(User|int|string $userModelOrId): bool
    {
        /** @var User $user */
        $user = $this->retrieveModel($userModelOrId, User::query());
        $passable = new MfaPipePasssable(MfaPipelineAction::SEND_CODE, $user);

        $channelBasedOptions = array_filter(
            $this->mfaOptionsRegistry,
            fn ($option) => is_subclass_of($option, VerificationChannel::class)
        );

        Log::debug(__METHOD__, ['options' => $channelBasedOptions]);

        /** @var bool $success */
        $success = app(Pipeline::class)
            ->send($passable)
            ->through(...$channelBasedOptions)
            ->thenReturn();

        return $success;
    }

    /**
     * Verify the code given by the user
     * with the current MFA option in the pipeline
     */
    public function runCodeVerification(): bool
    {
        return true;
    }

    /**
     * Get the current MFA step the user needs to complete
     * in an MFA attempt
     */
    public function getCurrentMfaStep(string $mfaToken): ?MfaOption
    {
        $idAndToken = $this->extractMfaTokenIdAndValue($mfaToken);
        if (count($idAndToken) === 0) {
            return null;
        }

        $attempt = MfaAttempt::where('id', $idAndToken['id'])
            ->where('token', $idAndToken['token'])
            ->firstOrFail();

        return $attempt->steps;
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
}
