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

namespace CDash\Model;

use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Topic\TopicCollection;
use ActionableBuildInterface;

interface SubscriberInterface
{
    /**
     * SubscriberInterface constructor.
     * @param NotificationPreferences $preferences
     * @param TopicCollection|null $topics
     */
    public function __construct(
        NotificationPreferences $preferences,
        TopicCollection $topics = null
    );

    /**
     * @param ActionableBuildInterface $build
     * @return bool
     */
    public function hasBuildTopics(ActionableBuildInterface $build);

    /**
     * @return TopicCollection
     */
    public function getTopics();

    /**
     * @return string
     */
    public function getAddress();

    /**
     * @param $address
     * @return mixed
     */
    public function setAddress($address);

    /**
     * @return string[]
     */
    public function getLabels();

    /**
     * @return \CDash\Messaging\Preferences\NotificationPreferencesInterface
     */
    public function getNotificationPreferences();
}
