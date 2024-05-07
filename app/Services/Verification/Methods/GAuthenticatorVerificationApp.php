<?php

namespace App\Services\Verification\Methods;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Services\Verification\AppVerificationMethod;
use App\Traits\Services\CanResolveModelFromId;
use ConversionHelper;
use PragmaRX\Google2FA\Google2FA;
use Storage;
use Throwable;

class GAuthenticatorVerificationApp extends AppVerificationMethod
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
        $secret = $this->getOrCreateSecret($user);
        $g2faUrl = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);

        $logoPath = Storage::disk('assets')->path('verification-qrcode-logo.png');

        return ConversionHelper::stringToBase64QrCode($g2faUrl, 400, 4, $logoPath);
    }

    /** {@inheritDoc} */
    public function verificationMethod(): VerificationMethod
    {
        return VerificationMethod::GOOGLE_AUTHENTICATOR;
    }
}
