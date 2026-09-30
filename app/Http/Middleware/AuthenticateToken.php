<?php

namespace App\Http\Middleware;

use App\Enums\AuthTokenScope;
use App\Models\AuthToken;
use App\Services\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * If a request has an associated bearer token, that is a valid method by which we can log in.
 */
class AuthenticateToken
{
    public function handle(Request $request, Closure $next)
    {
        $token_hash = AuthTokenService::hash($request->bearerToken());
        $auth_token = $token_hash === '' ? null : AuthToken::firstWhere('hash', $token_hash);

        // Only full-access tokens can be used to log in.
        if ($auth_token !== null
            && !$auth_token->expires->isPast()
            && $auth_token->scope === AuthTokenScope::FULL_ACCESS
        ) {
            Auth::loginUsingId($auth_token->userid);
        }

        return $next($request);
    }
}
