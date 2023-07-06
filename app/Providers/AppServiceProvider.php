<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extendImplicit('complexity', 'App\Validators\Password@complexity');

        /** For migrations on MySQL older than 5.7.7 **/
        if (config('database.default') !== 'pgsql') {
            Schema::defaultStringLength(191);
        }

        // Serve content over https in production mode.
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // This allows us to do response()->angular_view(<view_name>).
        Response::macro('angular_view', function (string $view_name) {
            $controller_name = '';
            $path = request()->path() === '/' ? 'index.php' : request()->path();
            $file = pathinfo(substr($path, strrpos($path, '/')), PATHINFO_FILENAME);

            // Special case: viewBuildGroup.php shares a controller with index.php.
            if ($file === 'viewBuildGroup') {
                $file = 'index';
            }
            $controller_path = config('cdash.file.path.js.controllers');
            $controller = "{$controller_path}/{$file}.js";
            if (is_readable($controller)) {
                $controller_name = Str::studly($file) . 'Controller';
            }

            return response()->view('cdash', [
                'xsl_content' => file_get_contents(base_path("app/cdash/public/build/views/$view_name.html")),
                'xsl' => true,
                'angular' => true,
                'angular_controller' => $controller_name,
            ]);
        });

        URL::forceRootUrl(Config::get('app.url'));
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
