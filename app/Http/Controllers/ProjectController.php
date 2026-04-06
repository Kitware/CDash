<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class ProjectController extends AbstractProjectController
{
    public function sites(int $project_id): View
    {
        $this->setProjectById($project_id);

        return $this->vue('project-sites-page', 'Sites', ['project-id' => $this->project->Id]);
    }
}
