<?php

namespace Tests\Feature\GraphQL;

use App\Models\PinnedTestMeasurement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class PinnedTestMeasurementTypeTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    /**
     * A basic test to ensure that each of the fields works
     */
    public function testBasicFieldAccess(): void
    {
        $project = $this->makePublicProject();

        /** @var PinnedTestMeasurement $measurement1 */
        $measurement1 = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 3,
        ]);

        /** @var PinnedTestMeasurement $measurement2 */
        $measurement2 = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        /** @var PinnedTestMeasurement $measurement3 */
        $measurement3 = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 2,
        ]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    pinnedTestMeasurements {
                        edges {
                            node {
                                id
                                name
                                position
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'pinnedTestMeasurements' => [
                        'edges' => [
                            [
                                'node' => [
                                    'id' => (string) $measurement2->id,
                                    'name' => $measurement2->name,
                                    'position' => $measurement2->position,
                                ],
                            ],
                            [
                                'node' => [
                                    'id' => (string) $measurement3->id,
                                    'name' => $measurement3->name,
                                    'position' => $measurement3->position,
                                ],
                            ],
                            [
                                'node' => [
                                    'id' => (string) $measurement1->id,
                                    'name' => $measurement1->name,
                                    'position' => $measurement1->position,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
