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

use CDash\Config;
use CDash\Messaging\Messenger;
use CDash\Test\CDashTestCase;

class MessengerTest extends CDashTestCase
{
    public function testSend()
    {
        /** @var AbstractHandler|PHPUnit_Framework_MockObject_MockObject $parser */
        $parser = $this->getMockBuilder(AbstractHandler::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $class = get_class($parser);
        $config = Config::getInstance();
        $config->set(
            "notifications.{$class}",
            [
                'CDash\\Messaging\\Subscription\\RepositorySubscriptionBuilder',
                'CDash\\Messaging\\Subscription\\CommitAuthorSubscriptionBuilder',
                'CDash\\Messaging\\Subscription\\UserSubscriptionBuilder',
            ]
        );

        $sut = new Messenger();
        $sut->send($parser);
    }
}
