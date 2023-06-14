<?php

namespace App\Http\Middleware;

use App\Services\AuthTokenService;
use CDash\Model\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordExpired
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
        if (Auth::check()) {
            $password_expired_path = '/editUser.php';
            if (!Str::contains(url()->current(), $password_expired_path)) {
                /** @var User $user */
                $user = Auth::user();
                if ($user->hasExpiredPassword()) {
                    $password_expired_uri = "{$password_expired_path}?password_expired=1";
                    return redirect($password_expired_uri);
                }
            }
        }

        return $next($request);
    }
}
