<?php

namespace Tests\Feature\Submission\Configure;

use App\Models\Label;
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

    /**
     * Test submitting a Configure.xml file with build, site, and stamp parameters.  The
     * response should include the id of the build, which should be populated with the
     * information from the submitted file.
     */
    public function testAssignBuildIdFromParameters(): void
    {
        $begin_test_time = time();

        $buildid = $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Configure/data/assign_buildid.xml'
        ), parameters: [
            'build' => 'assign_buildid',
            'site' => 'localhost',
            'stamp' => '20180918-0100-Nightly',
        ]);

        $this->assertNotNull($buildid);
        $build = $this->project->builds()->findOrFail($buildid);
        $this->assertSame('assign_buildid', $build->name);
        $this->assertGreaterThanOrEqual($begin_test_time, $build->submittime->getTimestamp());
        $this->assertSame('2018-09-18 14:27:07', $build->starttime->toDateTimeString());
        $this->assertSame('2018-09-18 14:27:08', $build->endtime->toDateTimeString());
        $this->assertSame('ctest-3.13.0', $build->generator);
        $this->assertSame('Linux', $build->osname);
        $this->assertSame('x86_64', $build->osplatform);
        $this->assertSame('4.4.0', $build->osrelease);
        $this->assertSame('#166', $build->osversion);
        $this->assertSame('gcc', $build->compilername);
        $this->assertSame('5.5.0', $build->compilerversion);
    }

    /**
     * Test parsing a Configure.xml file with a large number of subprojects.  A child build
     * and a label should be created for each subproject.
     */
    public function testManySubProjects(): void
    {
        // Generate the submission file programmatically to avoid adding a large file to the repository.
        $xml = file_get_contents(base_path('tests/Feature/Submission/Configure/data/many_subprojects_before.xml'));
        foreach (range(1, 100) as $i) {
            $xml .= "\t<Subproject name=\"ManySubProjects{$i}\">\n";
            $xml .= "\t\t<Label>ManySubProjects{$i}</Label>\n";
            $xml .= "\t</Subproject>\n";
        }
        $xml .= file_get_contents(base_path('tests/Feature/Submission/Configure/data/many_subprojects_after.xml'));

        $file_to_submit = tempnam(sys_get_temp_dir(), 'many_subprojects');
        $this->assertNotFalse($file_to_submit);
        try {
            file_put_contents($file_to_submit, $xml);
            $this->makeSubmission($this->project->name, $file_to_submit);
        } finally {
            unlink($file_to_submit);
        }

        // 1 parent + 100 children
        $this->assertSame(101, $this->project->builds()->count());
        $this->assertSame(100, Label::where('text', 'LIKE', 'ManySubProjects%')->count());
    }
}
