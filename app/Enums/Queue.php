<?php

namespace App\Enums;

enum Queue: string
{
    case EMAILS = 'emails';
    case DEFAULT = 'default';
    case DEV_ALERTS = 'dev_alerts';
    case OTP = 'otp';
}
