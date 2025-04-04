<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

final class ProjectMembersController extends AbstractProjectController
{
    public function members(int $project_id): View
    {
        $this->setProjectById($project_id);

        return $this->view('project.members', 'Members');
    }
}
