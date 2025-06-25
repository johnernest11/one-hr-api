<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BeforeOrEqualCurrentMonthYear implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $expectedFormat = 'Y-m';

        try {
            // Get the passed value and set it to the start of the current month.
            $inputDate = Carbon::createFromFormat($expectedFormat, $value)->startOfMonth();

            // Also get the current start of the month date for comparison.
            $currentDate = Carbon::now()->startOfMonth();

            // Check if the input date is after the current month.
            if ($inputDate->isAfter($currentDate)) {
                $fail('The :attribute must be a month and year before or equal to the current month.');
            }
        } catch (\InvalidArgumentException $e) {
            // Catch incorrect formats.
            $fail('The :attribute must be in the valid YYYY-MM format.');
        }

    }
}
