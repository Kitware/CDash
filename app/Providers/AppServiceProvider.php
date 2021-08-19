<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        // Force root URL to match app URL.
        \URL::forceRootUrl(\Config::get('app.url'));

        // Serve content over https if that's what our app URL specifies.
        if (Str::contains(\Config::get('app.url'), 'https://')) {
            \URL::forceScheme('https');
        }
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
