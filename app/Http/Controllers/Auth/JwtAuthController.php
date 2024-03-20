<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\Authentication\Interfaces\AuthTokenManager;
use App\Services\User\UserManager;
use Carbon\Carbon;

class JwtAuthController extends AuthController
{
    private AuthTokenManager $authTokenManager;

    public function __construct(UserManager $userService, AuthTokenManager $authTokenManager)
    {
        parent::__construct($userService);
        $this->authTokenManager = $authTokenManager;
    }

    /** {@inheritDoc} */
    public function generateAuthToken(User $user, Carbon $expiresAt, string $clientName): string
    {
        return $this->authTokenManager->generateToken($user, $expiresAt, $clientName);
    }

    /** {@inheritDoc} */
    public function getTokenExpiration(): Carbon
    {
        return now()->addMinutes(config('jwt.lifetime_minutes'));
    }
}
