<?php

namespace App\Services\Authentication;

use App\Interfaces\Authentication\AuthTokenManager;
use App\Models\User;
use JWT;

class JWTAuthService implements AuthTokenManager
{
    private User $model;

    public function __construct(User $user)
    {
        $this->model = $user;
    }

    /** {@inheritDoc} */
    public function generate(User $user)
    {
        return JWT::get(
            $user->id,
            [],
            now()->addSeconds(config('auth.jwt.lifetime_seconds')),
            config('auth.jwt.signing_key')
        );
    }

    /** {@inheritDoc} */
    public function validate(string $token)
    {
        // TODO: Implement validate() method.
    }
}
