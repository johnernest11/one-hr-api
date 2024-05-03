<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Enums\AuthenticationType;
use App\Http\Requests\MfaRequest;
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
     * Channel-based MFA Options can deliver the MFA code to the users
     */
    public function sendCode(MfaRequest $request): JsonResponse
    {
        $mfaToken = $request->input('token');
        $tokenIsValid = $this->mfaOrchestrator->verifyMfaAttemptToken($mfaToken);
        if (! $tokenIsValid) {
            return $this->error('Invalid MFA Attempt Token', Response::HTTP_BAD_REQUEST, ApiErrorCode::INVALID_MFA_ATTEMPT_TOKEN);
        }

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaToken);
        if (! $step) {
            return $this->error('All MFA steps have already been completed', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsCodeDelivery($step)) {
            return $this->error('Current MFA step does not support code delivery', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        $this->mfaOrchestrator->runCodeDelivery($mfaToken);

        return $this->success(['message' => 'OTP sent successfully', 'current_step' => $step], Response::HTTP_ACCEPTED);
    }

    /**
     * App-based MFA Options can generate a QR code
     */
    public function generateQrCode(MfaRequest $request): JsonResponse
    {
        $mfaToken = $request->input('token');
        $tokenIsValid = $this->mfaOrchestrator->verifyMfaAttemptToken($mfaToken);
        if (! $tokenIsValid) {
            return $this->error('Invalid MFA Attempt Token', Response::HTTP_BAD_REQUEST, ApiErrorCode::INVALID_MFA_ATTEMPT_TOKEN);
        }

        $step = $this->mfaOrchestrator->getCurrentMfaStep($mfaToken);
        if (! $step) {
            return $this->error('All MFA steps have already been completed', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        if (! $this->mfaOrchestrator->stepSupportsQrCodeGeneration($step)) {
            return $this->error('Current MFA step does not support QR code generation', Response::HTTP_BAD_REQUEST, ApiErrorCode::BAD_REQUEST);
        }

        $qrCode = $this->mfaOrchestrator->runQrCodeGeneration($mfaToken);
        $data = [
            'qr_code' => $qrCode,
            'current_step' => $step,
        ];

        return $this->success(['data' => $data], Response::HTTP_OK);
    }

    /**
     * All MFA Options can verify the MFA code from the user
     */
    public function verifyCode(MfaRequest $request): JsonResponse
    {
        // Validate Attempt Token
        $mfaToken = $request->input('token');
        $tokenIsValid = $this->mfaOrchestrator->verifyMfaAttemptToken($mfaToken);
        if (! $tokenIsValid) {
            return $this->error('Invalid MFA Attempt Token', Response::HTTP_BAD_REQUEST, ApiErrorCode::INVALID_MFA_ATTEMPT_TOKEN);
        }

        // Validate MFA Code
        $currentStep = $this->mfaOrchestrator->getCurrentMfaStep($mfaToken);
        $code = $request->input('code');
        $success = $this->mfaOrchestrator->runCodeVerification($mfaToken, $code);

        if (! $success) {
            return $this->error('Invalid MFA Code provided', Response::HTTP_UNPROCESSABLE_ENTITY, ApiErrorCode::INVALID_MFA_CODE);
        }

        // If there are still incomplete MFA steps, we just return a success message
        $mfaStepsCompleted = $this->mfaOrchestrator->allMfaStepsAreCompleted($mfaToken);
        $nextStep = $this->mfaOrchestrator->getCurrentMfaStep($mfaToken);
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
        $mfaAttempt = $this->mfaOrchestrator->getMfaAttemptRecordFromToken($mfaToken);
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
}
