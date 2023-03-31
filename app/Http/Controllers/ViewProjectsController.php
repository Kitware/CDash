<?php
namespace App\Http\Controllers;

use CDash\Database;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ViewProjectsController extends AbstractController
{
    public function viewAllProjects(): View
    {
        return view("project.view-all-projects");
    }

    public function fetchPageContent(): JsonResponse
    {
        $controller = new \CDash\Controller\Api\ViewProjects(Database::getInstance());
        return response()->json(cast_data_for_JSON($controller->getResponse()));
    }
}
