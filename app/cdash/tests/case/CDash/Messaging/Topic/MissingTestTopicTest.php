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

use CDash\Collection\TestCollection;
use CDash\Messaging\Topic\MissingTestTopic;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use Tests\TestCase;

class MissingTestTopicTest extends TestCase
{
    public function testSubscribesToBuild()
    {
        $sut = new MissingTestTopic();

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build1 */
        $build1 = $this->getMockBuilder(Build::class)
            ->setMethods(['GetMissingTests'])
            ->getMock();

        $build1->expects($this->once())
            ->method('GetMissingTests')
            ->willReturn([]);
        $build1->Id = 1;

        $this->assertFalse($sut->subscribesToBuild($build1));

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build2 */
        $build2 = $this->getMockBuilder(Build::class)
            ->setMethods(['GetMissingTests'])
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
        $this->assertInstanceOf(TestCollection::class, $collection);
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

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build2 */
        $build2 = $this->getMockBuilder(Build::class)
            ->setMethods(['GetMissingTests'])
            ->getMock();

        $build2->expects($this->any())
            ->method('GetMissingTests')
            ->willReturn(['101' => 'TestA', '303' => 'TestC']);
        $build2->Id = '2';

        $this->assertTrue($sut->subscribesToBuild($build2));

        $sut->setTopicData($build2);

        $collection = $sut->getTopicCollection();

        $this->assertTrue($collection->has('TestA'));
        $this->assertTrue($collection->has('TestC'));

        /** @var \CDash\Model\Test $a */
        $a = $collection->get('TestA');
        $c = $collection->get('TestC');

        $this->assertEquals('101', $a->test->id);
        $this->assertEquals('TestA', $a->test->name);
        $this->assertEquals('2', $a->buildid);

        $this->assertEquals('303', $c->test->id);
        $this->assertEquals('TestC', $c->test->name);
        $this->assertEquals('2', $c->buildid);

        $this->assertEquals(2, $sut->getTopicCount());
    }
}
