<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\ProjectMetric;
use App\Models\ProjectMetricValue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class DeleteProjectMetricTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    /**
     * @return TestResponse<Response>
     */
    private function deleteMetric(int $id): TestResponse
    {
        return $this->graphQL('
            mutation deleteProjectMetric($input: DeleteProjectMetricInput!) {
                deleteProjectMetric(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'id' => $id,
            ],
        ]);
    }

    private function makeMetricWithValue(): ProjectMetric
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create();
        $metric->values()->create(['value' => '48.2 MB']);

        return $metric;
    }

    public function testDeletesMetricAndItsValues(): void
    {
        $metric = $this->makeMetricWithValue();
        $otherMetric = $this->makeMetricWithValue();
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->deleteMetric($metric->id)->assertExactJson([
            'data' => [
                'deleteProjectMetric' => [
                    'message' => null,
                ],
            ],
        ]);

        self::assertNull($metric->fresh());
        self::assertSame([$otherMetric->id], ProjectMetricValue::pluck('metric_id')->all());
    }

    public function testFailsWhenNoUser(): void
    {
        $metric = $this->makeMetricWithValue();

        $this->deleteMetric($metric->id)
            ->assertJsonPath('data.deleteProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertNotNull($metric->fresh());
        self::assertSame(1, $metric->values()->count());
    }

    public function testFailsWhenBasicUser(): void
    {
        $metric = $this->makeMetricWithValue();
        $user = User::factory()->create();

        $this->actingAs($user)->deleteMetric($metric->id)
            ->assertJsonPath('data.deleteProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertNotNull($metric->fresh());
        self::assertSame(1, $metric->values()->count());
    }

    public function testFailsWhenNoMetric(): void
    {
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->deleteMetric(1234567)
            ->assertJsonPath('data.deleteProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');
    }
}
