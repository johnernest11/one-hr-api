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
            fn ($method) => (bool) class_implements($method, AppVerificationMethod::class)
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
        $mfaAttempt = $this->getMfaAttemptFromToken($mfaAttemptToken);

        if (! $mfaAttempt) {
            Log::debug('Unable to find an MFA Attempt record from token', [
                'method' => __METHOD__,
            ]);

            return false;
        }

        $activeStep = $this->getCurrentMfaStep($mfaAttempt);
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
                $factor->getOrCreateSecret($mfaAttempt->user);

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
    public function runCodeDelivery(MfaAttempt $mfaAttempt): bool
    {
        $user = $mfaAttempt->user;
        $activeStep = $this->getCurrentMfaStep($mfaAttempt);
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
    public function runCodeVerification(MfaAttempt $mfaAttempt, string|int $code): bool
    {
        // Get the MFA step that needs verification
        $activeStep = $this->getCurrentMfaStep($mfaAttempt);

        // Check if all steps are completed
        if (! $activeStep && $this->allMfaStepsAreCompleted($mfaAttempt)) {
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
     * Generate the QR code that authenticator apps will scan.
     * This is only available for app-based verification options in the pipeline.
     *
     * E.g. GoogleAuthenticator, TwilioAuthy
     */
    public function runQrCodeGeneration(MfaAttempt $mfaAttempt): ?string
    {
        // Get the MFA step that needs verification
        $activeStep = $this->getCurrentMfaStep($mfaAttempt);

        // Check if all steps are completed
        if (! $activeStep && $this->allMfaStepsAreCompleted($mfaAttempt)) {
            Log::debug('All MFA Steps are completed', ['method' => __METHOD__]);

            return null;
        }

        // Run through the registry list and generate the QR code
        $user = $mfaAttempt->user;
        foreach ($this->mfaMethodsRegistry as $methodClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($activeStep === $factor->verificationMethod()) {
                return $factor->generateQrCode($user);
            }
        }

        Log::debug('Unable to create QR code', [
            'method' => __METHOD__,
            'active_step' => $activeStep,
        ]);

        return null;
    }

    public function runGenerateBackupCodes(MfaAttempt $mfaAttempt): array
    {
        // Get the MFA step that needs verification
        $activeStep = $this->getCurrentMfaStep($mfaAttempt);

        // Check if all steps are completed
        if (! $activeStep && $this->allMfaStepsAreCompleted($mfaAttempt)) {
            Log::debug('All MFA Steps are completed', ['method' => __METHOD__]);

            return [];
        }

        // Run through the registry list and generate backup codes
        foreach ($this->mfaMethodsRegistry as $methodClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($activeStep === $factor->verificationMethod()) {
                return $factor->generateBackupCodes($mfaAttempt); // TODO: Change
            }
        }

        Log::debug('Unable to create QR code', [
            'method' => __METHOD__,
            'active_step' => $activeStep,
        ]);

        return [];
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
    public function getCurrentMfaStep(MfaAttempt $mfaAttempt): ?VerificationMethod
    {
        foreach ($mfaAttempt->steps as $step) {
            if (! $step['completed']) {
                return VerificationMethod::from($step['name']);
            }
        }

        Log::debug('There is not more next step in the MFA pipeline', [
            'method' => __METHOD__,
            'mfa_attempt_id' => $mfaAttempt->id,
        ]);

        return null;
    }

    /**
     * Check if the MFA Step (Verification method) supports code delivery
     */
    public function stepSupportsCodeDelivery(VerificationMethod $verificationMethod): bool
    {
        foreach ($this->channelBasedMethodsRegistry as $channelClass) {
            /** @var DeliveryVerificationMethod $channelFactor */
            $channelFactor = resolve($channelClass);
            if ($verificationMethod === $channelFactor->verificationMethod()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the MFA Step (Verification method) supports QR code generation
     */
    public function stepSupportsQrCodeGeneration(VerificationMethod $verificationMethod): bool
    {
        foreach ($this->appBasedMethodsRegistry as $appClass) {
            /** @var AppVerificationMethod $channelFactor */
            $channelFactor = resolve($appClass);
            if ($verificationMethod === $channelFactor->verificationMethod()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the MFA Attempt record via token
     */
    public function getMfaAttemptFromToken(string $mfaAttemptToken): ?MfaAttempt
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

    /**
     * Check if all MFA steps have been completed
     */
    public function allMfaStepsAreCompleted(MfaAttempt $mfaAttempt): bool
    {
        return collect($mfaAttempt->steps)->every(fn ($s) => $s['completed']);
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
            $mfaAttempt = $this->getMfaAttemptFromToken($mfaAttemptTokenOrModel);
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
