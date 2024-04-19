<?php

namespace App\Services\MFA;

use App\Models\User;
use OTPHP\TOTP;

abstract class MfaChannel
{
    /** Create an MFA Code */
    public function generateCode(int|string|User $modelOrId): string
    {
        $otp = TOTP::create();
        $secret = $otp->getSecret();

        return '';
    }

    /** Verify MFA code */
    public function verifyCode(int|string|User $modelOrId, string $input): bool
    {
        return true;
    }

    /** Send an MFA code notification to the user */
    abstract public function sendCode(int|string|User $modelOrId, string $code): bool;
}
