<?php

namespace App\Enums;

enum MfaAuthTypes: string
{
    case EMAIL_OTP = 'email-otp';
    case SMS_OTP = 'sms-otp';
    case GOOGLE_AUTHENTICATOR = 'google-authenticator';
}
