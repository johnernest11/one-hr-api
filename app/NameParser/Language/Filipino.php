<?php

namespace App\NameParser\Language;

use TheIconic\NameParser\Language\English;

/**
 * Added additional common lastname prefixes in the Philippines
 */
class Filipino extends English
{
    /**
     * Overrides the last name prefixes by adding new Filipino-specific ones.
     */
    public function getLastnamePrefixes(): array
    {
        return array_merge(
            parent::getLastnamePrefixes(),
            [
                'dela' => 'dela',
                'delos' => 'delos',
                'los' => 'los',
                'san' => 'san',
                'sta' => 'sta.',
                'santa' => 'santa',
            ]
        );
    }
}
