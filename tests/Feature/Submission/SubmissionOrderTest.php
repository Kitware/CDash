<?php

namespace Tests\Feature\Submission;

use App\Models\DynamicAnalysisDefect;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesSubmissions;

/**
 * Tests that the files which make up a build produce the same build regardless of the order in
 * which they are submitted.
 */
class SubmissionOrderTest extends TestCase
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
     * @return array<string,array{list<string>}>
     */
    public static function fileOrders(): array
    {
        return [
            'original order' => [['Build', 'Configure', 'Coverage', 'CoverageLog', 'DynamicAnalysis', 'Notes', 'Test', 'Update', 'Upload']],
            'reverse order' => [['Upload', 'Update', 'Test', 'Notes', 'DynamicAnalysis', 'CoverageLog', 'Coverage', 'Configure', 'Build']],
            'configure first' => [['Configure', 'Test', 'Notes', 'CoverageLog', 'Coverage', 'Build', 'DynamicAnalysis', 'Upload', 'Update']],
            'coverage first' => [['Coverage', 'DynamicAnalysis', 'Configure', 'Build', 'CoverageLog', 'Upload', 'Notes', 'Update', 'Test']],
            'coverage log first' => [['CoverageLog', 'Notes', 'Coverage', 'Configure', 'Upload', 'Build', 'DynamicAnalysis', 'Update', 'Test']],
            'dynamic analysis first' => [['DynamicAnalysis', 'Notes', 'Configure', 'Upload', 'Test', 'Coverage', 'Update', 'CoverageLog', 'Build']],
            'notes first' => [['Notes', 'DynamicAnalysis', 'CoverageLog', 'Update', 'Configure', 'Upload', 'Build', 'Test', 'Coverage']],
            'test first' => [['Test', 'CoverageLog', 'DynamicAnalysis', 'Build', 'Configure', 'Notes', 'Update', 'Coverage', 'Upload']],
            'update first' => [['Update', 'Notes', 'DynamicAnalysis', 'Upload', 'Configure', 'Test', 'CoverageLog', 'Build', 'Coverage']],
        ];
    }

    /**
     * @param list<string> $file_order
     */
    #[DataProvider('fileOrders')]
    public function testSubmissionOrderDoesNotAffectBuild(array $file_order): void
    {
        foreach ($file_order as $type) {
            $this->makeSubmission($this->project->name, base_path(
                "app/cdash/tests/data/InsightExperimentalExample/Insight_Experimental_$type.xml"
            ));
        }

        $this->graphQL('
            query project($id: ID) {
              project(id: $id) {
                builds {
                  edges {
                    node {
                      startTime
                      endTime
                      configureErrorsCount
                      configureWarningsCount
                      configureDuration
                      buildErrorsCount
                      buildWarningsCount
                      buildDuration
                      notRunTestsCount
                      failedTestsCount
                      passedTestsCount
                      notes {
                        edges {
                          node {
                            name
                          }
                        }
                      }
                      files {
                        edges {
                          node {
                            name
                          }
                        }
                      }
                      updateStep {
                        type
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
                                    'startTime' => '2009-02-23T07:10:37+00:00',
                                    'endTime' => '2009-02-23T12:32:22+00:00',
                                    'configureErrorsCount' => 0,
                                    'configureWarningsCount' => 0,
                                    'configureDuration' => 0,
                                    'buildErrorsCount' => 0,
                                    'buildWarningsCount' => 3,
                                    'buildDuration' => 3103,
                                    'notRunTestsCount' => 1,
                                    'failedTestsCount' => 5,
                                    'passedTestsCount' => 46,
                                    'notes' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'name' => '/home/ibanez/src/Work/Luis/DashboardScripts/camelot_itk_lesion_sizing_sandbox_debug_gcc41.cmake',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'files' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'name' => 'tmp.txt',
                                                ],
                                            ],
                                        ],
                                    ],
                                    'updateStep' => [
                                        'type' => 'CVS',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $build = $this->project->builds()->firstOrFail();
        $this->assertDatabaseHas('build2note', [
            'buildid' => $build->id,
            'time' => '2009-02-23 12:32:00',
        ]);
        $this->assertSame(0, $build->updateStep?->nfiles);
        $this->assertSame(225, (int) DynamicAnalysisDefect::whereIn('dynamicanalysisid', $build->dynamicAnalyses()->select('id'))->sum('value'));
        $this->assertDatabaseHas('coveragesummary', [
            'buildid' => $build->id,
            'loctested' => 2185,
            'locuntested' => 674,
        ]);
        $this->assertSame(2, DB::table('coveragefilelog')->where('buildid', $build->id)->count());
    }
}
