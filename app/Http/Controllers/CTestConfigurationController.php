<?php

namespace App\Http\Controllers;

use App\Services\ProjectService;
use Illuminate\Http\Response;

final class CTestConfigurationController extends AbstractProjectController
{
    public function get(int $id): Response
    {
        $this->setProjectById($id);

        $view = $this->view('project.ctest-configuration', '')
            ->with('subprojects', ProjectService::getSubProjects((int) $this->project->Id));
        return response($view, 200, ['Content-Type' => 'text/plain']);
    }
}
