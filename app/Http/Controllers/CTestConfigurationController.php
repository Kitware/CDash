<?php
namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class CTestConfigurationController extends AbstractProjectController
{
    public function get(int $id): Response
    {
        $this->setProjectById($id);

        $view = $this->view('project.ctest-configuration')
            ->with('subprojects', $this->project->GetSubProjects());
        return response($view, 200, ['Content-Type' => 'text/plain']);
    }
}
