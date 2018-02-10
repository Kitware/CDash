<?php

/**
 * Class Message
 */
class Message
{
    private $Subscriber;

    public function SetSubscriber(EmailSubscriber $subscriber)
    {

    }

    public function Send(ActionableBuildInterface $handler)
    {
        $topics = $this->Subscriber->GetTopics();
        foreach ($topics as $topic) {

        }

        $transports = $this->Subscriber->GetTransports();
        foreach ($transports as $transport) {
            $transport->Send($this);
        }
    }
}
