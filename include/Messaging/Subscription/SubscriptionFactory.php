<?php
namespace CDash\Messaging\Subscription;

class SubscriptionFactory
{
    public function create()
    {
        return new Subscription();
    }
}
