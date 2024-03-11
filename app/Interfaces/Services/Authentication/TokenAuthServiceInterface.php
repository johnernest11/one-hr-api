<?php

namespace App\Interfaces\Services\Authentication;

use App\Models\User;

interface TokenAuthServiceInterface
{
    /**
     * Create access token for the user
     */
    public function bindAuthToken(User $user, string $tokenName, int $expiresAtHours = 12, bool $withUserDetails = true): array;

    /**
     * Delete the current access token of a user
     */
    public function destroyCurrentAuthToken(User $user): User;

    /**
     * Fetch all active access tokens of a user
     */
    public function getUserAuthTokens(User $user): array;

    /**
     * Delete multiple access tokens of a user
     */
    public function destroyAccessTokens(User $user, array $tokenIds): array;
}
