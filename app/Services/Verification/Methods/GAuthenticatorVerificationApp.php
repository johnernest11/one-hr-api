<?php

namespace App\Services\Verification\Methods;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Services\Verification\AppVerificationMethod;
use App\Traits\Services\CanResolveModelFromId;
use ConversionHelper;
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

        return $this->google2fa->verify($secret, $input);
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

        $secret = $this->google2fa->generateSecretKey();
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
     */
    public function generateQrCode(int|string|User $user, string $companyName, string $holder, string $secret): string
    {
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', VerificationMethod::GOOGLE_AUTHENTICATOR)
            ->firstOrFail();

        $g2faUrl = $this->google2fa->getQRCodeUrl(
            $companyName,
            $holder,
            $verificationFactor->secret
        );

        return ConversionHelper::stringToBase64QrCode($g2faUrl);
    }

    /** {@inheritDoc} */
    public function verificationMethod(): VerificationMethod
    {
        return VerificationMethod::GOOGLE_AUTHENTICATOR;
    }
}
