<?php

namespace App\Services\Verification\Methods;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Services\Verification\DeliveryVerificationMethod;
use Carbon\Carbon;

class EmailVerificationChannel extends DeliveryVerificationMethod
{
    /**
     * {@inheritDoc}
     */
    public function sendCode(User|int|string $userModelOrId, string $code): string
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $carbon = Carbon::createFromTimestamp($this->getCodeExpirationSeconds());
        $minutesExpiration = $carbon->minute;
        $user->sendEmailOtpNotification($code, $minutesExpiration);

        return $code;
    }

    /**
     * {@inheritDoc}
     */
    public function verificationMethod(): VerificationMethod
    {
        return VerificationMethod::EMAIL_CHANNEL;
    }

    /**
     * {@inheritDoc}
     */
    protected function getCodeExpirationSeconds(): int
    {
        return config('auth.verification_codes.expiration.email');
    }
}
