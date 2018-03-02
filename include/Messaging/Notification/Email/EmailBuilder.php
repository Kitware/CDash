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
     * @return NotificationInterface
     */
    public function createNotification(SubscriptionInterface $subscription)
    {
        $message = new EmailMessage();
        $this->uniquifyTopics($subscription);
        $this->setPreamble($message, $subscription);
        $this->setSummary($message, $subscription);
        $this->setTopics($message, $subscription);
        $this->setFooter($message);
        $this->setSubject($message, $subscription);
        $this->setRecipient($message, $subscription);
        $this->setSender();
        return $message;
    }

    protected function uniquifyTopics(SubscriptionInterface $subscription)
    {
      $topics = $subscription->getTopicCollection();

      $labeled = $topics->remove('Labeled');
      if ($labeled) {
        $labeled->mergeTopics($topics);
      }
    }

    /**
     * @return void
     */
    protected function setPreamble(EmailMessage $email, SubscriptionInterface $subscription)
    {
        $preamble = new PreambleDecorator(null);
        $preamble->addSubject($subscription->getBuildSummary());
        $email->setBody($preamble);
    }

    protected function setSummary(EmailMessage $email, SubscriptionInterface $subscription)
    {
        $summary = new SummaryDecorator($email->getBody());
        $summary->addSubject($subscription);
        $email->setBody($summary);
    }

    protected function setTopics(EmailMessage $email, SubscriptionInterface $subscription)
    {
        $topics = $subscription->getTopicCollection();
        $project = $subscription->getProject();
        $maxItems = $project->EmailMaxItems;

        foreach ($topics as $topic) {
            $decorator = DecoratorFactory::createFromTopic($topic, $email->getBody());
            $decorator
                ->setMaxTopicItems($maxItems)
                ->addSubject($topic);
            $email->setBody($decorator);
        }
    }

    protected function setFooter(EmailMessage $email)
    {
        $footer = new FooterDecorator($email->getBody());
        $footer->addSubject(Config::getInstance());
        $email->setBody($footer);
    }


    protected function setSubject(EmailMessage $emailMessage, SubscriptionInterface $subscription)
    {
        $template = 'FAILED (%s) %s - %s - %s';
        $totals = [];
        $summary = $subscription->getBuildSummary();
        foreach ($summary['topics'] as $topic) {
            $description = $topic['description'];
            switch ($description) {
              case 'Failing Tests':
                  $totals[] = "t={$topic['count']}";
                  break;
            }
        }

        $subscriberLabels = $subscription
            ->getSubscriber()
            ->getLabels();

        $projectName = $summary['project_name'];

        // What's happening here is a little strange. What we're trying to accomplish is this:
        // if a user is subscribed to just one subproject, concat the subproject's name to the
        // project in the subject. The problem with just testing the number of label subscriptions
        // is that the user may have other criteria, besides label subscriptions, that increase
        // the number of subprojects associated with this notification.
        // TODO: does it make sense that this is the logic?
        if (count($subscriberLabels) === 1 && count($summary['build_subproject_names']) === 1) {
            $projectName .= "/{$subscriberLabels[0]}";
        }

        $subject = sprintf(
          $template,
          implode(", ", $totals),
          $projectName,
          $summary['build_name'],
          $summary['build_type']
        );

        $emailMessage->setSubject($subject);
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
