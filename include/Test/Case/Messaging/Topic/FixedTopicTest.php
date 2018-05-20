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
    public function testSubscribesToBuild()
    {
        $sut = new FixedTopic();

        $diff = [
            'builderrorpositive' => 0,
            'buildwarningpositive' => 0,
            'builderrorsnegative' => 0,
            'buildwarningsnegative' => 0,
            'configureerrors' => 0,
            'configurewarnings' => 0,
            'testpassedpositive' => 0,
            'testpassednegative' => 0,
            'testfailedpositive' => 0,
            'testfailednegative' => 0,
            'testnotrunpositive' => 0,
            'testnotrunnegative' => 0,
        ];

        $key = 'builderrorsnegative';
        $value = 0;

        // We need to call Build::GetErrorDifferences, which makes a call to the database
        // so, for this test, let's create a mock Build object and stub its GetErrorDifferences

        /** @var Build|PHPUnit_Framework_MockObject_MockObject $mock_build */
        $mock_build = $this->getMockBuilder(Build::class)
            ->disableOriginalConstructor()
            ->setMethods(['GetErrorDifferences'])
            ->getMock();

        // This warrants some explanation, we want to check that certain values returned by
        // Build::GetErrorDifferences will cause our SUT's method to return true. We also
        // want to ensure that we start with a pristine state where all of our diff's amount
        // to zero. Then, after setting our property to it's value check again that the sum
        // of the contents of our array is equal to our $value argument. Notice that the $diff
        // argument, unlike,  &$key and &$value is not being passed by reference; this is because
        // we want $diff to remain unchanged throughout our consecutive calls.
        $mock_build
            ->expects($this->any())
            ->method('GetErrorDifferences')
            ->willReturnCallback(function () use ($diff, &$key, &$value) {
                $this->assertEquals(0, array_sum($diff));
                $diff[$key] = $value;
                $this->assertEquals($value, array_sum($diff));
                return array_merge([], $diff);
            });

        $this->assertFalse($sut->subscribesToBuild($mock_build));

        // Set the 'builderrorsnegative' so that it's value is 1 and all other values are 0
        $key = 'builderrorsnegative';
        $value = 1;
        $this->assertTrue($sut->subscribesToBuild($mock_build));

        // Set the 'testfailednegative' so that it's value is 1 and all other values are 0
        $key = 'testfailednegative';
        $this->assertTrue($sut->subscribesToBuild($mock_build));

        // Set the 'testnotrunnegative' so that it's value is 1 and all other values are 0
        $key = 'testnotrunnegative';
        $this->assertTrue($sut->subscribesToBuild($mock_build));

        // Set the 'builderrorsnegative' so that it's value is 1 and all other values are 0
        $key = 'builderrorsnegative';
        $this->assertTrue($sut->subscribesToBuild($mock_build));

        // Set the 'configurewarnings' so that it's value is -1 and all other values are 0
        $key = 'configurewarnings';
        $value = -1;
        $this->assertTrue($sut->subscribesToBuild($mock_build));

        // Set the 'configureerrors' so that it's value is -1 and all other values are 0
        $key = 'configureerrors';
        $this->assertTrue($sut->subscribesToBuild($mock_build));
    }
}
