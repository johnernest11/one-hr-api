<?php

namespace App\Enums;

enum AuthenticationType: string
{
    /** @Note Sanctum uses an opaque token type */
    case SANCTUM = 'sanctum';
    case JWT = 'jwt';
}
