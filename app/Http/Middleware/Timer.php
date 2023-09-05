<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Timer
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $duration = round(microtime(true) - LARAVEL_START, 2);
        if ($duration >= (int) config('cdash.slow_page_time')) {
            $url = request()->getRequestUri();
            Log::warning("Slow page: $url took $duration seconds to load");
        }

        return $response;
    }
}
