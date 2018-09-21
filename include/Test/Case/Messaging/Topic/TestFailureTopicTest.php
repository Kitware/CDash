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

use CDash\Messaging\Topic\TestFailureTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\BuildTest;
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

        $passed = new BuildTest();
        $passed->Status = Test::PASSED;

        $failed = new BuildTest();
        $failed->Status = Test::FAILED;

        $notrun = new BuildTest();
        $notrun->Status = Test::NOTRUN;

        $test1->SetBuildTest($passed);
        $test2->SetBuildTest($failed);
        $test3->SetBuildTest($notrun);

        $build
            ->AddTest($test1)
            ->AddTest($test2)
            ->AddTest($test3);

        $sut->setTopicData($build);

        $collection = $sut->getTopicCollection();
        $this->assertCount(1, $collection);
        $this->assertSame($test2, $collection->current());
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

    public function testHasFixed()
    {
        $sut = new TestFailureTopic();
        $build = $this->createMockBuildWithDiff($this->getDiff());
        $this->assertFalse($sut->subscribesToBuild($build));
        $this->assertFalse($sut->hasFixes());

        $key = 'testfailednegative';
        $diff = $this->createFixed($key);
        $this->assertEquals(1, $diff[$key]);
        $build = $this->createMockBuildWithDiff($diff);
        $this->assertFalse($sut->subscribesToBuild($build));
        $this->assertTrue($sut->hasFixes());

        $key = 'testnotrunnegative';
        $diff = $this->createFixed($key);
        $this->assertEquals(1, $diff[$key]);
        $build = $this->createMockBuildWithDiff($diff);
        $this->assertFalse($sut->subscribesToBuild($build));
        $this->assertTrue($sut->hasFixes());
    }
}
