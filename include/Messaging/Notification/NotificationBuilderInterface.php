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
namespace CDash\Messaging\Notification;

use CDash\Collection\CollectionInterface;
use CDash\Collection\SubscriberCollection;
use CDash\Messaging\FactoryInterface;
use CDash\Messaging\Subscription\SubscriptionInterface;

interface NotificationBuilderInterface
{
    /**
     * NotificationBuilderInterface constructor.
     * @param FactoryInterface $factory
     * @param CollectionInterface $collection
     */
    public function __construct(FactoryInterface $factory, CollectionInterface $collection);

    /**
     * @param SubscriptionInterface $subscription
     * @param string $templateName
     * @return NotificationInterface|null
     */
    public function createNotification(SubscriptionInterface $subscription, $templateName);

    /**
     * @return SubscriberCollection
     */
    public function getSubscriptions();

    /**
     * @return NotificationCollection
     */
    public function getNotifications();
}
