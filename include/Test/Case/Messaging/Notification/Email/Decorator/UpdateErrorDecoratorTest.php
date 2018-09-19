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
use CDash\Messaging\Notification\Email\Decorator\UpdateErrorDecorator;
use CDash\Messaging\Topic\UpdateErrorTopic;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;

class UpdateErrorDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testSetTopic()
    {
        $config = Config::getInstance();
        $config->set('CDASH_BASE_URL', 'https://cdash.tld/');
        $sut = new UpdateErrorDecorator();
        $topic = new UpdateErrorTopic();
        $build = new Build();
        $update = new BuildUpdate();

        $build->SetBuildUpdate($update);
        $update->BuildId = 101;
        $update->Status = 1;
        $topic->addBuild($build);

        $expected = "*Update Errors*\nStatus: 1 (https://cdash.tld/viewUpdate.php?buildid=101)\n";
        $actual = $sut->setTopic($topic);

        $this->assertEquals($expected, $actual);
    }
}
