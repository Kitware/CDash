<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->maxAttempts = config('cdash.login.max_attempts', 5);
        $this->decayMinutes = config('cdash.login.lockout.duration', 1);
    }

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        $e = ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);

        // Seems like this should be the default way to deal with failed response vs
        // Laravel's redirect with a status of 302 :(
        $e->status(401)
            ->response = response()
                ->view(
                    'auth.login',
                    [
                        'errors' => $e->validator->getMessageBag(),
                        'title' => 'Login',
                        'js_version' => self::getJsVersion(),
                    ],
                    401
                );
        throw $e;
    }

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm()
    {
        return self::staticShowLoginForm();
    }

    /**
     * @return string
     */
    public function redirectTo()
    {
        $previous = App::make('url')->previous();
        // prevent multiple redirects if $previous was /login
        if ($previous === route('login')) {
            // TODO: this should be configurable
            $previous = '/viewProjects.php';
        }
        return $previous ?: '/';
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public static function staticShowLoginForm()
    {
        return view('auth.login',
            [
                'title' => 'Login',
                'js_version' => self::getJsVersion()
            ]);
    }
}
