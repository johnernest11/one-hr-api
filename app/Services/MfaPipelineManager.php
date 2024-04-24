<?php

namespace App\Services;

use App\Models\MfaAttempt;
use Str;

class MfaOptionsPipeline
{
    /**
     * Here are all the available MFA Options
     * the pipeline will run. Add your created
     * MFA option in here.
     */
    protected array $mfaOptionsRegistry;

    public function __construct()
    {
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

        $createdToken = MfaAttempt::create([
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
