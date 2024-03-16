<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Str;

class DbTextMaxLength implements ValidationRule
{
    private int $dbTextMaxLength;

    public function __construct()
    {
        /** @see https://stackoverflow.com/questions/6766781/maximum-length-for-mysql-type-text */
        $this->dbTextMaxLength = 65535;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Str::length($value) > $this->dbTextMaxLength) {
            $fail("The :attribute must not exceed $this->dbTextMaxLength characters");
        }
    }
}
