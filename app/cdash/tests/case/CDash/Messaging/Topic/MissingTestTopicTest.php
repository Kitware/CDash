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

use CDash\Messaging\Topic\MissingTestTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class MissingTestTopicTest extends TestCase
{
    public function testSubscribesToBuild()
    {
        $sut = new MissingTestTopic();

        /** @var Build|MockObject $build1 */
        $build1 = $this->getMockBuilder(Build::class)
            ->onlyMethods(['GetMissingTests'])
            ->getMock();

        $build1->expects($this->once())
            ->method('GetMissingTests')
            ->willReturn([]);
        $build1->Id = 1;

        $this->assertFalse($sut->subscribesToBuild($build1));

        /** @var Build|MockObject $build2 */
        $build2 = $this->getMockBuilder(Build::class)
            ->onlyMethods(['GetMissingTests'])
            ->getMock();

        $build2->expects($this->once())
            ->method('GetMissingTests')
            ->willReturn(['101' => 'TestA', '303' => 'TestC']);
        $build2->Id = 2;

        $this->assertTrue($sut->subscribesToBuild($build2));
    }

    public function testGetTopicCollection()
    {
        $sut = new MissingTestTopic();

        $collection = $sut->getTopicCollection();
        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testGetTopicDescription()
    {
        $sut = new MissingTestTopic();

        $expected = 'Missing Tests';
        $actual = $sut->getTopicDescription();

        $this->assertEquals($expected, $actual);
    }

    public function testGetTopicName()
    {
        $sut = new MissingTestTopic();

        $expected = Topic::TEST_MISSING;
        $actual = $sut->getTopicName();

        $this->assertEquals($expected, $actual);
    }

    public function testSetTopicData()
    {
        $sut = new MissingTestTopic();

        $this->assertEquals(0, $sut->getTopicCount());

        /** @var Build|MockObject $build2 */
        $build2 = $this->getMockBuilder(Build::class)
            ->onlyMethods(['GetMissingTests'])
            ->getMock();

        $build2->expects($this->any())
            ->method('GetMissingTests')
            ->willReturn(['TestA', 'TestC']);
        $build2->Id = '2';

        $this->assertTrue($sut->subscribesToBuild($build2));

        $sut->setTopicData($build2);

        $collection = $sut->getTopicCollection();

        $this->assertTrue($collection->has('TestA'));
        $this->assertTrue($collection->has('TestC'));

        $a = $collection->get('TestA');
        $c = $collection->get('TestC');

        $this->assertEquals('TestA', $a->testname);
        $this->assertEquals('2', $a->buildid);

        $this->assertEquals('TestC', $c->testname);
        $this->assertEquals('2', $c->buildid);

        $this->assertEquals(2, $sut->getTopicCount());
    }
}
