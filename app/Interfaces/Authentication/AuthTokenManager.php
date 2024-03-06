<?php

namespace App\Interfaces\Authentication;

use App\Models\User;

interface AuthTokenManager
{
    /** Generate an JWT token for the user */
    public function generate(User $user);

    /** Validate a JWT Token */
    public function validate(string $token);
}
