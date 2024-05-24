<?php

namespace App\Rules;

use App\Services\Verification\AppVerificationMethod;
use App\Services\Verification\DeliveryVerificationMethod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class MfaStep implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $mfaRegisteredClasses = config('auth.mfa_methods');
        $validMethods = [];
        foreach ($mfaRegisteredClasses as $class) {
            /** @var DeliveryVerificationMethod|AppVerificationMethod $verificationMethod */
            $verificationMethod = resolve($class);
            $validMethods[] = $verificationMethod->verificationMethod()->value;
        }

        if (! in_array($value, $validMethods)) {
            $validMethodsInString = implode(', ', $validMethods);
            $fail("The valid :attribute values are: $validMethodsInString");
        }
    }
}
