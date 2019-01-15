<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */
namespace CDash\Messaging\Notification\Email;

use CDash\Collection\BuildEmailCollection;
use CDash\Config;
use CDash\Messaging\FactoryInterface;
use CDash\Collection\CollectionInterface;
use CDash\Messaging\Notification\NotificationCollection;
use CDash\Messaging\Notification\NotificationInterface;
use CDash\Messaging\Subscription\SubscriptionInterface;
use CDash\Messaging\Subscription\SubscriptionNotificationBuilder;
use CDash\Messaging\Topic\LabeledTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\ActionableTypes;
use CDash\Model\BuildEmail;
use Jenssegers\Blade\Blade;

class EmailBuilder extends SubscriptionNotificationBuilder
{
    /** @var string $templateDirectory */
    private $templateDirectory;

    /** @var string $cacheDirectory */
    private $cacheDirectory;

    public function __construct(FactoryInterface $factory, CollectionInterface $collection)
    {
        parent::__construct($factory, $collection);
        $this->templateDirectory = __DIR__ . '/Template';
        $this->cacheDirectory = Config::getInstance()->get('CDASH_ROOT_DIR') . '/log';
    }

    /**
     * @param SubscriptionInterface $subscription
     * @param string $templateName
     * @return EmailMessage|NotificationInterface|null
     */
    public function createNotification(SubscriptionInterface $subscription, $templateName)
    {
        $message = null;
        $blade = new Blade((array)$this->templateDirectory, $this->cacheDirectory);
        $data = ['subscription' => $subscription];
        $subject = $blade->make("{$templateName}.subject", $data);
        $body = $blade->make($templateName, $data);
        $recipient = $subscription->getSubscriber()->getAddress();
        /** @var EmailMessage $message */
        $message = $this->factory->create();
        $message->setSubject($subject)
            ->setBody($body)
            ->setRecipient($recipient);
        // todo: this doesn't really belong here, refactor asap
        $this->setBuildEmailCollection($message, $subscription);
        return $message;
    }

    /**
     * The purpose of this method is to remove duplicate topics from the topic collection. It is
     * possible to have duplicate topics under the conditions where, say, a build was included
     * because the build was authored by the user then included again because it matched a label
     * to which the user is subscribed. Duplicates must be removed so that they are not output
     * multiple times by the notification decorators.
     *
     * @param SubscriptionInterface $subscription
     * @return bool
     */
    protected function uniquifyTopics(SubscriptionInterface $subscription)
    {
        $topics = $subscription->getTopicCollection();

        /** @var LabeledTopic $labeled */
        $labeled = $topics->remove('Labeled');
        if ($labeled) {
            $labeled->mergeTopics($topics);
        }
        return !!$topics->count();
    }

    /**
     * @return NotificationCollection
     */
    public function getNotifications()
    {
        return $this->notifications;
    }

    /**
     * @param EmailMessage $message
     * @param SubscriptionInterface $subscription
     */
    public function setBuildEmailCollection(EmailMessage $message, SubscriptionInterface $subscription)
    {
        $topics = $subscription->getTopicCollection();
        $subscriber = $subscription->getSubscriber();
        $collection = new BuildEmailCollection();

        /** @var Topic $topic */
        foreach ($topics as $topic) {
            $builds = $topic->getBuildCollection();
            $category = ActionableTypes::$categories[$topic->getTopicName()];
            $userId = $subscriber->getUserId();
            $email = $subscription->getRecipient();
            foreach ($builds as $build) {
                $buildId = $build->Id;
                $buildEmail = new BuildEmail();
                $buildEmail
                    ->SetUserId($userId)
                    ->SetBuildId($buildId)
                    ->SetEmail($email)
                    ->SetCategory($category);
                $collection->add($buildEmail);
            }
        }

        $message->setBuildEmailCollection($collection);
    }
}
