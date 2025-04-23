<?php

namespace App\Enums;

/**
 * List of marital status. See source below.
 *
 * @link https://psa.gov.ph/content/marital-status#:~:text=status%20of%20an%20individual%20in,who%20therefore%20can%20remarry%3B%20d)
 */
enum Citizenship: string
{
    case FILIPINO = 'Filipino';
    case DUAL_CITIZENSHIP = 'Dual Citizenship';
}
