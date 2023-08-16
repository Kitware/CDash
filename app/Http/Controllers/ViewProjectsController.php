<?php
namespace App\Http\Controllers;

use CDash\Database;
use CDash\Model\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ViewProjectsController extends AbstractController
{
    public function viewAllProjects(): View|RedirectResponse
    {
        $num_public_projects = (int) DB::select('
                                     SELECT COUNT(*) AS c FROM project WHERE public=?
                                 ', [Project::ACCESS_PUBLIC])[0]->c;

        // If there are no public projects to see, redirect to the login page
        if (!Auth::check() && $num_public_projects === 0) {
            return $this->redirectToLogin();
        }

        return $this->view("project.view-all-projects");
    }

    public function fetchPageContent(): JsonResponse
    {
        $controller = new \CDash\Controller\Api\ViewProjects(Database::getInstance());
        return response()->json(cast_data_for_JSON($controller->getResponse()));
    }
}
