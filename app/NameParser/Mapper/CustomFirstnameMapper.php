<?php

namespace App\NameParser\Mapper;

use App\NameParser\Part\MiddlenamePrefix;
use TheIconic\NameParser\Mapper\FirstnameMapper;
use TheIconic\NameParser\Part\AbstractPart;
use TheIconic\NameParser\Part\Firstname;
use TheIconic\NameParser\Part\Lastname;
use TheIconic\NameParser\Part\LastnamePrefix;
use TheIconic\NameParser\Part\Middlename;

/**
 * Customizes the first name mapper. Now allows multiple first names which is common in the Philippines.
 */
class CustomFirstnameMapper extends FirstnameMapper
{
    public function map(array $parts): array
    {
        return $this->mapFrom($parts);
    }

    /**
     * @return mixed
     */
    protected function mapFrom($parts, ?int $start = 0): array
    {
        // maps the first name from the start of the array.
        // and continue doing so until middle name or last name is reached.

        $length = count($parts);

        for ($k = $start; $k < $length; $k++) {
            $part = $parts[$k];

            if ($part instanceof Middlename || $part instanceof MiddlenamePrefix || $part instanceof Lastname || $part instanceof LastnamePrefix) {
                break;
            }

            if ($part instanceof AbstractPart) {
                continue;
            }

            $parts[$k] = new Firstname($part);
        }

        return $parts;
    }
}
