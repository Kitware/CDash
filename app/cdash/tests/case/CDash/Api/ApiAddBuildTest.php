<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$
  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.
  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

use CDash\Config;
use CDash\Model\Build;
use CDash\Model\Project;
use CDash\Model\Site;
use CDash\Model\User;
use CDash\ServiceContainer;
use CDash\System;

/**
 * @runTestsInSeparateProcesses
 */
class ApiAddBuildTest extends CDash\Test\CDashApiTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->setEndpoint('addBuild');
        $container = ServiceContainer::container();

        $this->mock_system = $this->getMockBuilder(System::class)
            ->disableOriginalConstructor()
            ->setMethods(['system_exit'])
            ->getMock();
        $container->set(System::class, $this->mock_system);

        $this->mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'Exists',
                'GetIdByName',
                'Fill',
            ])
            ->getMock();
        $this->mock_project->Name = 'MockProject';
        $this->mock_project->Id = 1;
        $this->mock_project->Public = 1;
        $container->set(Project::class, $this->mock_project);

        $this->mock_site = $this->createMockFromBuilder(Site::class);
        $this->mock_site->Name = 'MockSite';
        $this->mock_site->Id = 1;
        $container->set(Site::class, $this->mock_site);

        $this->mock_build = $this->createMockFromBuilder(Build::class);
        $this->mock_build->Name = 'MockBuild';
        $this->mock_build->Id = 1;
        $container->set(Build::class, $this->mock_build);

        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    public function testAddBuildWithoutProject()
    {
        $this->mock_system
             ->expects($this->once())
             ->method('system_exit')
             ->willThrowException(new Exception('Exit!'));
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = 'Valid project required';
        $this->assertEquals($expected, $actual);
    }

    public function testAddBuildWithoutSite()
    {
        $this->mock_system
             ->expects($this->any())
             ->method('system_exit')
             ->willThrowException(new Exception('Exit!'));
        $this->mock_project
            ->expects($this->once())
            ->method('GetIdByName')
            ->willReturn(1);
        $this->mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);

        // Why not POST?
        $_GET['project'] = 'MockProject';
        $actual = $this->getEndpointResponse();

        $expected = new stdClass();
        $expected->error = 'Valid site required';
        $this->assertEquals($expected, $actual);
    }

    public function testAddBuildWithoutName()
    {
        $this->mock_system
             ->expects($this->any())
             ->method('system_exit')
             ->willThrowException(new Exception('Exit!'));
        $this->mock_project
            ->expects($this->once())
            ->method('GetIdByName')
            ->willReturn(1);
        $this->mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);
        $this->mock_site
            ->expects($this->once())
            ->method('Insert')
            ->willReturn(true);

        $_GET['project'] = 'MockProject';
        $_GET['site'] = 'MockSite';
        $actual = $this->getEndpointResponse();

        $expected = new stdClass();
        $expected->error = 'Valid name required';
        $this->assertEquals($expected, $actual);
    }

    public function testAddBuildWithoutStamp()
    {
        $this->mock_system
             ->expects($this->any())
             ->method('system_exit')
             ->willThrowException(new Exception('Exit!'));
        $this->mock_project
            ->expects($this->once())
            ->method('GetIdByName')
            ->willReturn(1);
        $this->mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);
        $this->mock_site
            ->expects($this->once())
            ->method('Insert')
            ->willReturn(true);

        $_GET['project'] = 'MockProject';
        $_GET['site'] = 'MockSite';
        $_GET['name'] = 'MockBuild';
        $actual = $this->getEndpointResponse();

        $expected = new stdClass();
        $expected->error = 'Valid stamp required';
        $this->assertEquals($expected, $actual);
    }

    public function testAddBuildWithAllRequiredParams()
    {
        $this->mock_system
             ->expects($this->any())
             ->method('system_exit')
             ->willThrowException(new Exception('Exit!'));
        $this->mock_project
            ->expects($this->once())
            ->method('GetIdByName')
            ->willReturn(1);
        $this->mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);
        $this->mock_site
            ->expects($this->once())
            ->method('Insert')
            ->willReturn(true);

        $_GET['project'] = 'MockProject';
        $_GET['site'] = 'MockSite';
        $_GET['name'] = 'MockBuild';
        $_GET['stamp'] = '20180705-0100-Experimental';
        $actual = $this->getEndpointResponse();

        $expected = new stdClass();
        $expected->buildid = 1;
        $this->assertEquals($expected, $actual);
    }
}
