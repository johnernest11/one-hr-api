<?php

namespace App\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\User;

interface AppVerificationMethod
{
    /**
     * Verify the code
     */
    public function verifyCode(int|string|User $userModelOrId, string $input): bool;

    /**
     * Generate the secret where the verification codes will be based on
     */
    public function getOrCreateSecret(User|int|string $userIdOrModel, bool $forceNew = false): string;

    /**
     * Assign a verification method. This will be used by the
     * App\Services\MfaOrchestrator class
     */
    public function verificationMethod(): VerificationMethod;

    /**
     * Generate the QR code that the authenticator clients will scan
     */
    public function generateQrCode(int|string|User $user, string $companyName, string $holder, string $secret): string;
}
