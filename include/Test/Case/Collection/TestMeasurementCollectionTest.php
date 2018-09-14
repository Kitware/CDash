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

use CDash\Collection\TestMeasurementCollection;
use CDash\Model\TestMeasurement;

class TestMeasurementCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $unit1 = new TestMeasurement();
        $unit2 = new TestMeasurement();

        $unit1->Name = 'Unit One';
        $unit2->Name = 'Unit  Two';

        $sut = new TestMeasurementCollection();

        $this->assertNull($sut->get('UnitOne'));
        $this->assertNull($sut->get('UnitTwo'));

        $this->assertSame($sut, $sut->add($unit1));
        $this->assertSame($sut, $sut->add($unit2));

        $this->assertSame($unit1, $sut->get('UnitOne'));
        $this->assertSame($unit2, $sut->get('UnitTwo'));
    }
}
