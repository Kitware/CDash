<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\ProjectPermissions;
use CDash\Config;
use CDash\Model\Project;
use Illuminate\Support\Facades\Auth;
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
     *
     * @return void
     */
    public function boot()
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
    }
}
