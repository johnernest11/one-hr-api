<?php

namespace App\Traits\Controllers;

use App\Models\User;
use Carbon\Carbon;

trait CanComposeUserTokenData
{
    public function composeUserTokenData(string $token, string $clientName, Carbon $expiresAt, User $user, bool $withUserDetails = true): array
    {
        return [
            'token' => $token,
            'token_name' => $clientName,
            'expires_at' => $expiresAt,
            'user' => $withUserDetails ? $user->fresh('userProfile') : $user,
        ];
    }
}
