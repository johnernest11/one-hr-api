<?php

namespace App\Enums;

enum MfaOption: string
{
    case GOOGLE_AUTHENTICATOR = 'google_authenticator';
    case EMAIL_CHANNEL = 'email_channel';
    case SMS_CHANNEL = 'sms_channel';
}
