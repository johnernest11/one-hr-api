<?php

namespace App\Helpers;

use Endroid\QrCode\Builder\Builder;
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

    /**
     * Convert a string to a base64 QR code representation
     */
    public function stringToBase64QrCode(string $string, int $size = 400, int $margin = 4, ?string $logoPath = null): string
    {
        $result = Builder::create()
            ->data($string)
            ->size($size)
            ->margin($margin);

        if ($logoPath) {
            $result->logoPath($logoPath)
                ->logoResizeToWidth(50)
                ->logoPunchoutBackground(true);
        }

        return $result->build()->getDataUri();
    }
}
