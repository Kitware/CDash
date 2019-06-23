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
        $password_expired_path = 'editUser.php';
        if (Auth::check() && !Str::contains(url()->current(), $password_expired_path)) {
            $user = Auth::user();
            if ($user->hasExpiredPassword()) {
                // TODO: figure out why we have to build the entire URI here, i.e. why
                //  url($password_expired_path) does not work
                $password_expired_uri  = $request->getSchemeAndHttpHost();
                $password_expired_uri .= "/$password_expired_path?password_expired=1";
                return redirect($password_expired_uri);
            }
        }

        return $next($request);
    }
}
