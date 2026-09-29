<?php

namespace Tests\Feature\Submission\Configure;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class ConfigureXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;
    use DatabaseTransactions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * Test parsing two valid Configure.xml files for the same build, where the
     * second file sets the Append attribute.  The two configures should be
     * combined into a single configure for a single build.
     */
    public function testAppend(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Configure/data/append_part_1.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Configure/data/append_part_2.xml'
        ));

        $this->graphQL('
            query project($id: ID) {
              project(id: $id) {
                builds {
                  edges {
                    node {
                      configureErrorsCount
                      configureWarningsCount
                      configureDuration
                      configure {
                        returnValue
                        log
                      }
                    }
                  }
                }
              }
            }
        ', [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'configureErrorsCount' => 3,
                                    'configureWarningsCount' => 5,
                                    'configureDuration' => 60,
                                    'configure' => [
                                        'returnValue' => 3,
                                        'log' => "-- This is the first part of my configure\n"
                                            . "-- CMake Warning (dev) at some/file/path:1234 (MESSAGE):\n"
                                            . "-- WARNING: foo\n"
                                            . "-- Configuring done\n"
                                            . "-- Generating done\n"
                                            . "-- Build files have been written to: /tmp/bin\n"
                                            . "\n"
                                            . "-- This is the second part of my configure\n"
                                            . "-- CMake Warning (dev) at some/file/path:5678 (MESSAGE):\n"
                                            . "-- WARNING: bar\n"
                                            . "-- WARNING: baz\n",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Configure.xml file for a build with a very long name.
     */
    public function testLongBuildName(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Configure/data/long_build_name.xml'
        ));

        $this->graphQL('
            query project($id: ID) {
              project(id: $id) {
                builds {
                  edges {
                    node {
                      name
                      configure {
                        log
                      }
                    }
                  }
                }
              }
            }
        ', [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'this build name is really really really really really really really really really really really really really really really really really really really really reall really really really really really really really really really really really really long',
                                    'configure' => [
                                        'log' => "This is my config output\n",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Configure.xml file that has a subproject's label but
     * no subprojects, followed by a Build.xml file with subprojects for the same
     * build.  The configure should remain with the resulting parent build rather
     * than being assigned to any of the subproject builds.
     */
    public function testMisassignedConfigure(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Configure/data/subproject_label_without_subprojects.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Configure/data/build_with_subprojects.xml'
        ));

        $this->graphQL('
            query project($id: ID) {
              project(id: $id) {
                builds {
                  edges {
                    node {
                      subProject {
                        name
                      }
                      configure {
                        command
                        returnValue
                      }
                      children {
                        edges {
                          node {
                            configure {
                              command
                            }
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
        ', [
            'id' => $this->project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'subProject' => null,
                                    'configure' => [
                                        'command' => '"/home/betsy/cmake_build/bin/cmake" "-DCTEST_USE_LAUNCHERS=1" "-GUnix Makefiles" "/home/betsy/cmake/Tests/CTestTestSubprojects"',
                                        'returnValue' => 1,
                                    ],
                                    'children' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'configure' => null,
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'configure' => null,
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'configure' => null,
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'configure' => null,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
