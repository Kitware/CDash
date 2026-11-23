<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\ProjectMetric;
use App\Models\ProjectMetricValue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class CreateProjectMetricValuesTest extends TestCase
{
    use CreatesProjects;
    use DatabaseTransactions;

    /**
     * @param array<array{name: string, value: string}> $values
     *
     * @return TestResponse<Response>
     */
    private function submit(int $projectId, array $values): TestResponse
    {
        return $this->graphQL('
            mutation createProjectMetricValues($input: CreateProjectMetricValuesInput!) {
                createProjectMetricValues(input: $input) {
                    message
                    projectMetricValues {
                        value
                        createdAt
                        user {
                            id
                        }
                        metric {
                            name
                        }
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $projectId,
                'values' => $values,
            ],
        ]);
    }

    private function makeMetric(Project $project, string $name, int $position = 1): ProjectMetric
    {
        /** @var ProjectMetric $metric */
        $metric = ProjectMetric::factory()->for($project)->create([
            'name' => $name,
            'position' => $position,
        ]);

        return $metric;
    }

    public function testAnySignedInUserCanSubmitValuesToPublicProject(): void
    {
        $project = $this->makePublicProject();
        $linesOfCode = $this->makeMetric($project, 'Lines of code', 1);
        $wheelSize = $this->makeMetric($project, 'Wheel size', 2);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->submit($project->id, [
            ['name' => 'Wheel size', 'value' => '48.2 MB'],
            ['name' => 'Lines of code', 'value' => '1,204,553'],
        ]);

        self::assertSame(2, ProjectMetricValue::count());
        $wheelSizeValue = $wheelSize->values()->first();
        $linesOfCodeValue = $linesOfCode->values()->first();
        self::assertNotNull($wheelSizeValue);
        self::assertNotNull($linesOfCodeValue);

        // Values submitted together share a creation time.
        self::assertEquals($wheelSizeValue->created_at, $linesOfCodeValue->created_at);
        $createdAt = $wheelSizeValue->created_at->toIso8601String();

        $response->assertExactJson([
            'data' => [
                'createProjectMetricValues' => [
                    'message' => null,
                    'projectMetricValues' => [
                        [
                            'value' => '48.2 MB',
                            'createdAt' => $createdAt,
                            'user' => ['id' => (string) $user->id],
                            'metric' => ['name' => 'Wheel size'],
                        ],
                        [
                            'value' => '1,204,553',
                            'createdAt' => $createdAt,
                            'user' => ['id' => (string) $user->id],
                            'metric' => ['name' => 'Lines of code'],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame('48.2 MB', $wheelSizeValue->value);
        self::assertSame($user->id, $wheelSizeValue->user_id);
        self::assertSame('1,204,553', $linesOfCodeValue->value);
        self::assertSame($user->id, $linesOfCodeValue->user_id);
    }

    public function testEverySubmissionIsKept(): void
    {
        $project = $this->makePublicProject();
        $metric = $this->makeMetric($project, 'Wheel size');
        $user = User::factory()->create();

        $this->actingAs($user)->submit($project->id, [['name' => 'Wheel size', 'value' => '48.2 MB']])
            ->assertJsonPath('data.createProjectMetricValues.message', null);
        $this->actingAs($user)->submit($project->id, [['name' => 'Wheel size', 'value' => '48.2 MB']])
            ->assertJsonPath('data.createProjectMetricValues.message', null);
        $this->actingAs($user)->submit($project->id, [['name' => 'Wheel size', 'value' => '49 MB']])
            ->assertJsonPath('data.createProjectMetricValues.message', null);

        self::assertSame(['48.2 MB', '48.2 MB', '49 MB'], $metric->values()->orderBy('id')->pluck('value')->all());
        self::assertSame('49 MB', $metric->latestValue()->first()?->value);
    }

    public function testProjectMemberCanSubmitValuesToPrivateProject(): void
    {
        $project = $this->makePrivateProject();
        $metric = $this->makeMetric($project, 'Wheel size');
        $user = User::factory()->create();
        $project->users()->attach($user->id, [
            'emailtype' => 0,
            'emailcategory' => 0,
            'emailsuccess' => true,
            'emailmissingsites' => true,
            'role' => ProjectRole::USER,
        ]);

        $this->actingAs($user)->submit($project->id, [['name' => 'Wheel size', 'value' => '48.2 MB']])
            ->assertJsonPath('data.createProjectMetricValues.message', null);

        self::assertSame(['48.2 MB'], $metric->values()->pluck('value')->all());
    }

    public function testFailsWhenNoUser(): void
    {
        $project = $this->makePublicProject();
        $this->makeMetric($project, 'Wheel size');

        $this->submit($project->id, [['name' => 'Wheel size', 'value' => '48.2 MB']])
            ->assertJsonPath('data.createProjectMetricValues', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame(0, ProjectMetricValue::count());
    }

    public function testFailsWhenNoProject(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->submit(1234567, [['name' => 'Wheel size', 'value' => '48.2 MB']])
            ->assertJsonPath('data.createProjectMetricValues', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');
    }

    /**
     * Unknown names must not produce a validation error, or users could find out which metrics a private
     * project has.
     */
    public function testFailsForPrivateProjectWithoutRevealingItsMetrics(): void
    {
        $project = $this->makePrivateProject();
        $this->makeMetric($project, 'Wheel size');
        $user = User::factory()->create();

        $this->actingAs($user)->submit($project->id, [
            ['name' => 'Wheel size', 'value' => '48.2 MB'],
            ['name' => 'Not a metric', 'value' => '1'],
        ])->assertJsonPath('data.createProjectMetricValues', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame(0, ProjectMetricValue::count());
    }

    public function testRejectsWholeSubmissionWhenAMetricDoesNotExist(): void
    {
        $project = $this->makePublicProject();
        $this->makeMetric($project, 'Wheel size');
        $otherProject = $this->makePublicProject();
        $this->makeMetric($otherProject, 'Startup time');
        $user = User::factory()->create();

        $this->actingAs($user)->submit($project->id, [
            ['name' => 'Wheel size', 'value' => '48.2 MB'],
            ['name' => 'Wheel sze', 'value' => '1'],
            ['name' => 'wheel size', 'value' => '2'],
            ['name' => 'Startup time', 'value' => '3'],
        ])->assertJsonPath('data.createProjectMetricValues', null)
            ->assertGraphQLValidationError('values.1.name', 'This project does not have a metric named "Wheel sze".')
            ->assertGraphQLValidationError('values.2.name', 'This project does not have a metric named "wheel size".')
            ->assertGraphQLValidationError('values.3.name', 'This project does not have a metric named "Startup time".')
            ->assertGraphQLValidationKeys(['values.1.name', 'values.2.name', 'values.3.name']);

        self::assertSame(0, ProjectMetricValue::count());
    }

    public function testRejectsTwoValuesForTheSameMetric(): void
    {
        $project = $this->makePublicProject();
        $this->makeMetric($project, 'Wheel size');
        $user = User::factory()->create();

        $this->actingAs($user)->submit($project->id, [
            ['name' => 'Wheel size', 'value' => '48.2 MB'],
            ['name' => 'Wheel size', 'value' => '49 MB'],
        ])->assertJsonPath('data.createProjectMetricValues', null)
            ->assertGraphQLValidationError('values.0.name', 'Only one value can be submitted for each metric.')
            ->assertGraphQLValidationError('values.1.name', 'Only one value can be submitted for each metric.');

        self::assertSame(0, ProjectMetricValue::count());
    }

    public function testRejectsEmptySubmission(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $this->actingAs($user)->submit($project->id, [])
            ->assertJsonPath('data.createProjectMetricValues', null)
            ->assertGraphQLValidationError('values', 'At least one value must be provided.');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyValues(): array
    {
        return [
            'empty' => [''],
            'only whitespace' => ['   '],
        ];
    }

    /**
     * Empty strings in variables become null before the query runs, so GraphQL rejects them as missing.
     */
    #[DataProvider('emptyValues')]
    public function testRejectsEmptyValue(string $value): void
    {
        $project = $this->makePublicProject();
        $this->makeMetric($project, 'Wheel size');
        $this->makeMetric($project, 'Startup time', 2);
        $user = User::factory()->create();

        $this->actingAs($user)->submit($project->id, [
            ['name' => 'Wheel size', 'value' => '48.2 MB'],
            ['name' => 'Startup time', 'value' => $value],
        ])->assertGraphQLErrorMessage('Variable "$input" got invalid value null at "input.values[1].value"; Expected non-nullable type "String!" not to be null.');

        self::assertSame(0, ProjectMetricValue::count());
    }

    public function testRejectsTooLongValue(): void
    {
        $project = $this->makePublicProject();
        $this->makeMetric($project, 'Wheel size');
        $this->makeMetric($project, 'Startup time', 2);
        $user = User::factory()->create();

        $this->actingAs($user)->submit($project->id, [
            ['name' => 'Wheel size', 'value' => '48.2 MB'],
            ['name' => 'Startup time', 'value' => str_repeat('a', 26)],
        ])->assertJsonPath('data.createProjectMetricValues', null)
            ->assertGraphQLValidationError('values.1.value', 'Values can be at most 25 characters long.');

        self::assertSame(0, ProjectMetricValue::count());
    }

    public function testAcceptsLongestValueAndTrimsWhitespace(): void
    {
        $project = $this->makePublicProject();
        $metric = $this->makeMetric($project, 'Commit');
        $user = User::factory()->create();
        $value = Str::repeat('a', 25);

        $this->actingAs($user)->submit($project->id, [['name' => ' Commit ', 'value' => " {$value} "]])
            ->assertJsonPath('data.createProjectMetricValues.message', null);

        self::assertSame([$value], $metric->values()->pluck('value')->all());
    }
}
