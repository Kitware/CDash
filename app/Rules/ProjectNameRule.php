<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ProjectNameRule implements ValidationRule
{
    /**
     * @param Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (preg_match('/^[a-zA-Z0-9\ +.\-_]+$/', $value) !== 1) {
            $fail('Project name may only contain letters, numbers, dashes, and underscores.');
        }
        if (str_contains($value, '_-_')) {
            $fail('Project name must not contain string "_-_"');
        }
    }
}
