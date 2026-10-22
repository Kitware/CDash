<?php

namespace Feature\Submission\Build;

use App\Models\BuildError;
use App\Models\Project;
use App\Utils\DatabaseCleanupUtils;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @return array<string,array{string,array<string>}>
     */
    public static function truncatedOutputCases(): array
    {
        return [
            'stdout' => ['truncate_stdout.xml', ['stdoutput']],
            'stderr' => ['truncate_stderr.xml', ['stderror']],
            'both' => ['truncate_both.xml', ['stdoutput', 'stderror']],
        ];
    }

    /**
     * Test that build error output which exceeds the large text limit is truncated
     * in the middle, preserving the beginning and end of the output.
     *
     * @param array<string> $fields
     */
    #[DataProvider('truncatedOutputCases')]
    public function testTruncatesLargeOutput(string $file, array $fields): void
    {
        Config::set('cdash.large_text_limit', 44);

        $this->makeSubmission($this->project->name, base_path("tests/Feature/Submission/Build/data/$file"));

        $build = $this->project->builds()->firstOrFail();
        $this->assertSame(1, $build->buildErrors()->count());
        $error = $build->buildErrors()->firstOrFail();
        foreach ($fields as $field) {
            $this->assertSame(
                "The beginning survives\n...\nCDash truncated output because it exceeded 44 characters.\n...\nThis part is preserved\n",
                $error->getAttribute($field),
            );
        }
    }

    /**
     * Test that suppressed warnings are removed from build error output which exceeds
     * the large text limit before resorting to truncation.
     */
    public function testRemovesSuppressedWarningsFromLargeOutput(): void
    {
        Config::set('cdash.large_text_limit', 44);

        $this->makeSubmission($this->project->name, base_path('tests/Feature/Submission/Build/data/suppressed_warnings.xml'));

        $build = $this->project->builds()->firstOrFail();
        $this->assertSame(1, $build->buildErrors()->count());
        $this->assertSame(
            "[CTest: warning matched] This part survives\n",
            $build->buildErrors()->firstOrFail()->stderror,
        );
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

    /**
     * Test that identical build failures submitted for two builds are stored separately for each
     * build, so that removing one build leaves the other build's failures intact.
     */
    public function testBuildFailuresRemovedWithBuild(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Build/data/build_failures_1.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Build/data/build_failures_2.xml'
        ));

        $this->assertSame(2, $this->project->builds()->count());
        $first_build = $this->project->builds()->where('stamp', '20150813-1324-Experimental')->firstOrFail();
        $second_build = $this->project->builds()->where('stamp', '20150813-1338-Experimental')->firstOrFail();
        $this->assertSame(2, $first_build->buildErrors()->count());
        $this->assertSame(2, $second_build->buildErrors()->count());

        DatabaseCleanupUtils::removeBuild($first_build->id);

        $this->assertSame(0, BuildError::where('buildid', $first_build->id)->count());
        $this->assertEqualsCanonicalizing(
            ['object file', 'executable'],
            $second_build->buildErrors()->pluck('outputtype')->all(),
        );

        DatabaseCleanupUtils::removeBuild($second_build->id);

        $this->assertSame(0, BuildError::where('buildid', $second_build->id)->count());
    }
}
