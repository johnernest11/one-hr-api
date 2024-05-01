<?php

namespace App\Services\Authentication\Interfaces;

use App\Models\User;
use Carbon\Carbon;

interface AuthTokenManager
{
    /**
     * Generate an auth token for the user
     *
     * @param  User  $user  - The token will be generated for a specific user
     * @param  string  $clientName  - Clients (SPA, mobile) can optionally send 'My iPhone14', 'Google Chrome', etc
     */
    public function generateToken(User $user, Carbon $expiresAt, string $clientName = ''): string;

    /** Validate an auth token */
    public function tokenIsValid(string $token): bool;

    public function getTokenOwner(string $token): ?User;
}
