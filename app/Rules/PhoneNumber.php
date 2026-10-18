<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = is_string($value) ? preg_replace('/[^0-9]/', '', $value) : '';
        if (! is_string($value) || ! preg_match('/^\+?[0-9 ()-]+$/D', $value) || strlen($digits) < 7 || strlen($digits) > 15) {
            $fail('Enter a valid phone number with 7 to 15 digits. A leading +, spaces, parentheses and hyphens are allowed.')->translate();
        }
    }
}
