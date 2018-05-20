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

use CDash\Collection\BuildErrorCollection;
use CDash\Model\BuildError;

class BuildErrorCollectionTest extends PHPUnit_Framework_TestCase
{

    public function testAdd()
    {
        $sut = new BuildErrorCollection();
        $buildError = new BuildError();
        $sut->add($buildError);

        $this->assertSame($buildError, $sut->current());
    }
}
