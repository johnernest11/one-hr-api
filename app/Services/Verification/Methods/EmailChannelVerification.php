<?php

namespace App\Services\Verification\Methods;

use App\Models\User;
use App\Services\Verification\DeliveryVerificationMethod;

class EmailChannelVerification extends DeliveryVerificationMethod
{
    /**
     * {@inheritDoc}
     */
    public function sendCode(User|int|string $modelOrId, string $code): bool
    {
        return true;
    }
}
