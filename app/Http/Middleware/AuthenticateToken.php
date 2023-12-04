<?php

namespace App\Http\Middleware;

use App\Utils\AuthTokenUtil;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * If a request has an associated bearer token, that is a valid method by which we can log in.
 */
class AuthenticateToken
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user_id = AuthTokenUtil::getUserIdFromRequest();
        if ($user_id !== null) {
            Auth::loginUsingId($user_id);
        }

        return $next($request);
    }
}
