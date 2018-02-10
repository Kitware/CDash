<?php
namespace CDash\Messaging\Notification\Email;

use CDash\Config;
use CDash\Messaging\Notification\Email\Decorator\Decorator;
use CDash\Messaging\Notification\Email\Decorator\DecoratorFactory;
use CDash\Collection\CollectionInterface;
use CDash\Messaging\FactoryInterface;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Subscription\SubscriptionNotificationBuilder;
use CDash\Messaging\Subscription\Subscription;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Messaging\Topic\Topic;
use Project;
use Site;

class EmailBuilder extends SubscriptionNotificationBuilder
{
    /** @var  Project $project */
    private $project;

    /**
     * @return NotificationCollection
     */
    public function createNotifications()
    {
        $project_name = null;
        /** @var Config $config */
        $config = Config::getInstance();

        /** @var Subscription $subscription */
        foreach ($this->subscriptions as $subscription) {
            $site_name = null;
            $project_name = $project_name ? $project_name : $subscription->getProject()->GetName();

            /** @var Topic $topic */
            foreach ($subscription->getTopics() as $topic) {
                $build = $topic->getBuild();
                $site_name = $site_name ? $site_name : $build->GetSite()->GetName();

                $subproject_name = $build->GetSubProjectName();
                $build_name = $subproject_name ? $subproject_name : $build->GetName();
                $build_time = date(FMT_DATETIMETZ, strtotime($build->StartTime . ' UTC'));
                $topic_data = $topic->getTopicData();

                // create initial message
                $notification = new EmailMessage();

                // create body preamble
                $body = DecoratorFactory::create('PreambleDecorator');
                $body->decorateWith([['url' => $build->GetSummaryUrl()]]);

                //create body summary
                $body_summary = DecoratorFactory::create('SummaryDecorator');
                $body_summary
                    ->setDecorator($body)
                    ->decorateWith([
                        'project_name' => $project_name,
                        'site_name' => $site_name,
                        'build_name' => $build_name,
                        'build_time' => $build_time,
                        'build_group' => $build->Type,
                        'summary_description' => $topic->getTopicDescription(),
                        'summary_count' => count($topic_data),
                    ]);

                //create body topic
                $body_topics = DecoratorFactory::createFromTopic($topic);
                $body_topics
                    ->setDecorator($body_summary)
                    ->decorateWith($topic_data);
                $subject = $body_topics->getSubject($project_name, $build_name);

                // create body footer
                $body_footer = DecoratorFactory::create('FooterDecorator');
                $body_footer
                    ->setDecorator($body_topics)
                    ->decorateWith(['server_name' => $config->getServer()]);

                // set delivery information
                $notification
                    ->setSender($subscription->getSender())
                    ->setRecipient($subscription->getRecipient())
                    ->setBody($body_footer)
                    ->setSubject($subject);

                $this->notifications->add($notification);
            }
        }

        return $this->notifications;
    }

    /**
     * @return void
     */
    public function createNotification()
    {
        $this->notification = new EmailMessage();
    }

    /**
     * @return void
     */
    public function addPreamble()
    {
        // TODO: Implement addPreamble() method.
    }

    public function addTopics()
    {
        // TODO: Implement addTopics() method.
    }

    public function addSummary()
    {
        // TODO: Implement addSummary() method.
    }

    public function addSubject()
    {
        // TODO: Implement addSubject() method.
    }

    public function addDeliveryInformation()
    {
        // TODO: Implement addDeliveryInformation() method.
    }

    /**
     * @return void
     */
    public function setSender()
    {
        // TODO: Implement setSender() method.
    }

    /**
     * @return void
     */
    public function setRecipient()
    {
        // TODO: Implement setRecipient() method.
    }

    /**
     * @return void
     */
    public function setSubject()
    {
        // TODO: Implement setSubject() method.
    }

    /**
     * @return void
     */
    public function setBody()
    {
    }

    /**
     * @return CollectionInterface
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    public function setProject(\Project $project)
    {
        $this->project = $project;
    }
}
