<?php
namespace CDash\Messaging\Topic;

trait CancelationTrait
{
    private $cancelSubscription = false;

    public function cancelSubscription()
    {
        return $this->cancelSubscription;
    }
}
