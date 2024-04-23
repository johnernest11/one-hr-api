<?php

namespace App\Services\MFA;

use App\Models\MfaOption;
use App\Models\MfaVerification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use OTPHP\TOTP;

abstract class MfaChannel
{
    public function generateMfaToken(array|string $mfaSteps): string
    {
        $token = Str::upper(Str::uuid());

        /** @var MfaVerification $mfaVerification */
        $mfaVerification = $this->getMfaVerificationModel();

        /** @var MfaVerification $createdToken */
        $createdToken = $mfaVerification::query()->create([
            'token' => $mfaVerification,
            'steps' => $mfaSteps,
        ]);

        // Raw Token Format: 1|XXX-YYYY-ZZZ
        $createdToken->rawTokenValue = "$createdToken->id|$token";

        return $createdToken;
    }

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

    protected function getMfaOptionModel(): Model
    {
        return new MfaOption();
    }

    protected function getMfaVerificationModel(): Model
    {
        return new MfaVerification();
    }

    /** Send an MFA code notification to the user */
    abstract public function sendCode(int|string|User $modelOrId, string $code): bool;
}
