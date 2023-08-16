<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

final class ManageMeasurementsController extends AbstractProjectController
{
    // Render the 'manage measurements' page.
    public function show($project_id)
    {
        $this->setProjectById((int) $project_id);
        Gate::authorize('edit-project', $this->project);

        return $this->view('admin.measurements', 'Test Measurements');
    }
}
