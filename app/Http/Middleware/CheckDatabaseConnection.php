<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Schema;

class CheckDatabaseConnection
{
    /**
     * Check if a database connection exists for an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (strpos($request->fullUrl(), 'install.php') === false) {
            try {
                \DB::connection()->getPdo();
                if (!Schema::hasTable('build')) {
                    throw new \Exception("build table missing");
                }
            } catch (\Exception $e) {
                if (config('app.env') == 'production') {
                    \App::abort(503, 'CDash cannot connect to the database.');
                } else {
                    return redirect(config('app.url') . '/install.php');
                }
            }
        }
        return $next($request);
    }
}
