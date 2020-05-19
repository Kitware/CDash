<?php
namespace App\Http\Controllers\Views;

require_once 'include/defines.php';

use App\Models\BuildTest;
use App\Models\Test;
use App\Services\TestingDay;
use CDash\Model\Build;
use CDash\Model\Project;

class TestController extends ProjectController
{
    protected $project;

    public function __construct()
    {
        parent::__construct();
    }

    protected function setup(Project $project = null)
    {
        parent::setup($this->project);
    }

    // Render the test details page.
    public function details($buildtest_id = null)
    {
        if (!$buildtest_id) {
            abort(404);
        }
        $buildtest = BuildTest::findOrFail($buildtest_id);
        $this->project = new Project();
        $this->project->Id = $buildtest->test->projectid;
        $this->setup($this->project);
        if (!$this->authOk) {
            return $this->redirectToLogin();
        }
        return view('test.details')
            ->with('buildtest', json_encode($buildtest))
            ->with('cdashCss', $this->cdashCss)
            ->with('date', json_encode($this->date))
            ->with('logo', json_encode($this->logo))
            ->with('projectname', json_encode($this->project->Name))
            ->with('title', "{$this->project->Name} : Test Details");
    }
}
