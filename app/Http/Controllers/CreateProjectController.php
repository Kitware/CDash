<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class CreateProjectController extends AbstractController
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('create', Project::class);

        $isAdmin = $request->user()->admin ?? false;

        return $this->vue('create-project-page', 'Create Project', [
            'max-project-visibility' => $isAdmin ? 'PUBLIC' : Str::upper((string) config('cdash.max_project_visibility')),
            'authenticated-submissions-required' => !$isAdmin && (bool) config('cdash.require_authenticated_submissions'),
        ]);
    }
}
