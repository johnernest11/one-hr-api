<?php

namespace App\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Traits\Services\CanResolveModelFromId;
use OTPHP\TOTP;

abstract class DeliveryVerificationMethod
{
    use CanResolveModelFromId;

    /**
     * Create a verification code
     */
    public function generateCode(int|string|User $modelOrId): string
    {
        /** @var User $user */
        $user = $this->retrieveModel($modelOrId, User::query());
        $secret = $this->getOrCreateSecret($user->id);
        $totp = TOTP::create($secret, $this->getCodeExpirationSeconds());

        return $totp->now();
    }

    /**
     * Verify the code
     */
    public function verifyCode(int|string|User $userModelOrId, string $input): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $secret = $this->getOrCreateSecret($user->id);
        $timestamp = time();
        $totp = TOTP::create($secret, $this->getCodeExpirationSeconds());
        $success = $totp->verify($input, $timestamp);

        // Automatically verify the user's email if the verification factor is the Email Channel
        if ($success && $this->verificationMethod() === VerificationMethod::EMAIL_CHANNEL) {
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
                $user->save();
            }
        }

        return $success;
    }

    /**
     * Generate the secret where the verification codes will be based on
     */
    public function getOrCreateSecret(User|int|string $userIdOrModel, bool $forceNew = false): string
    {
        $user = $this->retrieveModel($userIdOrModel, User::query());
        /** @var VerificationFactor $secret */
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
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

                // Delivery based typically do not show one-time creds to be scanned / noted by the user,
                // so they are marked as enrolled as default
                'enrolled_at' => now(),
            ]
        );

        return $verificationFactor->secret;
    }

    /**
     * The time it takes before the MFA Code expires (10 minutes default).
     */
    protected function getCodeExpirationSeconds(): int
    {
        return 15 * 60;
    }

    /** Send an MFA code notification to the user */
    abstract public function sendCode(int|string|User $userModelOrId, string $code): string;

    /** Assign a verification method for the subclass */
    abstract public function verificationMethod(): VerificationMethod;
}
