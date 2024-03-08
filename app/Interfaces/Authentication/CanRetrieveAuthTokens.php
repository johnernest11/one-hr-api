<?php

namespace App\Interfaces\Authentication;

use App\Models\User;

interface CanRetrieveAuthTokens
{
    /**
     * Fetch all active access tokens of a user
     */
    public function getAllActiveTokens(User $user): array;
}
