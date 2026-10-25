<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\PinnedTestMeasurement;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class CreatePinnedTestMeasurement extends AbstractMutation
{
    public ?PinnedTestMeasurement $pinnedTestMeasurement = null;

    /**
     * @param array{
     *     projectId: int,
     *     name: string,
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        $project = Project::find((int) $args['projectId']);
        Gate::authorize('createPinnedTestMeasurement', $project);

        if ($project?->pinnedTestMeasurements()->where('name', $args['name'])->exists() === true) {
            throw new GraphQLMutationException('This test measurement is already pinned.');
        }

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

        $user = auth()->user();
        Log::info("User {$user?->id} created pinned test measurement {$this->pinnedTestMeasurement?->id} for project {$project?->id}.");

        return $this;
    }
}
