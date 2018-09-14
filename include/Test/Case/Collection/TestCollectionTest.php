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
use CDash\Model\Test;

class TestCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $test = new Test();
        $test->Name = 'Barney';

        $sut = new TestCollection();
        $this->assertSame($sut, $sut->add($test));
        $this->assertSame($test, $sut->get('Barney'));
    }
}
