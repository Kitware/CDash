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

use CDash\Model\BuildError;
use CDash\Model\Project;
use CDash\ServiceContainer;
use CDash\Test\CDashTestCase;

class BuildErrorTest extends CDashTestCase
{
    public function setUp(): void
    {
        $container = ServiceContainer::container();
        $this->mock_project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mock_project->Name = 'foo';
        $this->mock_project->Id = 1;
        $this->mock_project->CvsViewerType = 'GitHub';
        $container->set(Project::class, $this->mock_project);
    }

    public function testMarshalBuildError(): void
    {
        $input_data = [
            'logline' => 16,
            'newstatus' => 1,
            'sourcefile' => 'src/main.cpp',
            'sourceline' => '2',
            'stdoutput' => "Scanning dependencies of target main\n[ 83%] Building CXX object src/CMakeFiles/main.dir/main.cpp.o\n/.../foo/src/main.cpp: In function `int main(int, char**)`:/.../foo/src/main.cpp:2:3: error: `asdf` not declared in this scope   asdf = 0;\n   ^\n[100%] Linking CXX executable main",
        ];

        $this->mock_project->CvsUrl = 'https://github.com/FooCo/foo';
        $marshaled = BuildError::marshal($input_data, $this->mock_project, '12');

        $expected = [
            'new' => 1,
            'logline' => 16,
            'cvsurl' => 'https://github.com/FooCo/foo/blob/12/src/main.cpp',
            'precontext' => '',
            'text' => "Scanning dependencies of target main\n[ 83%] Building CXX object src/CMakeFiles/main.dir/main.cpp.o\n/.../foo/src/main.cpp: In function `int main(int, char**)`:<a class='cdash-link' href='https://github.com/FooCo/foo/blob/12/src/main.cpp#L2'>src/main.cpp:2</a>:3: error: `asdf` not declared in this scope   asdf = 0;\n   ^\n[100%] Linking CXX executable main",
            'postcontext' => '',
            'sourcefile' => 'src/main.cpp',
            'sourceline' => '2',
        ];
        $this->assertEquals($expected, $marshaled);
    }
}
