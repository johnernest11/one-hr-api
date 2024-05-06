<?php

namespace App\Services\Verification\Methods;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Models\VfBackupCode;
use App\Services\Verification\AppVerificationMethod;
use App\Traits\Services\CanResolveModelFromId;
use ConversionHelper;
use DB;
use PragmaRX\Google2FA\Google2FA;
use Storage;
use Throwable;

class GAuthenticatorVerificationApp implements AppVerificationMethod
{
    use CanResolveModelFromId;

    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function verifyCode(int|string|User $userModelOrId, string $input): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $secret = $this->getOrCreateSecret($user->id);

        return $this->google2fa->verify($input, $secret);
    }

    /**
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function getOrCreateSecret(User|int|string $userIdOrModel, bool $forceNew = false): string
    {
        $user = $this->retrieveModel($userIdOrModel, User::query());
        /** @var VerificationFactor $secret */
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->first();

        if ($verificationFactor && ! $forceNew) {
            return $verificationFactor->secret;
        }

        $secret = $this->google2fa->generateSecretKey(32);
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
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function generateQrCode(int|string|User $user): string
    {
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->firstOrFail();

        $g2faUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $verificationFactor->secret
        );

        $logoPath = Storage::disk('assets')->path('verification-qrcode-logo.png');

        return ConversionHelper::stringToBase64QrCode($g2faUrl, 400, 4, $logoPath);
    }

    /**
     * {@inheritDoc}
     *
     * @param  int|string|VerificationFactor  $verificationFactor
     */
    public function completeEnrollment(User|int|string $userModelOrId): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->firstOrFail();

        $verificationFactor->enrolled_at = now();

        return $verificationFactor->save();
    }

    /**
     * Check if the user is enrolled to the verification method
     */
    public function userIsEnrolled(User|int|string $userModelOrId): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->firstOrFail();

        return (bool) $verificationFactor->enrolled_at;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function generateBackupCodes(User|int|string $userModelOrId, int $count = 10): array
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->firstOrFail();

        return DB::transaction(function () use ($verificationFactor, $count) {
            // We delete the old backup codes
            $verificationFactor->backupCodes()->delete();

            $generatedCodes = [];
            foreach (range(1, $count) as $ignored) {
                $generatedCodes[] = [
                    'verification_factor_id' => $verificationFactor->id,
                    'code' => $this->getBackupCode(),
                ];
            }

            $verificationFactor->backupCodes()->createMany($generatedCodes)->pluck('code');

            return array_map(fn (array $gc) => $gc['code'], $generatedCodes);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function verifyBackupCode(User|int|string $userModelOrId, string $code): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->firstOrFail();

        $backupCodes = $verificationFactor->backupCodes()->cursor();

        /** @var VfBackupCode $backupCode */
        foreach ($backupCodes as $backupCode) {
            if ($code === $backupCode->code && is_null($backupCode->used_at)) {
                return $backupCode->update(['used_at' => now()]);
            }
        }

        return false;
    }

    /** {@inheritDoc} */
    public function verificationMethod(): VerificationMethod
    {
        return VerificationMethod::GOOGLE_AUTHENTICATOR;
    }

    /**
     * Create a backup code
     */
    protected function getBackupCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $length = 12;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $code;
    }
}
