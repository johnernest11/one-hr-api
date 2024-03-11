<?php

namespace App\Interfaces\Authentication;

use App\Models\User;

interface CanInvalidateAuthTokens
{
    /**
     * Invalidate the current access token of a user
     */
    public function invalidateCurrentToken(User $user): bool;

    /**
     * Invalidate multiple access tokens of a user
     */
    public function invalidateMultipleTokens(User $user, array $tokenIds): bool;
}
