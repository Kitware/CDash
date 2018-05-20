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

use CDash\Collection\BuildCollection;
use CDash\Model\Build;
use CDash\Test\CDashTestCase;

class BuildCollectionTest extends CDashTestCase
{
    public function testForeachReturnsNextItemInCollection()
    {
        $build1 = new Build();
        $build1->Name = 'BuildC';

        $build2 = new Build();
        $build2->Name = 'BuildB';

        $build3 = new Build();
        $build3->Name = 'BuildA';

        $sut = new BuildCollection();
        $sut->add($build1);
        $sut->add($build2);
        $sut->add($build3);

        $names = ['BuildC', 'BuildB', 'BuildA'];
        $count = 0;
        foreach ($sut as $name => $build) {
            $this->assertEquals($name, $names[$count++]);
        }
    }
}
