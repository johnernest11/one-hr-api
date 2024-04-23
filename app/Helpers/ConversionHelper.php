<?php

namespace App\Helpers;

use InvalidArgumentException;
use NumberFormatter;

class ConversionHelper
{
    /*
     * Convert an Enum Class to its array version
     */
    public function enumToArray($enumClass, string $field = 'value'): array
    {
        if (! in_array($field, ['name', 'value'])) {
            throw new InvalidArgumentException('The `field` arg must either be `value` or `name`');
        }

        return array_column($enumClass::cases(), $field);
    }

    /**
     * Convert a number to its ordinal value.
     * E.g. 2 => 2nd
     */
    public function numberToOrdinal(int $number): string
    {
        $formatter = new NumberFormatter('en-US', NumberFormatter::ORDINAL);

        return $formatter->format($number);
    }
}
