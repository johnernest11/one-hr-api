<?php

namespace App\Enums;

enum AcademicLevel: string
{
    case ELEMENTARY = 'Elementary';
    case SECONDARY = 'Secondary';
    case VOCATIONAL = 'Vocational';
    case COLLEGE = 'College';
    case GRADUATE = 'Graduate';
}
