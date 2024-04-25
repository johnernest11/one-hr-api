<?php

namespace App\Services\Verification;

use App\Enums\MfaOption;
use App\Models\MfaCredential;
use App\Models\User;
use App\Traits\Services\CanResolveModelFromId;
use App\Traits\Services\MfaPipeStage;
use OTPHP\TOTP;

abstract class VerificationChannel
{
    use CanResolveModelFromId;
    use MfaPipeStage;

    private int $codeExpirationSeconds;

    public function __construct(int $codeExpirationInSeconds)
    {
        $this->codeExpirationSeconds = $codeExpirationInSeconds;
    }

    /** Create an MFA Code */
    public function generateCode(int|string|User $modelOrId): string
    {
        /** @var User $user */
        $user = $this->retrieveModel($modelOrId, User::query());
        $secret = $this->generateSecret($user->id);
        $totp = TOTP::create($secret, $this->codeExpirationSeconds);

        return $totp->now();
    }

    /** Verify MFA code */
    public function verifyCode(int|string|User $modelOrId, string $input): bool
    {
        $user = $this->retrieveModel($modelOrId, User::query());
        $secret = $this->generateSecret($user->id);
        $timestamp = time();
        $totp = TOTP::create($secret, $this->codeExpirationSeconds);

        $isCorrect = $totp->verify($input, $timestamp);
        if (! $isCorrect) {
            return false;
        }

        // Update the step in the `mfa_verifications` table
        return true;
    }

    protected function generateSecret(int|string $userId, bool $forceNew = false): string
    {
        /** @var MfaCredential $secret */
        $mfaOption = User::where('user_id', '=', $userId)
            ->where('type', '=', MfaOption::EMAIL_CHANNEL)
            ->first();

        if ($mfaOption && ! $forceNew) {
            return $mfaOption->secret;
        }

        $totp = TOTP::create();
        $secret = $totp->getSecret();

        $createdOption = User::updateOrCreate(
            [
                'user_id' => $userId,
                'type' => MfaOption::EMAIL_CHANNEL,
            ],
            [
                'secret' => $secret,
            ]
        );

        return $createdOption->secret;
    }

    /** Send an MFA code notification to the user */
    abstract public function sendCode(int|string|User $modelOrId, string $code): bool;
}
