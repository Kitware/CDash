<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Slides\Saml2\Events\SignedOut as Saml2SignedOutEvent;

class Saml2Logout
{
    /** Saml2 logout  */
    public function handle(Saml2SignedOutEvent $event) : void
    {
        Auth::logout();
        Session::save();
    }
}
