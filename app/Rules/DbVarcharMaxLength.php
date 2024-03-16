<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Str;

class DbVarcharMaxLength implements Rule
{
    private int $dbMaxVarCharLength;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->dbMaxVarCharLength = 255;
    }

    /**
     * Check if the string length does not exceed DB allowed VARCHAR length
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        return Str::length($value) <= $this->dbMaxVarCharLength;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return "The :attribute must not exceed $this->dbMaxVarCharLength characters";
    }
}
