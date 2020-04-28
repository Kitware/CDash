<?php

namespace App\Http\Middleware;

use Closure;

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
