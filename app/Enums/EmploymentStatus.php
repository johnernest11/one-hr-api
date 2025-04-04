<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case PERMANENT = 'Permanent';

    case CONTRACTUAL = 'Contractual';

    case CASUAL = 'Casual';

    case CONTRACT_OF_SERVICE = 'Contract of Service';
}
