<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;
use Slides\Saml2\Events\SignedIn as Saml2SignedInEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

use App\Models\User;
use App\Http\Controllers\Auth\RegisterController;

class Saml2Login
{
    /**
      * Login or register the authenticated Saml2 user
      * @throws InvalidArgumentException
      * @throws HttpException
      */
    public function handle(Saml2SignedInEvent $event) : void
    {
        // Prevent reuse of $messageId to stop replay attacks.
        $messageId = $event->getAuth()->getLastMessageId();
        $cacheKey = 'saml-message-id-' . $messageId;
        if (Cache::has($cacheKey)) {
            abort(400, 'Invalid SAML2 message ID');
        }
        $cache_expiration_seconds = $event->getAuth()->getBase()->getLastAssertionNotOnOrAfter();
        Cache::put($cacheKey, true, $cache_expiration_seconds);

        // Get the user's email from the SAML2 login event.
        $samlUser = $event->getSaml2User();
        $email = $samlUser->getUserId();

        // Login as this user if they already have an account with CDash.
        $user = User::firstWhere('email', $email);
        if ($user !== null) {
            Auth::login($user);
            return;
        }

        if (config('saml2.autoregister_new_users') === true) {
            // Automatically register this new user.
            $registerData = [
                'email' => $email,
                'fname' => strtok($email, '@'),
                'lname' => '',
                'institution' => '',
                'password' => Hash::make(Str::random(40)),
            ];
            $registerController = new RegisterController();
            $user = $registerController->create($registerData);
            if ($user === null) {
                Log::error("Error registering new SAML2 user: $email");
                abort(500, "Error registering new SAML2 user");
            }
            Auth::login($user);
        } else {
            abort(401);
        }
    }
}
