<?php

namespace App\Providers;

use App\Listeners\ConfiguredSendEmailVerificationNotification;
use App\Listeners\SuccessfulLdapAuthListener;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use SocialiteProviders\GitHub\GitHubExtendSocialite;
use SocialiteProviders\GitLab\GitLabExtendSocialite;
use SocialiteProviders\Google\GoogleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\PingIdentity\PingIdentityExtendSocialite;

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
        SocialiteWasCalled::class => [
            // ... other providers
            GitLabExtendSocialite::class . '@handle',
            GitHubExtendSocialite::class . '@handle',
            GoogleExtendSocialite::class . '@handle',
            PingIdentityExtendSocialite::class . '@handle',
        ],
        Login::class => [
            SuccessfulLdapAuthListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
