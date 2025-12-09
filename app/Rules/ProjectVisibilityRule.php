<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class ProjectVisibilityRule implements ValidationRule
{
    /**
     * Verify that the current user is able to create/edit a project with the requested visibility.
     *
     * @param Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (int) $value;

        if ($value < 0 || $value > 2) {
            $fail('Invalid project visibility setting');
        }

        $user = Auth::user();
        // Admins can always set the project to whatever visibility they want
        if ($user === null || !$user->admin) {
            $max_visibility = match (Str::upper(config('cdash.max_project_visibility'))) {
                'PUBLIC' => Project::ACCESS_PUBLIC,
                'PROTECTED' => Project::ACCESS_PROTECTED,
                'PRIVATE' => Project::ACCESS_PRIVATE,
                default => $fail('This instance contains an improper MAX_PROJECT_VISIBILITY configuration.'),
            };

            // This horrible logic is an unfortunate byproduct of the decision to misorder the access numbering scheme,
            // which prevents range-based logic...
            if ($max_visibility === Project::ACCESS_PROTECTED && $value === Project::ACCESS_PUBLIC) {
                $fail('This instance is only configured to contain protected or private projects.');
            } elseif ($max_visibility === Project::ACCESS_PRIVATE && ($value === Project::ACCESS_PUBLIC || $value === Project::ACCESS_PROTECTED)) {
                $fail('This instance is only configured to contain private projects.');
            }
        }
    }
}
