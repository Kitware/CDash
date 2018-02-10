<?php
namespace CDash\archive\Messaging\Subscription;

use Topic\TopicDiscoveryInterface;

abstract class TopicDecorator implements TopicDiscoveryInterface
{
    protected $subscription;

    public function __construct(TopicDiscoveryInterface $subscription)
    {
        $this->subscription = $subscription;
    }
}
