<?php

namespace App\Services\Verification\Methods;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Services\Verification\AppVerificationMethod;
use App\Traits\Services\CanResolveModelFromId;
use ConversionHelper;
use DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
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

        return ConversionHelper::stringToBase64QrCode($g2faUrl);
    }

    /** {@inheritDoc} */
    public function verificationMethod(): VerificationMethod
    {
        return VerificationMethod::GOOGLE_AUTHENTICATOR;
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
     * {@inheritDoc}
     *
     * @throws Throwable
     */
    public function generateBackupCodes(User|int|string $userModelOrId, int $count = 5): array
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
    public function verifyBackupCode(User|int|string $userModelOrId): bool
    {
        return true;
    }

    /**
     * Create a backup code
     */
    protected function getBackupCode(): string
    {
        return Str::upper(Str::uuid()->toString());
    }
}
