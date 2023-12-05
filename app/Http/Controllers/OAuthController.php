<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\RedirectResponse as symfonyResponse;
use RuntimeException;

use App\Models\User;
use App\Http\Controllers\Auth\RegisterController;
use CDash\Middleware\OAuth2\OAuth2Interface;

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

    /**
     * @param Request $request
     * @param OAuth2Interface $service
     * @return RedirectResponse
     */
    public function authenticate(Request $request, OAuth2Interface $service)
    {
        $service->setRequest($request);
        $session = $request->session();
        if (($destination = $request->get('destination'))) {
            $session->put('auth.oauth.destination', $destination);
        }

        $to = $service->getAuthorizationUrl();
        $session->put('auth.oauth.state', $service->getState());
        return redirect($to)
            ->header('Cache-Control', 'no-cache, must-revalidate')
            ->header('Expires', 'Sat, 26 Jul 1997 05:00:00GMT');
    }

    /**
     * @param Request $request
     * @param OAuth2Interface $service
     * @param User $user_table
     * @return RedirectResponse
     */
    public function login(Request $request, OAuth2Interface $service, User $user_table)
    {
        $service->setRequest($request);
        $session = $request->session();

        // TODO: Here, the service should recognize the error, not the request
        if ($request->get('error')) {
            return $this->handleError($request);
        }

        if (!$service->checkState()) {
            throw new RuntimeException('OAuth2: Invalid state');
        }

        $email_collection = $service->getEmail();
        $emails = $email_collection->map(function ($item) {
            return $item->email;
        });

        // TODO: What if, for whatever reason, there is more than one user found?
        $user = $user_table->whereIn('email', $emails)->first();

        if (!$user) {
            return $this->handleRegistration($service);
        }

        Auth::login($user, true);

        // TODO: create a default route, i.e. route('default') or route('index')
        $to = $session->remove('auth.oauth.destination') ?: '/projects';

        return redirect($to);
    }

    /**
     * @param OAuth2Interface $service
     * @param Collection $emails
     * @return RedirectResponse
     */
    public function handleRegistration(OAuth2Interface $service)
    {
        $names = explode(' ', $service->getOwnerName());
        $lname = array_pop($names);
        $fname = implode(' ', $names);
        $email = $service->getPrimaryEmail();
        $parameters = compact('fname', 'lname', 'email');
        $to = route('register', $parameters);
        return redirect($to);
    }

    /**
     * @param Request $request
     */
    public function handleError(Request $request)
    {
        // ?error=redirect_uri_mismatch&error_description=The+redirect_uri+MUST+match+the+registered+callback+URL+for+this+application.&error_uri=https%3A%2F%2Fdeveloper.github.com%2Fapps%2Fmanaging-oauth-apps%2Ftroubleshooting-authorization-request-errors%2F%23redirect-uri-mismatch&state=811a89410a427768f6a7de8bf13c61fa
        // TODO: handle without exception
        throw new RuntimeException($request->get('error_description'));
    }
    // http://localhost/oauth/google
}
