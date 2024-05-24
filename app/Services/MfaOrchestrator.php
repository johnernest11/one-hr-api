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
use Throwable;

class MfaOrchestrator
{
    use CanResolveModelFromId;

    protected array $mfaMethodsRegistry;

    protected Carbon $mfaAttemptExpiresAt;

    private array $channelBasedMethodsRegistry;

    private array $appBasedMethodsRegistry;

    private static string $APP_MFA_TYPE = 'app';

    private static string $DELIVERY_MFA_TYPE = 'delivery';

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
        // Example value: [['name' => 'email_channel', 'completed' => false], [...]]
        $stepsWithStatus = array_map(fn ($s) => ['name' => $s, 'completed' => false], $mfaSteps);

        // Add whether the step is App-based or Delivery-based and if the user is enrolled
        // Example value: [['name' => 'email_channel', 'completed' => false, 'type' => 'delivery', 'enrolled' => false], [...]]
        $stepsWithTypeAndEnrolledStatus = [];
        foreach ($stepsWithStatus as $step) {
            foreach ($this->mfaMethodsRegistry as $methodClass) {
                /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
                $factor = resolve($methodClass);

                $verificationMethod = VerificationMethod::from($step['name']);
                if ($verificationMethod === $factor->verificationMethod()) {
                    $step['type'] = is_subclass_of($methodClass, DeliveryVerificationMethod::class) ? static::$DELIVERY_MFA_TYPE : static::$APP_MFA_TYPE;
                    $step['enrolled'] = $factor->userIsEnrolled($user, $verificationMethod);
                    $stepsWithTypeAndEnrolledStatus[] = $step;
                    break;
                }
            }
        }

        $mfaAttempt = MfaAttempt::create([
            'user_id' => $user->id,
            'token' => $token,
            'steps' => $stepsWithTypeAndEnrolledStatus,
            'auth_metadata' => $authMeta,
            'expires_at' => $this->mfaAttemptExpiresAt,
        ]);

        return [
            'token' => $this->buildRawMfaTokenFormat($mfaAttempt, $token),
            'steps' => $stepsWithTypeAndEnrolledStatus,
            'expires_at' => $this->mfaAttemptExpiresAt,
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

        foreach ($this->channelBasedMethodsRegistry as $methodClass) {
            /** @var DeliveryVerificationMethod $factor */
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
            return null;
        }

        // Run through the registry list and generate the QR code
        $user = $mfaAttempt->user;
        foreach ($this->appBasedMethodsRegistry as $methodClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($activeStep === $factor->verificationMethod()) {
                $qrCode = $factor->generateQrCode($user);
                $factor->enrollUser($user, $activeStep);

                return $qrCode;
            }
        }

        Log::debug('Unable to create QR code', [
            'method' => __METHOD__,
            'active_step' => $activeStep,
        ]);

        return null;
    }

