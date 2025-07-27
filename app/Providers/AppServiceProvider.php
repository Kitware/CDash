<?php

namespace App\Providers;

define('FMT_TIME', 'H:i:s');  // time
define('FMT_DATE', 'Y-m-d');  // date
define('FMT_DATETIMESTD', 'Y-m-d H:i:s');  // date and time standard
define('FMT_DATETIME', 'Y-m-d\TH:i:s');  // date and time
define('FMT_DATETIMETZ', 'Y-m-d\TH:i:s T');  // date and time with time zone
define('FMT_DATETIMEMS', 'Y-m-d\TH:i:s.u');  // date and time with milliseconds
define('FMT_DATETIMEDISPLAY', 'M d, Y - H:i T');  // date and time standard

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

require_once 'include/common.php';
require_once 'include/pdo.php';

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Log::shareContext([
            // Limit the size of the string to prevent users from (un)intentionally filling
            // up the logs with a very long query string.
            'uri' => substr(request()->uri(), 0, 1000),
        ]);

        Validator::extendImplicit('complexity', 'App\Validators\Password@complexity');

        URL::forceRootUrl(Config::get('app.url'));

        // Work around k8s and proxy issues where Laravel automatically changes the protocol to HTTP
        // if the incoming request is HTTP.
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }
}
