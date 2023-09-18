<?php

namespace App\Providers;

use App\Models\Project;
use CDash\Model\Image;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Route::bind('image', function ($id) {
            $image = new Image();
            $image->Id = $id;
            if ($image->Load()) {
                return $image;
            }
            abort(404);
        });

        // Special binding handler for projects to ensure that Laravel doesn't reveal too much information
        // about whether models exist or not.
        Route::bind('project', function ($projectid) {
            // TODO: Eventually we only want to use Eloquent models here, rather than querying the same project twice.
            $project = new \CDash\Model\Project();
            $project->Id = $projectid;
            Gate::authorize('view-project', $project);
            return Project::findOrFail($projectid);
        });
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }
}
