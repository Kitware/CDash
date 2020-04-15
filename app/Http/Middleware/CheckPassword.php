<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CheckPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $password_expired_path = '/editUser.php';
        if (Auth::check() && !Str::contains(url()->current(), $password_expired_path)) {
            $user = Auth::user();
            if ($user->hasExpiredPassword()) {
                $password_expired_uri = "{$password_expired_path}?password_expired=1";
                return redirect($password_expired_uri);
            }
        }

        return $next($request);
    }
}
