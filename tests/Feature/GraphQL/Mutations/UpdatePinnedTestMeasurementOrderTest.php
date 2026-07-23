<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;

class UpdatePinnedTestMeasurementOrderTest extends TestCase
{
    use CreatesProjects;

    use DatabaseTransactions;

    public function testFailsWhenIdsDontMatchProject(): void
    {
        $project1 = $this->makePublicProject();
        $project2 = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $measurement1 = $project1->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);
        $measurement2 = $project2->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $this->actingAs($user)->graphQL('
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
                updatePinnedTestMeasurementOrder(input: $input) {
                    message
                    pinnedTestMeasurements {
                        id
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project1->id,
                'pinnedTestMeasurementIds' => [$measurement2->id],
            ],
        ])->assertJsonPath('data.updatePinnedTestMeasurementOrder', null)
            ->assertGraphQLErrorMessage('IDs for all PinnedTestMeasurements must be provided.');
    }

    public function testFailsWhenMissingIds(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $measurement1 = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);
        $measurement2 = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 2,
        ]);

        $this->actingAs($user)->graphQL('
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
                updatePinnedTestMeasurementOrder(input: $input) {
                    message
                    pinnedTestMeasurements {
                        id
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'pinnedTestMeasurementIds' => [$measurement1->id],
            ],
        ])->assertJsonPath('data.updatePinnedTestMeasurementOrder', null)
            ->assertGraphQLErrorMessage('IDs for all PinnedTestMeasurements must be provided.');

        self::assertSame(1, $measurement1->fresh()?->position);
        self::assertSame(2, $measurement2->fresh()?->position);
    }

    public function testFailsWhenNoMeasurements(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $this->actingAs($user)->graphQL('
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
                updatePinnedTestMeasurementOrder(input: $input) {
                    message
                    pinnedTestMeasurements {
                        id
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'pinnedTestMeasurementIds' => [],
            ],
        ])->assertJsonPath('data.updatePinnedTestMeasurementOrder', null)
            ->assertGraphQLErrorMessage("Can't order an empty set.");
    }

    public function testFailsWhenAnonymousUser(): void
    {
        $project = $this->makePublicProject();
        $measurement = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $this->graphQL('
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
                updatePinnedTestMeasurementOrder(input: $input) {
                    message
                    pinnedTestMeasurements {
                        id
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'pinnedTestMeasurementIds' => [$measurement->id],
            ],
        ])->assertJsonPath('data.updatePinnedTestMeasurementOrder', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame(1, $measurement->fresh()?->position);
    }

    public function testFailsWhenNormalUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->create();
        $measurement = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $this->actingAs($user)->graphQL('
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
                updatePinnedTestMeasurementOrder(input: $input) {
                    message
                    pinnedTestMeasurements {
                        id
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'pinnedTestMeasurementIds' => [$measurement->id],
            ],
        ])->assertJsonPath('data.updatePinnedTestMeasurementOrder', null)
            ->assertGraphQLErrorMessage('This action is unauthorized.');

        self::assertSame(1, $measurement->fresh()?->position);
    }

    public function testFailsWithDuplicateIds(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $measurement = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $this->actingAs($user)->graphQL('
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
                updatePinnedTestMeasurementOrder(input: $input) {
                    message
                    pinnedTestMeasurements {
                        id
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'pinnedTestMeasurementIds' => [$measurement->id, $measurement->id],
            ],
        ])->assertJsonPath('data.updatePinnedTestMeasurementOrder', null)
            ->assertGraphQLErrorMessage('Provided set cannot contain duplicate IDs.');

        self::assertSame(1, $measurement->fresh()?->position);
    }

    public function testSucceedsWhenAdminUser(): void
    {
        $project = $this->makePublicProject();
        $user = User::factory()->adminUser()->create();

        $measurement1 = $project->pinnedTestMeasurements()->create([
            'name' => 'Measurement 1',
            'position' => 1,
        ]);
        $measurement2 = $project->pinnedTestMeasurements()->create([
            'name' => 'Measurement 2',
            'position' => 2,
        ]);

        $this->actingAs($user)->graphQL('
            mutation updatePinnedTestMeasurementOrder($input: UpdatePinnedTestMeasurementOrderInput!) {
                updatePinnedTestMeasurementOrder(input: $input) {
                    message
                    pinnedTestMeasurements {
                        id
                        name
                        position
                    }
                }
            }
        ', [
            'input' => [
                'projectId' => $project->id,
                'pinnedTestMeasurementIds' => [$measurement2->id, $measurement1->id],
            ],
        ])->assertExactJson([
            'data' => [
                'updatePinnedTestMeasurementOrder' => [
                    'message' => null,
                    'pinnedTestMeasurements' => [
                        [
                            'id' => (string) $measurement2->id,
                            'name' => 'Measurement 2',
                            'position' => 3,
                        ],
                        [
                            'id' => (string) $measurement1->id,
                            'name' => 'Measurement 1',
                            'position' => 4,
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame(3, $measurement2->fresh()?->position);
        self::assertSame(4, $measurement1->fresh()?->position);
    }
}
