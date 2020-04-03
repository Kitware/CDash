<?php
namespace App\Http\Controllers\Views;

require_once 'include/common.php';
require_once 'include/defines.php';

use CDash\Model\Project;

abstract class ProjectController extends ViewController
{
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

    // Retrieve common data used by all build-specific pages in CDash.
    protected function setup(Project $project = null)
    {
        parent::setup();

        // Check if user is authorized to view this project.
        if (checkUserPolicy($this->user['id'], $project->Id, 1)) {
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
            $this->logo = env('APP_URL') . "/displayImage.php?imgid={$project->ImageId}";
        }
        $this->date = $project->GetTestingDay(date(FMT_DATETIME));
    }
}
