<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

class ManageMeasurementsController extends ProjectController
{
    // Render the 'manage measurements' page.
    public function show($project_id)
    {
        $this->setProjectById((int) $project_id);
        Gate::authorize('edit-project', $this->project);

        return view('admin.measurements')
            ->with('project', $this->project)
            ->with('title', 'Test Measurements');
    }
}
