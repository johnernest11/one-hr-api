<?php

namespace App\NameParser\Part;

use TheIconic\NameParser\Part\Middlename;

/**
 * Creates a new custom part. This is similar to the Lastname Prefix since Middlename is treated the same in the Philippines.
 */
class MiddlenamePrefix extends Middlename
{
    protected $normalized = '';

    public function __construct(string $value, ?string $normalized = null)
    {
        $this->normalized = $normalized ?? $value;

        parent::__construct($value);
    }

    /**
     * if this is a lastname prefix, look up normalized version from registry
     * otherwise camelcase the lastname
     */
    public function normalize(): string
    {
        return $this->normalized;
    }
}
