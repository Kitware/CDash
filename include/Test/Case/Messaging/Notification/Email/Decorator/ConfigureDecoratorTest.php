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
 * Date: 9/15/18
 * Time: 3:22 PM
 */

use CDash\Config;
use CDash\Messaging\Notification\Email\Decorator\ConfigureDecorator;
use CDash\Messaging\Topic\ConfigureTopic;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;

class ConfigureDecoratorTest extends PHPUnit_Framework_TestCase
{
    public function testSetTopic()
    {
        $configure= new BuildConfigure();
        $configure->Log = implode(PHP_EOL, [
            'Running CMake to regenerate build system...',
            'CDASH_DIR_NAME = CDash',
            'Using url: http://localhost/CDash',
            'Loading composer repositories with package information',
            'Installing dependencies (including require-dev) from lock file',
            'CMake Warning: cannot install dependecy whatevs...',
            'Warning bada-bing',
            'Error: Missing dependencies',
            'FATAL ERROR: Cannot continue exiting with status 127',
        ]);
        $configure->Status = 2;

        $buildOne = new Build();
        $buildOne->Id = 101;
        $buildOne->SetBuildConfigure($configure);
        $buildOne->SubProjectName = 'BuildOne';

        $buildTwo = new Build();
        $buildTwo->Id = 102;
        $buildTwo->SetBuildConfigure($configure);
        $buildTwo->SubProjectName = 'BuildTwo';

        $buildOne->SetParentId(100);

        $topic = new ConfigureTopic();

        $topic->addBuild($buildOne)
            ->addBuild($buildTwo);

        $sut = (new ConfigureDecorator())
            ->setMaxChars(200);

        $expected = implode(PHP_EOL, [
            '*Configure*',
            "Status: 2 (http://cdash.dev/viewConfigure.php?buildid=100)",
            'Output: Running CMake to regenerate build system...',
            '        CDASH_DIR_NAME = CDash',
            '        Using url: http://localhost/CDash',
            '        Loading composer repositories with package information',
            '        Installing dependencies (including require-d',
            '',
        ]);

        $actual = $sut->setTopic($topic);
        $this->assertEquals($expected, $actual);
    }
}
