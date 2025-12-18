<?php

namespace Tests\Feature\GraphQL;

use App\Enums\BuildCommandType;
use App\Models\Build;
use App\Models\BuildCommand;
use App\Models\CoverageFile;
use App\Models\Project;
use App\Models\Target;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class BuildTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

    private Project $project;
    private Project $project2;

    /** @var array<CoverageFile> */
    private array $coverageFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = $this->makePublicProject();
        $this->project2 = $this->makePrivateProject();
    }

    protected function tearDown(): void
    {
        // Deleting the project will delete all corresponding builds
        $this->project->delete();
        $this->project2->delete();

        foreach ($this->coverageFiles as $file) {
            $file->delete();
        }
        $this->coverageFiles = [];

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
            'osname' => 'Windows',
            'osplatform' => 'x86',
            'osrelease' => 'Vista',
            'osversion' => '(Build 7600)',
            'compilername' => 'abc',
            'compilerversion' => '1.2.3',
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
                                operatingSystemName
                                operatingSystemPlatform
                                operatingSystemRelease
                                operatingSystemVersion
                                compilerName
                                compilerVersion
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
                                    'operatingSystemName' => 'Windows',
                                    'operatingSystemPlatform' => 'x86',
                                    'operatingSystemRelease' => 'Vista',
                                    'operatingSystemVersion' => '(Build 7600)',
                                    'compilerName' => 'abc',
                                    'compilerVersion' => '1.2.3',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
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
        ])->assertExactJson([
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
        ])->assertExactJson([
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
        ])->assertExactJson([
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
        ])->assertExactJson([
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
        /** @var array<Build> $builds */
        $builds = [];
        for ($i = 0; $i < 4; $i++) {
            $builds[] = $this->project->builds()->create([
                'name' => "build{$i}" . Str::uuid()->toString(),
                'uuid' => Str::uuid()->toString(),
            ]);
        }

        $this->graphQL('
            query($projectid: ID, $buildname: String) {
                projects(filters: {
                    eq: {
                        id: $projectid
                    }
                }) {
                    edges {
                        node {
                            name
                            builds(filters: {
                                eq: {
                                    name: $buildname
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
        ', [
            'projectid' => $this->project->id,
            'buildname' => $builds[2]->name,
        ])->assertExactJson([
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
                                                'name' => $builds[2]->name,
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

    public function testTopLevelBuildField(): void
    {
        $build1 = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $build2 = $this->project2->builds()->create([
            'name' => 'build2',
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    id
                    name
                }
            }
        ', [
            'id' => $build1->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'id' => (string) $build1->id,
                    'name' => 'build1',
                ],
            ],
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    id
                    name
                }
            }
        ', [
            'id' => $build2->id,
        ])->assertJson([
            'data' => [
                'build' => null,
            ],
        ], true)->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    public function testLabelRelationship(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $label = $build->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    labels {
                        edges {
                            node {
                                id
                                text
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'labels' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $label->id,
                                    'text' => $label->text,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $label->delete();
    }

    public function testLabelFilters(): void
    {
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $label1 = $build->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $label2 = $build->labels()->create([
            'text' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID, $labeltext: String!) {
                build(id: $id) {
                    labels(
                        filters: {
                            eq: {
                                text: $labeltext
                            }
                        }
                    ){
                        edges {
                            node {
                                id
                                text
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
            'labeltext' => $label1->text,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'labels' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $label1->id,
                                    'text' => $label1->text,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $label1->delete();
        $label2->delete();
    }

    public function testTargetRelationship(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Target $target */
        $target = $build->targets()->create([
            'name' => Str::uuid()->toString(),
            'type' => 'EXECUTABLE',
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    targets {
                        edges {
                            node {
                                id
                                name
                                type
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'targets' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $target->id,
                                    'name' => $target->name,
                                    'type' => 'EXECUTABLE',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testBuildCommandRelationship(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var BuildCommand $command */
        $command = $build->commands()->create([
            'type' => BuildCommandType::CUSTOM,
            'starttime' => Carbon::now(),
            'duration' => 0,
            'command' => '',
            'result' => '',
            'workingdirectory' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    commands {
                        edges {
                            node {
                                id
                                type
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'commands' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $command->id,
                                    'type' => 'CUSTOM',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testChildBuildRelationshipNoChildren(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    id
                    children {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'id' => (string) $build->id,
                    'children' => [
                        'edges' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testChildBuildRelationshipGetAllChildren(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var Build $child1 */
        $child1 = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'parentid' => $build->id,
        ]);

        /** @var Build $child2 */
        $child2 = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'parentid' => $build->id,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    id
                    children {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'id' => (string) $build->id,
                    'children' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $child1->id,
                                ],
                            ],
                            [
                                'node' => [
                                    'id' => (string) $child2->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testChildBuildRelationshipFilterChildren(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
        ]);

        $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'parentid' => $build->id,
        ]);

        /** @var Build $child2 */
        $child2 = $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'parentid' => $build->id,
        ]);

        $this->project->builds()->create([
            'name' => Str::uuid()->toString(),
            'uuid' => Str::uuid()->toString(),
            'parentid' => $build->id,
        ]);

        $this->graphQL('
            query build($id: ID, $childid: ID) {
                build(id: $id) {
                    id
                    children(
                        filters: {
                            eq: {
                                id: $childid
                            }
                        }
                    ) {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $build->id,
            'childid' => $child2->id,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'id' => (string) $build->id,
                    'children' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $child2->id,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<array<string|float|null>>
     */
    public static function coveragePaths(): array
    {
        return [
            // Basic test case
            ['abc', 25.0],
            // Check summing multiple rows
            ['/', 37.5],
            // Check prefixes get stripped
            ['./abc', 25.0],
            ['/abc', 25.0],
            ['/.abc', 25.0],
            // Check for divide-by-zero issues / missing file
            ['xyz', null],
        ];
    }

    #[DataProvider('coveragePaths')]
    public function testPercentCoverageForPath(string $path, ?float $expected_percent): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        $coverageFile1 = CoverageFile::create([
            'fullpath' => './abc/' . Str::uuid()->toString(),
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);
        $this->coverageFiles[] = $coverageFile1;

        $coverageFile2 = CoverageFile::create([
            'fullpath' => '/def/' . Str::uuid()->toString(),
            'file' => Str::uuid()->toString(),
            'crc32' => 0,
        ]);
        $this->coverageFiles[] = $coverageFile2;

        $build->coverageResults()->create([
            'fileid' => $coverageFile1->id,
            'loctested' => 1,
            'locuntested' => 3,
            'branchestested' => 0,
            'branchesuntested' => 0,
            'functionstested' => 0,
            'functionsuntested' => 0,
        ]);

        $build->coverageResults()->create([
            'fileid' => $coverageFile2->id,
            'loctested' => 2,
            'locuntested' => 2,
            'branchestested' => 0,
            'branchesuntested' => 0,
            'functionstested' => 0,
            'functionsuntested' => 0,
        ]);

        $this->graphQL('
            query build($id: ID!, $path: String!) {
                build(id: $id) {
                    percentCoverageForPath(path: $path)
                }
            }
        ', [
            'id' => $build->id,
            'path' => $path,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'percentCoverageForPath' => $expected_percent,
                ],
            ],
        ]);
    }
}
