<?php

namespace App\Interfaces\Authentication;

use App\Models\User;

interface AuthTokenManager
{
    /** Generate an JWT token for the user */
    public function generate(User $user): string;

    /** Validate a JWT Token */
    public function isValid(string $token): bool;
}
