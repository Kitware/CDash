<?php

namespace Tests\Feature\GraphQL\Mutations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class UpdatePinnedTestMeasurementOrderTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testFailsWhenIdsDontMatchProject(): void
    {
        $project1 = $this->makePublicProject();
        $project2 = $this->makePublicProject();
        $user = $this->makeAdminUser();

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
        ])->assertExactJson([
            'data' => [
                'updatePinnedTestMeasurementOrder' => [
                    'message' => 'IDs for all PinnedTestMeasurements must be provided.',
                    'pinnedTestMeasurements' => null,
                ],
            ],
        ]);
    }

    public function testFailsWhenMissingIds(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

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
        ])->assertExactJson([
            'data' => [
                'updatePinnedTestMeasurementOrder' => [
                    'message' => 'IDs for all PinnedTestMeasurements must be provided.',
                    'pinnedTestMeasurements' => null,
                ],
            ],
        ]);

        self::assertSame(1, $measurement1->fresh()?->position);
        self::assertSame(2, $measurement2->fresh()?->position);
    }

    public function testFailsWhenNoMeasurements(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

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
        ])->assertExactJson([
            'data' => [
                'updatePinnedTestMeasurementOrder' => [
                    'message' => "Can't order an empty set.",
                    'pinnedTestMeasurements' => null,
                ],
            ],
        ]);
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
        ])->assertExactJson([
            'data' => [
                'updatePinnedTestMeasurementOrder' => [
                    'message' => 'This action is unauthorized.',
                    'pinnedTestMeasurements' => null,
                ],
            ],
        ]);

        self::assertSame(1, $measurement->fresh()?->position);
    }

    public function testFailsWhenNormalUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeNormalUser();
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
        ])->assertExactJson([
            'data' => [
                'updatePinnedTestMeasurementOrder' => [
                    'message' => 'This action is unauthorized.',
                    'pinnedTestMeasurements' => null,
                ],
            ],
        ]);

        self::assertSame(1, $measurement->fresh()?->position);
    }

    public function testFailsWithDuplicateIds(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

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
        ])->assertExactJson([
            'data' => [
                'updatePinnedTestMeasurementOrder' => [
                    'message' => 'Provided set cannot contain duplicate IDs.',
                    'pinnedTestMeasurements' => null,
                ],
            ],
        ]);

        self::assertSame(1, $measurement->fresh()?->position);
    }

    public function testSucceedsWhenAdminUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

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
