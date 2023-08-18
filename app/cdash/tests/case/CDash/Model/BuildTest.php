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

use CDash\Collection\BuildEmailCollection;
use CDash\Model\Build;
use CDash\Test\CDashTestCase;

class BuildTest extends CDashTestCase
{
    public function setUp(): void
    {
        $this->setDatabaseMocked();
    }

    public function testGetDiffWithPreviousBuild()
    {
        $sut = new Build();

        $collection = $sut->GetBuildEmailCollection();

        $this->assertFalse($collection->hasItems());
        $this->assertInstanceOf(BuildEmailCollection::class, $collection);
    }

    public function testGetBuildEmailCollection()
    {
        // This is a bad idea, don't do this
        // TODO: refactor asap
        /** @var Build|PHPUnit_Framework_MockObject_MockObject $sut */
        $sut = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences', 'GetPreviousBuildId'])
            ->getMock();
        $sut->Id = 1;
        $sut->expects($this->once())
            ->method('GetPreviousBuildId')
            ->willReturn(12);

        $sut->expects($this->once())
            ->method('GetErrorDifferences')
            ->willReturn([
                'builderrorspositive' => 10,
                'builderrorsnegative' => 20,
                'buildwarningspositive' => 30,
                'buildwarningsnegative' => 40,
                'configureerrors' => 50,
                'configurewarnings' => 60,
                'testpassedpositive' => 70,
                'testpassednegative' => 80,
                'testfailedpositive' => 90,
                'testfailednegative' => 10,
                'testnotrunpositive' => 11,
                'testnotrunnegative' => 12,

            ]);

        $diff = $sut->GetDiffWithPreviousBuild();
        $this->assertEquals(10, $diff['BuildError']['new']);
        $this->assertEquals(20, $diff['BuildError']['fixed']);
        $this->assertEquals(30, $diff['BuildWarning']['new']);
        $this->assertEquals(40, $diff['BuildWarning']['fixed']);
        $this->assertEquals(50, $diff['Configure']['errors']);
        $this->assertEquals(60, $diff['Configure']['warnings']);
        $this->assertEquals(70, $diff['TestFailure']['passed']['new']);
        $this->assertEquals(80, $diff['TestFailure']['passed']['broken']);
        $this->assertEquals(90, $diff['TestFailure']['failed']['new']);
        $this->assertEquals(10, $diff['TestFailure']['failed']['fixed']);
        $this->assertEquals(11, $diff['TestFailure']['notrun']['new']);
        $this->assertEquals(12, $diff['TestFailure']['notrun']['fixed']);
    }
}
