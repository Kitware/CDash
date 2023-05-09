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
use CDash\Messaging\Topic\Topic;
use CDash\Model\ActionableTypes;
use CDash\Model\BuildEmail;

class EmailBuilder extends SubscriptionNotificationBuilder
{
    public function __construct(FactoryInterface $factory, CollectionInterface $collection)
    {
        parent::__construct($factory, $collection);
    }

    /**
     * @param SubscriptionInterface $subscription
     * @param string $templateName
     */
    public function createNotification(SubscriptionInterface $subscription, $templateName): EmailMessage|NotificationInterface
    {
        $subject_template = "email.{$templateName}.subject";
        $template = "email.{$templateName}";

        $data = ['subscription' => $subscription];
        $subject = view($subject_template)->with($data);
        $body = view($template)->with($data);
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
