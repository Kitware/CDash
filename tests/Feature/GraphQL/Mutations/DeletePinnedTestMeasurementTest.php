<?php

namespace Tests\Feature\GraphQL\Mutations;

use App\Models\PinnedTestMeasurement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesProjects;
use Tests\Traits\CreatesUsers;

class DeletePinnedTestMeasurementTest extends TestCase
{
    use CreatesProjects;
    use CreatesUsers;
    use DatabaseTransactions;

    public function testFailsWhenNoMeasurement(): void
    {
        $user = $this->makeAdminUser();

        $this->actingAs($user)->graphQL('
            mutation deletePinnedTestMeasurement($input: DeletePinnedTestMeasurementInput!) {
                deletePinnedTestMeasurement(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'id' => 123456789,
            ],
        ])->assertExactJson([
            'data' => [
                'deletePinnedTestMeasurement' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
    }

    public function testFailsWhenNoUser(): void
    {
        $project = $this->makePublicProject();

        /** @var PinnedTestMeasurement $measurement */
        $measurement = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $this->graphQL('
            mutation deletePinnedTestMeasurement($input: DeletePinnedTestMeasurementInput!) {
                deletePinnedTestMeasurement(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'id' => $measurement->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deletePinnedTestMeasurement' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
        self::assertDatabaseHas(PinnedTestMeasurement::class, ['id' => $measurement->id]);
    }

    public function testFailsWhenBasicUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeNormalUser();

        /** @var PinnedTestMeasurement $measurement */
        $measurement = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $this->actingAs($user)->graphQL('
            mutation deletePinnedTestMeasurement($input: DeletePinnedTestMeasurementInput!) {
                deletePinnedTestMeasurement(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'id' => $measurement->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deletePinnedTestMeasurement' => [
                    'message' => 'This action is unauthorized.',
                ],
            ],
        ]);
        self::assertDatabaseHas(PinnedTestMeasurement::class, ['id' => $measurement->id]);
    }

    public function testSucceedsWhenAdminUser(): void
    {
        $project = $this->makePublicProject();
        $user = $this->makeAdminUser();

        /** @var PinnedTestMeasurement $measurement */
        $measurement = $project->pinnedTestMeasurements()->create([
            'name' => Str::uuid()->toString(),
            'position' => 1,
        ]);

        $this->actingAs($user)->graphQL('
            mutation deletePinnedTestMeasurement($input: DeletePinnedTestMeasurementInput!) {
                deletePinnedTestMeasurement(input: $input) {
                    message
                }
            }
        ', [
            'input' => [
                'id' => $measurement->id,
            ],
        ])->assertExactJson([
            'data' => [
                'deletePinnedTestMeasurement' => [
                    'message' => null,
                ],
            ],
        ]);
        self::assertEmpty(PinnedTestMeasurement::all());
    }
}
