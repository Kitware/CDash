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

use CDash\Collection\DynamicAnalysisCollection;
use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\DynamicAnalysisTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\DynamicAnalysis;
use CDash\Model\Subscriber;
use Tests\TestCase;

class DynamicAnalysisTopicTest extends TestCase
{
    public function testSubscribesToBuild()
    {
        $sut = new DynamicAnalysisTopic();
        $build = new Build();

        $this->assertFalse($sut->subscribesToBuild($build));

        $analysis = new DynamicAnalysis();
        $build->AddDynamicAnalysis($analysis);
        $this->assertFalse($sut->subscribesToBuild($build));

        $analysis->Status = DynamicAnalysis::PASSED;
        $this->assertFalse($sut->subscribesToBuild($build));

        $analysis->Status = DynamicAnalysis::FAILED;
        $this->assertTrue($sut->subscribesToBuild($build));

        $analysis->Status = DynamicAnalysis::NOTRUN;
        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testItemHasTopicSubject()
    {
        $sut = new DynamicAnalysisTopic();
        $build = new Build();
        $analysis = new DynamicAnalysis();

        $this->assertFalse($sut->itemHasTopicSubject($build, $analysis));

        $analysis->Status = DynamicAnalysis::PASSED;
        $this->assertFalse($sut->itemHasTopicSubject($build, $analysis));

        $analysis->Status = DynamicAnalysis::FAILED;
        $this->assertTrue($sut->itemHasTopicSubject($build, $analysis));

        $analysis->Status = DynamicAnalysis::NOTRUN;
        $this->assertTrue($sut->itemHasTopicSubject($build, $analysis));
    }

    public function testGetTopicCollection()
    {
        $sut = new DynamicAnalysisTopic();
        $collection = $sut->getTopicCollection();
        $this->assertInstanceOf(DynamicAnalysisCollection::class, $collection);
    }

    public function testSetTopicData()
    {
        $sut = new DynamicAnalysisTopic();
        $build = new Build();

        $a1 = new DynamicAnalysis();
        $a2 = new DynamicAnalysis();
        $a3 = new DynamicAnalysis();

        $a1->Status = DynamicAnalysis::NOTRUN;
        $a2->Status = DynamicAnalysis::PASSED;
        $a3->Status = DynamicAnalysis::FAILED;

        $a1->Name = 'A';
        $a2->Name = 'B';
        $a3->Name = 'C';

        $build
            ->AddDynamicAnalysis($a1)
            ->AddDynamicAnalysis($a2)
            ->AddDynamicAnalysis($a3);

        $sut->setTopicData($build);
        $collection = $sut->getTopicCollection();

        $this->assertEquals(2, $sut->getTopicCount());
        $this->assertSame($a1, $collection->get('A'));
        $this->assertSame($a3, $collection->get('C'));
        $this->assertNull($collection->get('B'));
    }

    public function testGetTopicName()
    {
        $sut = new DynamicAnalysisTopic();
        $expected = Topic::DYNAMIC_ANALYSIS;
        $actual = $sut->getTopicName();
        $this->assertEquals($expected, $actual);
    }

    public function testGetTopicDescription()
    {
        $sut = new DynamicAnalysisTopic();
        $expected = 'Dynamic analysis tests failing or not run';
        $actual = $sut->getTopicDescription();
        $this->assertEquals($expected, $actual);
    }

    public function testIsSubscribedToBy()
    {
        $sut = new DynamicAnalysisTopic();

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $this->assertFalse($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_DYNAMIC_ANALYSIS;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));
    }
}
