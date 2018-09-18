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

use CDash\Messaging\Topic\FixedTopic;
use CDash\Model\Build;

class FixedTopicTest extends \CDash\Test\CDashTestCase
{
    private $diff;

    public function setUp()
    {
        parent::setUp();
        $this->diff = [
            'builderrorspositive'   => 0,
            'builderrorsnegative'   => 0,
            'buildwarningspositive' => 0,
            'buildwarningsnegative' => 0,
            'configureerrors'       => 0,
            'configurewarnings'     => 0,
            'testpassedpositive'    => 0,
            'testpassednegative'    => 0,
            'testfailedpositive'    => 0,
            'testfailednegative'    => 0,
            'testnotrunpositive'    => 0,
            'testnotrunnegative'    => 0,
        ];
    }

    public function testSubscribesToBuildGivenNoFixes()
    {
        $sut = new FixedTopic();
        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences'])
            ->getMock();

        $build->expects($this->exactly(1))
            ->method('GetErrorDifferences')
            ->willReturn($this->diff);
        $build->Id = 1;

        $this->assertFalse($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenBuildErrorFix()
    {
        $sut = new FixedTopic();
        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences'])
            ->getMock();

        $build->expects($this->exactly(1))
            ->method('GetErrorDifferences')
            ->willReturn($this->createFixed('builderrorsnegative'));
        $build->Id = 1;

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenBuildWarningFix()
    {
        $sut = new FixedTopic();
        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences'])
            ->getMock();

        $build->expects($this->exactly(1))
            ->method('GetErrorDifferences')
            ->willReturn($this->createFixed('buildwarningsnegative'));
        $build->Id = 1;

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenTestFailureFix()
    {
        $sut = new FixedTopic();
        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences'])
            ->getMock();

        $build->expects($this->exactly(1))
            ->method('GetErrorDifferences')
            ->willReturn($this->createFixed('testfailednegative'));
        $build->Id = 1;

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function testSubscribesToBuildGivenTestNotRunFix()
    {
        $sut = new FixedTopic();
        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences'])
            ->getMock();

        $build->expects($this->exactly(1))
            ->method('GetErrorDifferences')
            ->willReturn($this->createFixed('testnotrunnegative'));
        $build->Id = 1;

        $this->assertTrue($sut->subscribesToBuild($build));
    }

    public function createFixed($key)
    {
        $fixed = array_merge($this->diff, ["{$key}" => 1]);
        return $fixed;
    }
}
