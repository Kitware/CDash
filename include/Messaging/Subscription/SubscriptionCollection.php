<?php
namespace CDash\Messaging\Subscription;

use CDash\Collection\Collection;

/**
 * Class SubscriptionCollection
 * @package CDash\Messaging\Collection
 */
class SubscriptionCollection extends Collection
{
    public function add(Subscription $item)
    {
        parent::addItem($item);
        return $this;
    }
}
