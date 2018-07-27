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
use CDash\Model\Project;
use CDash\Model\User;
use CDash\Model\UserProject;
use CDash\ServiceContainer;

class ApiCreateProjectTest extends CDash\Test\CDashApiTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->setEndpoint('createProject');
        $container = ServiceContainer::container();
        $mock_project = $this->getMockProject();
        $container->set(Project::class, $mock_project);
        $container->set(User::class, $this->getSessionUser());
        $_GET['projectid'] = 1;
    }

    public function testCreateProjectWhenIdDoesNotExist()
    {
        $container = ServiceContainer::container();
        $mock_project = $container->get(Project::class);
        $mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(false);

        $_GET['projectid'] = 'non-existent';

        $expected = new stdClass();
        $expected->error = 'This project does not exist.';
        $actual = $this->getEndpointResponse();
        $this->assertEquals($expected, $actual);
    }

    public function testCreateProjectWithInsufficientlyPrivilegedUser()
    {
        $container = ServiceContainer::container();
        $mock_project = $container->get(Project::class);
        $mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);

        $expected = new stdClass();
        $expected->error = 'You do not have permission to access this page.';
        $actual = $this->getEndpointResponse();
        $this->assertEquals($expected, $actual);
    }

    public function testCreateProjectWithUserProjectRoleSufficientToEdit()
    {
        $container = ServiceContainer::container();
        $mock_user = $container->get(User::class);
        $mock_project = $container->get(Project::class);
        $mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);

        $mock_project
            ->expects($this->any())
            ->method('GetUserRole')
            ->willReturn(Project::PROJECT_ADMIN);

        $mock_project
            ->expects($this->any())
            ->method('GetBlockedBuilds')
            ->willReturn([]);

        $mock_project
            ->expects($this->any())
            ->method('GetRepositories')
            ->willReturn([]);

        $mock_usrproj = $this->getMockUserProject();
        $mock_usrproj->Role = Project::PROJECT_ADMIN;
        $container->set(UserProject::class, $mock_usrproj);

        $expected = new stdClass();
        $expected->user = new stdClass();
        $expected->user->admin = 1;
        $expected->user->id = $mock_user->Id;
        $actual = $this->getEndpointResponse();
        $this->assertEquals($expected->user, $actual->user);
    }

    public function testCreateProjectHasUploadQuotaForAdminEdit()
    {
        $container = ServiceContainer::container();
        $mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'Exists',
                'GetUserRole',
                'GetBlockedBuilds',
                'GetRepositories',
                'FindByName',
                'Fill',
            ])
            ->getMock();
        $mock_project->Name = 'Pitstop';
        $mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);

        $mock_project
            ->expects($this->any())
            ->method('GetUserRole')
            ->willReturn(Project::PROJECT_ADMIN);

        $mock_project
            ->expects($this->any())
            ->method('GetBlockedBuilds')
            ->willReturn([]);

        $mock_project
            ->expects($this->any())
            ->method('GetRepositories')
            ->willReturn([]);

        $mock_usrproj = $this->getMockUserProject();
        $mock_usrproj->Role = Project::PROJECT_ADMIN;

        $container->set(Project::class, $mock_project);
        $container->set(UserProject::class, $mock_usrproj);

        $response = $this->getEndpointResponse();

        $this->assertObjectHasAttribute('project', $response);
        $this->assertObjectHasAttribute('blockedbuilds', $response->project);
        $this->assertObjectHasAttribute('repositories', $response->project);
        $this->assertObjectHasAttribute('UploadQuota', $response->project);
        $this->assertObjectHasAttribute('selectedViewer', $response);
        $this->assertObjectHasAttribute('vcsviewers', $response);
    }

    public function testCreateProjectMissingUploadQuotaForNonAdminUser()
    {
        $config = Config::getInstance();
        $config->set('CDASH_USER_CREATE_PROJECTS', true);

        $container = ServiceContainer::container();
        $mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'Exists',
                'GetUserRole',
                'GetBlockedBuilds',
                'GetRepositories',
                'FindByName',
                'Fill',
            ])
            ->getMock();
        $mock_project->Name = 'Pitstop';
        $mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);

        $mock_project
            ->expects($this->any())
            ->method('GetUserRole')
            ->willReturn(Project::PROJECT_ADMIN);

        $mock_project
            ->expects($this->any())
            ->method('GetBlockedBuilds')
            ->willReturn([]);

        $mock_project
            ->expects($this->any())
            ->method('GetRepositories')
            ->willReturn([]);

        $mock_usrproj = $this->getMockUserProject();
        $mock_usrproj->Role = Project::PROJECT_USER;

        $container->set(Project::class, $mock_project);
        $container->set(UserProject::class, $mock_usrproj);

        $response = $this->getEndpointResponse();

        $this->assertObjectNotHasAttribute('UploadQuota', $response->project);
    }

    public function testCreateProjectHasUploadQuotaForProjectSuperUser()
    {
        $config = Config::getInstance();
        $config->set('CDASH_USER_CREATE_PROJECTS', true);
        $mock_user = $this->getSessionUser();
        $mock_user
            ->expects($this->any())
            ->method('IsAdmin')
            ->willReturn(true);

        $container = ServiceContainer::container();
        $mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'Exists',
                'GetUserRole',
                'GetBlockedBuilds',
                'GetRepositories',
                'FindByName',
                'Fill',
            ])
            ->getMock();
        $mock_project->Name = 'Pitstop';
        $mock_project
            ->expects($this->once())
            ->method('Exists')
            ->willReturn(true);

        $mock_project
            ->expects($this->any())
            ->method('GetUserRole')
            ->willReturn(Project::PROJECT_ADMIN);

        $mock_project
            ->expects($this->any())
            ->method('GetBlockedBuilds')
            ->willReturn([]);

        $mock_project
            ->expects($this->any())
            ->method('GetRepositories')
            ->willReturn([]);

        $mock_usrproj = $this->getMockUserProject();
        $mock_usrproj->Role = Project::PROJECT_USER;

        $container->set(Project::class, $mock_project);
        $container->set(UserProject::class, $mock_usrproj);

        $response = $this->getEndpointResponse();

        $this->assertObjectHasAttribute('UploadQuota', $response->project);
    }
}
