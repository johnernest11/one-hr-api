<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case PERMANENT = 'Permanent';

    case CONTRACTUAL = 'Contractual';

    case CASUAL = 'Casual';

    case CONTRACT_OF_SERVICE = 'Contract of Service';

    // Additional Employment Status for Individual Work Experience.
    case TEMPORARY = 'Temporary';
    case COTERMINOUS = 'Coterminous';
    case JOB_ORDER = 'Job Order';
    case PROBATIONARY = 'Probationary';
}
