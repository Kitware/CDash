<?php

namespace App\Policies;

use App\Models\DynamicAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DynamicAnalysisPolicy
{
    public function view(?User $user, DynamicAnalysis $dynamicAnalysis): bool
    {
        return Gate::check('view', $dynamicAnalysis->build?->project);
    }
}
