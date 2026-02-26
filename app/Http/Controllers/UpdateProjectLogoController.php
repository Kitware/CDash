<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateProjectLogoController extends AbstractController
{
    /**
     * @throws Exception
     * @throws ValidationException
     */
    public function __invoke(Request $request, int $project_id): RedirectResponse
    {
        $project = Project::find($project_id);
        Gate::authorize('update', $project);

        $request->validate([
            'logo' => 'required|image',
        ]);

        if ($project === null) {
            throw new Exception();
        }

        ProjectService::setLogo($project, $request->file('logo'));

        return response()->redirectTo(url("/projects/{$project->id}/settings"));
    }
}
