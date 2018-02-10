<?php

use ActionableBuildInterface;
/**
 * Class MessageBuilder
 */
class MessageBuilder
{
    /** @var  ActionableBuildInterface $Handler */
    private $Handler;

    /** @var  SubscriberCollection $Subscribers */
    private $Subscribers;

    public function SetHandler(ActionableBuildInterface $handler)
    {
        $this->Handler = $handler;
    }

    public function SetSubscriberCollection(SubcriberCollection $subscribers)
    {
        $this->Subscribers = $subscribers;
    }

    public function Send()
    {
        foreach ($this->Subscribers as $subscriber) {
            $message = new Message();
            $message->SetSubscriber($subscriber);
            $message->send($this->Handler);
        }
    }

}
