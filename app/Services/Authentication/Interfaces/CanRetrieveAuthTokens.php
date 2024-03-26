<?php

namespace App\Services\Authentication\Interfaces;

use App\Models\User;

interface CanRetrieveAuthTokens
{
    /**
     * Fetch all active access tokens of a user
     */
    public function getAllActiveTokens(User $user): array;
}
