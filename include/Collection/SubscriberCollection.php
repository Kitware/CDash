<?php
namespace CDash\Collection;

use CDash\Model\SubscriberInterface;

class SubscriberCollection extends Collection
{
    public function add(SubscriberInterface $subscriber)
    {
        parent::addItem($subscriber, $subscriber->getAddress());
    }
}
