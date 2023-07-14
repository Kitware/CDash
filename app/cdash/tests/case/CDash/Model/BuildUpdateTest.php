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

use CDash\Model\BuildUpdate;
use Tests\TestCase;

class BuildUpdateTest extends TestCase
{
    public function testGetUrlForSelf()
    {
        $base_url = config('app.url');
        config(['app.url' => 'https://cdash.tld/']);

        $sut = new BuildUpdate();
        $sut->BuildId = 1001;
        $expected = 'https://cdash.tld/build/1001/update';
        $actual = $sut->GetUrlForSelf();
        $this->assertEquals($expected, $actual);

        config(['app.url' => $base_url]);
    }
}
