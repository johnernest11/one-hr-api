<?php

namespace App\Enums;

enum MfaPipelineAction: string
{
    case SEND_CODE = 'send_code';
    case GENERATE_QRCODE = 'generate_qrcode';
    case VERIFY_CODE = 'verify_code';
    case GENERATE_SECRET = 'generate_secret';
}
