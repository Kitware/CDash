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
        $exempted_endpoints = ['install.php', 'submit.php'];
        $skip_check = false;
        foreach ($exempted_endpoints as $exempted_endpoint) {
            if (strpos($request->fullUrl(), $exempted_endpoint) !== false) {
                $skip_check = true;
            }
        }

        if (!$skip_check) {
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
