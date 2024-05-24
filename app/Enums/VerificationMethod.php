<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case GOOGLE_AUTHENTICATOR = 'google_authenticator';
    case EMAIL_CHANNEL = 'email_channel';
}
