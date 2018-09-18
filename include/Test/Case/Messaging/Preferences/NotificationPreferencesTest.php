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

use CDash\Messaging\Notification\NotifyOn;
use CDash\Messaging\Preferences\NotificationPreferences;

class NotificationPreferencesTest extends PHPUnit_Framework_TestCase
{
    public function testGetPropertyNames()
    {
        $property = new ReflectionProperty(NotificationPreferences::class, 'properties');
        $property->setAccessible(true);

        /** @var NotificationPreferences $sut */
        $sut = $this->getMockForAbstractClass(NotificationPreferences::class);

        $expected = $property->getValue($sut);
        $actual = $sut->getPropertyNames();

        $this->assertEquals($expected, $actual);
    }

    public function testHas()
    {
        /** @var NotificationPreferences $sut */
        $sut = $this->getMockForAbstractClass(NotificationPreferences::class);

        $this->assertFalse($sut->has(NotifyOn::class));
        $this->assertTrue($sut->has(NotifyOn::FILTERED));
        $this->assertTrue($sut->has(NotifyOn::UPDATE_ERROR));
        $this->assertTrue($sut->has(NotifyOn::CONFIGURE));
        $this->assertTrue($sut->has(NotifyOn::BUILD_WARNING));
        $this->assertTrue($sut->has(NotifyOn::BUILD_ERROR));
        $this->assertTrue($sut->has(NotifyOn::TEST_FAILURE));
        $this->assertTrue($sut->has(NotifyOn::DYNAMIC_ANALYSIS));
        $this->assertTrue($sut->has(NotifyOn::FIXED));
        $this->assertTrue($sut->has(NotifyOn::SITE_MISSING));
        $this->assertTrue($sut->has(NotifyOn::AUTHORED));
        $this->assertTrue($sut->has(NotifyOn::GROUP_NIGHTLY));
        $this->assertTrue($sut->has(NotifyOn::ANY));
        $this->assertTrue($sut->has(NotifyOn::LABELED));
        $this->assertTrue($sut->has(NotifyOn::NEVER));
        $this->assertTrue($sut->has(NotifyOn::REDUNDANT));
    }

    public function testSetPreferencesFromEmailTypeProperty()
    {
        /** @var NotificationPreferences $sut */
        $sut = $this->getMockForAbstractClass(NotificationPreferences::class);

        $this->assertFalse($sut->get(NotifyOn::AUTHORED));
        $this->assertFalse($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertFalse($sut->get(NotifyOn::ANY));

        $sut->setPreferencesFromEmailTypeProperty(1);
        $this->assertTrue($sut->get(NotifyOn::AUTHORED));
        $this->assertFalse($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertFalse($sut->get(NotifyOn::ANY));

        $sut->setPreferencesFromEmailTypeProperty(3);
        $this->assertFalse($sut->get(NotifyOn::AUTHORED));
        $this->assertFalse($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertTrue($sut->get(NotifyOn::ANY));

        $sut->setPreferencesFromEmailTypeProperty(2);
        $this->assertFalse($sut->get(NotifyOn::AUTHORED));
        $this->assertTrue($sut->get(NotifyOn::GROUP_NIGHTLY));
        $this->assertFalse($sut->get(NotifyOn::ANY));
    }

    public function testNotifyOn()
    {
        /** @var NotificationPreferences $sut */
        $sut = $this->getMockForAbstractClass(NotificationPreferences::class);

        $this->assertFalse($sut->notifyOn('TestFailure'));
        $sut->set(NotifyOn::TEST_FAILURE, true);
        $this->assertTrue($sut->notifyOn('TestFailure'));
        $sut->set(NotifyOn::TEST_FAILURE, false);
        $this->assertFalse($sut->notifyOn('TestFailure'));
    }
}
