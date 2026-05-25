<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Jobs\ComputeCoverageDifference;
use App\Models\Build;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class CreateCoverageDiff extends AbstractMutation
{
    /**
     * @param array{
     *     baseBuildId: int,
     *     compareBuildId: int,
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        $baseBuild = Build::find((int) $args['baseBuildId']);
        $compareBuild = Build::find((int) $args['compareBuildId']);

        Gate::authorize('createCoverageDiff', $baseBuild->project ?? new Project());

        if ($baseBuild === null || $compareBuild === null || $baseBuild->projectid !== $compareBuild->projectid) {
            throw new GraphQLMutationException('Builds must belong to the same project.');
        }

        ComputeCoverageDifference::dispatch($baseBuild, $compareBuild);

        Log::info('User ' . auth()->id() . " queued coverage diff for builds {$baseBuild->id} and {$compareBuild->id}.");

        return $this;
    }
}
