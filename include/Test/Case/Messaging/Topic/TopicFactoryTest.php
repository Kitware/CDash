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

use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\AuthoredTopic;
use CDash\Messaging\Topic\EmailSentTopic;
use CDash\Messaging\Topic\FixedTopic;
use CDash\Messaging\Topic\GroupMembershipTopic;
use CDash\Messaging\Topic\LabeledTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\TopicDecorator;
use CDash\Model\UserProject;

class TopicFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testFactoryGivenNotifyOnBuildWarningAndBuildHandler()
    {
        $handler = $this->getMockBuilder(ActionableBuildInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $settings = BitmaskNotificationPreferences::EMAIL_WARNING;
    }
}
