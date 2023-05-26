<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Test;
use App\Models\TestImage;
use App\Models\User;
use App\Services\ProjectPermissions;
use CDash\Config;
use CDash\Model\Image;
use CDash\Model\Project;
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
            return ProjectPermissions::canViewProject($project, $user);
        });

        Gate::define('edit-project', function (User $user, Project $project) {
            return ProjectPermissions::canEditProject($project, $user);
        });

        Gate::define('create-project', function (User $user) {
            $config = Config::getInstance();
            return $user->IsAdmin() || $config->get('CDASH_USER_CREATE_PROJECTS');
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
