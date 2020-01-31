<?php
namespace App\Http\Controllers\Views;

require_once 'include/common.php';
require_once 'include/defines.php';

use CDash\Model\Build;
use CDash\Model\Project;

class BuildController extends ProjectController
{
    protected $build;

    public function __construct()
    {
        parent::__construct();
        $this->build = null;
        $this->date = date(FMT_DATETIME);
    }

    // Fetch data used by all build-specific pages in CDash.
    protected function setup($build_id = null)
    {
        if (!$build_id) {
            return;
        }

        $this->build = new Build();
        $this->build->FillFromId($build_id);

        parent::setup($this->build->GetProject());
        $this->date = $this->project->GetTestingDay($this->build->StartTime);
    }

    // Render the build summary page.
    public function summary($build_id = null)
    {
        $this->setup($build_id);
        return view('build.summary')
            ->with('build', json_encode($this->build))
            ->with('cdashCss', $this->cdashCss)
            ->with('date', json_encode($this->date))
            ->with('logo', json_encode($this->logo))
            ->with('projectname', json_encode($this->project->Name))
            ->with('title', "{$this->project->Name} - Build Summary");
    }
}
