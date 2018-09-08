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

use CDash\Collection\BuildFailureCollection;
use CDash\Messaging\Topic\BuildFailureTopic;
use CDash\Model\Build;
use CDash\Model\BuildFailure;

class BuildFailureTopicTest extends \CDash\Test\CDashTestCase
{
    public function testItemHasTopic()
    {
        $sut = new BuildFailureTopic();
        $build = new Build();

        $error = new BuildFailure();
        $warning = new BuildFailure();
        $error->Type = Build::TYPE_ERROR;
        $warning->Type = Build::TYPE_WARN;

        $sut->setType(Build::TYPE_ERROR);
        $this->assertFalse($sut->itemHasTopicSubject($build, $warning));
        $this->assertTrue($sut->itemHasTopicSubject($build, $error));

        $sut->setType(Build::TYPE_WARN);
        $this->assertTrue($sut->itemHasTopicSubject($build, $warning));
        $this->assertFalse($sut->itemHasTopicSubject($build, $error));
    }

    public function testGetTopicCollection()
    {
        $sut = new BuildFailureTopic();
        $collection = $sut->getTopicCollection();
        $this->assertInstanceOf(BuildFailureCollection::class, $collection);
    }

    public function testGetTopicName()
    {
        $sut = new BuildFailureTopic();
        $sut->setType(Build::TYPE_ERROR);
        $expected = 'BuildFailureError';
        $actual = $sut->getTopicName();
        $this->assertEquals($expected, $actual);

        $sut->setType(Build::TYPE_WARN);
        $expected = 'BuildFailureWarning';
        $actual = $sut->getTopicName();
        $this->assertEquals($expected, $actual);
    }

    public function testGetTopicDescription()
    {
        $sut = new BuildFailureTopic();
        $sut->setType(Build::TYPE_ERROR);
        $expected = 'Errors';
        $actual = $sut->getTopicDescription();
        $this->assertEquals($expected, $actual);

        $sut->setType(Build::TYPE_WARN);
        $expected = 'Warnings';
        $actual = $sut->getTopicDescription();
        $this->assertEquals($expected, $actual);
    }

    public function testSubscribesToBuild()
    {
        $sut = new BuildFailureTopic();
        $sut->setType(Build::TYPE_ERROR);
        $build = new Build();

        $this->assertFalse($sut->subscribesToBuild($build));

        $failures = new ReflectionProperty(Build::class, 'Failures');
        $failures->setAccessible(true);
        $failures->setValue($build, []);

        $this->assertFalse($sut->subscribesToBuild($build));

        $warning = new BuildFailure();
        $warning->Type = Build::TYPE_WARN;

        $failures->setValue($build, [$warning]);

        $this->assertFalse($sut->subscribesToBuild($build));

        $sut->setType(Build::TYPE_WARN);

        $this->assertTrue($sut->subscribesToBuild($build));

        $error = new BuildFailure();
        $error->Type = Build::TYPE_ERROR;
        $sut->setType(Build::TYPE_ERROR);
        $failures->setValue($build, [$error]);

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testSetTopicData()
    {
        $build = new Build();
        $sut = new BuildFailureTopic();
        $sut->setType(Build::TYPE_ERROR);
        $failures = new ReflectionProperty(Build::class, 'Failures');
        $failures->setAccessible(true);

        $error = new BuildFailure();
        $error->Type = Build::TYPE_ERROR;

        $warning = new BuildFailure();
        $warning->Type = Build::TYPE_WARN;

        $failures->setValue($build, [$warning, $error]);
        $sut->setTopicData($build);

        $collection = $sut->getTopicCollection();
        $this->assertCount(1, $collection);
        $this->assertSame($error, $collection->current());

        $sut = new BuildFailureTopic();
        $sut->setType(Build::TYPE_WARN);
        $sut->setTopicData($build);

        $collection = $sut->getTopicCollection();
        $this->assertCount(1, $collection);
        $this->assertSame($warning, $collection->current());
    }
}
