<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\ProjectMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class UpdateProjectMetricTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    /**
     * @return TestResponse<Response>
     */
    private function renameMetric(int $id, string $name): TestResponse
    {
        return $this->graphQL('
            mutation updateProjectMetric($input: UpdateProjectMetricInput!) {
                updateProjectMetric(input: $input) {
                    message
                    projectMetric {
                        id
                        name
                        latestValue {
                            value
                        }
                    }
                }
            }
        ', [
            'input' => [
                'id' => $id,
                'name' => $name,
            ],
        ]);
    }

    public function testRenamingKeepsValues(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);
        $metric->values()->create(['value' => '48.2 MB']);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->renameMetric($metric->id, 'Wheel size (Linux)')->assertExactJson([
            'data' => [
                'updateProjectMetric' => [
                    'message' => null,
                    'projectMetric' => [
                        'id' => (string) $metric->id,
                        'name' => 'Wheel size (Linux)',
                        'latestValue' => [
                            'value' => '48.2 MB',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('Wheel size (Linux)', $metric->fresh()?->name);
        self::assertSame(1, $metric->values()->count());
    }

    public function testCanKeepSameName(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->renameMetric($metric->id, 'Wheel size')
            ->assertJsonPath('data.updateProjectMetric.message', null)
            ->assertJsonPath('data.updateProjectMetric.projectMetric.name', 'Wheel size');
    }

    public function testRejectsNameOfAnotherMetric(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);
        ProjectMetric::factory()->for($project)->create(['name' => 'Startup time', 'position' => 2]);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->renameMetric($metric->id, 'Startup time')
            ->assertJsonPath('data.updateProjectMetric', null)
            ->assertGraphQLValidationError('name', 'A metric with this name already exists.');

        self::assertSame('Wheel size', $metric->fresh()?->name);
    }

    public function testRejectsTooLongName(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->renameMetric($metric->id, str_repeat('a', 26))
            ->assertJsonPath('data.updateProjectMetric', null)
            ->assertGraphQLValidationError('name', 'Metric names can be at most 25 characters long.');

        self::assertSame('Wheel size', $metric->fresh()?->name);
    }

    public function testFailsWhenNoUser(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);

        $this->renameMetric($metric->id, 'Startup time')
            ->assertJsonPath('data.updateProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame('Wheel size', $metric->fresh()?->name);
    }

    public function testFailsWhenBasicUser(): void
    {
        $project = $this->makePublicProject();
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);
        $user = User::factory()->create();

        $this->actingAs($user)->renameMetric($metric->id, 'Startup time')
            ->assertJsonPath('data.updateProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame('Wheel size', $metric->fresh()?->name);
    }

    public function testFailsWhenNoMetric(): void
    {
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->renameMetric(1234567, 'Startup time')
            ->assertJsonPath('data.updateProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');
    }
}
