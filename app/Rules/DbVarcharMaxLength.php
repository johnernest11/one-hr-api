<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Str;

class DbVarcharMaxLength implements ValidationRule
{
    private int $dbVarcharMaxLength;

    public function __construct()
    {
        $this->dbVarcharMaxLength = 255;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Str::length($value) > $this->dbVarcharMaxLength) {
            $fail("The :attribute must not exceed $this->dbVarcharMaxLength characters");
        }
    }
}
