<?php

namespace App\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Services\User\UserAccountManager;
use App\Traits\Services\CanResolveModelFromId;
use App\Traits\Services\Verification\CanManageUserEnrollment;
use OTPHP\TOTP;

abstract class DeliveryVerificationMethod
{
    use CanManageUserEnrollment;
    use CanResolveModelFromId;

    private UserAccountManager $userAccountManager;

    protected bool $autoEnroll = true;

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

        return $totp->verify($input, $timestamp);
    }

    /**
     * Generate the secret where the verification codes will be based on
     */
    public function getOrCreateSecret(User|int|string $userIdOrModel, bool $forceNew = false): string
    {
        $user = $this->retrieveModel($userIdOrModel, User::query());
        /** @var VerificationFactor $secret */
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', $this->verificationMethod())
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
                'enrolled_at' => $this->autoEnroll ? now() : null,
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
