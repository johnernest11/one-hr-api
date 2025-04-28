<?php

namespace App\Enums;

enum FamilyMemberCategory: string
{
    case SPOUSE = 'Spouse';
    case FATHER = 'Father';
    case MOTHER = 'Mother';
    case CHILDREN = 'Children';
}
