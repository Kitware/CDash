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

namespace CDash\Test;

use CDash\Model\Build;
use PHPUnit_Framework_MockObject_MockObject;

trait BuildDiffForTesting
{
    private $diff = [
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

    private $fixed_keys = [
        'builderrorsnegative',
        'buildwarningsnegative',
        'testfailednegative',
        'testnotrunnegative',
    ];

    /**
     * @return array
     */
    protected function getDiff()
    {
        return $this->diff;
    }

    /**
     * @param $key
     * @return array
     */
    protected function createFixed($key)
    {
        if (in_array($key, $this->fixed_keys)) {
            return array_merge($this->diff, ["{$key}" => 1]);
        }
    }

    /**
     * @param $key
     * @return array
     */
    protected function createNew($key)
    {
        if (!in_array($key, $this->fixed_keys)) {
            return array_merge($this->diff, ["{$key}" => 1]);
        }
    }

    /**
     * @param $diff
     * @return Build|PHPUnit_Framework_MockObject_MockObject
     */
    protected function createMockBuildWithDiff($diff)
    {
        /** @var Build|PHPUnit_Framework_MockObject_MockObject $build */
        $build = $this->getMockBuilder(Build::class)
            ->setMethods(['GetErrorDifferences', 'GetPreviousBuildId'])
            ->getMock();

        $build->expects($this->any())
            ->method('GetErrorDifferences')
            ->willReturn($diff);

        $build->expects($this->any())
            ->method('GetPreviousBuildId')
            ->willReturn(1);
        $build->Id = 2;

        return $build;
    }
}
