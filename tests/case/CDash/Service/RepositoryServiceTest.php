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
use GuzzleHttp\ClientInterface;
use Ramsey\Uuid\Uuid;

class RepositoryServiceTest extends PHPUnit_Framework_TestCase
{
    /** @var RepositoryInterface|PHPUnit_Framework_MockObject_MockObject */
    private $repository;

    /** @var ClientInterface|PHPUnit_Framework_MockObject_MockObject */
    private $client;

    public function setUp()
    {
        parent::setUp();
        $this->repository = $this->getMockBuilder(RepositoryInterface::class)
            ->getMockForAbstractClass();

        $this->client = $this->getMockBuilder(ClientInterface::class)
            ->getMockForAbstractClass();
    }

    public function test__construct()
    {
        $sut = new RepositoryService($this->repository, $this->client);
        $this->assertInstanceOf(RepositoryService::class, $sut);
    }

    public function testSetStatusPending()
    {
        $sut = new RepositoryService($this->repository, $this->client);

        $hash = str_replace('-', '', Uuid::uuid4()->toString());

        $buildUpdate = new BuildUpdate();
        $buildUpdate->Revision = $hash;

        $build = new Build();
        $build->Name = 'FooBar';
        $build->Id = '1001001';
        $build->SetBuildUpdate($buildUpdate);


        $options = [
            'context' => 'CDash by Kitware',
            'description' => "Build: {$build->Name}",
            'commit_hash' => $hash,
            'state' => 'pending',
            'target_url' => $build->GetBuildSummaryUrl(),
        ];

        $this->repository
            ->expects($this->once())
            ->method('setStatus')
            ->with($this->client, $options);

        $sut->setStatusPending($build);
    }
}
