<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\AbstractController;
use App\Models\User;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class ResetPasswordController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords {
        sendResetFailedResponse as traitSendResetFailedResponse;
    }

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/login';

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
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $password_min = config('cdash.password.min');

        return [
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'string', "min:{$password_min}", 'complexity', 'confirmed'],
        ];
    }

    /**
     * Unlike Laravel's default, this does not log the user in.  Users must log in with their
     * new password, which applies all of the usual login restrictions (lockouts, etc.).
     * All of the user's existing sessions are logged out by the AuthenticateSession
     * middleware because their password hash no longer matches.
     *
     * @throws ValidationException
     */
    protected function resetPassword(User $user, string $password): void
    {
        if (password_verify($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'New password matches old password.']);
        }

        $user->password = Hash::make($password);
        $user->password_updated_at = Carbon::now();
        $user->setRememberToken(Str::random(60));
        $user->save();

        event(new PasswordReset($user));
    }

    protected function sendResetFailedResponse(Request $request, string $response): RedirectResponse|JsonResponse
    {
        // Don't reveal whether the email address belongs to an account.
        if ($response === Password::INVALID_USER) {
            $response = Password::INVALID_TOKEN;
        }

        return $this->traitSendResetFailedResponse($request, $response);
    }
}
