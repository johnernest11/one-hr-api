<?php

namespace App\Services\Authentication;

use App\Interfaces\Services\Authentication\PersistentAuthTokenManager;
use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Log;

class SanctumAuthService implements PersistentAuthTokenManager
{
    /** {@inheritDoc} */
    public function generateToken(User $user, Carbon $expiresAt, string $clientName = ''): string
    {
        /**
         * We'll set the abilities to allow everything [*]. Authorization will be handled by Spatie
         *
         * @see https://spatie.be/docs/laravel-permission/v5/introduction
         */
        return $user->createToken($clientName, ['*'], $expiresAt)->plainTextToken;
    }

    /** {@inheritDoc} */
    public function tokenIsValid(string $token): bool
    {
        $sanctumToken = PersonalAccessToken::findToken($token);

        // The token maybe pruned / deleted
        if (! $sanctumToken) {
            return false;
        }

        // Check if the owner of this token no longer exists. We log as warning if someone is still trying to
        // use the token.
        if (! $sanctumToken->tokenable()->exists()) {
            Log::warning(__CLASS__, ['error' => 'Owner no longer exists']);

            return false;
        }

        // Check the token has not expired
        if ($sanctumToken->expires_at && $sanctumToken->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getTokenOwner(string $token): ?User
    {
        if (! $this->tokenIsValid($token)) {
            return null;
        }

        $sanctumToken = PersonalAccessToken::findToken($token);

        /** @var User $user */
        $user = $sanctumToken->tokenable;

        return $user;
    }

    /** {@inheritDoc} */
    public function invalidateCurrentToken(User $user): bool
    {
        return (bool) $user->currentAccessToken()->delete();
    }

    /** {@inheritDoc} */
    public function invalidateMultipleTokens(User $user, array $tokenIds): bool
    {
        // delete everything if they pass a star (*)
        if ($tokenIds === ['*']) {
            return (bool) $user->tokens()->delete();
        }

        return (bool) $user->tokens()->whereIn('id', $tokenIds)->delete();
    }

    /** {@inheritDoc} */
    public function getAllActiveTokens(User $user): array
    {
        return $user->tokens
            ->map(function (PersonalAccessToken $token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'expires_at' => $token->expires_at,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                ];
            })
            // only get un-expired tokens
            ->reject(fn (array $token) => now() >= $token['expires_at'])
            ->values()
            ->toArray();
    }
}
