<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Test;
use App\Models\TestImage;
use App\Models\User;
use App\Utils\ProjectPermissions;
use CDash\Model\Image;
use CDash\Model\Project;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Auth;
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
        Auth::provider('cdash', function ($app, array $config) {
            return new CDashDatabaseUserProvider($app['hash'], $config['model']);
        });

        $this->defineGates();
    }

    private function defineGates(): void
    {
        Gate::define('view-project', function (?User $user, Project $project) {
            if (!ProjectPermissions::canViewProject($project, $user)) {
                return Response::denyAsNotFound('You do not have access to the requested project or the requested project does not exist.');
            }
            return Response::allow();
        });

        Gate::define('edit-project', function (User $user, Project $project) {
            // First check to make sure we can see the project to begin with
            Gate::authorize('view-project', $project);

            if (!ProjectPermissions::canEditProject($project, $user)) {
                return Response::denyWithStatus(403, 'You do not have permission to edit this project.');
            }
            return Response::allow();
        });

        Gate::define('create-project', function (User $user) {
            if (!$user->admin && !boolval(config('cdash.user_create_projects'))) {
                return Response::denyWithStatus(403, 'You do not have permission to create new projects.');
            }
            return Response::allow();
        });

        Gate::define('view-test', function (?User $user, Test $test) {
            $project = new Project();
            $project->Id = $test->projectid;
            return Gate::allows('view-project', $project);
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
                if (Gate::allows('view-test', $output->testOutput->test)) {
                    return true;
                }
            }

            return false;
        });
    }
}
