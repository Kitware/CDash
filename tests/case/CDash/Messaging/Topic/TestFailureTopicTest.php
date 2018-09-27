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

use CDash\Collection\LabelCollection;
use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\BuildTest;
use CDash\Model\Label;
use CDash\Test\BuildDiffForTesting;
use CDash\Model\Test;

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
        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testItemHasTopicSubject()
    {
        $sut = new TestFailureTopic();
        $build = new Build();
        $test = new Test();
        $buildTest = new BuildTest();

        $test->SetBuildTest($buildTest);
        $build->AddTest($test);

        $this->assertFalse($sut->itemHasTopicSubject($build, $test));

        $buildTest->Status = Test::PASSED;

        $this->assertFalse($sut->itemHasTopicSubject($build, $test));

        $buildTest->Status = Test::NOTRUN;

        $this->assertTrue($sut->itemHasTopicSubject($build, $test));

        $test->Details = Test::DISABLED;

        $this->assertFalse($sut->itemHasTopicSubject($build, $test));

        $buildTest->Status = Test::FAILED;

        $this->assertTrue($sut->itemHasTopicSubject($build, $test));
    }

    public function testSetTopicData()
    {
        $sut = new TestFailureTopic();
        $build = new Build();
        $test1 = new Test();
        $test1->Name = 'Passed';
        $test2 = new Test();
        $test2->Name = 'Failed';
        $test3 = new Test();
        $test3->Name = 'NotRun';
        $test4 = new Test();
        $test4->Name = 'Disabled';
        $test4->Details = Test::DISABLED;

        $passed = new BuildTest();
        $passed->Status = Test::PASSED;

        $failed = new BuildTest();
        $failed->Status = Test::FAILED;

        $notrun = new BuildTest();
        $notrun->Status = Test::NOTRUN;

        $disabled = new BuildTest();
        $disabled->Status = Test::NOTRUN;

        $test1->SetBuildTest($passed);
        $test2->SetBuildTest($failed);
        $test3->SetBuildTest($notrun);

        $build
            ->AddTest($test1)
            ->AddTest($test2)
            ->AddTest($test3)
            ->AddTest($test4);

        $this->assertEquals(0, $sut->getTopicCount());

        $sut->setTopicData($build);

        $collection = $sut->getTopicCollection();
        $this->assertEquals(2, $sut->getTopicCount());

        $this->assertTrue($collection->has('Failed'));
        $this->assertTrue($collection->has('NotRun'));
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
        $this->assertArrayHasKey('fixed' , $diff['failed']);

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
        $test1->Name = 'TestOne';
        $buildTestOne = new BuildTest();
        $buildTestOne->Status = Test::PASSED;
        $test1->AddLabel($labelForOne);
        $test1->SetBuildTest($buildTestOne);

        // Create a test that has failed but does not have a label we're searching for
        $labelForTwo = new Label();
        $labelForTwo->Text = 'Two';
        $test2 = new Test();
        $test2->Name = 'TestTwo';
        $buildTestTwo = new BuildTest();
        $buildTestTwo->Status = Test::FAILED;
        $test2->AddLabel($labelForTwo);
        $test2->SetBuildTest($buildTestTwo);

        // Create a test that is not run and has a label that we're searching for
        $labelFor3 = new Label();
        $labelFor3->Text = 'Three';
        $test3 = new Test();
        $test3->Name = 'TestThree';
        $buildTestThree = new BuildTest();
        $buildTestThree->Status = Test::NOTRUN;
        $test3->AddLabel($labelFor3);
        $test3->SetBuildTest($buildTestThree);

        $build
            ->AddTest($test1)
            ->AddTest($test2)
            ->AddTest($test3);

        $lbl1 = new Label();
        $lbl1->Text = 'One';
        $lbl2 = new Label();
        $lbl2->Text = 'Nope';
        $lbl3 = new Label();
        $lbl3->Text = 'Three';

        $lblCollection = new LabelCollection();
        $lblCollection
            ->add($lbl1)
            ->add($lbl2)
            ->add($lbl3);

        $sut->setTopicDataWithLabels($build, $lblCollection);

        $this->assertEquals(1, $collection->count());
        $this->assertTrue($collection->has($test3->Name));
    }

    public function testGetLabelsFromBuild()
    {
        $sut = new TestFailureTopic();
        $build = new Build();

        // Create a test that has a label we're searching for but has passed, does not get added
        $labelForOne = new Label();
        $labelForOne->Text = 'One';
        $test1 = new Test();
        $test1->Name = 'TestOne';
        $buildTestOne = new BuildTest();
        $buildTestOne->Status = Test::PASSED;
        $test1->AddLabel($labelForOne);
        $test1->SetBuildTest($buildTestOne);

        // Create a test that has failed but does not have a label we're searching for
        $labelForTwo = new Label();
        $labelForTwo->Text = 'Two';
        $test2 = new Test();
        $test2->Name = 'TestTwo';
        $buildTestTwo = new BuildTest();
        $buildTestTwo->Status = Test::FAILED;
        $test2->AddLabel($labelForTwo);
        $test2->SetBuildTest($buildTestTwo);

        // Create a test that is not run and has a label that we're searching for
        $labelFor3 = new Label();
        $labelFor3->Text = 'Three';
        $test3 = new Test();
        $test3->Name = 'TestThree';
        $buildTestThree = new BuildTest();
        $buildTestThree->Status = Test::NOTRUN;
        $test3->AddLabel($labelFor3);
        $test3->SetBuildTest($buildTestThree);

        $collection = $sut->getLabelsFromBuild($build);
        $this->assertEquals(0, $collection->count());

        $build
            ->AddTest($test1)
            ->AddTest($test2)
            ->AddTest($test3);

        $collection = $sut->getLabelsFromBuild($build);

        $this->assertTrue($collection->has($labelForTwo->Text));
        $this->assertTrue($collection->has($labelFor3->Text));
    }
}
