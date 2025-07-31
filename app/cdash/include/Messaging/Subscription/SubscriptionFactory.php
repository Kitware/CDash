<?php

namespace CDash\Messaging\Subscription;

class SubscriptionFactory
{
    public function create(): Subscription
    {
        return new Subscription();
    }
}
