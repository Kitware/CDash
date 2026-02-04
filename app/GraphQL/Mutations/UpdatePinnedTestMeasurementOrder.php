<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\PinnedTestMeasurement;
use App\Models\Project;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class UpdatePinnedTestMeasurementOrder extends AbstractMutation
{
    /** @var ?Collection<PinnedTestMeasurement> */
    public ?Collection $pinnedTestMeasurements = null;

    /**
     * @param array{
     *     projectId: int,
     *     pinnedTestMeasurementIds: array<int>,
     * } $args
     */
    protected function mutate(array $args): void
    {
        $project = Project::find((int) $args['projectId']);
        Gate::authorize('updatePinnedTestMeasurementOrder', $project);

        $projectMeasurementIds = $project?->pinnedTestMeasurements()->pluck('id');
        $newOrder = collect($args['pinnedTestMeasurementIds']);

        if ($projectMeasurementIds->diff($newOrder)->isNotEmpty()) {
            throw new Exception('IDs for all PinnedTestMeasurements must be provided.');
        }

        if ($newOrder->count() !== $projectMeasurementIds->count()) {
            throw new Exception('Provided set cannot contain duplicate IDs.');
        }

        if ($newOrder->isEmpty()) {
            throw new Exception("Can't order an empty set.");
        }

        // We start at the previous maximum ID + 1 to guarantee that there are never any conflicts.
        // Only the relative order matters, so we don't care if the minimum position is now 1.
        $position = (int) $project?->pinnedTestMeasurements()->max('position') + 1;
        foreach ($newOrder as $id) {
            /** @var PinnedTestMeasurement $measurement */
            $measurement = $project?->pinnedTestMeasurements()->findOrFail((int) $id);

            $measurement->position = $position;
            $measurement->save();
            $position++;
        }

        $this->pinnedTestMeasurements = $project?->pinnedTestMeasurements()->orderBy('position')->get();
    }
}
