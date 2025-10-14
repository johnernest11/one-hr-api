<?php

namespace App\Enums;

enum ApprovalType: string
{
    case OFFICIAL_BUSINESS = 'official_business';
    case OFFICIAL_TIME = 'official_time';
    case PERSONAL_TIME = 'personal_time';
}
