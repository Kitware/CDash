<?php

namespace App\Policies;

use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TestPolicy
{
    public function view(?User $user, Test $test): bool
    {
        return Gate::check('view', $test->build);
    }
}
