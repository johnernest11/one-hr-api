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
    public function generateQrCode(int|string|User $user): string;

    /**
     * Generate backup codes that the user can use if they lose their primary device
     */
    public function generateBackupCodes(User|int|string $userModelOrId, int $count = 5): array;

    /**
     * Verify if the backup code is valid
     */
    public function verifyBackupCode(User|int|string $userModelOrId): bool;

    /**
     * We only show the QR code for the user to scan during their initial login
     * with an app-based MFA. This method will flag the database if the user has
     * already enrolled, so we don't show the QR code everytime they log in
     */
    public function completeEnrollment(User|int|string $userModelOrId): bool;
}
