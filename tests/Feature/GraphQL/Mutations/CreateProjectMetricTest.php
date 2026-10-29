<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Models\ProjectMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class CreateProjectMetricTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    /**
     * @return TestResponse<Response>
     */
    private function createMetric(int $projectId, string $name): TestResponse
    {
        return $this->graphQL('
            mutation createProjectMetric($input: CreateProjectMetricInput!) {
                createProjectMetric(input: $input) {
                    message
                    projectMetric {
                        name
                        position
                        latestValue {
                            value
                        }
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $projectId,
                'name' => $name,
            ],
        ]);
    }

    public function testProjectAdministratorCanCreateMetric(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();
        $project->users()->attach($user->id, [
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => true,
            'emailmissingsites' => true,
            'role' => ProjectRole::ADMINISTRATOR,
        ]);

        $this->actingAs($user)->createMetric($project->id, 'Wheel size')->assertExactJson([
            'data' => [
                'createProjectMetric' => [
                    'message' => null,
                    'projectMetric' => [
                        'name' => 'Wheel size',
                        'position' => 1,
                        'latestValue' => null,
                    ],
                ],
            ],
        ]);

        self::assertSame(['Wheel size'], $project->metrics()->pluck('name')->all());
    }

    public function testNewMetricsAreAddedAfterExistingMetrics(): void
    {
        $project = $this->makePublicProject();
        ProjectMetric::factory()->for($project)->create(['position' => 5]);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->createMetric($project->id, 'Wheel size')
            ->assertJsonPath('data.createProjectMetric.projectMetric.position', 6);
    }

    public function testFailsWhenNoUser(): void
    {
        $project = $this->makePublicProject();

        $this->createMetric($project->id, 'Wheel size')
            ->assertJsonPath('data.createProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame(0, ProjectMetric::count());
    }

    public function testFailsWhenBasicUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $this->actingAs($user)->createMetric($project->id, 'Wheel size')
            ->assertJsonPath('data.createProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame(0, ProjectMetric::count());
    }

    public function testFailsWhenNoProject(): void
    {
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->createMetric(1234567, 'Wheel size')
            ->assertJsonPath('data.createProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    public function testDoesNotRevealMetricsOfPrivateProject(): void
    {
        $project = $this->makePrivateProject();
        ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);
        $user = User::factory()->create();

        $this->actingAs($user)->createMetric($project->id, 'Wheel size')
            ->assertJsonPath('data.createProjectMetric', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    public function testRejectsDuplicateName(): void
    {
        $project = $this->makePublicProject();
        ProjectMetric::factory()->for($project)->create(['name' => 'Wheel size']);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->createMetric($project->id, 'Wheel size')
            ->assertJsonPath('data.createProjectMetric', null)
            ->assertGraphQLValidationError('name', 'A metric with this name already exists.');

        self::assertSame(1, $project->metrics()->count());
    }

    public function testAllowsNameUsedByAnotherProject(): void
    {
        $project = $this->makePublicProject();
        $otherProject = $this->makePublicProject();
        ProjectMetric::factory()->for($otherProject)->create(['name' => 'Wheel size']);
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->createMetric($project->id, 'Wheel size')
            ->assertJsonPath('data.createProjectMetric.message', null);

        self::assertSame(['Wheel size'], $project->metrics()->pluck('name')->all());
    }

    public function testRejectsTooLongName(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->createMetric($project->id, str_repeat('a', 26))
            ->assertJsonPath('data.createProjectMetric', null)
            ->assertGraphQLValidationError('name', 'Metric names can be at most 25 characters long.');

        $this->actingAs($user)->createMetric($project->id, ' ' . str_repeat('a', 25) . ' ')
            ->assertJsonPath('data.createProjectMetric.projectMetric.name', str_repeat('a', 25));
    }

    public function testRejectsEmptyName(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->createMetric($project->id, '   ')
            ->assertGraphQLErrorMessage('Variable "$input" got invalid value null at "input.name"; Expected non-nullable type "String!" not to be null.');

        self::assertSame(0, ProjectMetric::count());
    }
}
