<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\Project;
use App\Models\ProjectMetric;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class UpdateProjectMetricOrder extends AbstractMutation
{
    /** @var ?Collection<int, ProjectMetric> */
    public ?Collection $projectMetrics = null;

    /**
     * @param array{
     *     projectId: string,
     *     projectMetricIds: array<string>,
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        $project = Project::find((int) $args['projectId']);
        Gate::authorize('updateProjectMetricOrder', $project);

        $projectMetricIds = $project?->metrics()->pluck('id') ?? collect();
        $newOrder = collect($args['projectMetricIds'])->map(fn (string $id): int => (int) $id);

        // A list which is missing a metric is most likely out of date, so we reject it rather than guess.
        if ($projectMetricIds->diff($newOrder)->isNotEmpty() || $newOrder->diff($projectMetricIds)->isNotEmpty()) {
            throw new GraphQLMutationException('IDs for all ProjectMetrics must be provided.');
        }

        if ($newOrder->count() !== $projectMetricIds->count()) {
            throw new GraphQLMutationException('Provided set cannot contain duplicate IDs.');
        }

        if ($newOrder->isEmpty()) {
            throw new GraphQLMutationException("Can't order an empty set.");
        }

        DB::transaction(function () use ($project, $newOrder): void {
            // We start at the previous maximum position + 1 to guarantee that there are never any conflicts.
            // Only the relative order matters, so we don't care if the minimum position is now 1.
            /** @var ?int $maxPosition */
            $maxPosition = $project?->metrics()->max('position');
            $position = ($maxPosition ?? 0) + 1;
            foreach ($newOrder as $id) {
                $project?->metrics()->whereKey($id)->update(['position' => $position]);
                $position++;
            }
        });

        $this->projectMetrics = $project?->metrics()->orderBy('position')->get();

        $user = auth()->user();
        Log::info("User {$user?->id} updated metric order for project {$project?->id}.");

        return $this;
    }
}
