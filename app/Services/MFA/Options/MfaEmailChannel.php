<?php

namespace App\Services\MFA\Options;

use App\Models\User;
use App\Services\MFA\MfaChannel;

class MfaEmailChannel extends MfaChannel
{
    /**
     * {@inheritDoc}
     */
    public function sendCode(User|int|string $modelOrId, string $code): bool
    {
        return true;
    }
}
