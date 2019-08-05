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
use CDash\Model\BuildRelationship;
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
        $this->setEndpoint('relateBuilds');
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

        $this->mock_build = $this->createMockFromBuilder(Build::class);
        $this->mock_build->Name = 'MockBuild';
        $this->mock_build->Id = 1;
        $container->set(Build::class, $this->mock_build);

        $this->another_mock_build = $this->createMockFromBuilder(Build::class);
        $this->another_mock_build->Name = 'AnotherMockBuild';
        $this->another_mock_build->Id = 2;
        $container->set(Build::class, $this->another_mock_build);

        $this->mock_relationship = $this->createMockFromBuilder(BuildRelationship::class);
        $container->set(BuildRelationship::class, $this->mock_relationship);

        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    public function testRelateBuildsWithoutProject()
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

    public function testRelateBuildsWithoutBuildId()
    {
        $this->mock_system
             ->expects($this->once())
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

        $_GET['project'] = 'MockProject';
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = 'Valid buildid required';
        $this->assertEquals($expected, $actual);
    }

    public function testRelateBuildsWithoutRelatedId()
    {
        $this->mock_system
             ->expects($this->once())
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

        $_GET['project'] = 'MockProject';
        $_GET['buildid'] = 1;
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = 'Valid relatedid required';
        $this->assertEquals($expected, $actual);
    }

    public function testRelateBuildsWithoutRelationship()
    {
        $this->mock_system
             ->expects($this->once())
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

        $_GET['project'] = 'MockProject';
        $_GET['buildid'] = 1;
        $_GET['relatedid'] = 2;
        $actual = $this->getEndpointResponse();
        $expected = new stdClass();
        $expected->error = 'Valid relationship required';
        $this->assertEquals($expected, $actual);
    }

    public function testRelateBuildsSuccessfulPost()
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
        $this->mock_build
            ->expects($this->any())
            ->method('Exists')
            ->willReturn(true);
        $this->another_mock_build
            ->expects($this->any())
            ->method('Exists')
            ->willReturn(true);
        $this->mock_relationship
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(false);
        $this->mock_relationship
            ->expects($this->once())
            ->method('Save')
            ->willReturn(true);
        $expected = new stdClass();
        $expected->buildid = 1;
        $expected->relatedid = 2;
        $expected->relationship = 'depends on';
        $this->mock_relationship
            ->expects($this->once())
            ->method('marshal')
            ->willReturn($expected);

        $_GET['project'] = 'MockProject';
        $_GET['buildid'] = 1;
        $_GET['relatedid'] = 2;
        $_GET['relationship'] = 'depends on';
        $actual = $this->getEndpointResponse();
        $this->assertEquals($expected, $actual);
    }

    public function testRelateBuildsGet404()
    {
        $this->mock_system
             ->expects($this->once())
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
        $this->mock_relationship
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(false);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_REQUEST['project'] = 'MockProject';
        $_REQUEST['buildid'] = '1';
        $_REQUEST['relatedid'] = '2';

        $expected = new stdClass();
        $expected->error = 'No relationship exists between Builds 1 and 2';
        $actual = $this->getEndpointResponse();
        $this->assertEquals($expected, $actual);
    }

    public function testRelateBuildsGetSuccess()
    {
        $this->mock_system
             ->expects($this->once())
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
        $this->mock_build
            ->expects($this->any())
            ->method('Exists')
            ->willReturn(true);
        $this->another_mock_build
            ->expects($this->any())
            ->method('Exists')
            ->willReturn(true);
        $this->mock_relationship
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);
        $expected = new stdClass();
        $expected->buildid = 1;
        $expected->relatedid = 2;
        $expected->relationship = 'depends on';
        $this->mock_relationship
            ->expects($this->once())
            ->method('marshal')
            ->willReturn($expected);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_REQUEST['project'] = 'MockProject';
        $_REQUEST['buildid'] = '1';
        $_REQUEST['relatedid'] = '2';

        $actual = $this->getEndpointResponse();
        $this->assertEquals($expected, $actual);
    }

    public function testRelateBuildsDeleteUnauthorized()
    {
        $this->mock_system
             ->expects($this->once())
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
        $mock_user = $this->getSessionUser();
        $mock_user
            ->expects($this->any())
            ->method('IsAdmin')
            ->willReturn(false);

        $expected = new stdClass();
        $expected->error = 'You do not have permission to access this page.';

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_GET['project'] = 'MockProject';
        $_GET['buildid'] = '1';
        $_GET['relatedid'] = '2';

        $actual = $this->getEndpointResponse();
        $this->assertEquals($expected, $actual);
    }

    public function testRelateBuildsDeleteSuccess()
    {
        $this->mock_system
             ->expects($this->once())
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
        $mock_user = $this->getSessionUser();
        $mock_user
            ->expects($this->any())
            ->method('IsAdmin')
            ->willReturn(true);
        $this->mock_relationship
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);
        $this->mock_relationship
            ->expects($this->once())
            ->method('Delete')
            ->willReturn(true);

        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_GET['project'] = 'MockProject';
        $_GET['buildid'] = '1';
        $_GET['relatedid'] = '2';

        $actual = $this->getEndpointResponse();
        $this->assertEquals('', $actual);
    }
}
