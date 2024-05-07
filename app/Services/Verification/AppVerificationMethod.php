<?php

namespace App\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Models\VfBackupCode;
use App\Traits\Services\CanResolveModelFromId;
use DB;
use Throwable;

abstract class AppVerificationMethod
{
    use CanResolveModelFromId;

    /**
     * Generate backup codes that the user can use if they lose their primary device
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
     * Verify if the backup code is valid
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

    /**
     * We only show the QR code for the user to scan during their initial login
     * with an app-based verification method. This method will flag the database if the user has
     * already enrolled, so we don't show the QR code everytime they log in
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
            ->first();

        return (bool) $verificationFactor?->enrolled_at;
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

    /**
     * Verify the code
     */
    abstract public function verifyCode(int|string|User $userModelOrId, string $input): bool;

    /**
     * Generate the secret where the verification codes will be based on
     */
    abstract public function getOrCreateSecret(User|int|string $userIdOrModel, bool $forceNew = false): string;

    /**
     * Assign a verification method. This will be used by the
     * App\Services\MfaOrchestrator class
     */
    abstract public function verificationMethod(): VerificationMethod;

    /**
     * Generate the QR code that the authenticator clients will scan
     */
    abstract public function generateQrCode(int|string|User $user): string;
}
