<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

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
