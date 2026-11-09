<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniqueEmailRule implements ValidationRule
{
    public function __construct(
        private readonly ?User $ignore = null,
    ) {
    }

    /**
     * Mail servers generally treat email addresses as case-insensitive, so two accounts can't have addresses
     * which only differ by case.
     *
     * @param Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $query = User::whereRaw('LOWER(email) = LOWER(?)', [$value]);
        if ($this->ignore !== null) {
            $query->whereKeyNot($this->ignore->id);
        }

        if ($query->exists()) {
            $fail('The :attribute has already been taken.');
        }
    }
}
