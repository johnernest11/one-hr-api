<?php

namespace App\Services\Authentication\Interfaces;

use App\Models\User;

interface CanInvalidateAuthTokens
{
    /**
     * Invalidate a specific access token
     */
    public function invalidateToken(string $token): bool;

    /**
     * Invalidate multiple access tokens of a user
     */
    public function invalidateMultipleTokens(User $user, array $tokenIds): bool;
}
