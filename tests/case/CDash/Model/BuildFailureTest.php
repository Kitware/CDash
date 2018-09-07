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

use CDash\Model\BuildFailure;
use CDash\Model\Project;
use CDash\ServiceContainer;

class BuildFailureModelTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $container = ServiceContainer::container();
        $this->mock_buildfailure = $this->getMockBuilder(BuildFailure::class)
            ->disableOriginalConstructor()
            ->setMethods(['GetBuildFailureArguments'])
            ->getMock();
        $this->mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mock_project->Name = 'foo';
        $this->mock_project->Id = 1;
        $this->mock_project->CvsViewerType = 'GitHub';
        $container->set(Project::class, $this->mock_project);
    }

    public function testMarshalBuildFailure()
    {
        $this->mock_buildfailure
            ->expects($this->once())
            ->method('GetBuildFailureArguments')
            ->willReturn([]);

        $input_data = [
            'id'               => 1,
            'language'         => 'C++',
            'sourcefile'       => '/projects/foo/src/main.cpp',
            'targetname'       => 'main',
            'outputfile'       => 'CMakeFiles\main.dir\main.cpp.obj',
            'outputtype'       => 'object file',
            'workingdirectory' => '/projects/foo/bin',
            'exitcondition'    => 2,
            'stdoutput'        => '',
            'stderror'         => '/projects/foo/src/main.cpp: In function `int main(int, char**)`:
/projects/foo/src/main.cpp:2:3: error: `asdf` was not declared in this scope
   asdf = 0;'
        ];
        $input_project = [
            'id' => 1,
            'cvsurl' => 'https://github.com/FooCo/foo'
        ];
        $marshaled = $this->mock_buildfailure->marshal($input_data, $input_project, '12', true, $this->mock_buildfailure);

        $expected = [
            'language'         => 'C++',
            'sourcefile'       => '/projects/foo/src/main.cpp',
            'targetname'       => 'main',
            'outputfile'       => 'CMakeFiles\main.dir\main.cpp.obj',
            'outputtype'       => 'object file',
            'workingdirectory' => '/projects/foo/bin',
            'exitcondition'    => '2',
            'stdoutput'        => '',
            'stderror'         => "foo/src/main.cpp: In function `int main(int, char**)`:\n<a href='https://github.com/FooCo/foo/blob/12/src/main.cpp#L2'>src/main.cpp:2</a>:3: error: `asdf` was not declared in this scope\n   asdf = 0;",
            'cvsurl' => 'https://github.com/FooCo/foo/blob/12/src/main.cpp'
        ];
        $this->assertEquals($expected, $marshaled);
    }
}
