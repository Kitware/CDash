<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as symfonyResponse;

/**
 * Class OAuthController
 */
final class OAuthController extends AbstractController
{
    public function socialite(string $service): symfonyResponse
    {
        return Socialite::driver($service)->redirect();
    }

    public function callback(string $service): RedirectResponse|Redirector
    {
        // Try/Catch to prevent 500  error when access is denied at provider
        try {
            $authUser = Socialite::driver($service)->user();
        } catch (Exception $e) {
            Log::error("Problem logging in with $service.  Error was " . $e->getMessage());
            return redirect('/login');
        }

        $email = $authUser->getEmail();
        $name = $authUser->getName() ?? '';

        // Check if name has space.  Avoid issue where username = "Real" name
        if (str_contains($name, ' ')) {
            [$fname, $lname] = explode(' ', $name);
        } else {
            [$fname, $lname] = [$name, ''];
        }

        // TODO: What if, for whatever reason, there is more than one user found?
        $user = User::firstWhere('email', $email);
        if ($user === null) {
            $user = User::create([
                'firstname' => $fname,
                'lastname' => $lname,
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'institution' => '',
            ]);
            if ($user === null) {
                Log::error("Error registering new user via $service: $email");
                abort(500, 'Error registering new user');
            }
        }
        Auth::login($user, true);
        return redirect(session('url.intended') ?? '/profile');
    }
}
