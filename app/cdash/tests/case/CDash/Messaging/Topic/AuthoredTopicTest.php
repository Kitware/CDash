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
use CDash\Test\CDashTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AuthoredTopicTest extends CDashTestCase
{
    public function testSubscribesToBuild(): void
    {
        $mock_topic = $this->getMockBuilder(Topic::class)
            ->onlyMethods(['subscribesToBuild'])
            ->getMock();

        $mock_topic->expects($this->any())
            ->method('subscribesToBuild')
            ->willReturn(true);

        /** @var Build|MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->onlyMethods(['GetCommitAuthors'])
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
