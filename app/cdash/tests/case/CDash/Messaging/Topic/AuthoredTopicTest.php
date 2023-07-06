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

use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\AuthoredTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\Subscriber;

class AuthoredTopicTest extends \CDash\Test\CDashTestCase
{
    public function testSubscribesToBuild()
    {
        $mock_topic = $this->getMockForAbstractClass(
            Topic::class,
            [],
            '',
            false,
            true,
            true,
            ['subscribesToBuild']);

        $mock_topic->expects($this->any())
            ->method('subscribesToBuild')
            ->willReturn(true);

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetCommitAuthors'])
            ->getMock();

        $build->expects($this->exactly(2))
            ->method('GetCommitAuthors')
            ->willReturnOnConsecutiveCalls(
                [],
                ['cdash.user@company.tld']
            );

        $subscriber = new Subscriber(new BitmaskNotificationPreferences());
        $subscriber->setAddress('cdash.user@company.tld');

        $sut = new AuthoredTopic($mock_topic);
        $sut->setSubscriber($subscriber);

        $this->assertFalse($sut->subscribesToBuild($build));
        $this->assertTrue($sut->subscribesToBuild($build));
    }
}
