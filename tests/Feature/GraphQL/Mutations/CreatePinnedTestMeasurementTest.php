<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\PinnedTestMeasurement;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class CreatePinnedTestMeasurementTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testFailsWhenNoProject(): void
    {
        $user = User::factory()->adminUser()->create();

        $name = Str::uuid()->toString();
        $this->actingAs($user)->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => 1234567,
                'name' => $name,
            ],
        ])->assertJsonPath('data.createPinnedTestMeasurement', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertEmpty(PinnedTestMeasurement::all());
    }

    public function testFailsWhenNoUser(): void
    {
        $project = $this->makePublicProject();

        $name = Str::uuid()->toString();
        $this->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'name' => $name,
            ],
        ])->assertJsonPath('data.createPinnedTestMeasurement', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertEmpty(PinnedTestMeasurement::all());
    }

    public function testFailsWhenBasicUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();

        $name = Str::uuid()->toString();
        $this->actingAs($user)->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'name' => $name,
            ],
        ])->assertJsonPath('data.createPinnedTestMeasurement', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertEmpty(PinnedTestMeasurement::all());
    }

    public function testSucceedsWhenAdminUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $name = Str::uuid()->toString();
        $this->actingAs($user)->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'name' => $name,
            ],
        ])->assertExactJson([
            'data' => [
                'createPinnedTestMeasurement' => [
                    'message' => null,
                    'pinnedTestMeasurement' => [
                        'name' => $name,
                        'position' => 1,
                    ],
                ],
            ],
        ]);

        self::assertContains($name, $project->pinnedTestMeasurements()->pluck('name')->toArray());
    }

    public function testCreatesMultipleMeasurementsInOrder(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $name1 = Str::uuid()->toString();
        $name2 = Str::uuid()->toString();

        $this->actingAs($user)->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'name' => $name1,
            ],
        ])->assertExactJson([
            'data' => [
                'createPinnedTestMeasurement' => [
                    'message' => null,
                    'pinnedTestMeasurement' => [
                        'name' => $name1,
                        'position' => 1,
                    ],
                ],
            ],
        ]);

        $this->actingAs($user)->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'name' => $name2,
            ],
        ])->assertExactJson([
            'data' => [
                'createPinnedTestMeasurement' => [
                    'message' => null,
                    'pinnedTestMeasurement' => [
                        'name' => $name2,
                        'position' => 2,
                    ],
                ],
            ],
        ]);

        self::assertContains($name1, $project->pinnedTestMeasurements()->pluck('name')->toArray());
        self::assertContains($name2, $project->pinnedTestMeasurements()->pluck('name')->toArray());
    }

    public function testFailsWhenDuplicateName(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $name = Str::uuid()->toString();
        $project->pinnedTestMeasurements()->create([
            'name' => $name,
            'position' => 1,
        ]);

        $this->actingAs($user)->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'name' => $name,
            ],
        ])->assertJsonPath('data.createPinnedTestMeasurement', null)
            ->assertGraphQLErrorMessage('This test measurement is already pinned.');

        self::assertSame([$name], $project->pinnedTestMeasurements()->pluck('name')->toArray());
    }

    public function testAllowsSameNameInDifferentProjects(): void
    {
        $project1 = $this->makePublicProject();
        $project2 = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $name = Str::uuid()->toString();
        $project1->pinnedTestMeasurements()->create([
            'name' => $name,
            'position' => 1,
        ]);

        $this->actingAs($user)->graphQL('
            mutation createPinnedTestMeasurement($input: CreatePinnedTestMeasurementInput!) {
                createPinnedTestMeasurement(input: $input) {
                    message
                    pinnedTestMeasurement {
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project2->id,
                'name' => $name,
            ],
        ])->assertExactJson([
            'data' => [
                'createPinnedTestMeasurement' => [
                    'message' => null,
                    'pinnedTestMeasurement' => [
                        'name' => $name,
                        'position' => 1,
                    ],
                ],
            ],
        ]);

        self::assertSame([$name], $project1->pinnedTestMeasurements()->pluck('name')->toArray());
        self::assertSame([$name], $project2->pinnedTestMeasurements()->pluck('name')->toArray());
    }
}
