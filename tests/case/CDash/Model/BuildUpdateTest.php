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

class BuildUpdateTest extends PHPUnit_Framework_TestCase
{
    public function testGetUrlForSelf()
    {
        $config = \CDash\Config::getInstance();
        $config->set('CDASH_BASE_URL', 'https://cdash.tld/');
        $sut = new BuildUpdate();
        $sut->BuildId = 1001;

        $expected = 'https://cdash.tld/viewUpdate.php?buildid=1001';
        $actual = $sut->GetUrlForSelf();

        $this->assertEquals($expected, $actual);
    }
}
