<?php

namespace Feature\Submission\Build;

use App\Models\Project;
use Exception;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class BuildXMLTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;

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
        $this->submitFiles($this->project->name, [
            base_path(
                'tests/Feature/Submission/Build/data/with_build_source_binary_directories.xml'
            ),
        ]);

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
     * A basic submission which tests all of the core parts of the instrumentation functionality
     */
    public function testValidSubmissionWithInstrumentation(): void
    {
        $this->submitFiles($this->project->name, [
            base_path('tests/Feature/Submission/Build/data/with_instrumentation_data.xml'),
        ]);

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
