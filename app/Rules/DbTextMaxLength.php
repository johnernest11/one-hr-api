<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Str;

class DbTextMaxLength implements ValidationRule
{
    private int $dbMaxTextLength;

    public function __construct()
    {
        /** @see https://stackoverflow.com/questions/6766781/maximum-length-for-mysql-type-text */
        $this->dbMaxTextLength = 65535;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Str::length($value) > $this->dbMaxTextLength) {
            $fail("The :attribute must not exceed $this->dbMaxTextLength characters");
        }
    }
}
