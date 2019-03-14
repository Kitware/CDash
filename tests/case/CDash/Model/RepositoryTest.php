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

use CDash\Lib\Repository\GitHub;
use CDash\Model\Project;
use CDash\Model\Repository;
use Ramsey\Uuid\Uuid;

class RepositoryTest extends PHPUnit_Framework_TestCase
{
    private $project;
    private $repo = [];

    public function setUp()
    {
        $this->project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->setMethods(['GetRepositories'])
            ->getMock();

        $this->project->CvsViewerType = Repository::VIEWER_GITHUB;
        $this->project->CvsUrl = 'https://github.com/foo/bar';
        $this->project->expects($this->once())
            ->method('GetRepositories')
            ->willReturnCallback(function () {
                return $this->repo;
            });
    }

    public function testGetRepositoryInterfaceReturnsGitHubService()
    {
        $apiToken = str_replace('-', '', Uuid::uuid4()->toString());
        $this->repo[] = [
            'url' => 'http://github.com/foo/bar',
            'password' => $apiToken,
        ];

        $service = Repository::getRepositoryInterface($this->project);
        $this->assertInstanceOf(GitHub::class, $service);

        $this->assertEquals('foo', $service->getOwner());
        $this->assertEquals('bar', $service->getRepository());
        $this->assertEquals($apiToken, $service->getToken());
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Unable to find credentials for repository
     */
    public function testGetRepositoryInterfaceReturnsGitHubServiceThrowsExceptionGivenNoGitHubRepository()
    {
        $apiToken = str_replace('-', '', Uuid::uuid4()->toString());
        $this->repo[] = [
            'url' => 'https://gitlab.com/foo/baz',
            'password' => $apiToken,
        ];

        Repository::getRepositoryInterface($this->project);
    }
}
