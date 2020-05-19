<?php
namespace App\Http\Controllers\Views;

require_once 'include/common.php';
require_once 'include/defines.php';

use App\Services\TestingDay;
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
            abort(404);
        }

        $this->build = new Build();
        $this->build->Id = $build_id;
        $this->build->FillFromId($build_id);
        if (!$this->build->Exists()) {
            abort(404);
        }

        parent::setup($this->build->GetProject());
        if (!is_null($this->project)) {
            $this->date = TestingDay::get($this->project, $this->build->StartTime);
        }
    }

    // Render the build configure page.
    public function configure($build_id = null)
    {
        return $this->renderBuildPage($build_id, 'configure');
    }

    // Render the build notes page.
    public function notes($build_id = null)
    {
        return $this->renderBuildPage($build_id, 'notes');
    }

    // Render the build summary page.
    public function summary($build_id = null)
    {
        return $this->renderBuildPage($build_id, 'summary', 'Build Summary');
    }

    protected function renderBuildPage($build_id = null, $page_name, $page_title = '')
    {
        $this->setup($build_id);
        if (!$this->authOk) {
            return $this->redirectToLogin();
        }
        if (!$page_title) {
            $page_title = ucfirst($page_name);
        }
        return view("build.{$page_name}")
            ->with('build', json_encode($this->build))
            ->with('cdashCss', $this->cdashCss)
            ->with('date', json_encode($this->date))
            ->with('logo', json_encode($this->logo))
            ->with('projectname', json_encode($this->project->Name))
            ->with('title', "{$this->project->Name} : {$page_title}");
    }
}
