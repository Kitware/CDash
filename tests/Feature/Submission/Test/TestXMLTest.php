<?php

namespace Feature\Submission\Test;

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class TestXMLTest extends TestCase
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
     * Test parsing a valid Test.xml file that contains angle brackets
     * in the test name.
     */
    public function testAngleBracketsInName(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Test/data/angle_brackets_in_test_name.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                tests {
                  edges {
                    node {
                      name
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
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'MyTest<parameterized>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Test.xml file that contains non-UTF-8 characters
     * in the test output.
     */
    public function testNonUTF8Output(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Test/data/non_utf8_output.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                tests {
                  edges {
                    node {
                      name
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
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'NonUtf8Output',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Test.xml file that contains the StartTestTime
     * attribute for each Test entry in the test output.
     */
    public function testStartTestTime(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Test/data/with_starttesttime.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                tests {
                  edges {
                    node {
                      name
                      startTime
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
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'exec',
                                    'startTime' => '2026-02-16T13:56:30.660000Z',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Test.xml file that contains terminal color escape
     * sequences in the test output.  CTest replaces the escape character with
     * a [NON-XML-CHAR-0x1B] placeholder in plain text output, which is stored
     * as-is and decoded by the frontend.  Compressed output is not escaped by
     * CTest, so it contains the actual escape characters once decompressed.
     */
    public function testColorOutput(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Test/data/color_output.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                tests {
                  edges {
                    node {
                      name
                      output
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
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'colortest_short',
                                    'output' => "\n"
                                        . "not bold [NON-XML-CHAR-0x1B][1mbold[NON-XML-CHAR-0x1B][0m not bold\n"
                                        . "[NON-XML-CHAR-0x1B][32mHello world!\n"
                                        . "[NON-XML-CHAR-0x1B][31mThis is a test\n",
                                ],
                            ],
                            [
                                'node' => [
                                    'name' => 'colortest_long',
                                    'output' => "\x1B[32mHello world!\n"
                                        . "\x1B[91m<script type=\"text/javascript\">console.log(\"MALICIOUS JAVASCRIPT!!!\");</script>\n"
                                        . "\x1B[0mGood bye world!\n",
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid Test.xml file that contains terminal color escape
     * sequences in a preformatted test measurement.
     */
    public function testColorOutputInPreformattedMeasurement(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/Test/data/color_output_preformatted_measurement.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                tests {
                  edges {
                    node {
                      name
                      testMeasurements(filters: {
                        eq: {
                          type: "text/preformatted"
                        }
                      }) {
                        name
                        value
                      }
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
                    'tests' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'preformatted_color',
                                    'testMeasurements' => [
                                        [
                                            'name' => 'Color Output',
                                            'value' => "not bold[NON-XML-CHAR-0x1B][1m bold[NON-XML-CHAR-0x1B][0;0m not bold\n"
                                                . "[NON-XML-CHAR-0x1B][32mHello world![NON-XML-CHAR-0x1B][0m\n"
                                                . '[NON-XML-CHAR-0x1B][31mThis is test output[NON-XML-CHAR-0x1B][0m',
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
