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

use CDash\Messaging\Notification\Email\EmailBuilder;
use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Notification\Email\EmailNotificationFactory;
use CDash\Messaging\Subscription\Subscription;

class EmailBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testSetBuildEmailCollection()
    {
    }

    public function testCreateNotification()
    {
        $factory = new EmailNotificationFactory();
        $sut = new EmailBuilder($factory);
        $subscription = new Subscription();
        $templateName = 'some.template';

        // $notification = $sut->createNotification($subscription, $templateName);
        // $this->assertInstanceOf(EmailMessage::class, $notification);
    }

    public function test__construct()
    {
    }

    public function testGetNotifications()
    {
    }
}
