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

use CDash\Collection\BuildEmailCollection;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\EmailSentTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\ActionableTypes;
use CDash\Model\Build;
use CDash\Model\BuildEmail;
use CDash\Model\Subscriber;

class EmailSentTopicTest extends \CDash\Test\CDashTestCase
{
    public function testSubscribesToBuild()
    {
        $mock_topic = $this->getMockForAbstractClass(
            Topic::class,
            [],
            '',
            true,
            true,
            true,
            ['subscribesToBuild', 'getTopicName']
        );

        $mock_topic->expects($this->exactly(3))
            ->method('subscribesToBuild')
            ->willReturnOnConsecutiveCalls(false, true, true);

        $mock_topic->expects($this->any())
            ->method('getTopicName')
            ->willReturn(ActionableTypes::TEST);

        $sut = new EmailSentTopic($mock_topic);
        $build = new Build();
        $build->Id = 1;
        $this->assertFalse($sut->subscribesToBuild($build));

        $buildEmails = new BuildEmailCollection();
        $build->SetBuildEmailCollection($buildEmails);

        $email1 = new BuildEmail();
        $email1->SetEmail('ricky.bobby@company.tld');
        $email1->SetCategory(ActionableTypes::$categories[ActionableTypes::TEST]);

        $email2 = new BuildEmail();
        $email2->SetEmail('cal.naughton@company.tld');
        $email2->SetCategory(ActionableTypes::$categories[ActionableTypes::TEST]);

        $buildEmails
            ->add($email1)
            ->add($email2);

        $subscriber = new Subscriber(new BitmaskNotificationPreferences());
        $sut->setSubscriber($subscriber);

        $subscriber->setAddress('ricky.bobby@company.tld');

        $this->assertFalse($sut->subscribesToBuild($build));
        $subscriber->setAddress('texas.ranger@company.tld');

        $this->assertTrue($sut->subscribesToBuild($build));
    }
}
