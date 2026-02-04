<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\PinnedTestMeasurement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class CreatePinnedTestMeasurementTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testFailsWhenNoProject(): void
    {
        $user = $this->makeAdminUser();

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
        ])->assertExactJson([
            'data' => [
                'createPinnedTestMeasurement' => [
                    'message' => 'This action is unauthorized.',
                    'pinnedTestMeasurement' => null,
                ],
            ],
        ]);

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
        ])->assertExactJson([
            'data' => [
                'createPinnedTestMeasurement' => [
                    'message' => 'This action is unauthorized.',
                    'pinnedTestMeasurement' => null,
                ],
            ],
        ]);

        self::assertEmpty(PinnedTestMeasurement::all());
    }

    public function testFailsWhenBasicUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeNormalUser();

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
                    'message' => 'This action is unauthorized.',
                    'pinnedTestMeasurement' => null,
                ],
            ],
        ]);

        self::assertEmpty(PinnedTestMeasurement::all());
    }

    public function testSucceedsWhenAdminUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

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
        $user = $this->makeAdminUser();

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
}
