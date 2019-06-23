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

use CDash\Lib\Repository\RepositoryInterface;
use CDash\Model\Build;
use CDash\Model\BuildUpdate;
use CDash\Service\RepositoryService;
use CDash\Test\CDashTestCase;
use Ramsey\Uuid\Uuid;

class RepositoryServiceTest extends CDashTestCase
{
    /** @var RepositoryInterface|PHPUnit_Framework_MockObject_MockObject */
    private $repository;

    public function setUp()
    {
        parent::setUp();
        $this->setDatabaseMocked();
        $this->repository = $this->getMockBuilder(RepositoryInterface::class)
            ->getMockForAbstractClass();
    }

    public function test__construct()
    {
        $sut = new RepositoryService($this->repository);
        $this->assertInstanceOf(RepositoryService::class, $sut);
    }

    public function testSetStatusOnStart()
    {
        $sut = new RepositoryService($this->repository);

        $hash = str_replace('-', '', Uuid::uuid4()->toString());

        $buildUpdate = new BuildUpdate();
        $buildUpdate->Revision = $hash;

        $build = new Build();
        $build->Name = 'FooBar';
        $build->Id = '1001001';
        $build->SetBuildUpdate($buildUpdate);

        $options = [
            'context' => 'ci/CDash/Debug',
            'description' => "Build: {$build->Name}",
            'commit_hash' => $hash,
            'state' => 'pending',
            'target_url' => $build->GetBuildSummaryUrl(),
        ];

        $this->repository
            ->expects($this->once())
            ->method('setStatus')
            ->with($options);

        $sut->setStatusOnStart($build, 'Debug');
    }
}
