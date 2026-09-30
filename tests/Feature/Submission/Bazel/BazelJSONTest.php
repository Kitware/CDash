<?php

namespace Tests\Feature\Submission\Bazel;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class BazelJSONTest extends TestCase
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
     * Test parsing a Bazel Build Event Protocol file containing build errors and warnings along
     * with passing and failing tests.  Each test's output should only contain that test's output,
     * not the summary of every test that was run.
     */
    public function testBuildAndTestResults(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_BEP.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 1,
                    'buildWarningsCount' => 1,
                    'failedTestsCount' => 1,
                    'passedTestsCount' => 1,
                ],
            ],
        ]);

        $this->assertStringNotContainsString(
            'Executed 2 out of 2 tests',
            $this->getTestOutput($buildid, '//main:hello-good')
        );
    }

    /**
     * Test submitting the same Bazel results for two consecutive builds.  The test results are
     * unchanged, so no test differences should be recorded for the second build.
     */
    public function testIdenticalResubmissionRecordsNoTestDiff(): void
    {
        $first_buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_BEP.json'
        ), 'BazelJSON', build_metadata: [
            'stamp' => '20170823-1835-Experimental',
            'starttime' => 1503513355,
            'endtime' => 1503513355,
        ]);
        $second_buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_BEP.json'
        ), 'BazelJSON', build_metadata: [
            'stamp' => '20170824-1835-Experimental',
            'starttime' => 1503599755,
            'endtime' => 1503599755,
        ]);

        $this->assertNotNull($first_buildid);
        $this->assertNotNull($second_buildid);
        $this->assertNotSame($first_buildid, $second_buildid);
        $this->assertDatabaseMissing('testdiff', ['buildid' => $second_buildid]);
    }

    /**
     * Test submitting a list of Bazel packages to define the project's subprojects, followed by
     * build and test results for the same build.  The results should be split into one child
     * build per subproject, and the parent build should hold the totals.
     */
    public function testSubProjects(): void
    {
        // All three files must be submitted to the same build.
        $build_metadata = [
            'build' => 'bazel_subproj',
            'stamp' => '20170823-1835-Experimental',
            'starttime' => 1503513355,
            'endtime' => 1503513355,
        ];

        $parentid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/subproj/subproj_list.txt'
        ), 'SubProjectDirectories', build_metadata: $build_metadata);
        $this->assertNotNull($parentid);
        $this->assertSame($parentid, $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/subproj/subproj_build.json'
        ), 'BazelJSON', build_metadata: $build_metadata));
        $this->assertSame($parentid, $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/subproj/subproj_test.json'
        ), 'BazelJSON', build_metadata: $build_metadata));

        // Child builds are stored in an arbitrary order, so query each subproject separately.
        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
                subproj1: children(filters: { has: { subProject: { eq: { name: "subproj1" } } } }) {
                  edges {
                    node {
                      configureErrorsCount
                      configureWarningsCount
                      buildErrorsCount
                      buildWarningsCount
                      failedTestsCount
                      passedTestsCount
                    }
                  }
                }
                subproj2: children(filters: { has: { subProject: { eq: { name: "subproj2" } } } }) {
                  edges {
                    node {
                      configureErrorsCount
                      configureWarningsCount
                      buildErrorsCount
                      buildWarningsCount
                      failedTestsCount
                      passedTestsCount
                    }
                  }
                }
              }
            }
        ', [
            'id' => $parentid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 0,
                    'buildWarningsCount' => 2,
                    'failedTestsCount' => 1,
                    'passedTestsCount' => 1,
                    'subproj1' => [
                        'edges' => [
                            [
                                'node' => [
                                    'configureErrorsCount' => 0,
                                    'configureWarningsCount' => 0,
                                    'buildErrorsCount' => 0,
                                    'buildWarningsCount' => 1,
                                    'failedTestsCount' => 0,
                                    'passedTestsCount' => 1,
                                ],
                            ],
                        ],
                    ],
                    'subproj2' => [
                        'edges' => [
                            [
                                'node' => [
                                    'configureErrorsCount' => 0,
                                    'configureWarningsCount' => 0,
                                    'buildErrorsCount' => 0,
                                    'buildWarningsCount' => 1,
                                    'failedTestsCount' => 1,
                                    'passedTestsCount' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a Bazel Build Event Protocol file containing a failed test.  All of the
     * failed test's output should be recorded.
     */
    public function testFailedTestOutput(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_testFailed.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 0,
                    'buildWarningsCount' => 0,
                    'failedTestsCount' => 1,
                    'passedTestsCount' => 1,
                ],
            ],
        ]);

        $this->assertStringContainsString(
            'FAIL: testDrakeFindResourceOrThrowInInstall (__main__.TestCommonInstall)',
            $this->getTestOutput($buildid, '//drake/bindings:pydrake_common_install_test')
        );
    }

    /**
     * Test parsing a Bazel Build Event Protocol file containing a test which timed out.  Bazel
     * only reports the timeout on stderr, so it should be added to the test's output.
     */
    public function testTimeout(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_timeout.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
                tests(filters: { eq: { name: "//drake/bindings:pydrake_common_install_test" } }) {
                  edges {
                    node {
                      status
                      details
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 1,
                    'buildErrorsCount' => 0,
                    'buildWarningsCount' => 1,
                    'failedTestsCount' => 1,
                    'passedTestsCount' => 18,
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'status' => 'FAILED',
                                    'details' => 'Completed (Timeout)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString(
            'TIMEOUT',
            $this->getTestOutput($buildid, '//drake/bindings:pydrake_common_install_test')
        );
    }

    /**
     * Test parsing a Bazel Build Event Protocol file containing errors and warnings from the
     * loading and analysis phases, which CDash treats as the configure step, followed by build
     * errors from the execution phase.
     */
    public function testConfigure(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_configure.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 1,
                    'configureWarningsCount' => 700,
                    'buildErrorsCount' => 8,
                    'buildWarningsCount' => 0,
                    'failedTestsCount' => 0,
                    'passedTestsCount' => 0,
                ],
            ],
        ]);
    }

    /**
     * Test parsing a Bazel Build Event Protocol file in which a test result is reported more than
     * once.  The test should only be counted once.
     */
    public function testDuplicateTests(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/automotive_test.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 0,
                    'buildWarningsCount' => 0,
                    'failedTestsCount' => 1,
                    'passedTestsCount' => 0,
                ],
            ],
        ]);
    }

    /**
     * Test parsing a Bazel Build Event Protocol file containing a build error which spans
     * multiple lines.  The error should be recorded once, at the line on which it starts.
     */
    public function testMultipleLineError(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_multiple_line_error.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
                buildErrors {
                  edges {
                    node {
                      type
                      logLine
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 1,
                    'buildWarningsCount' => 0,
                    'failedTestsCount' => 0,
                    'passedTestsCount' => 0,
                    'buildErrors' => [
                        'edges' => [
                            [
                                'node' => [
                                    'type' => 'ERROR',
                                    'logLine' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a Bazel Build Event Protocol file containing a sharded test whose shards all
     * passed.  The shards should be combined into a single passing test.
     */
    public function testShardedTest(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_shard_test.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
                tests(filters: { eq: { name: "//automotive/maliput/multilane:multilane_lanes_test" } }) {
                  edges {
                    node {
                      status
                      details
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 0,
                    'buildWarningsCount' => 0,
                    'failedTestsCount' => 0,
                    'passedTestsCount' => 1,
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'status' => 'PASSED',
                                    'details' => 'Completed',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a Bazel Build Event Protocol file containing sharded tests, some of which
     * failed.  Each test's output should only contain the output of that test's shards.
     */
    public function testShardedTestFailures(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_shard_test_failures.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
                tests(filters: { eq: { name: "//automotive/maliput/multilane:multilane_lanes_test" } }) {
                  edges {
                    node {
                      status
                      details
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 0,
                    'buildWarningsCount' => 0,
                    'failedTestsCount' => 2,
                    'passedTestsCount' => 36,
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'status' => 'FAILED',
                                    'details' => 'Completed (Failed)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $builder_test_output = $this->getTestOutput($buildid, '//automotive/maliput/multilane:multilane_builder_test');
        $this->assertStringContainsString('//automotive/maliput/multilane:multilane_builder_test', $builder_test_output);
        $this->assertStringContainsString('multilane/multilane_builder_test/test.log', $builder_test_output);
        $this->assertStringNotContainsString('automotive/maliput/multilane:multilane_lanes_test', $builder_test_output);

        $this->assertStringContainsString(
            'automotive/maliput/multilane:multilane_lanes_test',
            $this->getTestOutput($buildid, '//automotive/maliput/multilane:multilane_lanes_test')
        );
    }

    /**
     * Test parsing a Bazel Build Event Protocol file containing a sharded test, some of whose
     * shards timed out.  The test's output should include the output of every shard, and its
     * running time should be the sum of the shards' running times.
     */
    public function testShardedTestTimeout(): void
    {
        $buildid = $this->makeUnparsedSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Bazel/data/bazel_shard_test_timeout.json'
        ), 'BazelJSON');

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                configureErrorsCount
                configureWarningsCount
                buildErrorsCount
                buildWarningsCount
                failedTestsCount
                passedTestsCount
                tests(filters: { eq: { name: "//automotive/maliput/multilane:multilane_lanes_test" } }) {
                  edges {
                    node {
                      status
                      details
                      runningTime
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
        ])->assertExactJson([
            'data' => [
                'build' => [
                    'configureErrorsCount' => 0,
                    'configureWarningsCount' => 0,
                    'buildErrorsCount' => 0,
                    'buildWarningsCount' => 0,
                    'failedTestsCount' => 1,
                    'passedTestsCount' => 0,
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'status' => 'FAILED',
                                    'details' => 'Completed (Timeout)',
                                    'runningTime' => 185.75,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $output = $this->getTestOutput($buildid, '//automotive/maliput/multilane:multilane_lanes_test');
        $this->assertStringContainsString('Note: This is test shard 8 of 10.', $output);
        $this->assertStringContainsString('Note: This is test shard 9 of 10.', $output);
        $this->assertStringContainsString('Note: This is test shard 10 of 10.', $output);
        // The "TIMEOUT" in this line is surrounded by markup, so only check the rest of the line.
        $this->assertStringContainsString('in 3 out of 10 in 60.1s', $output);
        $this->assertStringContainsString('Stats over 10 runs', $output);
    }

    private function getTestOutput(?int $buildid, string $test_name): string
    {
        $output = $this->graphQL('
            query build($id: ID, $testName: String) {
              build(id: $id) {
                tests(filters: { eq: { name: $testName } }) {
                  edges {
                    node {
                      output
                    }
                  }
                }
              }
            }
        ', [
            'id' => $buildid,
            'testName' => $test_name,
        ])->json('data.build.tests.edges.0.node.output');

        $this->assertIsString($output);
        return $output;
    }
}
