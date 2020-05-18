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

use CDash\Collection\BuildErrorCollection;
use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\BuildErrorTopic;
use CDash\Model\Build;
use CDash\Model\BuildError;
use CDash\Model\Subscriber;
use CDash\Test\BuildDiffForTesting;

class BuildErrorTopicTest extends \CDash\Test\CDashTestCase
{
    use BuildDiffForTesting;

    public function testSubscribesToBuildWithErrorDiff()
    {
        $build = new Build();

        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $this->assertFalse($sut->subscribesToBuild($build));
        $build = $this->getMockBuild();

        $build->expects($this->any())
            ->method('GetDiffWithPreviousBuild')
            ->willReturnOnConsecutiveCalls(
                ['BuildError' => ['new' => 1]],
                ['BuildError' => ['new' => 0]]
            );

        $this->assertTrue($sut->subscribesToBuild($build));
        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildWithWarningDiff()
    {
        $build = new Build();

        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_WARN);

        $this->assertFalse($sut->subscribesToBuild($build));
        $build = $this->getMockBuild();

        $build->expects($this->any())
            ->method('GetDiffWithPreviousBuild')
            ->willReturnOnConsecutiveCalls(
                ['BuildWarning' => ['new' => 1]],
                ['BuildWarning' => ['new' => 0]]
            );

        $this->assertTrue($sut->subscribesToBuild($build));
        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testGetTopicCollection()
    {
        $sut = new BuildErrorTopic();
        $collection = $sut->getTopicCollection();
        $this->assertInstanceOf(BuildErrorCollection::class, $collection);
    }

    public function testSetTopicData()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $build = new Build();
        $buildErrorError = new BuildError();
        $buildErrorWarning = new BuildError();
        $buildErrorError->Type = Build::TYPE_ERROR;
        $buildErrorWarning->Type = Build::TYPE_WARN;

        $build->AddError($buildErrorError);
        $build->AddError($buildErrorWarning);

        $sut->setTopicData($build);
        $collection = $sut->getTopicCollection();

        $this->assertSame($buildErrorError, $collection->current());

        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_WARN);

        $sut->setTopicData($build);
        $collection = $sut->getTopicCollection();

        $this->assertSame($buildErrorWarning, $collection->current());
    }

    public function testItemHasTopicSubject()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $build = new Build();
        $error = new BuildError();
        $build->AddError($error);

        $this->assertFalse($sut->itemHasTopicSubject($build, $error));

        $error->Type = Build::TYPE_ERROR;
        $this->assertTrue($sut->itemHasTopicSubject($build, $error));

        $sut->setType(Build::TYPE_WARN);
        $this->assertFalse($sut->itemHasTopicSubject($build, $error));

        $error->Type = Build::TYPE_WARN;
        $this->assertTrue($sut->itemHasTopicSubject($build, $error));
    }

    public function testGetTopicCount()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        $build = new Build();
        $e1 = new BuildError();
        $e1->Type = Build::TYPE_WARN;
        $e2 = new BuildError();
        $e2->Type = Build::TYPE_ERROR;
        $e3 = new BuildError();
        $e3->Type = Build::TYPE_ERROR;

        $build->AddError($e1);
        $build->AddError($e2);
        $build->AddError($e3);

        $sut->setTopicData($build);
        $this->assertEquals(2, $sut->getTopicCount());
    }

    public function testGetTopicName()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);
        $this->assertEquals('BuildError', $sut->getTopicName());

        $sut->setType(Build::TYPE_WARN);
        $this->assertEquals('BuildWarning', $sut->getTopicName());
    }

    public function testGetTopicDescription()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);
        $this->assertEquals('Errors', $sut->getTopicDescription());

        $sut->setType(Build::TYPE_WARN);
        $this->assertEquals('Warnings', $sut->getTopicDescription());
    }

    public function testHasFixes()
    {
        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_ERROR);

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences'])
            ->getMock();

        $build->expects($this->never())
            ->method('GetErrorDifferences')
            ->willReturn($this->getDiff());
        $build->Id = 201;

        $sut->subscribesToBuild($build);
        $this->assertFalse($sut->hasFixes());

        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences', 'GetPreviousBuildId'])
            ->getMock();

        $build->expects($this->once())
            ->method('GetErrorDifferences')
            ->willReturn($this->createFixed('builderrorsnegative'));

        $build->expects($this->once())
            ->method('GetPreviousBuildId')
            ->willReturn(201);

        $build->Id = 202;

        $sut->subscribesToBuild($build);
        $this->assertTrue($sut->hasFixes());
    }

    public function testGetTemplate()
    {
        $sut = new BuildErrorTopic();
        $exptected = 'issue';
        $actual = $sut->getTemplate();
        $this->assertEquals($exptected, $actual);
    }

    public function testIsSubscribedToBy()
    {
        $sut = new BuildErrorTopic();

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $this->assertFalse($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_ERROR;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);
        $sut->setType(Build::TYPE_ERROR);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));

        $sut->setType(Build::TYPE_WARN);
        $this->assertFalse($sut->isSubscribedToBy($subscriber));
        $preferences->set(NotifyOn::BUILD_WARNING, true);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_FIXES;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));
    }

    public function testSubscribesToRedundantBuild()
    {
        // Create a build with a redundant warning.
        $build = $this->getMockBuild();
        $build->expects($this->any())
            ->method('GetDiffWithPreviousBuild')
            ->willReturn(['BuildWarning' => ['new' => 0]]);
        $build->expects($this->any())
            ->method('GetNumberOfWarnings')
            ->willReturn(1);

        // Verify that a user will not be notified when redundant
        // notifications are disabled.
        $bitmask = BitmaskNotificationPreferences::EMAIL_WARNING;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $preferences->set(NotifyOn::REDUNDANT, 0);
        $subscriber = new Subscriber($preferences);

        $sut = new BuildErrorTopic();
        $sut->setType(Build::TYPE_WARN);
        $sut->setSubscriber($subscriber);
        $this->assertFalse($sut->subscribesToBuild($build));

        // Verify that the notification will be sent when the redundant
        // flag is enabled.
        $preferences->set(NotifyOn::REDUNDANT, 1);
        $subscriber = new Subscriber($preferences);
        $this->assertTrue($sut->subscribesToBuild($build));
    }
}
