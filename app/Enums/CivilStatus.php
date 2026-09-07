<?php

namespace App\Enums;

/**
 * List of marital status. See source below.
 *
 * @link https://psa.gov.ph/content/marital-status#:~:text=status%20of%20an%20individual%20in,who%20therefore%20can%20remarry%3B%20d)
 */
enum CivilStatus: string
{
    case SINGLE = 'Single';
    case MARRIED = 'Married';
    case WIDOWED = 'Widowed';
    case DIVORCED = 'Divorced';
    case SEPARATED = 'Separated';
    case UNNULLED = 'Annulled';
}
