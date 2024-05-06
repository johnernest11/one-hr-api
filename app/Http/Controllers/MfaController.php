<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Enums\AuthenticationType;
use App\Http\Requests\MfaRequest;
use App\Models\MfaAttempt;
use App\Models\User;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\Authentication\Interfaces\PersistentAuthTokenManager;
use App\Services\MfaOrchestrator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MfaController extends ApiController
{
    private MfaOrchestrator $mfaOrchestrator;

    private AuthTokenManager $authTokenManager;

    private PersistentAuthTokenManager $persistentAuthTokenManager;

    public function __construct(MfaOrchestrator $mfaOrchestrator, AuthTokenManager $authTokenManager, PersistentAuthTokenManager $persistentAuthTokenManager)
    {
        $this->mfaOrchestrator = $mfaOrchestrator;
        $this->persistentAuthTokenManager = $persistentAuthTokenManager;
        $this->authTokenManager = $authTokenManager;
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
            return $this->error('All MFA steps have already been completed', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsCodeDelivery($step)) {
            return $this->error('Current MFA step does not support code delivery', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
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
            return $this->error('All MFA steps have already been completed', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsQrCodeGeneration($step)) {
            return $this->error('Current MFA step does not support QR code generation', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        if ($this->mfaOrchestrator->userIsEnrolledToMfaStep($step, $mfaAttempt->user)) {
            return $this->error('QR Code generation is only available once during MFA', Response::HTTP_FORBIDDEN, ApiErrorCode::FORBIDDEN);
        }

        $qrCode = $this->mfaOrchestrator->runQrCodeGeneration($mfaAttempt);
        $backupCodes = $this->mfaOrchestrator->generateBackupCodes($step, $mfaAttempt->user);
        $data = [
            'qr_code' => $qrCode,
            'current_step' => $step,
            'backup_codes' => $backupCodes,
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

        // If there are still incomplete MFA steps, we just return a success message
        $mfaStepsCompleted = $this->mfaOrchestrator->allMfaStepsAreCompleted($mfaAttempt);
        $nextStep = $this->mfaOrchestrator->getCurrentMfaStep($mfaAttempt);
        if (! $mfaStepsCompleted) {
            return $this->success(
                [
                    'message' => 'MFA code validation success',
                    'current_step' => $currentStep,
                    'next_step' => $nextStep,
                ],
                Response::HTTP_OK
            );
        }

        // If all the MFA steps are completed, we authenticate the user
        $user = $mfaAttempt->user->load('userProfile');
        $authMeta = $mfaAttempt->auth_metadata;

        if ($authMeta['auth_type'] === AuthenticationType::SANCTUM->value) {
            $expiresAt = now()->addMinutes(config('sanctum.expiration'));
            $authToken = $this->persistentAuthTokenManager->generateToken($user, $expiresAt);

            $data = [
                'token' => $authToken,
                'token_name' => $authMeta['token_name'],
                'expires_at' => $expiresAt,
                'user' => $user,
            ];

            return $this->success(['data' => $data], Response::HTTP_OK);
        }

        $expiresAt = now()->addMinutes(config('jwt.lifetime_minutes'));
        $authToken = $this->authTokenManager->generateToken($user, $expiresAt);
        $data = [
            'token' => $authToken,
            'token_name' => $authMeta['token_name'],
            'expires_at' => $expiresAt,
            'user' => $user,
        ];

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
            return $this->error('All MFA steps have already been completed', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsBackupCodeVerification($step)) {
            $message = "The $step->value verification method does not support backup codes.";

            return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::VALIDATION);
        }

        /** @var User $user */
        $code = $request->input('code');
        $success = $this->mfaOrchestrator->verifyBackupCode($mfaAttempt, $code);

        if (! $success) {
            return $this->error('Invalid MFA Backup Code provided', Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::INVALID_MFA_BACKUP_CODE);
        }

        // If success, we return the QR code that the user can re-scan
        $qrCode = $this->mfaOrchestrator->runQrCodeGeneration($mfaAttempt);

        return $this->success(
            [
                'message' => 'Backup code validation success. New QR code generated.',
                'current_step' => $step,
                'qr_code' => $qrCode,
            ],
            Response::HTTP_OK
        );
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
