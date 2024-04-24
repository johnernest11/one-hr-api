<?php

namespace App\Services\Verification\Options;

use App\Models\User;
use App\Services\Verification\VerificationChannel;

class EmailVerificationChannel extends VerificationChannel
{
    /**
     * {@inheritDoc}
     */
    public function sendCode(User|int|string $modelOrId, string $code): bool
    {
        return true;
    }
}
