<?php
namespace App\Http\Controllers;

use CDash\Model\SubProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CTestConfigurationController extends ProjectController
{
    public function get(): View|RedirectResponse|Response
    {
        // Checks
        if (!isset($_GET['projectid']) || !is_numeric($_GET['projectid'])) {
            abort(400, 'Not a valid projectid!');
        }

        $this->setProjectById((int) $_GET['projectid']);


        // TODO: (williamjallen) replace this loop with a single query via a new method in the SubProject class.
        $subprojects = [];
        foreach ($this->project->GetSubProjects() as $id) {
            $subproject = new SubProject();
            $subproject->SetId($id);
            $subproject->Fill();
            $subprojects[] = $subproject;
        }


        $view = view('project.ctest-configuration', [
            'project' => $this->project,
            'subprojects' => $subprojects
        ]);
        return response($view, 200, ['Content-Type' => 'text/plain']);
    }
}
