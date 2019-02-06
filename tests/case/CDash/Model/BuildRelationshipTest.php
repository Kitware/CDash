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

require_once 'include/log.php';
use CDash\Model\Build;
use CDash\Model\BuildRelationship;
use CDash\Model\Project;
use CDash\ServiceContainer;
use CDash\Test\CDashTestCase;

class BuildRelationshipModelTest extends CDashTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->service = ServiceContainer::getInstance();
        $container = ServiceContainer::container();
        $this->relationship = $this->service->get(BuildRelationship::class);

        $this->mock_build1 = $this->getMockBuilder(Build::class)
            ->disableOriginalConstructor()
            ->setMethods(['Exists', 'FillFromId'])
            ->getMock();
        $this->mock_build1->Id = 1;
        $this->mock_build1->ProjectId = 1;
        $container->set(Build::class, $this->mock_build1);

        $this->mock_build2 = $this->getMockBuilder(Build::class)
            ->disableOriginalConstructor()
            ->setMethods(['Exists', 'FillFromId'])
            ->getMock();
        $this->mock_build2->Id = 2;
        $this->mock_build2->ProjectId = 1;
        $container->set(Build::class, $this->mock_build2);

        $this->mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mock_project->Id = 1;
        $container->set(Project::class, $this->mock_project);
    }

    public function testSaveChecksForMissingParams()
    {
        $error_msg = '';
        $this->relationship->Save($error_msg);
        $this->assertEquals('Build not set', $error_msg);

        $this->relationship->Build = $this->mock_build1;
        $this->relationship->Save($error_msg);
        $this->assertEquals('RelatedBuild not set', $error_msg);

        $this->relationship->RelatedBuild = $this->mock_build2;
        $this->relationship->Save($error_msg);
        $this->assertEquals('Project not set', $error_msg);

        $this->relationship->Project = $this->mock_project;
        $this->relationship->Save($error_msg);
        $this->assertEquals('Relationship not set', $error_msg);
    }

    public function testSaveCheckForNonexistentBuild()
    {
        $this->relationship->Build = $this->mock_build1;
        $this->relationship->RelatedBuild = $this->mock_build2;
        $this->relationship->Project = $this->mock_project;
        $this->relationship->Relationship = 'depends on';

        $this->mock_build1->method('Exists')->willReturn(false);
        $error_msg = '';
        $this->relationship->Save($error_msg);
        $this->assertEquals('Build #1 does not exist', $error_msg);
    }

    public function testSaveCheckForNonexistentRelatedBuild()
    {
        $this->relationship->Build = $this->mock_build1;
        $this->relationship->RelatedBuild = $this->mock_build2;
        $this->relationship->Project = $this->mock_project;
        $this->relationship->Relationship = 'depends on';

        $this->mock_build1->method('Exists')->willReturn(true);
        $this->mock_build2->method('Exists')->willReturn(false);
        $error_msg = '';
        $this->relationship->Save($error_msg);
        $this->assertEquals('Build #2 does not exist', $error_msg);
    }

    public function testSaveCheckForSelfReferentialRelationship()
    {
        $this->mock_build2->Id = $this->mock_build1->Id;
        $this->relationship->Build = $this->mock_build1;
        $this->relationship->RelatedBuild = $this->mock_build2;
        $this->relationship->Project = $this->mock_project;
        $this->relationship->Relationship = 'depends on';

        $this->mock_build1->method('Exists')->willReturn(true);
        $this->mock_build2->method('Exists')->willReturn(true);
        $error_msg = '';
        $this->relationship->Save($error_msg);
        $this->assertEquals('A build cannot be related to itself', $error_msg);
    }

    public function testSaveSuccess()
    {
        $this->relationship->Build = $this->mock_build1;
        $this->relationship->RelatedBuild = $this->mock_build2;
        $this->relationship->Project = $this->mock_project;
        $this->relationship->Relationship = 'depends on';

        $this->mock_build1->method('Exists')->willReturn(true);
        $this->mock_build2->method('Exists')->willReturn(true);
        $this->relationship->Save($error_msg);
        $this->assertEquals('', $error_msg);
    }

    public function testDeleteChecksForMissingParams()
    {
        $this->relationship->Build = null;
        $this->relationship->RelatedBuild = null;
        $error_msg = '';
        $this->relationship->Delete($error_msg);
        $this->assertEquals('Build not set', $error_msg);

        $this->relationship->Build = $this->mock_build1;
        $this->relationship->Delete($error_msg);
        $this->assertEquals('RelatedBuild not set', $error_msg);
    }

    public function testMarshal()
    {
        $this->relationship->Build = $this->mock_build1;
        $this->relationship->RelatedBuild =$this->mock_build2;
        $this->relationship->Relationship = 'depends on';
        $expected = [
            'buildid'      => 1,
            'relatedid'    => 2,
            'relationship' => 'depends on'
        ];
        $actual = $this->relationship->marshal();
        $this->assertEquals($expected, $actual);
    }
}
