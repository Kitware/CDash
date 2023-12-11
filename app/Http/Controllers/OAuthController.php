<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\RedirectResponse as symfonyResponse;

use App\Models\User;
use App\Http\Controllers\Auth\RegisterController;

/**
 * Class OAuthController
 * @package App\Http\Controllers
 */
final class OAuthController extends AbstractController
{
    public function socialite(string $service): symfonyResponse
    {
        return Socialite::driver($service)->redirect();
    }

    public function callback(string $service): RedirectResponse|Redirector
    {
        $authUser = Socialite::driver($service)->user();
        $email =  $authUser->getEmail();
        [$fname, $lname] = explode(" ", $authUser->getName() ?? '');

        // TODO: What if, for whatever reason, there is more than one user found?
        $user = User::firstWhere('email', $email);
        if ($user === null) {
            if (config("services.{$service}.autoregister") === true) {
                $registerData = [
                    'email' => $email,
                    'fname' => $fname,
                    'lname' => $lname,
                    'institution' => '',
                    'password' => Hash::make(Str::random(40)),
                ];
                $registerController = new RegisterController();
                $user = $registerController->create($registerData);
                if ($user === null) {
                    Log::error("Error registering new user via $service: $email");
                    abort(500, "Error registering new user");
                }
                Auth::login($user, true);
            } else {
                $parameters = compact('fname', "lname", 'email');
                $to = route('register', $parameters);
                return redirect($to);
            }
        } else {
            Auth::login($user, true);
        }
        return redirect(session('url.intended') ?? '/');
    }
}
