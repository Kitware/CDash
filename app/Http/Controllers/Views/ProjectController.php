<?php
namespace App\Http\Controllers\Views;

require_once 'include/common.php';
require_once 'include/defines.php';

use App\Services\TestingDay;
use App\Services\ProjectPermissions;
use CDash\Model\Project;

abstract class ProjectController extends ViewController
{
    protected $authOk;
    protected $date;
    protected $logo;
    protected $project;

    public function __construct()
    {
        parent::__construct();
        $this->project = null;
        $this->authOk = false;
        $this->date = date(FMT_DATETIME);
        $this->logo = env('APP_URL') . '/img/cdash.png';
    }

    // Retrieve common data used by all project-specific pages in CDash.
    protected function setup(Project $project = null)
    {
        parent::setup();

        if (is_null($project)) {
            return;
        }

        // Check if user is authorized to view this project.
        if (ProjectPermissions::userCanViewProject($project)) {
            $this->authOk = true;
        } else {
            $this->authOk = false;
            if (\Auth::check()) {
                abort(403, 'You do not have permission to access this page.');
            }
            // redirectToLogin() gets called later on.
            return;
        }

        $this->project = $project;
        $this->project->Fill();
        if ($project->ImageId) {
            $this->logo = env('APP_URL') . "/displayImage.php?imgid={$this->project->ImageId}";
        }
        $this->date = TestingDay::get($this->project, date(FMT_DATETIME));
    }
}
