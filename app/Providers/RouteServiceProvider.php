<?php

namespace App\Providers;

use CDash\Model\Image;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
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
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        // Registered first, so that any stateless routes under /api aren't shadowed by the
        // catch-all route at the end of routes/api.php.
        $this->mapStatelessRoutes();

        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes receive session state, and users logged in by a cookie must send a CSRF token.
     */
    protected function mapApiRoutes(): void
    {
        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/api.php'));
    }

    /**
     * Define the "stateless" routes for the application.
     *
     * These routes don't belong to any middleware group, so they don't start a session,
     * encrypt or queue cookies, or check CSRF tokens.
     */
    protected function mapStatelessRoutes(): void
    {
        Route::group([], base_path('routes/stateless.php'));
    }
}
