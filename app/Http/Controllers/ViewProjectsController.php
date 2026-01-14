<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ViewProjectsController extends AbstractController
{
    public function viewProjects(): View|RedirectResponse
    {
        $num_public_projects = (int) DB::select('
                                     SELECT COUNT(*) AS c FROM project WHERE public=?
                                 ', [Project::ACCESS_PUBLIC])[0]->c;

        // If there are no public projects to see, redirect to the login page
        if (!Auth::check() && $num_public_projects === 0) {
            return $this->redirectToLogin();
        }

        return $this->vue('projects-page', 'Projects', [
            'can-create-projects' => Gate::allows('create', Project::class),
        ]);
    }
}
