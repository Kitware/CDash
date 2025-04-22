<?php

namespace Tests\Feature\Submission\Instrumentation;

use App\Models\Project;
use Exception;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class BuildInstrumentationTest extends TestCase
{
    use CreatesProjects;
    use CreatesSubmissions;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();

        // The trait doesn't initialize the default buildgroups for us, so we do it manually
        $legacy_project = new \CDash\Model\Project();
        $legacy_project->Id = $this->project->id;
        $legacy_project->InitialSetup();

        $this->project->refresh();
    }

    protected function tearDown(): void
    {
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * A basic submission which tests all of the core parts of the instrumentation functionality
     */
    public function testValidSubmission(): void
    {
        $this->submitFiles($this->project->name, [
            base_path('tests/Feature/Submission/Instrumentation/data/Build.xml'),
        ]);

        $expected_result_json = file_get_contents(base_path('tests/Feature/Submission/Instrumentation/data/result.json'));
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
        ])->assertJson($expected_result_json, true);
    }
}
