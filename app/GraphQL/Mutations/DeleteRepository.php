<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Repository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class DeleteRepository extends AbstractMutation
{
    /**
     * @param array{
     *     repositoryId: int,
     * } $args
     */
    public function __invoke(null $_, array $args): self
    {
        $repository = Repository::find((int) $args['repositoryId']);

        Gate::authorize('deleteRepository', $repository?->project);

        $repository?->delete();

        $user = auth()->user();
        Log::info("User {$user?->id} deleted repository {$args['repositoryId']}.");

        return $this;
    }
}
