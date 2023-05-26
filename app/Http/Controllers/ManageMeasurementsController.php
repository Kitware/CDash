<?php

namespace App\Http\Controllers;

require_once 'include/common.php';
require_once 'include/defines.php';

use App\Services\ProjectPermissions;
use CDash\Model\Project;
use Illuminate\Support\Facades\Auth;

class ManageMeasurementsController extends ProjectController
{
    protected $project;

    public function __construct()
    {
        parent::__construct();
    }

    protected function setup($project_id = null): void
    {
        if (!is_null($project_id)) {
            $this->project = new Project();
            $this->project->Id = $project_id;
        }
        parent::setup($this->project);
    }

    // Render the 'manage measurements' page.
    public function show($project_id)
    {
        $this->setup($project_id);
        if (!Auth::check()) {
            return $this->redirectToLogin();
        }
        if (!$this->project->Exists()) {
            abort(404);
        }
        if (ProjectPermissions::userCanEditProject(Auth::user(), $this->project)) {
            return view('admin.measurements')
                ->with('date', json_encode($this->date))
                ->with('logo', json_encode($this->logo))
                ->with('projectid', $project_id)
                ->with('projectname', json_encode($this->project->Name))
                ->with('title', "{$this->project->Name} : Test Measurements");
        } else {
            abort(403, 'You do not have permission to access this page.');
        }
    }
}
