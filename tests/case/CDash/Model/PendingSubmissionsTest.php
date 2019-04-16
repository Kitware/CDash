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

use CDash\Model\Build;
use CDash\Model\PendingSubmissions;
use CDash\ServiceContainer;

class PendingSubmissionsModelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->service = ServiceContainer::getInstance();
        $container = ServiceContainer::container();

        $this->mock_build = $this->getMockBuilder(Build::class)
            ->disableOriginalConstructor()
            ->setMethods(['Exists', 'FillFromId'])
            ->getMock();
        $this->mock_build->Id = 1;
        $this->mock_build->ProjectId = 1;
        $container->set(Build::class, $this->mock_build);

        $this->sut = $this->service->get(PendingSubmissions::class);
        $this->sut->Build = $this->mock_build;
    }

    public function testPendingSubmissionsModel()
    {
        $this->assertEquals(false, $this->sut->Exists());

        $this->sut->NumFiles = 1;
        $this->sut->Save();
        $this->assertEquals(true, $this->sut->Exists());
        $this->assertEquals(1, $this->sut->GetNumFiles());

        $this->sut->Increment();
        $this->assertEquals(2, $this->sut->GetNumFiles());
        $this->sut->Decrement();
        $this->assertEquals(1, $this->sut->GetNumFiles());

        PendingSubmissions::IncrementForBuildId($this->mock_build->Id);
        $this->assertEquals(2, $this->sut->GetNumFiles());

        PendingSubmissions::RecheckForBuildId($this->mock_build->Id);
        $this->assertEquals(1, $this->sut->GetRecheck());

        $this->sut->Delete();
        $this->assertEquals(false, $this->sut->Exists());
    }
}
