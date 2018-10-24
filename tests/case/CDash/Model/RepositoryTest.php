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
use CDash\Lib\Repository\GitHub;
use CDash\Model\Project;
use CDash\Model\Repository;
use Ramsey\Uuid\Uuid;

class RepositoryTest extends PHPUnit_Framework_TestCase
{
    public function testFactoryReturnsGitHubService()
    {
        $apiToken = str_replace('-', '', Uuid::uuid4()->toString());
        $config = Config::getInstance();
        $config->set('CDASH_GITHUB_API_TOKEN', $apiToken);

        $project = new Project();
        $project->CvsViewerType = Repository::VIEWER_GITHUB;
        $project->CvsUrl = 'https://github.com/foo/bar';

        $service = Repository::factory($project);
        $this->assertInstanceOf(GitHub::class, $service);

        $token = new ReflectionProperty(GitHub::class, 'token');
        $token->setAccessible(true);
        $this->assertEquals($apiToken, $token->getValue($service));

        $owner = new ReflectionProperty(GitHub::class, 'owner');
        $owner->setAccessible(true);
        $this->assertEquals('foo', $owner->getValue($service));

        $repo = new ReflectionProperty(GitHub::class, 'repo');
        $repo->setAccessible(true);
        $this->assertEquals('bar', $repo->getValue($service));
    }
}
