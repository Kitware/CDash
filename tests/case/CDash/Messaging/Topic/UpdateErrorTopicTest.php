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
use CDash\Messaging\Topic\Topic;
use CDash\Messaging\Topic\UpdateErrorTopic;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use CDash\Model\Subscriber;
use CDash\Test\BuildDiffForTesting;

class UpdateErrorTopicTest extends PHPUnit_Framework_TestCase
{
    use BuildDiffForTesting;

    public function testGetTopicName()
    {
        $sut = new UpdateErrorTopic();
        $expected = Topic::UPDATE_ERROR;
        $actual = $sut->getTopicName();
        $this->assertEquals($expected, $actual);
    }

    public function testSubscribesToBuild()
    {
        $sut = new UpdateErrorTopic();
        $build = new Build();
        $update = new BuildUpdate();
        $update->Status = 0;
        $build->SetBuildUpdate($update);

        $this->assertFalse($sut->subscribesToBuild($build));

        $update->Status = 1;

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testGetTopicDescription()
    {
        $sut = new UpdateErrorTopic();
        $expected = 'Update Errors';
        $actual = $sut->getTopicDescription();
        $this->assertEquals($expected, $actual);
    }

    public function testAddBuild()
    {
        $sut = new UpdateErrorTopic();
        $build = new Build();

        $sut->addBuild($build);
        $collection = $sut->getBuildCollection();

        $this->assertSame($build, $collection->current());
    }

    public function testGetTemplate()
    {
        $sut = new UpdateErrorTopic();
        $expected = 'issue';
        $actual = $sut->getTemplate();
        $this->assertEquals($expected, $actual);
    }

    public function testIsSubscribedToBy()
    {
        $sut = new UpdateErrorTopic();

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $this->assertFalse($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_UPDATE;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_FIXES;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));
    }

    public function testHasFixes()
    {
        $sut = new UpdateErrorTopic();

        $build = $this->createMockBuildWithDiff($this->getDiff());
        $sut->subscribesToBuild($build);
        $this->assertFalse($sut->hasFixes());

        $diff = $this->createFixed('buildwarningsnegative');
        $build = $this->createMockBuildWithDiff($diff);
        $sut->subscribesToBuild($build);

        $this->assertTrue($sut->hasFixes());

        $diff = $this->createFixed('builderrorsnegative');
        $build = $this->createMockBuildWithDiff($diff);
        $sut->subscribesToBuild($build);

        $this->assertTrue($sut->hasFixes());

        $diff = $this->createFixed('testfailednegative');
        $build = $this->createMockBuildWithDiff($diff);
        $sut->subscribesToBuild($build);

        $this->assertTrue($sut->hasFixes());
    }

    public function testGetFixes()
    {
        $sut = new UpdateErrorTopic();

        $build = $this->createMockBuildWithDiff($this->getDiff());
        $sut->subscribesToBuild($build);

        $this->assertEquals($build->GetDiffWithPreviousBuild(), $sut->getFixes());

        $diff = $this->createFixed('buildwarningsnegative');
        $build = $this->createMockBuildWithDiff($diff);
        $sut->subscribesToBuild($build);

        $this->assertEquals($build->GetDiffWithPreviousBuild(), $sut->getFixes());

        $diff = $this->createFixed('builderrorsnegative');
        $build = $this->createMockBuildWithDiff($diff);
        $sut->subscribesToBuild($build);

        $this->assertEquals($build->GetDiffWithPreviousBuild(), $sut->getFixes());

        $diff = $this->createFixed('testfailednegative');
        $build = $this->createMockBuildWithDiff($diff);
        $sut->subscribesToBuild($build);

        $this->assertEquals($build->GetDiffWithPreviousBuild(), $sut->getFixes());
    }
}
