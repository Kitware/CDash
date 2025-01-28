<?php

namespace App\Policies;

use App\Enums\RegistrationPermissionsLevel;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

class UserPolicy
{
    protected function AttemptValueBuild(): int
    {
        return (bool) env('USER_REGISTRATION_FORM_ENABLED', true) ? RegistrationPermissionsLevel::PUBLIC->value : ((bool) env('PROJECT_ADMIN_REGISTRATION_FORM_ENABLED', true) ? RegistrationPermissionsLevel::PROJECT_ADMIN->value : RegistrationPermissionsLevel::ADMIN->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $user_permission_level = Project::whereRelation('administrators', 'users.id', request()->user()?->id)->exists() ? 1 : 0;
        $user_permission_level = $user->admin ? 2 : $user_permission_level;
        $registration_permission_level_required = match (Str::upper(config('auth.user_registration_access_level_required'))) {
            'PUBLIC' => RegistrationPermissionsLevel::PUBLIC->value,
            'PROJECT_ADMIN' => RegistrationPermissionsLevel::PROJECT_ADMIN->value,
            'ADMIN' => RegistrationPermissionsLevel::ADMIN->value,
            'DISABLED' => RegistrationPermissionsLevel::DISABLED->value,
            default => $this->AttemptValueBuild(),
        };

        // Fail if the caller is requesting a value that the setting disallows
        return $user_permission_level >= $registration_permission_level_required;
    }
}
