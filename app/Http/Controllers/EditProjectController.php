<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

class EditProjectController extends AbstractProjectController
{
    // Render the create project form.
    public function create()
    {
        Gate::authorize('create-project');

        return view('admin.project')
            ->with('title', 'Create Project');
    }

    // Render the edit project form.
    public function edit($project_id)
    {
        $this->setProjectById((int) $project_id);
        Gate::authorize('edit-project', $this->project);

        return view('admin.project')
            ->with('project', $this->project)
            ->with('title', 'Edit Project');
    }
}
