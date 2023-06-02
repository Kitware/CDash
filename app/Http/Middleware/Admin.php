<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Admin
{
    /**
     * Get the path the user should be redirected to if they are not an administrator
     */
    public function handle(Request $request, Closure $next)
    {
        // We can assume that the user is logged in at this point.  We deliberately want to fail with an
        // exception if this is not the case.
        if (!Auth::user()->IsAdmin()) {
            abort(403, 'You must be an administrator to access this page.');
        }

        return $next($request);
    }
}
