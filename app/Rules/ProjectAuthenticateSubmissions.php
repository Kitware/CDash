<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Translation\PotentiallyTranslatedString;

class ProjectAuthenticateSubmissions implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (bool) $value;

        $user = Auth::user();
        if ($user !== null && $user->admin) {
            // Instance admins can set any value they wish
            return;
        }

        if ((bool) config('cdash.require_authenticated_submissions') && !$value) {
            $fail('This CDash instance is configured to require authenticated submissions.');
        }
    }
}
