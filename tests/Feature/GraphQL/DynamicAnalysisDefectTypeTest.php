<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\DynamicAnalysis;
use App\Models\DynamicAnalysisDefect;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class DynamicAnalysisDefectTypeTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;

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
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        /** @var Build $build */
        $build = $this->project->builds()->create([
            'name' => 'build1',
            'uuid' => Str::uuid()->toString(),
        ]);

        /** @var DynamicAnalysis $da */
        $da = $build->dynamicAnalyses()->create([
            'status' => 'Passed',
            'checker' => Str::uuid()->toString(),
            'name' => Str::uuid()->toString(),
            'path' => Str::uuid()->toString(),
            'fullcommandline' => Str::uuid()->toString(),
            'log' => Str::uuid()->toString(),
        ]);

        /** @var DynamicAnalysisDefect $da_defect */
        $da_defect = $da->defects()->create([
            'type' => Str::uuid()->toString(),
            'value' => 42,
        ]);

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    dynamicAnalyses {
                        edges {
                            node {
                                defects {
                                    id
                                    type
                                    value
                                }
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
                    'dynamicAnalyses' => [
                        'edges' => [
                            [
                                'node' => [
                                    'defects' => [
                                        [
                                            'id' => (string) $da_defect->id,
                                            'type' => $da_defect->type,
                                            'value' => $da_defect->value,
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
