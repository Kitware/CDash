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
        $user = Auth::user();
        if ($user === null || !$user->IsAdmin()) {
            session(['url.intended' => url()->current()]);
            return redirect('/login');
        }

        return $next($request);
    }
}
