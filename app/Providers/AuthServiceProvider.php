<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use CDash\Model\Image;
use CDash\Model\Project;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->defineGates();
    }

    private function defineGates(): void
    {
        Gate::define('view-project', function (?User $user, Project $project) {
            $eloquent_project = \App\Models\Project::find((int) $project->Id);
            if ($eloquent_project === null || Gate::denies('view', $eloquent_project)) {
                return Response::denyAsNotFound('You do not have access to the requested project or the requested project does not exist.');
            }
            return Response::allow();
        });

        Gate::define('edit-project', function (User $user, Project $project) {
            $eloquent_project = \App\Models\Project::find((int) $project->Id);
            if ($eloquent_project === null || Gate::denies('update', $eloquent_project)) {
                return Response::denyWithStatus(403, 'You do not have permission to edit this project.');
            }
            return Response::allow();
        });

        Gate::define('create-project', function (User $user) {
            if (Gate::denies('create', \App\Models\Project::class)) {
                return Response::denyWithStatus(403, 'You do not have permission to create new projects.');
            }
            return Response::allow();
        });

        Gate::define('view-image', function (?User $user, Image $image) {
            // Make sure the current user has access to at least one project with this image as the project icon
            $projects_with_img = DB::select('SELECT id AS projectid FROM project WHERE imageid=?', [$image->Id]);
            foreach ($projects_with_img as $project_row) {
                $project = new Project();
                $project->Id = $project_row->projectid;
                if (Gate::allows('view-project', $project)) {
                    return true;
                }
            }

            // Make sure the current user has access to a test result with this image
            foreach (\App\Models\Image::findOrFail((int) $image->Id)->tests as $test) {
                $project = $test->build?->project;
                if ($project !== null && Gate::allows('view', $project)) {
                    return true;
                }
            }

            return false;
        });
    }
}
