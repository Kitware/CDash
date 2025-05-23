<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PasswordExpired
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     */
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            $password_expired_path = '/profile';
            if (!Str::contains(url()->current(), $password_expired_path)) {
                /** @var User $user */
                $user = Auth::user();
                $password_lifetime = (int) config('cdash.password.expires');
                if ($password_lifetime > 0 && $user->password_updated_at->addDays($password_lifetime) < Carbon::now()) {
                    return redirect("{$password_expired_path}?password_expired=1");
                }
            }
        }

        return $next($request);
    }
}
