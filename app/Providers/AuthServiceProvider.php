<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\TestImage;
use App\Models\User;
use CDash\Model\Image;
use CDash\Model\Project;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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
            $outputs_with_image = TestImage::where('imgid', '=', $image->Id)->get();
            foreach ($outputs_with_image as $output) {
                $buildtests = $output->testOutput?->tests;
                if ($buildtests === null) {
                    continue;
                }
                foreach ($buildtests as $buildtest) {
                    $project = $buildtest->build?->project;
                    if ($project !== null && Gate::allows('view', $project)) {
                        return true;
                    }
                }
            }

            return false;
        });
    }
}
