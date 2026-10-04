<?php

namespace Feature\Submission\Build;

use App\Models\Project;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class BuildXMLTest extends TestCase
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
     * Test parsing a valid Build.xml file that contains
     * the Source and Binary directories
     */
    public function testBuildDirectoriesHandling(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Build/data/with_build_source_binary_directories.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                sourceDirectory
                binaryDirectory
              }
            }
        ', [
            'id' => $this->project->builds()->firstOrFail()->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'sourceDirectory' => '/home/user/Work/cmake',
                    'binaryDirectory' => '/home/user/Work/cmake-build',
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Build.xml file that contains terminal color escape
     * sequences in the context of a build error.  CTest replaces the escape
     * character with a [NON-XML-CHAR-0x1B] placeholder, which is stored as-is
     * and decoded by the frontend.
     */
    public function testColorOutput(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Build/data/color_output.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                buildErrors(filters: {
                  eq: {
                    logLine: 5
                  }
                }) {
                  edges {
                    node {
                      stdOutput
                      stdError
                    }
                  }
                }
              }
            }
        ', [
            'id' => $this->project->builds()->firstOrFail()->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'buildErrors' => [
                        'edges' => [
                            [
                                'node' => [
                                    'stdOutput' => "Scanning dependencies of target colortest\n"
                                        . "[NON-XML-CHAR-0x1B][32mHello world!\n"
                                        . "[NON-XML-CHAR-0x1B][91mVisit our website: <a href=\"https://www.kitware.com/\">Kitware</a>\n"
                                        . "[NON-XML-CHAR-0x1B][0mGood bye!\n"
                                        . "CMakeFiles/colortest.dir/build.make:57: recipe for target 'CMakeFiles/colortest' failed\n",
                                    'stdError' => "CMakeFiles/colortest.dir/build.make:57: recipe for target 'CMakeFiles/colortest' failed",
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * A basic submission which tests all of the core parts of the instrumentation functionality
     */
    public function testValidSubmissionWithInstrumentation(): void
    {
        $this->makeSubmission($this->project->name, base_path('tests/Feature/Submission/Build/data/with_instrumentation_data.xml'));

        $expected_result_json = file_get_contents(base_path('tests/Feature/Submission/Build/data/instrumentation-result.json'));
        if ($expected_result_json === false) {
            throw new Exception('Failed to read result JSON.');
        }
        $expected_result_json = json_decode($expected_result_json, true);

        // Make PHPStan happy
        if (!is_array($expected_result_json)) {
            throw new Exception('Result JSON is not an associative array.');
        }

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                targets {
                                    edges {
                                        node {
                                            name
                                            type
                                            cumulativeDuration
                                            commands {
                                                edges {
                                                    node {
                                                        type
                                                        startTime
                                                        duration
                                                        command
                                                        result
                                                        source
                                                        language
                                                        config
                                                        target {
                                                            name
                                                            type
                                                            cumulativeDuration
                                                        }
                                                        measurements {
                                                            edges {
                                                                node {
                                                                    name
                                                                    type
                                                                    value
                                                                }
                                                            }
                                                        }
                                                        outputs {
                                                            edges {
                                                                node {
                                                                    name
                                                                    size
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            labels {
                                                edges {
                                                    node {
                                                        text
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                commands {
                                    edges {
                                        node {
                                            type
                                            startTime
                                            duration
                                            command
                                            result
                                            source
                                            language
                                            config
                                            target {
                                                name
                                                type
                                                cumulativeDuration
                                            }
                                            measurements {
                                                edges {
                                                    node {
                                                        name
                                                        type
                                                        value
                                                    }
                                                }
                                            }
                                            outputs {
                                                edges {
                                                    node {
                                                        name
                                                        size
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                children {
                                    edges {
                                        node {
                                            name
                                            targets {
                                                edges {
                                                    node {
                                                        name
                                                        type
                                                        cumulativeDuration
                                                        commands {
                                                            edges {
                                                                node {
                                                                    type
                                                                    startTime
                                                                    duration
                                                                    command
                                                                    result
                                                                    source
                                                                    language
                                                                    config
                                                                    target {
                                                                        name
                                                                        type
                                                                        cumulativeDuration
                                                                    }
                                                                    measurements {
                                                                        edges {
                                                                            node {
                                                                                name
                                                                                type
                                                                                value
                                                                            }
                                                                        }
                                                                    }
                                                                    outputs {
                                                                        edges {
                                                                            node {
                                                                                name
                                                                                size
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        labels {
                                                            edges {
                                                                node {
                                                                    text
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            commands {
                                                edges {
                                                    node {
                                                        type
                                                        startTime
                                                        duration
                                                        command
                                                        result
                                                        source
                                                        language
                                                        config
                                                        target {
                                                            name
                                                            type
                                                            cumulativeDuration
                                                        }
                                                        measurements {
                                                            edges {
                                                                node {
                                                                    name
                                                                    type
                                                                    value
                                                                }
                                                            }
                                                        }
                                                        outputs {
                                                            edges {
                                                                node {
                                                                    name
                                                                    size
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
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
        ])->assertExactJson($expected_result_json);
    }
}
