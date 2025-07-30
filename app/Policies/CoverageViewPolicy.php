<?php

namespace App\Policies;

use App\Models\CoverageView;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CoverageViewPolicy
{
    public function viewCode(?User $user, CoverageView $coverage): bool
    {
        $project = $coverage->build?->project;
        return Gate::allows('view', $project) && (bool) $project?->showcoveragecode;
    }
}
