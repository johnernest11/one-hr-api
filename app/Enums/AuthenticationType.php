<?php

namespace App\Enums;

enum AuthenticationType: string
{
    /**
     * @note Sanctum uses an opaque token type
     *
     * @see https://laravel.com/docs/10.x/sanctum#issuing-mobile-api-tokens
     */
    case SANCTUM = 'sanctum';

    /** @see https://jwt.io */
    case JWT = 'jwt';

    /** @see https://www.twilio.com/docs/glossary/what-is-basic-authentication# */
    case BASIC_AUTH = 'basic_auth';
}
