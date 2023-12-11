<?php
namespace App\Http\Controllers;

use App\Utils\TestingDay;
use CDash\Model\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

abstract class AbstractProjectController extends AbstractController
{
    protected string $date;
    protected Project $project;

    public function __construct()
    {
        $this->date = date(FMT_DATETIME);
    }

    protected function view(string $view, string $title = ''): View
    {
        if (!isset($this->project)) {
            return parent::view($view, $title);
        }

        return parent::view($view, $title)
            ->with('project', $this->project);
    }

    /** Retrieve common data used by all project-specific pages in CDash. */
    protected function setProject(Project $project): void
    {
        Gate::authorize('view-project', $project);

        $this->project = $project;
        $this->project->Fill();
        $this->date = TestingDay::get($this->project, date(FMT_DATETIME));
    }

    protected function setProjectById(int $projectid): void
    {
        $project = new Project();
        $project->Id = $projectid;
        $this->setProject($project);
    }

    protected function setProjectByName(string $project_name): void
    {
        $project = new Project();
        $project->FindByName($project_name);
        $this->setProject($project);
    }
}
