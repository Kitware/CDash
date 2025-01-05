<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Internal
{
    /**
     * Routes guarded by this middleware are expected to be used for internal use only.
     * The APP_KEY must be passed in the authorization header.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== config('app.key')) {
            abort(401, 'Invalid bearer token provided.');
        }

        return $next($request);
    }
}
