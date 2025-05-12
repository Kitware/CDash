<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

final class EditProjectController extends AbstractProjectController
{
    // Render the create project form.
    public function create()
    {
        Gate::authorize('create-project');

        return $this->vue('edit-project', 'Create Project', ['projectid' => 0], false);
    }

    // Render the edit project form.
    public function edit($project_id)
    {
        $this->setProjectById((int) $project_id);
        Gate::authorize('edit-project', $this->project);

        return $this->vue('edit-project', 'Edit Project', ['projectid' => $this->project->Id], false);
    }
}
