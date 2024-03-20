<?php

namespace SocialiteProviders\PingIdentity;

use SocialiteProviders\Manager\SocialiteWasCalled;

class PingIdentityExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite('pingidentity', Provider::class);
    }
}