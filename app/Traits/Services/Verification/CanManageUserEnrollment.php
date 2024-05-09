<?php

namespace App\Traits\Services\Verification;

use App\Enums\VerificationMethod;
use App\Models\User;
use App\Models\VerificationFactor;
use App\Traits\Services\CanResolveModelFromId;

trait CanManageUserEnrollment
{
    use CanResolveModelFromId;

    /**
     * We only show the QR code for the user to scan during their initial login
     * with an app-based verification method. This method will flag the database if the user has
     * already enrolled, so we don't show the QR code everytime they log in
     */
    public function completeEnrollment(User|int|string $userModelOrId, VerificationMethod $method): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', $method)
            ->firstOrFail();

        $verificationFactor->enrolled_at = now();

        return $verificationFactor->save();
    }

    /**
     * Check if the user is enrolled to the verification method
     */
    public function userIsEnrolled(User|int|string $userModelOrId, VerificationMethod $method): bool
    {
        $user = $this->retrieveModel($userModelOrId, User::query());
        $verificationFactor = VerificationFactor::where('user_id', $user->id)
            ->where('type', '=', $method)
            ->first();

        return (bool) $verificationFactor?->enrolled_at;
    }
}
