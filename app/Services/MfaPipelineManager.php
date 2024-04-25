<?php

namespace App\Services;

use App\Models\MfaAttempt;
use App\Models\User;
use App\Traits\Services\CanResolveModelFromId;
use Hash;
use Log;
use Str;

class MfaPipelineManager
{
    use CanResolveModelFromId;

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
    public function generateMfaAttemptToken(User|int|string $userModelOrId, array|string $mfaSteps): string
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $token = Str::upper(Str::uuid());

        $createdToken = MfaAttempt::create([
            'user_id' => $user->id,
            'token' => $token,
            'steps' => $mfaSteps,
        ]);

        // Raw Token Format: 1|XXX-YYYY-ZZZ
        $createdToken->rawTokenValue = "$createdToken->id|$token";

        return $createdToken;
    }

    public function verifyMfaToken(string $mfaToken): bool
    {
        $idAndToken = explode('|', $mfaToken);
        if (count($idAndToken) !== 2) {
            Log::debug('Cannot separate the MFA token ID from the raw value correctly', [
                'value' => $mfaToken,
                'method' => __METHOD__,
            ]);

            return false;
        }

        [$id, $token] = $idAndToken;

        $mfaAttempt = MfaAttempt::find($id);
        if (! $mfaAttempt) {
            Log::debug('MFA Attempt ID not found', ['ID' => $id, 'method' => __METHOD__]);

            return false;
        }

        if (! Hash::check($token, $mfaAttempt->token)) {
            Log::debug('MFA Attempt token is invalid', ['token' => $token, 'method' => __METHOD__]);

            return false;
        }

        return true;
    }

    public function runSecretGeneration(User|int|string $user): bool
    {
        return true;
    }

    /**
     * Deliver the OTP to the user for the current
     * channel-based MFA in the pipeline
     */
    public function runCodeDelivery(): bool
    {
        return true;
    }

    /**
     * Verify the code given by the user
     * with the current MFA option in the pipeline
     */
    public function runCodeVerification(): bool
    {
        return true;
    }
}
