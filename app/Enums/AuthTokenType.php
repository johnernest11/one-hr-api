<?php

namespace App\Enums;

enum AuthTokenType: string
{
    /** @Note Sanctum uses an opaque token type */
    case SANCTUM = 'SANCTUM';
    case JWT = 'JWT';
}
