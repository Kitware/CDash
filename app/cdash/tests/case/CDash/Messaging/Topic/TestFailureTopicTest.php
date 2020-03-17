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

use App\Models\BuildTest;
use App\Models\Test;

use CDash\Messaging\Preferences\BitmaskNotificationPreferences;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\Label;
use CDash\Model\Subscriber;
use CDash\Model\Test as TestConstants;
use CDash\Test\BuildDiffForTesting;

class TestFailureTopicTest extends \CDash\Test\CDashTestCase
{
    use BuildDiffForTesting;

    public function testSubscribesToBuild()
    {
        $sut = new TestFailureTopic();
        $build = $this->createMockBuildWithDiff($this->getDiff());
        $this->assertFalse($sut->subscribesToBuild($build));

        $diff = $this->createNew('testfailedpositive');
        $this->assertEquals(1, $diff['testfailedpositive']);
        $build = $this->createMockBuildWithDiff($diff);
        $this->assertTrue($sut->subscribesToBuild($build));

        $diff = $this->createNew('testnotrunpositive');
        $this->assertEquals(1, $diff['testnotrunpositive']);
        $build = $this->createMockBuildWithDiff($diff);
        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testItemHasTopicSubject()
    {
        $sut = new TestFailureTopic();
        $build = new Build();
        $test = new Test();
        $buildTest = new BuildTest();
        $buildTest->test = $test;

        $build->AddTest($buildTest);

        $this->assertFalse($sut->itemHasTopicSubject($build, $buildTest));

        $buildTest->status = TestConstants::PASSED;

        $this->assertFalse($sut->itemHasTopicSubject($build, $buildTest));

        $buildTest->status = TestConstants::NOTRUN;

        $this->assertFalse($sut->itemHasTopicSubject($build, $buildTest));

        $buildTest->details = TestConstants::DISABLED;

        $this->assertFalse($sut->itemHasTopicSubject($build, $buildTest));

        $buildTest->status = TestConstants::FAILED;

        $this->assertTrue($sut->itemHasTopicSubject($build, $buildTest));
    }

    public function testSetTopicData()
    {
        $sut = new TestFailureTopic();
        $build = new Build();

        $passed = new BuildTest();
        $passed->status = TestConstants::PASSED;
        $test1 = new Test();
        $test1->name = 'Passed';
        $passed->test = $test1;

        $failed = new BuildTest();
        $failed->status = TestConstants::FAILED;
        $test2 = new Test();
        $test2->name = 'Failed';
        $failed->test = $test2;

        $notrun = new BuildTest();
        $notrun->status = TestConstants::NOTRUN;
        $test3 = new Test();
        $test3->name = 'NotRun';
        $notrun->test = $test3;

        $disabled = new BuildTest();
        $disabled->status = TestConstants::NOTRUN;
        $disabled->details = TestConstants::DISABLED;
        $test4 = new Test();
        $test4->name = 'Disabled';
        $disabled->test = $test4;

        $build
            ->AddTest($passed)
            ->AddTest($failed)
            ->AddTest($notrun)
            ->AddTest($disabled);

        $this->assertEquals(0, $sut->getTopicCount());

        $sut->setTopicData($build);

        $collection = $sut->getTopicCollection();
        $this->assertEquals(1, $sut->getTopicCount());

        $this->assertTrue($collection->has('Failed'));
        $this->assertFalse($collection->has('NotRun'));
        $this->assertFalse($collection->has('Passed'));
    }

    public function testGetTopicName()
    {
        $sut = new TestFailureTopic();
        $expected = Topic::TEST_FAILURE;
        $actual = $sut->getTopicName();
        $this->assertEquals($expected, $actual);
    }

    public function testGetTopicDescription()
    {
        $sut = new TestFailureTopic();
        $expected = 'Failing Tests';
        $actual = $sut->getTopicDescription();
        $this->assertEquals($expected, $actual);
    }

    public function testHasFixes()
    {
        $sut = new TestFailureTopic();

        $this->assertFalse($sut->hasFixes());

        $build = $this->createMockBuildWithDiff($this->getDiff());
        $sut->subscribesToBuild($build);

        $this->assertFalse($sut->hasFixes());

        $diff = $this->createFixed('testfailednegative');
        $build = $this->createMockBuildWithDiff($diff);
        $sut->subscribesToBuild($build);
        $this->assertTrue($sut->hasFixes());
    }

    public function testGetFixes()
    {
        $sut = new TestFailureTopic();

        $this->assertEmpty($sut->getFixes());

        $build = $this->createMockBuildWithDiff($this->getDiff());
        $sut->subscribesToBuild($build);

        $diff = $sut->getFixes();

        $this->assertArrayHasKey('passed', $diff);
        $this->assertArrayHasKey('new', $diff['passed']);
        $this->assertArrayHasKey('broken', $diff['passed']);

        $this->assertArrayHasKey('failed', $diff);
        $this->assertArrayHasKey('new', $diff['failed']);
        $this->assertArrayHasKey('fixed', $diff['failed']);

        $this->assertArrayHasKey('notrun', $diff);
        $this->assertArrayHasKey('new', $diff['notrun']);
        $this->assertArrayHasKey('fixed', $diff['notrun']);
    }

    public function testSetTopicDataWithLabels()
    {
        $sut = new TestFailureTopic();
        $build = new Build();
        $collection = $sut->getTopicCollection();

        $this->assertEquals(0, $collection->count());

        // Create a test that has a label we're searching for but has passed, does not get added
        $labelForOne = new Label();
        $labelForOne->Text = 'One';
        $test1 = new Test();
        $test1->name = 'TestOne';
        $buildTestOne = new BuildTest();
        $buildTestOne->status = TestConstants::PASSED;
        $buildTestOne->addLabel($labelForOne);
        $buildTestOne->test = $test1;

        // Create a test that has failed but does not have a label we're searching for
        $labelForTwo = new Label();
        $labelForTwo->Text = 'Two';
        $test2 = new Test();
        $test2->name = 'TestTwo';
        $buildTestTwo = new BuildTest();
        $buildTestTwo->status = TestConstants::FAILED;
        $buildTestTwo->addLabel($labelForTwo);
        $buildTestTwo->test = $test2;

        // Create a test that has failed and has a label that we're searching for
        $labelForThree = new Label();
        $labelForThree->Text = 'Three';
        $test3 = new Test();
        $test3->name = 'TestThree';
        $buildTestThree = new BuildTest();
        $buildTestThree->status = TestConstants::FAILED;
        $buildTestThree->addLabel($labelForThree);
        $buildTestThree->test = $test3;

        $build
            ->AddTest($buildTestOne)
            ->AddTest($buildTestTwo)
            ->AddTest($buildTestThree);

        $lbl1 = new Label();
        $lbl1->Text = 'One';
        $lbl2 = new Label();
        $lbl2->Text = 'Nope';
        $lbl3 = new Label();
        $lbl3->Text = 'Three';

        $lblCollection = collect();
        $lblCollection
            ->put($lbl1->Text, $lbl1)
            ->put($lbl2->Text, $lbl2)
            ->put($lbl3->Text, $lbl3);

        $sut->setTopicDataWithLabels($build, $lblCollection);

        $this->assertEquals(1, $collection->count());
        $this->assertTrue($collection->has($test3->name));
    }

    public function testGetLabelsFromBuild()
    {
        $sut = new TestFailureTopic();
        $build = new Build();

        // Create a test that has a label we're searching for but has passed, does not get added
        $labelForOne = new Label();
        $labelForOne->Text = 'One';
        $test1 = new Test();
        $test1->name = 'TestOne';
        $buildTestOne = new BuildTest();
        $buildTestOne->status = TestConstants::PASSED;
        $buildTestOne->addLabel($labelForOne);
        $buildTestOne->test = $test1;

        // Create a test that has failed but does not have a label we're searching for
        $labelForTwo = new Label();
        $labelForTwo->Text = 'Two';
        $test2 = new Test();
        $test2->name = 'TestTwo';
        $buildTestTwo = new BuildTest();
        $buildTestTwo->status = TestConstants::FAILED;
        $buildTestTwo->addLabel($labelForTwo);
        $buildTestTwo->test = $test2;

        // Create a test that is not run and has a label that we're searching for
        $labelForThree = new Label();
        $labelForThree->Text = 'Three';
        $test3 = new Test();
        $test3->name = 'TestThree';
        $buildTestThree = new BuildTest();
        $buildTestThree->status = TestConstants::NOTRUN;
        $buildTestThree->addLabel($labelForThree);
        $buildTestThree->test = $test3;

        $collection = $sut->getLabelsFromBuild($build);
        $this->assertEquals(0, $collection->count());

        $build
            ->AddTest($buildTestOne)
            ->AddTest($buildTestTwo)
            ->AddTest($buildTestThree);

        $collection = $sut->getLabelsFromBuild($build);

        $this->assertTrue($collection->contains($labelForTwo));
        $this->assertFalse($collection->contains($labelForThree));
    }

    public function testIsSubscribedToBy()
    {
        $sut = new TestFailureTopic();

        $preferences = new BitmaskNotificationPreferences();
        $subscriber = new Subscriber($preferences);

        $this->assertFalse($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_TEST;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));

        $bitmask = BitmaskNotificationPreferences::EMAIL_FIXES;
        $preferences = new BitmaskNotificationPreferences($bitmask);
        $subscriber = new Subscriber($preferences);

        $this->assertTrue($sut->isSubscribedToBy($subscriber));
    }
}
