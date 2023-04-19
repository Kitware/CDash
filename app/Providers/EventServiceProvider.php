<?php

namespace App\Providers;

use App\Listeners\ConfiguredSendEmailVerificationNotification;
use App\Listeners\Saml2Login;
use App\Listeners\Saml2Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Slides\Saml2\SignedIn as Saml2SignedIn;
use Slides\Saml2\SignedOut as Saml2SignedOut;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            ConfiguredSendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot() : void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents() : bool
    {
        return true;
    }
}
