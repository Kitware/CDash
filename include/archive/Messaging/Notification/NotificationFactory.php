<?php

use CDash\Messaging\Collection\TopicCollection;
use CDash\Messaging\Subscription\Subscription;

class NotificationFactory
{
    // START HERE: NEW BLUEPRINT FOR MESSAGING...
    public function createNotification(ActionableBuildInterface $handler) : Notification
    {
        $builds = $handler->getActionableBuilds();
        $project = $builds[0]->GetProject();

        // Begin Subscriber Objects
        $subscribers = $project->GetProjectSubscribers();
        $subscriber_collection = new SubscriberCollection();
        foreach ($subscribers as $subscriber) {
            // Begin Topic Objects
            $topic_collection = new TopicCollection();
            $topics = $subscriber->GetSubscriptionTopics();
            $hasTopics = false;
            foreach ($topics as $topic) {
                if ($topic->hasTopic($handler)) {
                    $topic_collection->add($topic);
                    $hasTopics = true;
                }
            }

            if ($hasTopics) {
                // Begin Subscription Objects
                $user_subscription = new Subscription();

                // Begin Message Objects
                $message = new Message();
                foreach ($topic_collection as $topic) {
                    $message = $topic->GetDecorator($message);
                }
                $user_subscription->AddMessage($message);
                $user_subscription->addTopicCollection($topic_collection);
                $user_subscription->addSubscriber($subscriber);
                $subscriber_collection->add($subscriber);
            }
        }

        $builder = new MessageBuilder();
        $builder->SetHandler($handler);
        $builder->SetSubscriberCollection();
        return $builder;
    }
}
