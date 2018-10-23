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

use CDash\Collection\SubscriptionBuilderCollection;
use CDash\Messaging\Subscription\SubscriptionBuilderInterface;

class SubscriptionBuilderCollectionTest extends PHPUnit_Framework_TestCase
{
    public function testAdd()
    {
        $sut = new SubscriptionBuilderCollection();
        /** @var SubscriptionBuilderInterface $mock_builder */
        $mock_builder = $this->getMockBuilder(SubscriptionBuilderInterface::class)
            ->getMockForAbstractClass();

        $key = get_class($mock_builder);
        $this->assertCount(0, $sut);
        $this->assertFalse($sut->has($key));

        $this->assertSame($sut, $sut->add($mock_builder));

        $this->assertCount(1, $sut);
        $this->assertTrue($sut->has($key));
    }
}
