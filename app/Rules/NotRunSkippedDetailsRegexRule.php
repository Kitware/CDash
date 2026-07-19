<?php

namespace App\Rules;

use App\Utils\TestDisplay;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class NotRunSkippedDetailsRegexRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            $fail('The skipped test details pattern must be a string.');
            return;
        }

        if (!TestDisplay::isValidPatternsText($value)) {
            $fail('The skipped test details pattern contains an invalid regular expression.');
        }
    }
}
