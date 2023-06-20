<?php
namespace App\Http\Controllers;

use App\Services\TestingDay;
use CDash\Model\Project;
use Illuminate\Support\Facades\Gate;

abstract class AbstractProjectController extends AbstractController
{
    protected string $date;
    protected Project $project;

    public function __construct()
    {
        $this->date = date(FMT_DATETIME);
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
