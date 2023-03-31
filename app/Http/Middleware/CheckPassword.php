<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use CDash\Model\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                /** @var User $user */
                $user = Auth::user();
                if ($user->hasExpiredPassword()) {
                    $password_expired_uri = "{$password_expired_path}?password_expired=1";
                    return redirect($password_expired_uri);
                }
            }
        } else {
            // Make sure we have a database before proceeding.
            try {
                DB::connection()->getPdo();
            } catch (\Exception) {
                return $next($request);
            }

            $user_id = AuthTokenService::getUserIdFromRequest();
            if ($user_id !== null) {
                Auth::loginUsingId($user_id);
            }
        }

        return $next($request);
    }
}
