<?php

namespace App\Enums;

enum Role: string
{
    case STANDARD_USER = 'standard_user';
    case ADMIN = 'admin';
    case HR_PAS_ADMIN = 'hr_pas_admin';
    case HR_PPMS_ADMIN = 'hr_ppms_admin';
    case SYSTEM_SUPPORT = 'system_support';
    case SUPER_USER = 'super_user';
}
