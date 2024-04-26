<?php

namespace App\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Traits\Services\CanResolveModelFromId;
use App\Traits\Services\MfaPipeStage;
use OTPHP\TOTP;

abstract class DeliveryVerificationMethod
{
    use CanResolveModelFromId;
    use MfaPipeStage;

    /** Create an MFA Code */
    public function generateCode(int|string|User $modelOrId): string
    {
        /** @var User $user */
        $user = $this->retrieveModel($modelOrId, User::query());
        $secret = $this->generateSecret($user->id);
        $totp = TOTP::create($secret, $this->getCodeExpirationSeconds());

        return $totp->now();
    }

    /** Verify MFA code */
    public function verifyCode(int|string|User $userModelOrId, string $input): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $secret = $this->generateSecret($user->id);
        $timestamp = time();
        $totp = TOTP::create($secret, $this->getCodeExpirationSeconds());

        $isCorrect = $totp->verify($input, $timestamp);
        if (! $isCorrect) {
            return false;
        }

        // Update the step in the `mfa_attempts` table
        return true;
    }

    protected function generateSecret(User|int|string $userIdOrModel, bool $forceNew = false): string
    {
        $user = $this->retrieveModel($userIdOrModel, User::query());
        /** @var VerificationFactor $secret */
        $verificationFactor = VerificationFactor::where('user_id', '=', $user->id)
            ->where('type', '=', VerificationMethod::EMAIL_CHANNEL)
            ->first();

        if ($verificationFactor && ! $forceNew) {
            return $verificationFactor->secret;
        }

        $totp = TOTP::create();
        $secret = $totp->getSecret();

        $verificationFactor = VerificationFactor::updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => $this->verificationMethod(),
            ],
            [
                'user_id' => $user->id,
                'type' => $this->verificationMethod(),
                'secret' => $secret,
            ]
        );

        return $verificationFactor->secret;
    }

    /**
     * The time it takes before the MFA Code expires (10 minutes default).
     */
    protected function getCodeExpirationSeconds(): int
    {
        return 10 * 60;
    }

    /** Send an MFA code notification to the user */
    abstract public function sendCode(int|string|User $userModelOrId, string $code): string;

    /** Assign a verification method for the subclass */
    abstract public function verificationMethod(): VerificationMethod;
}
