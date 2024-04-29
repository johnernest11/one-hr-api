<?php

namespace App\Services;

use App\Enums\VerificationMethod;
use App\Models\MfaAttempt;
use App\Models\User;
use App\Services\Verification\AppVerificationMethod;
use App\Services\Verification\DeliveryVerificationMethod;
use App\Traits\Services\CanResolveModelFromId;
use Carbon\Carbon;
use Hash;
use Log;
use Str;

class MfaOrchestrator
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
    public function generateMfaAttemptToken(User|int|string $userModelOrId, array $mfaSteps, array $authMeta = []): array
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $token = Str::upper(Str::uuid());

        // Add default 'false' status to the steps
        $stepsWithStatus = array_map(fn ($s) => ['name' => $s, 'completed' => false], $mfaSteps);

        $mfaAttempt = MfaAttempt::create([
            'user_id' => $user->id,
            'token' => $token,
            'steps' => $stepsWithStatus,
            'auth_metadata' => $authMeta,
            'expires_at' => $this->mfaAttemptExpiresAt,
        ]);

        Log::debug(__METHOD__, ['steps' => $stepsWithStatus, 'is_array' => is_array($stepsWithStatus)]);

        return [
            'token' => $this->buildRawMfaTokenFormat($mfaAttempt, $token),
            'steps' => $stepsWithStatus,
        ];
    }

    /**
     * Generate the secret for all the verification methods
     * in the MFA methods registry
     */
    public function runSecretGeneration(string $mfaAttemptToken): bool
    {
        $mfaAttempt = $this->getMfaAttemptRecordFromToken($mfaAttemptToken);
        $activeStep = $this->getCurrentMfaStep($mfaAttemptToken);
        if (! $activeStep) {
            Log::debug('Secret generation stopped as there are no active steps.', [
                'method' => __METHOD__,
            ]);

            return true;
        }

        foreach ($this->mfaMethodsRegistry as $methodClass) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($activeStep === $factor->verificationMethod()) {
                $factor->generateSecret($mfaAttempt->user);

                return true;
            }
        }

        Log::debug('Unable to create a secret', [
            'method' => __METHOD__,
            'active_step' => $activeStep,
        ]);

        return false;
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
        $user = $mfaAttempt->user;
        $activeStep = $this->getCurrentMfaStep($mfaAttemptToken);
        if (! $activeStep) {
            Log::debug('Code delivery stopped as there are no active steps.', [
                'method' => __METHOD__,
            ]);

            return true;
        }

        foreach ($this->mfaMethodsRegistry as $methodClass) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
            $factor = resolve($methodClass);
            if ($activeStep === $factor->verificationMethod()) {
                $code = $factor->generateCode($user);
                $factor->sendCode($user, $code);

                return true;
            }
        }

        Log::debug('Unable to send MFA code', [
            'method' => __METHOD__,
            'active_step' => $activeStep,
        ]);

        return false;
    }

    /**
     * Verify the code given by the user
     * with the current MFA option in the pipeline
     */
    public function runCodeVerification(string $mfaAttemptToken, string|int $code): bool
    {
        $mfaAttempt = $this->getMfaAttemptRecordFromToken($mfaAttemptToken);

        // Get the MFA step that needs verification
        $activeStep = $this->getCurrentMfaStep($mfaAttemptToken);

        // Check if all steps are completed
        if (! $activeStep && $this->allMfaStepsAreCompleted($mfaAttemptToken)) {
            Log::debug('All MFA Steps are completed', ['method' => __METHOD__]);

            return false;
        }

        // Run through the registry list to verify the code and flag the MFA step as completed
        foreach ($this->mfaMethodsRegistry as $methodClass) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($activeStep === $factor->verificationMethod()) {
                $isValid = $factor->verifyCode($mfaAttempt->user, $code);
                if ($isValid) {
                    $this->completeStep($mfaAttempt, $activeStep);
                }

                return $isValid;
            }
        }

        return true;
    }

    /**
     * Check if all MFA steps have been completed
     */
    public function allMfaStepsAreCompleted(string $mfaAttemptToken): bool
    {
        $mfaAttempt = $this->resolveMfaAttemptFrom($mfaAttemptToken);
        if (! $mfaAttempt) {
            Log::debug('Unable to resolve MFA Attempt record from token', ['method' => __METHOD__]);

            return false;
        }

        return collect($mfaAttempt->steps)->every(fn ($s) => $s['completed']);
    }

    public function runQrCodeGeneration(string $mfaAttemptToken): bool
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
            Log::debug('MFA Attempt ID not found', ['id' => $idAndToken['id'], 'method' => __METHOD__]);

            return false;
        }

        if (! Hash::check($idAndToken['token'], $mfaAttempt->token)) {
            Log::debug('MFA Attempt token is invalid', ['token' => $idAndToken['token'], 'method' => __METHOD__]);

            return false;
        }

        if (now() >= $mfaAttempt->expires_at) {
            Log::debug('MFA Attempt token expired', ['mfa_attempt_id' => $mfaAttempt->id, 'method' => __METHOD__]);

            return false;
        }

        return true;
    }

    /**
     * Get the current MFA step the user needs to complete
     * in an MFA attempt
     */
    public function getCurrentMfaStep(string|MfaAttempt $mfaAttemptTokenOrModel): ?VerificationMethod
    {
        $attempt = $this->resolveMfaAttemptFrom($mfaAttemptTokenOrModel);

        if (! $attempt) {
            return null;
        }

        foreach ($attempt->steps as $step) {
            if (! $step['completed']) {
                return VerificationMethod::from($step['name']);
            }
        }

        Log::debug('There is not more next step in the MFA pipeline', [
            'method' => __METHOD__,
            'mfa_attempt_id' => $attempt->id,
        ]);

        return null;
    }

    /**
     * Get the MFA Attempt record via token
     */
    public function getMfaAttemptRecordFromToken(string $mfaAttemptToken): ?MfaAttempt
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

    private function resolveMfaAttemptFrom(string|MfaAttempt $mfaAttemptTokenOrModel): ?MfaAttempt
    {
        $mfaAttempt = $mfaAttemptTokenOrModel;
        if (! ($mfaAttempt instanceof MfaAttempt)) {
            $mfaAttempt = $this->getMfaAttemptRecordFromToken($mfaAttemptTokenOrModel);
        }

        return $mfaAttempt;
    }

    private function completeStep(string|MfaAttempt $mfaAttemptTokenOrModel, VerificationMethod $activeStep): bool
    {
        $mfaAttempt = $mfaAttemptTokenOrModel;
        if (! ($mfaAttempt instanceof MfaAttempt)) {
            $mfaAttempt = $this->resolveMfaAttemptFrom($mfaAttemptTokenOrModel);
        }

        if (! $mfaAttempt) {
            Log::debug('Unable to resolve the MFA Attempt record from token', ['method' => __METHOD__]);
        }

        $updatedSteps = [];
        foreach ($mfaAttempt->steps as $step) {
            if ($step['name'] === $activeStep->value) {
                $updatedSteps[] = ['name' => $step['name'], 'completed' => true];

                continue;
            }
            $updatedSteps[] = $step;
        }
        $mfaAttempt->steps = $updatedSteps;
        $mfaAttempt->save();

        return true;
    }
}
