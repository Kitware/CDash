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

use CDash\Model\BuildErrorFilter;
use CDash\Model\Project;
use CDash\ServiceContainer;
use CDash\Test\CDashTestCase;

class BuildErrorFilterModelTest extends CDashTestCase
{
    public function setUp()
    {
        $this->setDatabaseMocked();
        $this->service = ServiceContainer::getInstance();
        $container = ServiceContainer::container();

        $this->mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mock_project->Id = 1;
        $container->set(Project::class, $this->mock_project);

        $this->sut = $container->make(
                BuildErrorFilter::class,
                ['project' => $this->mock_project]);
    }

    public function testExists()
    {
        $this->assertFalse($this->sut->Exists());
    }

    public function testAddFilter()
    {
        $this->assertFalse($this->sut->AddOrUpdateFilters('', ''));
    }

    public function testFilterWarning()
    {
        $this->sut->SetWarningsFilter('false warning');
        $this->assertTrue($this->sut->FilterWarning('this is a false warning'));
        $this->assertFalse($this->sut->FilterWarning('this is a real warning'));
    }

    public function testFilterError()
    {
        $this->sut->SetErrorsFilter('false error');
        $this->assertTrue($this->sut->FilterError('this is a false error'));
        $this->assertFalse($this->sut->FilterError('this is a real error'));
    }
}
