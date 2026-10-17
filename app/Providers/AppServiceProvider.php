<?php

namespace App\Providers;

use App\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::connection()->setQueryGrammar(new PostgresGrammar(DB::connection()));

        Validator::extendImplicit('complexity', 'App\Validators\Password@complexity');

        URL::forceRootUrl(Config::get('app.url'));

        // Work around k8s and proxy issues where Laravel automatically changes the protocol to HTTP
        // if the incoming request is HTTP.
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());

        // Queue workers process many jobs in a single process, so undo any timezone change made by a
        // previous job (e.g., by CDash\Model\Project::SetNightlyTime()).
        Queue::before(static function (): void {
            date_default_timezone_set((string) config('app.timezone'));
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
    }
}
