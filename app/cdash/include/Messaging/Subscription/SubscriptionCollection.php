<?php

namespace CDash\Messaging\Subscription;

use CDash\Collection\Collection;

/**
 * Class SubscriptionCollection
 */
class SubscriptionCollection extends Collection
{
    public function add(Subscription $item)
    {
        if ($item->getRecipient()) {
            parent::addItem($item, $item->getRecipient());
        } else {
            parent::addItem($item);
        }

        return $this;
    }
}
