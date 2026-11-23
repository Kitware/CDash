<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\Project;
use App\Models\ProjectMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class UpdateProjectMetricOrderTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    /**
     * @param array<int> $ids
     *
     * @return TestResponse<Response>
     */
    private function reorder(int $projectId, array $ids): TestResponse
    {
        return $this->graphQL('
            mutation updateProjectMetricOrder($input: UpdateProjectMetricOrderInput!) {
                updateProjectMetricOrder(input: $input) {
                    message
                    projectMetrics {
                        id
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $projectId,
                'projectMetricIds' => $ids,
            ],
        ]);
    }

    /**
     * @return array<ProjectMetric>
     */
    private function makeMetrics(Project $project, int $count): array
    {
        $metrics = [];
        for ($position = 1; $position <= $count; $position++) {
            /** @var ProjectMetric $metric */
            $metric = ProjectMetric::factory()->for($project)->create(['position' => $position]);
            $metrics[] = $metric;
        }

        return $metrics;
    }

    /**
     * @return array<int>
     */
    private function idsInOrder(Project $project): array
    {
        return $project->metrics()->orderBy('position')->get()->map(fn (ProjectMetric $metric): int => $metric->id)->all();
    }

    public function testReordersMetrics(): void
    {
        $project = $this->makePublicProject();
        [$metric1, $metric2, $metric3] = $this->makeMetrics($project, 3);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->reorder($project->id, [$metric3->id, $metric1->id, $metric2->id])->assertExactJson([
            'data' => [
                'updateProjectMetricOrder' => [
                    'message' => null,
                    'projectMetrics' => [
                        ['id' => (string) $metric3->id],
                        ['id' => (string) $metric1->id],
                        ['id' => (string) $metric2->id],
                    ],
                ],
            ],
        ]);

        self::assertSame([$metric3->id, $metric1->id, $metric2->id], $this->idsInOrder($project));
    }

    /**
     * A list which is missing a metric was most likely loaded before someone else added the metric.
     */
    public function testFailsWhenMissingIds(): void
    {
        $project = $this->makePublicProject();
        [$metric1, $metric2, $metric3] = $this->makeMetrics($project, 3);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->reorder($project->id, [$metric2->id, $metric1->id])
            ->assertJsonPath('data.updateProjectMetricOrder', null)
            ->assertGraphQLErrorMessage('IDs for all ProjectMetrics must be provided.');

        self::assertSame([$metric1->id, $metric2->id, $metric3->id], $this->idsInOrder($project));
    }

    public function testFailsWhenIdsDontMatchProject(): void
    {
        $project = $this->makePublicProject();
        [$metric1] = $this->makeMetrics($project, 1);
        [$otherMetric] = $this->makeMetrics($this->makePublicProject(), 1);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->reorder($project->id, [$otherMetric->id, $metric1->id])
            ->assertJsonPath('data.updateProjectMetricOrder', null)
            ->assertGraphQLErrorMessage('IDs for all ProjectMetrics must be provided.');
    }

    public function testFailsWhenDuplicateIds(): void
    {
        $project = $this->makePublicProject();
        [$metric1, $metric2] = $this->makeMetrics($project, 2);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->reorder($project->id, [$metric2->id, $metric1->id, $metric2->id])
            ->assertJsonPath('data.updateProjectMetricOrder', null)
            ->assertGraphQLErrorMessage('Provided set cannot contain duplicate IDs.');

        self::assertSame([$metric1->id, $metric2->id], $this->idsInOrder($project));
    }

    public function testFailsWhenNoMetrics(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->reorder($project->id, [])
            ->assertJsonPath('data.updateProjectMetricOrder', null)
            ->assertGraphQLErrorMessage("Can't order an empty set.");
    }

    public function testFailsWhenNoUser(): void
    {
        $project = $this->makePublicProject();
        [$metric1, $metric2] = $this->makeMetrics($project, 2);

        $this->reorder($project->id, [$metric2->id, $metric1->id])
            ->assertJsonPath('data.updateProjectMetricOrder', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame([$metric1->id, $metric2->id], $this->idsInOrder($project));
    }

    public function testFailsWhenBasicUser(): void
    {
        $project = $this->makePublicProject();
        [$metric1, $metric2] = $this->makeMetrics($project, 2);
        $user = User::factory()->create();

        $this->actingAs($user)->reorder($project->id, [$metric2->id, $metric1->id])
            ->assertJsonPath('data.updateProjectMetricOrder', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame([$metric1->id, $metric2->id], $this->idsInOrder($project));
    }
}
