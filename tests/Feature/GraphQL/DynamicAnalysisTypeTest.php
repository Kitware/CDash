<?php

namespace Tests\Feature\GraphQL;

use App\Models\Build;
use App\Models\DynamicAnalysis;
use App\Models\Project;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class DynamicAnalysisTypeTest extends TestCase
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

        $this->graphQL('
            query build($id: ID) {
                build(id: $id) {
                    dynamicAnalyses {
                        edges {
                            node {
                                id
                                status
                                checker
                                name
                                path
                                fullCommandLine
                                log
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
                                    'id' => (string) $da->id,
                                    'status' => $da->status,
                                    'checker' => $da->checker,
                                    'name' => $da->name,
                                    'path' => $da->path,
                                    'fullCommandLine' => $da->fullcommandline,
                                    'log' => $da->log,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
