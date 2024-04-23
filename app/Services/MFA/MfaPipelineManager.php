<?php

namespace App\Services\MFA;

use App\Models\MfaOption;
use App\Models\MfaVerification;
use Str;

class MfaOptionsPipeline
{
    private MfaOption $mfaOptionModel;

    private MfaVerification $mfaVerificationModel;

    public function __construct(MfaOption $mfaOption, MfaVerification $mfaVerification)
    {
        $this->mfaOptionModel = $mfaOption;
        $this->mfaVerificationModel = $mfaVerification;
    }

    /**
     * The MFA token is a unique identifier used to track a user's current
     * MFA verification state. This enables support for concurrent login
     * attempts by verifying tokens associated with previous login attempts.
     */
    public function generateMfaToken(array|string $mfaSteps): string
    {
        $token = Str::upper(Str::uuid());

        $createdToken = $this->mfaVerificationModel::create([
            'token' => $token,
            'steps' => $mfaSteps,
        ]);

        // Raw Token Format: 1|XXX-YYYY-ZZZ
        $createdToken->rawTokenValue = "$createdToken->id|$token";

        return $createdToken;
    }

    public function getPipelines(): array
    {
        return [];
    }
}
