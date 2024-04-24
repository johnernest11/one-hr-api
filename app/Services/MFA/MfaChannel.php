<?php

namespace App\Services\MFA;

use App\Enums\MfaOption;
use App\Models\MfaCredential;
use App\Models\User;
use App\Traits\Services\CanResolveModelFromId;
use OTPHP\TOTP;

abstract class MfaChannel
{
    use CanResolveModelFromId;

    private MfaCredential $model;

    private int $codeExpirationSeconds;

    public function __construct(MfaCredential $model, int $codeExpirationInSeconds)
    {
        $this->model = $model;
        $this->codeExpirationSeconds = $codeExpirationInSeconds;
    }

    /** Create an MFA Code */
    public function generateCode(int|string|User $modelOrId): string
    {
        /** @var User $user */
        $user = $this->retrieveModel($modelOrId);
        $secret = $this->getSecret($user->id);
        $totp = TOTP::create($secret, $this->codeExpirationSeconds);

        return $totp->now();
    }

    /** Verify MFA code */
    public function verifyCode(int|string|User $modelOrId, string $input): bool
    {
        $user = $this->retrieveModel($modelOrId);
        $secret = $this->getSecret($user->id);
        $timestamp = time();
        $totp = TOTP::create($secret, $this->codeExpirationSeconds);

        $isCorrect = $totp->verify($input, $timestamp);
        if (! $isCorrect) {
            return false;
        }

        // Update the step in the `mfa_verifications` table
        return true;
    }

    protected function getSecret(int|string $userId): string
    {
        /** @var MfaCredential $secret */
        $mfaOption = $this->model::where('user_id', '=', $userId)
            ->where('type', '=', MfaOption::EMAIL_CHANNEL)
            ->first();

        if ($mfaOption) {
            return $mfaOption->secret;
        }

        $totp = TOTP::create();
        $secret = $totp->getSecret();

        $createdOption = $this->model::create([
            'user_id' => $userId,
            'type' => MfaOption::EMAIL_CHANNEL,
            'secret' => $secret,
        ]);

        return $createdOption->secret;
    }

    /** Send an MFA code notification to the user */
    abstract public function sendCode(int|string|User $modelOrId, string $code): bool;
}
