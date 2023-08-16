<?php
namespace App\Http\Controllers;

use CDash\Model\SubProject;
use Illuminate\Http\Response;

final class CTestConfigurationController extends AbstractProjectController
{
    public function get(int $id): Response
    {
        $this->setProjectById($id);

        // TODO: (williamjallen) replace this loop with a single query via a new method in the SubProject class.
        $subprojects = [];
        foreach ($this->project->GetSubProjects() as $subproject_id) {
            $subproject = new SubProject();
            $subproject->SetId($subproject_id);
            $subproject->Fill();
            $subprojects[] = $subproject;
        }

        $view = $this->view('project.ctest-configuration')->with('subprojects', $subprojects);
        return response($view, 200, ['Content-Type' => 'text/plain']);
    }
}
