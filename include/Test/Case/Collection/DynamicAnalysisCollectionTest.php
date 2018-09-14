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

use CDash\Collection\DynamicAnalysisCollection;
use CDash\Model\DynamicAnalysis;

class DynamicAnalysisCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $key = 'Barney';
        $analysis = new DynamicAnalysis();
        $analysis->Name = $key;

        $sut = new DynamicAnalysisCollection();

        $this->assertNull($sut->get($key));

        $this->assertSame($sut, $sut->add($analysis));
        $this->assertSame($analysis, $sut->get($key));
    }
}
