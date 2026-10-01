<?php

namespace Tests\Feature\Submission\DynamicAnalysis;

use App\Models\DynamicAnalysisSummary;
use App\Models\Project;
use App\Utils\DatabaseCleanupUtils;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

class DynamicAnalysisXMLTest extends TestCase
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
     * Test parsing a valid DynamicAnalysis.xml file with a gzip-compressed, base64-encoded log
     * which is large enough to be decompressed in multiple chunks.  The DynamicAnalysis.xml file
     * is submitted after the Configure.xml, Build.xml, and Test.xml files for the same build.
     */
    public function testCompressedLog(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/compressed_log_Configure.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/compressed_log_Build.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/compressed_log_Test.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/compressed_log_DynamicAnalysis.xml'
        ));

        $expected_log = file_get_contents(base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/compressed_log_expected.log'
        ));
        if ($expected_log === false) {
            throw new Exception('Failed to read expected log.');
        }

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                dynamicAnalyses {
                  edges {
                    node {
                      name
                      checker
                      log
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
                    'dynamicAnalyses' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'main',
                                    'checker' => 'Valgrind',
                                    'log' => $expected_log,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test parsing a valid DynamicAnalysis.xml file containing a defect with a very long type.
     */
    public function testLongDefectType(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/long_defect_type.xml'
        ));

        $this->graphQL('
            query build($id: ID) {
              build(id: $id) {
                dynamicAnalyses {
                  edges {
                    node {
                      name
                      defects {
                        type
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
                    'dynamicAnalyses' => [
                        'edges' => [
                            [
                                'node' => [
                                    'name' => 'mytest',
                                    'defects' => [
                                        [
                                            'type' => "member call on address 0x7f9ce2a84da8 which does not point to an object of type 'error_category'",
                                            'value' => 1,
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
     * Test that the dynamic analysis summary for a build contains the total number of defects
     * found across all of the build's dynamic analyses.
     */
    public function testDefectSummary(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'app/cdash/tests/data/InsightExperimentalExample/Insight_Experimental_DynamicAnalysis.xml'
        ));

        self::assertSame([
            'checker' => 'Valgrind',
            'numdefects' => 225,
        ], DynamicAnalysisSummary::findOrFail($this->project->builds()->firstOrFail()->id)->only([
            'checker',
            'numdefects',
        ]));
    }

    /**
     * Test parsing valid DynamicAnalysis.xml files for three subprojects of the same build.  Each
     * child build's summary should contain its own defects, and the parent build's summary should
     * contain the defects of all its children.
     */
    public function testSubProjectDefectSummary(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/subproject_1.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/subproject_2.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/subproject_3.xml'
        ));

        $parent = $this->project->builds()->onlyParents()->firstOrFail();

        self::assertSame([
            'checker' => 'Valgrind',
            'numdefects' => 3,
        ], DynamicAnalysisSummary::findOrFail($parent->id)->only([
            'checker',
            'numdefects',
        ]));

        self::assertSame([
            [
                'checker' => 'Valgrind',
                'numdefects' => 1,
            ],
            [
                'checker' => 'Valgrind',
                'numdefects' => 1,
            ],
            [
                'checker' => 'Valgrind',
                'numdefects' => 1,
            ],
        ], DynamicAnalysisSummary::whereIn('buildid', $parent->children()->pluck('id'))
            ->get(['checker', 'numdefects'])
            ->toArray()
        );
    }

    /**
     * Test that the dynamic analysis summaries for a parent build and its children are deleted
     * when the parent build is removed.
     */
    public function testDefectSummaryDeletedWithBuild(): void
    {
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/subproject_1.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/subproject_2.xml'
        ));
        $this->makeSubmission($this->project->name, base_path(
            'tests/Feature/Submission/DynamicAnalysis/data/subproject_3.xml'
        ));

        $parent = $this->project->builds()->onlyParents()->firstOrFail();
        $buildids = [$parent->id, ...$parent->children()->pluck('id')];
        self::assertSame(4, DynamicAnalysisSummary::whereIn('buildid', $buildids)->count());

        DatabaseCleanupUtils::removeBuild($parent->id);

        self::assertSame(0, DynamicAnalysisSummary::whereIn('buildid', $buildids)->count());
    }
}
