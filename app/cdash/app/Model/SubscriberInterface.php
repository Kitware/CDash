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

use ActionableBuildInterface;
use CDash\Messaging\Preferences\NotificationPreferences;
use CDash\Messaging\Preferences\NotificationPreferencesInterface;
use CDash\Messaging\Topic\TopicCollection;

interface SubscriberInterface
{
    /**
     * SubscriberInterface constructor.
     */
    public function __construct(
        NotificationPreferences $preferences,
        ?TopicCollection $topics = null,
    );

    /**
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

    public function setAddress($address);

    /**
     * @return string[]
     */
    public function getLabels();

    /**
     * @return NotificationPreferencesInterface
     */
    public function getNotificationPreferences();

    /**
     * @return array
     */
    public function getUserCredentials();
}
