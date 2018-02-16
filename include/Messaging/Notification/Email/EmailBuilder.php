<?php
namespace CDash\Messaging\Notification\Email;

use CDash\Config;
use CDash\Messaging\Notification\Email\Decorator\Decorator;
use CDash\Messaging\Notification\Email\Decorator\DecoratorFactory;
use CDash\Collection\CollectionInterface;
use CDash\Messaging\FactoryInterface;
use CDash\Messaging\Notification\Email\Decorator\FooterDecorator;
use CDash\Messaging\Notification\Email\Decorator\PreambleDecorator;
use CDash\Messaging\Notification\Email\Decorator\SummaryDecorator;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationInterface;
use CDash\Messaging\Subscription\SubscriptionInterface;
use CDash\Messaging\Subscription\SubscriptionNotificationBuilder;
use CDash\Messaging\Subscription\Subscription;
use CDash\Messaging\Subscription\SubscriptionCollection;
use CDash\Messaging\Topic\Topic;
use Project;
use SendGrid\Email;
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
     * @return NotificationInterface
     */
    public function createNotification(SubscriptionInterface $subscription)
    {
        $message = new EmailMessage();

        $this->setPreamble($message);
        $this->setSummary($message, $subscription);
        $this->setTopics($message, $subscription);
        $this->setFooter($message);
        $this->setSubject($message, $subscription);
        $this->setRecipient($message, $subscription);
        $this->setSender();
        return $message;
    }

    /**
     * @return void
     */
    protected function setPreamble(EmailMessage $email)
    {
        $preamble = new PreambleDecorator(null);
        $email->setBody($preamble);
    }

    protected function setTopics(EmailMessage $email, SubscriptionInterface $subscription)
    {
        $topics = $subscription->getTopicCollection();
        foreach ($topics as $topic) {
            $decorator = DecoratorFactory::createFromTopic($topic, $email->getBody());
            $email->setBody($decorator);
        }
    }

    protected function setFooter(EmailMessage $email)
    {
        $footer = new FooterDecorator($email->getBody());
        $email->setBody($footer);
    }

    protected function setSummary(EmailMessage $email, SubscriptionInterface $subscription)
    {
        $summary = new SummaryDecorator($email->getBody());
        $summary->setTemplateData($subscription);
        $email->setBody($summary);
    }

    protected function setSubject(EmailMessage $emailMessage, SubscriptionInterface $subscription)
    {
        // TODO: Implement addSubject() method.
    }

    protected function addDeliveryInformation()
    {
        // TODO: Implement addDeliveryInformation() method.
    }

    /**
     * @return void
     */
    protected function setSender()
    {
        // TODO: Implement setSender() method.
    }

    /**
     * @return void
     */
    protected function setRecipient(EmailMessage $email, SubscriptionInterface $subscription)
    {
        $email->setRecipient($subscription->getRecipient());
    }

    /**
     * @return void
     */
    protected function setBody()
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
