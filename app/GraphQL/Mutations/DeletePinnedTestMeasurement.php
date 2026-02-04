<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\PinnedTestMeasurement;
use Illuminate\Support\Facades\Gate;

final class DeletePinnedTestMeasurement extends AbstractMutation
{
    /**
     * @param array{
     *     id: int,
     * } $args
     */
    protected function mutate(array $args): void
    {
        $measurement = PinnedTestMeasurement::find((int) $args['id']);

        Gate::authorize('deletePinnedTestMeasurement', $measurement?->project);

        $measurement?->delete();
    }
}
