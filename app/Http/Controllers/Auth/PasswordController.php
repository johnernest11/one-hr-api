<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\ApiController;
use App\Http\Requests\AuthRequest;
use Illuminate\Http\JsonResponse;
use Password;
use Str;
use Symfony\Component\HttpFoundation\Response;

class PasswordController extends ApiController
{
    /**
     * Forgot password request
     */
    public function forgotPassword(AuthRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->error(
                'Unable to send password reset email',
                Response::HTTP_FAILED_DEPENDENCY,
                ApiErrorCode::DEPENDENCY_ERROR
            );
        }

        $data = ['message' => 'Password reset request sent', 'email' => $request->get('email')];

        return $this->success($data, Response::HTTP_OK);
    }

    /**
     * Forgot password request
     */
    public function resetPassword(AuthRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = $password;
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(
                'Unable to reset password',
                Response::HTTP_BAD_REQUEST,
                ApiErrorCode::BAD_REQUEST
            );
        }

        $data = ['message' => 'Password reset was successful'];

        return $this->success($data, Response::HTTP_OK);
    }
}
