<?php

namespace App\Http\Middleware;

use CDash\Model\AuthToken;
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
        if (Auth::check()) {
            if (!Str::contains(url()->current(), $password_expired_path)) {
                $user = Auth::user();
                if ($user->hasExpiredPassword()) {
                    $password_expired_uri = "{$password_expired_path}?password_expired=1";
                    return redirect($password_expired_uri);
                }
            }
        } else {
            // Make sure we have a database before proceeding.
            try {
                \DB::connection()->getPdo();
            } catch (\Exception $e) {
                return $next($request);
            }

            // Check for the presence of a bearer token if we are not
            // already authenticated.
            $authtoken = new AuthToken();
            $userid = $authtoken->getUserIdFromRequest();
            if (!is_null($userid)) {
                Auth::loginUsingId($userid);
            }
        }

        return $next($request);
    }
}
