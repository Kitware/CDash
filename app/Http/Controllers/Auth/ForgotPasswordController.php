<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AbstractController;
use Closure;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

final class ForgotPasswordController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
        $this->middleware(function (Request $request, Closure $next): Response {
            // Passwords can't be reset when password login is disabled or passwords are managed by LDAP.
            if (config('cdash.username_password_authentication_enabled') === false || (bool) config('cdash.ldap_enabled')) {
                abort(404, 'Password resets are disabled');
            }
            return $next($request);
        });
        $this->middleware('throttle:6,1')->only('sendResetLinkEmail');
    }

    /**
     * Respond as though a link was sent, so that this form cannot be used to discover
     * which email addresses belong to accounts.
     */
    protected function sendResetLinkFailedResponse(Request $request, string $response): RedirectResponse|JsonResponse
    {
        return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
    }
}
