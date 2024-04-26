<?php

namespace App\Http\Controllers;

use App\Http\Requests\MfaRequest;
use App\Services\MFA\MfaPipelineManager;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MfaController extends ApiController
{
    private MfaPipelineManager $mfaPipelineManager;

    public function __construct(MfaPipelineManager $mfaPipelineManager)
    {
        $this->mfaPipelineManager = $mfaPipelineManager;
    }

    /**
     * Channel-based MFA Options can deliver the MFA code to the users
     */
    public function sendCode(MfaRequest $request): JsonResponse
    {
        $mfaToken = $request->input('token');
        $tokenIsValid = $this->mfaPipelineManager->verifyMfaAttemptToken($mfaToken);
        if (! $tokenIsValid) {
            return $this->error('Invalid MFA Attempt Token', Response::HTTP_BAD_REQUEST);
        }

        $step = $this->mfaPipelineManager->getCurrentMfaStep($mfaToken);
        $this->mfaPipelineManager->runCodeDelivery($mfaToken);

        return $this->success(['message' => 'OTP sent successfully', 'step' => $step], Response::HTTP_ACCEPTED);
    }

    /**
     * App-based MFA Options can generate a QR code
     */
    public function generateQrCode(MfaRequest $request): JsonResponse
    {
        return $this->success(['data' => null], Response::HTTP_OK);
    }

    /**
     * All MFA Options can verify the MFA code from the user
     */
    public function verifyCode(MfaRequest $request): JsonResponse
    {
        return $this->success(['data' => null], Response::HTTP_OK);
    }
}
