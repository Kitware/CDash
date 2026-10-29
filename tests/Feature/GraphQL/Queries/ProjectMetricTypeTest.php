<?php

namespace Tests\Feature\GraphQL\Queries;

use App\Models\ProjectMetric;
use App\Models\ProjectMetricValue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class ProjectMetricTypeTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    public function testMetricsAreOrderedByPosition(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric1 */
        $metric1 = ProjectMetric::factory()->for($project)->create(['position' => 3]);
        /** @var ProjectMetric $metric2 */
        $metric2 = ProjectMetric::factory()->for($project)->create(['position' => 1]);
        /** @var ProjectMetric $metric3 */
        $metric3 = ProjectMetric::factory()->for($project)->create(['position' => 2]);
        ProjectMetric::factory()->for($this->makePublicProject())->create();

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    metrics {
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
                    'metrics' => [
                        'edges' => [
                            ['node' => ['id' => (string) $metric2->id, 'name' => $metric2->name, 'position' => 1]],
                            ['node' => ['id' => (string) $metric3->id, 'name' => $metric3->name, 'position' => 2]],
                            ['node' => ['id' => (string) $metric1->id, 'name' => $metric1->name, 'position' => 3]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Values submitted together share a creation time, so the newest value has to be chosen per metric.
     */
    public function testLatestValueIsNewestValueOfEachMetric(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric1 */
        $metric1 = ProjectMetric::factory()->for($project)->create(['position' => 1]);
        /** @var ProjectMetric $metric2 */
        $metric2 = ProjectMetric::factory()->for($project)->create(['position' => 2]);
        /** @var ProjectMetric $metric3 */
        $metric3 = ProjectMetric::factory()->for($project)->create(['position' => 3]);

        $now = Carbon::now()->startOfMillisecond();
        $metric1->values()->create(['value' => 'old', 'created_at' => $now->copy()->subHours(2)]);
        $metric1->values()->create(['value' => 'tied', 'created_at' => $now]);
        $metric1->values()->create(['value' => 'newest', 'created_at' => $now]);
        $metric2->values()->create(['value' => 'newest', 'created_at' => $now]);
        $metric2->values()->create(['value' => 'older', 'created_at' => $now->copy()->subHour()]);

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    metrics {
                        edges {
                            node {
                                id
                                latestValue {
                                    value
                                }
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
                    'metrics' => [
                        'edges' => [
                            ['node' => ['id' => (string) $metric1->id, 'latestValue' => ['value' => 'newest']]],
                            ['node' => ['id' => (string) $metric2->id, 'latestValue' => ['value' => 'newest']]],
                            ['node' => ['id' => (string) $metric3->id, 'latestValue' => null]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testValuesAreNewestFirst(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create();
        $user = User::factory()->create();
        $deletedUser = User::factory()->create();

        $now = Carbon::now()->startOfSecond();
        /** @var ProjectMetricValue $value1 */
        $value1 = $metric->values()->create(['value' => '1', 'created_at' => $now->copy()->subDay(), 'user_id' => $deletedUser->id]);
        /** @var ProjectMetricValue $value2 */
        $value2 = $metric->values()->create(['value' => '2', 'created_at' => $now, 'user_id' => $user->id]);
        $deletedUser->delete();

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    metrics {
                        edges {
                            node {
                                valueCount
                                values {
                                    edges {
                                        node {
                                            id
                                            value
                                            createdAt
                                            user {
                                                id
                                            }
                                            metric {
                                                id
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
            'id' => $project->id,
        ])->assertExactJson([
            'data' => [
                'project' => [
                    'metrics' => [
                        'edges' => [
                            [
                                'node' => [
                                    'valueCount' => 2,
                                    'values' => [
                                        'edges' => [
                                            [
                                                'node' => [
                                                    'id' => (string) $value2->id,
                                                    'value' => '2',
                                                    'createdAt' => $now->toIso8601String(),
                                                    'user' => ['id' => (string) $user->id],
                                                    'metric' => ['id' => (string) $metric->id],
                                                ],
                                            ],
                                            [
                                                'node' => [
                                                    'id' => (string) $value1->id,
                                                    'value' => '1',
                                                    'createdAt' => $now->copy()->subDay()->toIso8601String(),
                                                    // The value is kept when the user who submitted it is deleted.
                                                    'user' => null,
                                                    'metric' => ['id' => (string) $metric->id],
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

    public function testMetricsOfPrivateProjectAreHidden(): void
    {
        $project = $this->makePrivateProject();
        ProjectMetric::factory()->for($project)->create();

        $this->graphQL('
            query project($id: ID) {
                project(id: $id) {
                    metrics {
                        edges {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        ', [
            'id' => $project->id,
        ])->assertExactJson([
            'data' => [
                'project' => null,
            ],
        ]);
    }
}
