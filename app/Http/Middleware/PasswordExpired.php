<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Auth;
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
            $password_expired_path = '/profile';
            if (!Str::contains(url()->current(), $password_expired_path)) {
                /** @var User $user */
                $user = Auth::user();
                $current_password = $user->currentPassword ?? null;
                if ($current_password !== null) {
                    $password_lifetime = (int) config('cdash.password.expires');
                    $password_expired = $current_password->date->addDays($password_lifetime) < Carbon::now();
                    if ($password_lifetime > 0 && $password_expired) {
                        $password_expired_uri = "{$password_expired_path}?password_expired=1";
                        return redirect($password_expired_uri);
                    }
                }
            }
        }

        return $next($request);
    }
}
