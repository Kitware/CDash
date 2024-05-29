<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildTypeTest extends TestCase
{
    use CreatesUsers;
    use CreatesProjects;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::findOrFail((int) $this->makePublicProject()->Id);
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds
        $this->project->delete();

        parent::tearDown();
    }

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $uuid = Str::uuid()->toString();
        $this->project->builds()->create([
            'stamp' => 'abcdefg',
            'name' => 'build1',
            'type' => 'Continuous',
            'generator' => 'ctest-2.9.20091218',
            'starttime' => '2011-07-22 15:11:41',
            'endtime' => '2011-07-22 15:29:30',
            'submittime' => '2024-03-21 20:30:51',
            'command' => 'foo bar',
            'configureerrors' => 1,
            'configurewarnings' => 2,
            'configureduration' => 10,
            'builderrors' => 3,
            'buildwarnings' => 4,
            'buildduration' => 20,
            'testnotrun' => 5,
            'testfailed' => 6,
            'testpassed' => 7,
            'testtimestatusfailed' => 8,
            'testduration' => 30,
            'uuid' => $uuid,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                stamp
                                name
                                buildType
                                generator
                                startTime
                                endTime
                                submissionTime
                                command
                                configureErrorsCount
                                configureWarningsCount
                                configureDuration
                                buildErrorsCount
                                buildWarningsCount
                                buildDuration
                                notRunTestsCount
                                failedTestsCount
                                passedTestsCount
                                timeStatusFailedTestsCount
                                testDuration
                                uuid
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $this->project->id,
        ])->assertJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'stamp' => 'abcdefg',
                                    'name' => 'build1',
                                    'buildType' => 'Continuous',
                                    'generator' => 'ctest-2.9.20091218',
                                    'startTime' => '2011-07-22T15:11:41+00:00',
                                    'endTime' => '2011-07-22T15:29:30+00:00',
                                    'submissionTime' => '2024-03-21T20:30:51+00:00',
                                    'command' => 'foo bar',
                                    'configureErrorsCount' => 1,
                                    'configureWarningsCount' => 2,
                                    'configureDuration' => 10,
                                    'buildErrorsCount' => 3,
                                    'buildWarningsCount' => 4,
                                    'buildDuration' => 20,
                                    'notRunTestsCount' => 5,
                                    'failedTestsCount' => 6,
                                    'passedTestsCount' => 7,
                                    'timeStatusFailedTestsCount' => 8,
                                    'testDuration' => 30,
                                    'uuid' => $uuid,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }

    public function testNoBasicWarningsOrBasicErrorsReturnsEmptyArray(): void
    {
        $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                basicWarnings {
                                    edges {
                                        node {
                                            text
                                        }
                                    }
                                }
                                basicErrors {
                                    edges {
                                        node {
                                            text
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
        ])->assertJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'build1',
                                    'basicWarnings' => [
                                        'edges' => [],
                                    ],
                                    'basicErrors' => [
                                        'edges' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBasicWarningFields(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);
        $build->basicAlerts()->create([
            'type' => Build::TYPE_WARN,
            'logline' => 5,
            'text' => 'def',
            'sourcefile' => '/a/b/c',
            'sourceline' => 7,
            'precontext' => 'ghi',
            'postcontext' => 'jlk',
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                basicWarnings {
                                    edges {
                                        node {
                                            logLine
                                            text
                                            sourceFile
                                            sourceLine
                                            preContext
                                            postContext
                                        }
                                    }
                                }
                                basicErrors {
                                    edges {
                                        node {
                                            text
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
        ])->assertJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'build1',
                                    'basicWarnings' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'logLine' => 5,
                                                    'text' => 'def',
                                                    'sourceFile' => '/a/b/c',
                                                    'sourceLine' => 7,
                                                    'preContext' => 'ghi',
                                                    'postContext' => 'jlk',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'basicErrors' => [
                                        'edges' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBasicErrorFields(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);
        $build->basicAlerts()->create([
            'type' => Build::TYPE_ERROR,
            'logline' => 5,
            'text' => 'def',
            'sourcefile' => '/a/b/c',
            'sourceline' => 7,
            'precontext' => 'ghi',
            'postcontext' => 'jlk',
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                basicWarnings {
                                    edges {
                                        node {
                                            text
                                        }
                                    }
                                }
                                basicErrors {
                                    edges {
                                        node {
                                            logLine
                                            text
                                            sourceFile
                                            sourceLine
                                            preContext
                                            postContext
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
        ])->assertJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'build1',
                                    'basicWarnings' => [
                                        'edges' => [],
                                    ],
                                    'basicErrors' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'logLine' => 5,
                                                    'text' => 'def',
                                                    'sourceFile' => '/a/b/c',
                                                    'sourceLine' => 7,
                                                    'preContext' => 'ghi',
                                                    'postContext' => 'jlk',
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

    public function testMultipleBasicWarningsAndBasicErrors(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $warnings = [];
        $errors = [];
        for ($i = 0; $i < 10; $i++) {
            $warning = [
                'text' => Str::uuid()->toString(),
            ];
            $error = [
                'text' => Str::uuid()->toString(),
            ];

            $build->basicAlerts()->create(array_merge($warning, ['type' => Build::TYPE_WARN]));
            $build->basicAlerts()->create(array_merge($error, ['type' => Build::TYPE_ERROR]));

            $warnings[] = [
                'node' => $warning,
            ];
            $errors[] = [
                'node' => $error,
            ];
        }

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    builds {
                        edges {
                            node {
                                name
                                basicWarnings {
                                    edges {
                                        node {
                                            text
                                        }
                                    }
                                }
                                basicErrors {
                                    edges {
                                        node {
                                            text
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
        ])->assertJson([
            'data' => [
                'project' => [
                    'builds' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'build1',
                                    'basicWarnings' => [
                                        'edges' => array_reverse($warnings),
                                    ],
                                    'basicErrors' => [
                                        'edges' => array_reverse($errors),
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
     * This test isn't intended to be a complete test of the GraphQL filtering
     * capability, but rather a quick smoke check to verify that the most basic
     * filters work for the builds relation, and that extra information is not leaked.
     */
    public function testBasicBuildFiltering(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->project->builds()->create([
                'name' => "build{$i}",
                'uuid' => Str::uuid()->toString(),
            ]);
        }

        $this->graphQL('
            query {
                projects {
                    edges {
                        node {
                            name
                            builds(filters: {
                                eq: {
                                    name: "build2"
                                }
                            }) {
                                edges {
                                    node {
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ')->assertJson([
            'data' => [
                'projects' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => $this->project->name,
                                'builds' => [
                                    'edges' => [
                                        [
                                            'node' => [
                                                'name' => 'build2',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], true);
    }
}
