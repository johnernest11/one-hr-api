<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Enums\AuthenticationType;
use App\Enums\VerificationMethod;
use App\Http\Requests\MfaRequest;
use App\Models\MfaAttempt;
use App\Models\User;
use App\Services\AppSettingsManager;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use App\Services\MfaOrchestrator;
use App\Services\User\UserAccountManager;
use Illuminate\Http\JsonResponse;
use Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MfaController extends ApiController
{
    private MfaOrchestrator $mfaOrchestrator;

    private AuthTokenManager $authTokenManager;

    private PersistentAuthTokenManager $persistentAuthTokenManager;

    private UserAccountManager $userAccountManager;

    public function __construct(
        MfaOrchestrator $mfaOrchestrator,
        AuthTokenManager $authTokenManager,
        PersistentAuthTokenManager $persistentAuthTokenManager,
        UserAccountManager $userAccountManager,
    ) {
        $this->mfaOrchestrator = $mfaOrchestrator;
        $this->persistentAuthTokenManager = $persistentAuthTokenManager;
        $this->authTokenManager = $authTokenManager;
        $this->userAccountManager = $userAccountManager;
    }

    /**
     * Channel-based MFA Methods can deliver the MFA code to the users
     */
    public function sendCode(MfaRequest $request): JsonResponse
    {
        $mfaToken = $request->input('token');
        $mfaAttempt = $this->validateTokenAndGetMfaAttempt($mfaToken);

        if ($mfaAttempt instanceof JsonResponse) {
            return $mfaAttempt;
        }

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        if (! $step) {
            return $this->error('All MFA steps have already been completed', Response::HTTP_CONFLICT, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsCodeDelivery($step)) {
            return $this->error('Current MFA step does not support code delivery', Response::HTTP_CONFLICT, ApiErrorCode::BAD_REQUEST);
        }

        $this->mfaOrchestrator->runCodeDelivery($mfaAttempt);

        return $this->success(['message' => 'OTP sent successfully', 'current_step' => $step], Response::HTTP_ACCEPTED);
    }

    /**
     * App-based MFA Methods can generate a QR code
     */
    public function generateQrCode(MfaRequest $request): JsonResponse
    {
        $mfaToken = $request->input('token');
        $mfaAttempt = $this->validateTokenAndGetMfaAttempt($mfaToken);

        if ($mfaAttempt instanceof JsonResponse) {
            return $mfaAttempt;
        }

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        if (! $step) {
            return $this->error('All MFA steps have already been completed', Response::HTTP_CONFLICT, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsQrCodeGeneration($step)) {
            return $this->error('Current MFA step does not support QR code generation', Response::HTTP_CONFLICT, ApiErrorCode::BAD_REQUEST);
        }

        $user = $mfaAttempt->user;
        if ($this->mfaOrchestrator->userIsEnrolledToMfaStep($step, $user)) {
            return $this->error('QR Code generation is only available once during MFA', Response::HTTP_FORBIDDEN, ApiErrorCode::FORBIDDEN);
        }

        $qrCode = $this->mfaOrchestrator->runQrCodeGeneration($mfaAttempt);

        $backupCodes = [];
        try {
            $backupCodes = $this->mfaOrchestrator->runBackupCodeGeneration($step, $user);
        } catch (Throwable) {
            Log::error('Unable to generate backup codes', [
                'method' => __METHOD__,
                'user_id' => $user,
            ]);
        }

        $secretKey = $this->mfaOrchestrator->runGetSecretKey($step, $user);

        $data = [
            'qr_code' => $qrCode,
            'current_step' => $step,
            'backup_codes' => $backupCodes,
            'secret_key' => $secretKey,
        ];

        return $this->success(['data' => $data], Response::HTTP_OK);
    }

    /**
     * All MFA Methods can verify the MFA code from the user
     */
    public function verifyCode(MfaRequest $request): JsonResponse
    {
        // Validate Attempt Token
        $mfaToken = $request->input('token');
        $mfaAttempt = $this->validateTokenAndGetMfaAttempt($mfaToken);

        if ($mfaAttempt instanceof JsonResponse) {
            return $mfaAttempt;
        }

        $currentStep = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        $code = $request->input('code');
        $success = $this->mfaOrchestrator->runCodeVerification($mfaAttempt, $code);

        if (! $success) {
            return $this->error('Invalid MFA Code provided', Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::INVALID_MFA_CODE);
        }

        // If the MFA code verification is successful and the step is the Email Channel,
        // we automatically verify the user's email if it's still unverified
        $user = $mfaAttempt->user->load('userProfile');
        if ($currentStep === VerificationMethod::EMAIL_CHANNEL && ! $user->email_verified_at) {
            $user = $this->userAccountManager->update($user, ['email_verified_at' => now()]);
        }

        // If there are still incomplete MFA steps, we just return a success message
        $mfaStepsCompleted = $this->mfaOrchestrator->allMfaStepsAreCompleted($mfaAttempt);
        $nextStep = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        $data = ['message' => 'MFA code validation success', 'current_step' => $currentStep, 'next_step' => $nextStep];
        if (! $mfaStepsCompleted) {
            return $this->success(['data' => $data], Response::HTTP_OK);
        }

        // If all the MFA steps are completed, we authenticate the user
        $authMeta = $mfaAttempt->auth_metadata;

        $data = [];
        if (isset($authMeta['with_user']) && $authMeta['with_user']) {
            $data['user'] = $user;
        }

        if (! isset($authMeta['auth_type']) || $authMeta['auth_type'] === AuthenticationType::SANCTUM->value) {
            $expiresAt = now()->addMinutes(config('sanctum.expiration'));
            $authToken = $this->persistentAuthTokenManager->generateToken($user, $expiresAt);

            $data = array_merge($data, [
                'token' => $authToken,
                'token_name' => $authMeta['token_name'] ?? 'api_token',
                'expires_at' => $expiresAt,
            ]);

            return $this->success(['data' => $data], Response::HTTP_OK);
        }

        $expiresAt = now()->addMinutes(config('jwt.lifetime_minutes'));
        $authToken = $this->authTokenManager->generateToken($user, $expiresAt);

        $data = array_merge($data, [
            'token' => $authToken,
            'token_name' => $authMeta['token_name'] ?? 'api_token',
            'expires_at' => $expiresAt,
        ]);

        return $this->success(['data' => $data], Response::HTTP_OK);
    }

    /**
     * Verify a backup code provided by the user.
     * A successful verification will return back the QR code
     * for the MFA step that the user can scan again.
     */
    public function verifyBackupCode(MfaRequest $request): JsonResponse
    {
        // Validate Attempt Token
        $mfaToken = $request->input('token');
        $mfaAttempt = $this->validateTokenAndGetMfaAttempt($mfaToken);

        if ($mfaAttempt instanceof JsonResponse) {
            return $mfaAttempt;
        }

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        if (! $step) {
            return $this->error('All MFA steps have already been completed', Response::HTTP_CONFLICT, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsBackupCodeVerification($step)) {
            $message = "The $step->value verification method does not support backup codes.";

            return $this->error($message, Response::HTTP_CONFLICT, ApiErrorCode::BAD_REQUEST);
        }

        /** @var User $user */
        $code = $request->input('code');
        $success = $this->mfaOrchestrator->runBackupCodeVerification($mfaAttempt, $code);

        if (! $success) {
            return $this->error('Invalid MFA Backup Code provided', Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::INVALID_MFA_BACKUP_CODE);
        }

        // If success, we return the QR code that the user can re-scan
        $qrCode = $this->mfaOrchestrator->runQrCodeGeneration($mfaAttempt);
        $secretKey = $this->mfaOrchestrator->runGetSecretKey($step, $mfaAttempt->user);
        $data = [
            'message' => 'Backup code validation success. New QR code generated.',
            'current_step' => $step,
            'qr_code' => $qrCode,
            'secret_key' => $secretKey,
        ];

        return $this->success(['data' => $data], Response::HTTP_OK);
    }

    /**
     * Fetch all available verification methods for MFA
     */
    public function fetchAllAvailableMfaMethods(MfaRequest $request, AppSettingsManager $settingsManager): JsonResponse
    {
        $allSteps = $this->mfaOrchestrator->getAllMfaMethods($settingsManager);

        return $this->success(['data' => $allSteps], Response::HTTP_OK);
    }

    /**
     * Enroll a user to an MFA verification method
     */
    public function unEnrollUser(string|int $userId, MfaRequest $request): JsonResponse
    {
        $verificationMethod = VerificationMethod::from($request->validated('mfa_step'));
        $success = $this->mfaOrchestrator->unEnrollUser($userId, $verificationMethod);

        if (! $success) {
            return $this->error('Unable to un-enroll user from MFA step', Response::HTTP_INTERNAL_SERVER_ERROR, ApiErrorCode::SERVER);
        }

        return $this->success(['message' => 'User successfully un-enrolled'], Response::HTTP_OK);
    }

    private function validateTokenAndGetMfaAttempt(string $mfaToken): JsonResponse|MfaAttempt
    {
        $tokenIsValid = $this->mfaOrchestrator->verifyMfaAttemptToken($mfaToken);
        if (! $tokenIsValid) {
            return $this->error('Invalid MFA Attempt Token', Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::INVALID_MFA_ATTEMPT_TOKEN);
        }

        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptFromToken($mfaToken);
        if (! $mfaAttempt) {
            return $this->error('Unable to find MFA attempt record from token', Response::HTTP_NOT_FOUND, ApiErrorCode::RESOURCE_NOT_FOUND);
        }

        return $mfaAttempt;
    }
}
