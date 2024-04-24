<?php

namespace App\Services\MFA;

use App\Models\MfaAttempt;
use App\Models\MfaCredential;
use Str;

class MfaOptionsPipeline
{
    private MfaCredential $mfaOptionModel;

    private MfaAttempt $mfaVerificationModel;

    /**
     * Here are all the available MFA Options
     * the pipeline will run. Add your created
     * MFA option in here.
     */
    protected array $mfaOptionsRegistry;

    public function __construct(MfaCredential $mfaOption, MfaAttempt $mfaVerification)
    {
        $this->mfaOptionModel = $mfaOption;
        $this->mfaVerificationModel = $mfaVerification;
        $this->mfaOptionsRegistry = config('auth.mfa_options');
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

    public function runMfaPipeline(): array
    {
        return [];
    }
}