    public function verifyMfaAttemptToken(string $mfaToken): bool
    {
        $idAndToken = $this->extractMfaTokenIdAndValue($mfaToken);
        if (count($idAndToken) === 0) {
            return false;
        }

        $mfaAttempt = MfaAttempt::find($idAndToken['id']);
        if (! $mfaAttempt) {
            Log::debug('MFA Attempt ID no found', ['id' => $idAndToken['id'], 'method' => __METHOD__]);

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
     * Generate back-up codes in case the user loses their primary device for app-based MFA.
     * This is only available for app-based verification options in the pipeline.
     *
     * E.g. GoogleAuthenticator, TwilioAuthy
     *
     * @throws Throwable
     */
    public function runBackupCodeGeneration(VerificationMethod $verificationMethod, User $user): array
    {
        // Run through the registry list and generate backup codes
        foreach ($this->appBasedMethodsRegistry as $methodClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($verificationMethod === $factor->verificationMethod()) {
                return $factor->generateBackupCodes($user);
            }
        }

        Log::debug('Unable to create backup codes', [
            'method' => __METHOD__,
            'verification_method' => $verificationMethod,
        ]);

        return [];
    }

    /**
     * Verify the backup code generate by App-based MFA methods
     */
    public function runBackupCodeVerification(MfaAttempt $mfaAttempt, string $code): bool
    {
        // Get the MFA step that needs verification
        $activeStep = $this->getCurrentMfaStep($mfaAttempt);

        // Check if all steps are completed
        if (! $activeStep && $this->allMfaStepsAreCompleted($mfaAttempt)) {
            return false;
        }

        // Run through the registry list and generate the QR code
        $user = $mfaAttempt->user;
        foreach ($this->appBasedMethodsRegistry as $methodClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($activeStep === $factor->verificationMethod()) {
                return $factor->verifyBackupCode($user, $code);
            }
        }

        Log::debug('Unable to verify backup code', [
            'method' => __METHOD__,
            'active_step' => $activeStep,
        ]);

        return false;
    }

    /**
     * Get secret key of a verification factor. Users may need to enter the secret key in the
     * authenticator app manually instead of scanning the QR code.
     * A typical set-up key of a VerificationFactor is the decrypted value
     * of the secret key
     *
     * This is only available for app-based MFA methods
     */
    public function runGetSecretKey(VerificationMethod $verificationMethod, User $user): ?string
    {
        // Run through the registry list and generate backup codes
        foreach ($this->mfaMethodsRegistry as $methodClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($verificationMethod === $factor->verificationMethod()) {
                return $factor->getOrCreateSecret($user);
            }
        }

        Log::debug('Unable to get the setup key', [
            'method' => __METHOD__,
            'verification_method' => $verificationMethod,
        ]);

        return null;
    }

    /**
     * Check if the user is enrolled to the MFA step.
     */
    public function userIsEnrolledToMfaStep(VerificationMethod $verificationMethod, User $user): bool
    {
        // Run through the registry list and generate backup codes
        foreach ($this->mfaMethodsRegistry as $methodClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($methodClass);

            if ($verificationMethod === $factor->verificationMethod()) {
                return $factor->userIsEnrolled($user, $verificationMethod);
            }
        }

        Log::debug('Unable to check if the user is enrolled to the MFA method', [
            'method' => __METHOD__,
            'verification_method' => $verificationMethod,
        ]);

        return false;
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
     *
     * Right now, all App-based Verification Methods supports this
     */
    public function stepSupportsQrCodeGeneration(VerificationMethod $verificationMethod): bool
    {
        foreach ($this->appBasedMethodsRegistry as $appClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($appClass);

            return $verificationMethod === $factor->verificationMethod();
        }

        return false;
    }

    /**
     * Check if the MFA Step (Verification method) supports Backup Codes
     *
     * Right now, all App-based Verification Methods support this
     */
    public function stepSupportsBackupCodeVerification(VerificationMethod $verificationMethod): bool
    {
        foreach ($this->appBasedMethodsRegistry as $appClass) {
            /** @var AppVerificationMethod $factor */
            $factor = resolve($appClass);

            return $verificationMethod === $factor->verificationMethod();
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

    /**
     * Get all MFA methods available, along with their activation status
     */
    public function getAllMfaMethods(AppSettingsManager $settingsManager): array
    {
        $activatedMfaSteps = $settingsManager->getMfaConfig()['steps'];
        $allMethods = [];

        foreach ($this->mfaMethodsRegistry as $verificationMethod) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
            $factor = resolve($verificationMethod);
            $methodName = $factor->verificationMethod()->value;
            $methodType = is_subclass_of($verificationMethod, DeliveryVerificationMethod::class) ? static::$DELIVERY_MFA_TYPE : static::$APP_MFA_TYPE;

            // If the method is activated, we insert it at the correct position
            if (in_array($methodName, $activatedMfaSteps)) {
                $method = ['name' => $methodName, 'enabled' => true, 'type' => $methodType];
                $index = array_search($methodName, $activatedMfaSteps);
                array_splice($allMethods, $index, 0, [$method]);

                continue;
            }

            // If not enabled, we add them at the end of the list
            $allMethods[] = ['name' => $methodName, 'enabled' => false, 'type' => $methodType];
        }

        return $allMethods;
    }

    /**
     * Un-enroll a user from a verification method. Useful for app-based
     * verification methods where the QR code and backup codes need to be
     * regenerated for the user
     */
    public function unEnrollUser(User|int|string $userModeOrId, VerificationMethod $method): bool
    {
        $user = $this->retrieveModel($userModeOrId, User::query());

        foreach ($this->mfaMethodsRegistry as $verificationMethodClass) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $factor */
            $factor = resolve($verificationMethodClass);
            if ($method === $factor->verificationMethod()) {
                return $factor->unEnrollUser($user, $method);
            }
        }

        Log::debug('Unable to un-enroll user from a verification method', [
            'method' => __METHOD__,
            'user_id' => $user->id,
            'verification_method' => $method,
        ]);

        return false;
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
                $step['completed'] = true;
                $updatedSteps[] = $step;

                continue;
            }

            $updatedSteps[] = $step;
        }
        $mfaAttempt->steps = $updatedSteps;
        $mfaAttempt->save();

        return true;
    }
}
