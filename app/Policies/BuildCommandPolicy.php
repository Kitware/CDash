<?php

namespace App\Policies;

use App\Models\BuildCommand;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BuildCommandPolicy
{
    public function view(?User $user, BuildCommand $buildCommand): bool
    {
        return Gate::check('view', $buildCommand->build?->project);
    }
}
