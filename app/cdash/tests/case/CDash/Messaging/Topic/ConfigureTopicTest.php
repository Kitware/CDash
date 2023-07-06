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


use CDash\Collection\ConfigureCollection;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\ConfigureTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\Label;
use CDash\Model\Subscriber;

class ConfigureTopicTest extends \CDash\Test\CDashTestCase
{
    /** @var Topic|PHPUnit_Framework_MockObject_MockObject */
    private $parent;

    public function setUp() : void
    {
        parent::setUp();
        $this->parent = $this->getMockForAbstractClass(Topic::class);
    }

    public function testSubscribesToBuild()
    {
        $sut = new ConfigureTopic();
        $build = new Build();
        $buildConfigure = new BuildConfigure();
        $build->SetBuildConfigure($buildConfigure);

        // SUT has no parent, BuildConfigure has no warnings or errors
        $this->assertFalse($sut->subscribesToBuild($build));

        $this->parent
            ->method('subscribesToBuild')
            ->willReturnOnConsecutiveCalls(false, true, true, true);

        // SUT has parent does not subscribe, BuildConfigure has no warnings or errors
        $sut = new ConfigureTopic($this->parent);
        $this->assertFalse($sut->subscribesToBuild($build));

        // SUT has parent who subscribes, BuildConfigure has no warnings or errors
        $sut = new ConfigureTopic($this->parent);
        $this->assertFalse($sut->subscribesToBuild($build));

        // SUT has parent who subscribes, BuildConfigure has warnings, no errors
        // warnings do not trigger subscriptions
        $buildConfigure->NumberOfWarnings = 1;
        $sut = new ConfigureTopic($this->parent);
        $this->assertFalse($sut->subscribesToBuild($build));

        // SUT has parent who subscribes, BuildConfigure errors, no warnings
        $buildConfigure->NumberOfWarnings = 0;
        $buildConfigure->NumberOfErrors = 1;
        $sut = new ConfigureTopic($this->parent);
        $this->assertTrue($sut->subscribesToBuild($build));

        $buildConfigure->NumberOfWarnings = -1;
        $buildConfigure->NumberOfErrors = 0;
        $sut = new ConfigureTopic();
        $this->assertFalse($sut->subscribesToBuild($build));

        $buildConfigure->NumberOfWarnings = 0;
        $buildConfigure->NumberOfErrors = -1;
        $sut = new ConfigureTopic();
        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testSetTopicData()
    {
        $sut = new ConfigureTopic();
        $build = new Build();
        $buildConfigure = new BuildConfigure();
        $build->SetBuildConfigure($buildConfigure);

        $sut->setTopicData($build);
        $collection = $sut->getTopicCollection();
        $this->assertSame($buildConfigure, $collection->get(Topic::CONFIGURE));
    }

    public function testGetTopicCount()
    {
        $sut = new ConfigureTopic();
        $build = new Build();
        $buildConfigure = new BuildConfigure();
        $build->SetBuildConfigure($buildConfigure);

        $sut->setTopicData($build);
        $this->assertEquals(0, $sut->getTopicCount());

        $buildConfigure->NumberOfErrors = '0';
        $this->assertEquals(0, $sut->getTopicCount());

        $buildConfigure->NumberOfErrors = '127';
        $this->assertEquals(127, $sut->getTopicCount());
    }

    public function testGetTopicDescription()
    {
        $sut = new ConfigureTopic();
        $this->assertEquals('Configure Errors', $sut->getTopicDescription());
    }

    public function testGetTopicName()
    {
        $sut = new ConfigureTopic();
        $this->assertEquals(Topic::CONFIGURE, $sut->getTopicName());
    }

    public function testGetTopicCollection()
    {
        $sut = new ConfigureTopic();
        $collection = $sut->getTopicCollection();
        $this->assertInstanceOf(ConfigureCollection::class, $collection);
    }

    public function testGetTemplate()
    {
        $sut = new ConfigureTopic();
        $expected = 'issue';
        $actual = $sut->getTemplate();
        $this->assertEquals($expected, $actual);
    }

    public function testGetLabelsFromBuild()
    {
        $sut = new ConfigureTopic();
        $build = new Build();
        $configure = new BuildConfigure();
        $build->SetBuildConfigure($configure);

        $collection = $sut->getLabelsFromBuild($build);
        $this->assertEmpty($collection->toArray());

        $lbl1 = new Label();
        $lbl2 = new Label();

        $lbl1->Text = 'one';
        $lbl2->Text = 'two';

        $build->AddLabel($lbl2);
        $configure->AddLabel($lbl1);

        $collection = $sut->getLabelsFromBuild($build);

        $this->assertTrue($collection->has('one'));
        $this->assertTrue($collection->has('two'));
    }

    public function testSetTopicDataWithLabels()
    {
        $sut = new ConfigureTopic();
        $build = new Build();
        $configure = new BuildConfigure();

        $build->SetBuildConfigure($configure);
        $sut->setTopicDataWithLabels($build, collect());

        $collection = $sut->getTopicCollection();
        $this->assertInstanceOf(ConfigureCollection::class, $collection);

        $this->assertSame($configure, $collection->current());
        $this->assertCount(1, $collection);
    }

    public function testIsSubscribedToBy()
    {
        $sut = new ConfigureTopic();

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $this->assertFalse($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_CONFIGURE;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));
    }
}
