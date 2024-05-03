<?php

namespace App\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;

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
    public function generateQrCode(int|string|User $user): string;

    /**
     * Generate backup codes that the user can use if they lose their primary device
     */
    public function generateBackupCodes(VerificationFactor|int|string $verificationFactor, int $count = 5): array;

    /**
     * Verify if the backup code is valid
     */
    public function verifyBackupCode(VerificationFactor|int|string $verificationFactor): bool;
}
