<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\ApiController;
use App\Http\Requests\NoAuthEmailVerificationRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class VerifyController extends ApiController
{
    /**
     * Verify Email
     */
    public function verifyEmail(NoAuthEmailVerificationRequest $request): JsonResponse
    {
        $request->fulfill();

        return $this->success(['message' => 'Email successfully verified'], Response::HTTP_OK);
    }

    /**
     * Resend the email verification notification
     */
    public function resendEmailVerification(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $user->sendEmailVerificationNotification();
        $data = [
            'message' => 'Email verification sent',
            'email' => $user->email,
        ];

        return $this->success($data, Response::HTTP_OK);
    }
}
