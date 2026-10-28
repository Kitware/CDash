<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cookie;

/**
 * Checks the CSRF token of requests whose user is logged in by a cookie (the session or
 * "remember me"), since a browser sends those cookies with requests forged by other sites.
 * Anonymous requests and requests authenticated by a bearer token don't need a CSRF token.
 *
 * This must run before AuthenticateToken, which logs bearer token users into the session.
 */
class VerifyCsrfTokenForSessions extends VerifyCsrfToken
{
    /**
     * @param Request $request
     *
     * @throws TokenMismatchException
     */
    public function handle($request, Closure $next): mixed
    {
        if ($request->user() === null) {
            return $next($request);
        }

        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException $e) {
            // Send the session's current token, so that a client holding a stale one can retry.
            Cookie::queue($this->newCookie($request, (array) config('session')));
            throw $e;
        }
    }
}
