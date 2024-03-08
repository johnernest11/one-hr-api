<?php

namespace App\Services\Authentication;

use App\Interfaces\Authentication\PersistentAuthTokenManager;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class SanctumAuthService implements PersistentAuthTokenManager
{
    /** {@inheritDoc} */
    public function generateToken(User $user, string $clientName = ''): string
    {
        /**
         * We'll set the abilities to allow everything [*]. Authorization will be handled by Spatie
         *
         * @see https://spatie.be/docs/laravel-permission/v5/introduction
         */
        $expiresAt = now()->addMinutes(config('sanctum.expiration'));

        return $user->createToken($clientName, ['*'], $expiresAt)->plainTextToken;
    }

    /** {@inheritDoc} */
    public function tokenIsValid(string $token): bool
    {
        $sanctumToken = PersonalAccessToken::findToken($token);

        // The token maybe pruned / deleted
        if (! $sanctumToken) {
            echo 'Token not found in the DB'.PHP_EOL;

            return false;
        }

        // Check if the owner of this token still exists
        if (! $sanctumToken->tokenable()->exists()) {
            echo 'Owner already deleted'.PHP_EOL;

            return false;
        }

        // Check the token has not expired
        if ($sanctumToken->expires_at && $sanctumToken->expires_at->isPast()) {
            echo 'Token has expired'.PHP_EOL;

            return false;
        }

        return true;
    }

    /** {@inheritDoc} */
    public function invalidateMultipleTokens(User $user, array $tokenIds): array
    {
        // TODO: Implement invalidateMultiple() method.
        return [];
    }

    /** {@inheritDoc} */
    public function invalidateCurrentToken(User $user): bool
    {
        // TODO: Implement invalidate() method.
        return true;
    }

    /** {@inheritDoc} */
    public function getAllActiveTokens(User $user): array
    {
        // TODO: Implement all() method.
        return [];
    }
}
