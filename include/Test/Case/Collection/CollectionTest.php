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

/**
 * Created by PhpStorm.
 * User: bryonbean
 * Date: 8/26/18
 * Time: 2:08 PM
 */

use CDash\Collection\Collection;

class CollectionTest extends PHPUnit_Framework_TestCase
{
    public function testAddWithEmptyKeyDoesNotResultInEndlessLoop()
    {
        $stdObj = new stdClass;
        $stdObj->property = true;

        $sut = $this->getMockBuilder(Collection::class)
            ->setMethods(['addItem'])
            ->setConstructorArgs([['' => $stdObj]])
            ->getMockForAbstractClass();

        $count = 0;

        // Collections with set with an array containing a key equal to an empty string
        // were getting stuck in an endless loop, this test ensures that bug is fixed
        foreach ($sut as $key => $value) {
            $count++;
            if ($count > 1) {
                break;
            }
        }

        $this->assertEquals(1, $count);
    }
}
