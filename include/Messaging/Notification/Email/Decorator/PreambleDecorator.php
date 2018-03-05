<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Messaging\Subscription\SubscriptionInterface;
use CDash\Messaging\Topic\Topic;

class PreambleDecorator extends Decorator
{
    private $template = 'A submission to CDash for the project {{ project_name }} has '
                . '{{ topic_list }}. You have been identified as one of the authors '
                . 'who have checked in changes that are part of this submission '
                . 'or you are listed in the default contact list.' . "\n\n"
                . 'Details on the submission can be found at {{ project_url }}' . "\n";

    public function createPreamble(SubscriptionInterface $subscription)
    {
        $summary = $subscription->getBuildSummary();
        $descriptions = array_column($summary['topics'], 'description');
        $data = [
            'project_name' => $summary['project_name'],
            'topic_list' => implode(' and ', array_map('strtolower', $descriptions)),
            'project_url' => $summary['project_url'],
        ];
        $this->text = $this->decorateWith($this->template, $data);
    }

    /**
     * @param Topic $topic
     * @return string|void
     */
    public function setTopic(Topic $topic)
    {
    }
}
