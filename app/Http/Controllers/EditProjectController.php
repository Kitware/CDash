<?php
namespace App\Http\Controllers;

require_once 'include/common.php';
require_once 'include/defines.php';

use App\Services\ProjectPermissions;
use CDash\Model\Project;
use Illuminate\Support\Facades\Auth;

class EditProjectController extends ProjectController
{
    protected $build;

    public function __construct()
    {
        parent::__construct();
    }

    protected function setup($project_id = null): void
    {
        if (!is_null($project_id)) {
            $this->project = new Project();
            $this->project->Id = $project_id;
            $this->project->Fill();
        }
    }

    // Render the create project form.
    public function create()
    {
        $this->setup();
        if (!Auth::check()) {
            return $this->redirectToLogin();
        }
        if (ProjectPermissions::userCanCreateProject(Auth::user())) {
            $project_name = 'CDash';
            return view('admin.project')
                ->with('cdashCss', $this->cdashCss)
                ->with('date', json_encode($this->date))
                ->with('logo', json_encode($this->logo))
                ->with('projectid', 0)
                ->with('projectname', json_encode($project_name))
                ->with('title', 'Create Project');
        } else {
            abort(403, 'You do not have permission to access this page.');
        }
    }

    // Render the edit project form.
    public function edit($project_id)
    {
        $this->setup($project_id);
        if (!Auth::check()) {
            return $this->redirectToLogin();
        }
        if (!$this->project->Exists()) {
            abort(404);
        }
        if (ProjectPermissions::userCanEditProject(Auth::user(), $this->project)) {
            return view('admin.project')
                ->with('cdashCss', $this->cdashCss)
                ->with('date', json_encode($this->date))
                ->with('logo', json_encode($this->logo))
                ->with('projectid', $project_id)
                ->with('projectname', json_encode($this->project->Name))
                ->with('title', "Edit Project");
        } else {
            abort(403, 'You do not have permission to access this page.');
        }
    }
}
