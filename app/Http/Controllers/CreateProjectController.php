<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CreateProjectController extends AbstractController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('create', Project::class);

        return $this->vue('create-project-page', 'Create Project', [
            'max-project-visibility' => $request->user()->admin ?? false ? 'PUBLIC' : config('cdash.max_project_visibility'),
        ]);
    }
}
