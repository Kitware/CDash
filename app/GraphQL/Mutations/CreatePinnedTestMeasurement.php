<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\PinnedTestMeasurement;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;

final class CreatePinnedTestMeasurement extends AbstractMutation
{
    public ?PinnedTestMeasurement $pinnedTestMeasurement = null;

    /**
     * @param array{
     *     projectId: int,
     *     name: string,
     * } $args
     */
    protected function mutate(array $args): void
    {
        $project = Project::find((int) $args['projectId']);
        Gate::authorize('addPinnedTestMeasurement', $project);

        $nextAvailablePosition = $project?->pinnedTestMeasurements()->max('position');
        if ($nextAvailablePosition === null) {
            $nextAvailablePosition = 1;
        } else {
            $nextAvailablePosition++;
        }

        $this->pinnedTestMeasurement = $project?->pinnedTestMeasurements()->create([
            'name' => $args['name'],
            'position' => $nextAvailablePosition,
        ]);
    }
}
