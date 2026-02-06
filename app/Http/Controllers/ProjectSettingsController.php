<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ProjectSettingsController extends AbstractProjectController
{
    public function __invoke(Request $request, int $project_id): View
    {
        $this->setProjectById($project_id);

        $project = Project::find($project_id);
        Gate::authorize('update', $project);

        return $this->vue('project-settings-page', 'Project Settings', [
            'project-id' => $project_id,
            'ldap-enabled' => (bool) config('cdash.ldap_enabled'),
        ]);
    }
}
